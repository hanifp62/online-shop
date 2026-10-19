<?php

/** Minimal HTTPS CONNECT proxy. Allow only exact hostnames on TCP/443. */
// Single-process stream_select loop: parallel Composer downloads must not queue behind one tunnel,
// and the stage image has no pcntl to fork.
$allowed = array_fill_keys([
    'repo.packagist.org', 'packagist.org', 'api.github.com',
    'codeload.github.com', 'github.com', 'objects.githubusercontent.com',
], true);

const HEADER_TIMEOUT = 5.0;
const CONNECT_TIMEOUT = 10.0;
const IDLE_TIMEOUT = 30.0;
const MAX_BUFFER = 262144;

$listen = getenv('PROXY_LISTEN') ?: '127.0.0.1:8080';
[$listenHost] = array_pad(explode(':', $listen, 2), 2, '');
$isLoopback = in_array(strtolower($listenHost), ['127.0.0.1', 'localhost', '[::1]'], true);
if (! $isLoopback && getenv('PROXY_ALLOW_NONLOOPBACK') !== '1') {
    fwrite(STDERR, "non-loopback proxy bind requires PROXY_ALLOW_NONLOOPBACK=1\n");
    exit(2);
}
$server = stream_socket_server('tcp://'.$listen, $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
if (! $server) {
    fwrite(STDERR, "proxy bind failed: $error\n");
    exit(1);
}
stream_set_blocking($server, false);

/** @var array<int, array{s: resource, state: string, buf: string, out: string, peer: ?int, deadline: float}> */
$conns = [];

function add(array &$conns, $socket, string $state, float $deadline, ?int $peer = null): int
{
    stream_set_blocking($socket, false);
    $id = (int) $socket;
    $conns[$id] = ['s' => $socket, 'state' => $state, 'buf' => '', 'out' => '', 'peer' => $peer, 'deadline' => $deadline];

    return $id;
}

function drop(array &$conns, int $id): void
{
    if (! isset($conns[$id])) {
        return;
    }
    $peer = $conns[$id]['peer'];
    @fclose($conns[$id]['s']);
    unset($conns[$id]);
    // Let the peer flush what it already received, then close.
    if ($peer !== null && isset($conns[$peer])) {
        $conns[$peer]['peer'] = null;
        $conns[$peer]['state'] = 'closing';
        $conns[$peer]['deadline'] = microtime(true) + CONNECT_TIMEOUT;
    }
}

function respond(array &$conns, int $id, string $status): void
{
    $conns[$id]['out'] .= "HTTP/1.1 $status\r\nConnection: close\r\n\r\n";
    $conns[$id]['state'] = 'closing';
    $conns[$id]['deadline'] = microtime(true) + CONNECT_TIMEOUT;
}

/** Returns null while incomplete, false when malformed, or [request line, leftover bytes]. */
function parseHeaders(string $buffer): array|false|null
{
    $offset = 0;
    $lines = [];
    while (($end = strpos($buffer, "\n", $offset)) !== false) {
        $line = substr($buffer, $offset, $end - $offset + 1);
        $offset = $end + 1;
        if (strlen($line) > 4095) {
            return false;
        }
        if (trim($line) === '') {
            return $lines === [] ? false : [$lines[0], substr($buffer, $offset)];
        }
        $lines[] = $line;
        if (count($lines) >= 64) {
            return false;
        }
    }

    return strlen($buffer) - $offset >= 4095 ? false : null;
}

function decide(string $line, array $allowed): ?string
{
    $parts = preg_split('/\s+/', trim($line));
    $target = $parts[1] ?? '';
    [$host, $port] = array_pad(explode(':', $target, 2), 2, '');
    $ok = ($parts[0] ?? '') === 'CONNECT' && isset($allowed[strtolower($host)]) && $port === '443';
    $validatedIp = null;
    if ($ok) {
        $addresses = @gethostbynamel($host);
        $ok = is_array($addresses) && count($addresses) > 0;
        foreach ($addresses ?: [] as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ok = false;
            }
        }
        if ($ok) {
            $validatedIp = $addresses[0];
        }
    }
    file_put_contents('php://stderr', json_encode(['host' => $host, 'port' => $port, 'allowed' => $ok])."\n");

    return $ok ? $validatedIp : null;
}

while (true) {
    $read = [$server];
    $write = [];
    foreach ($conns as $id => $c) {
        $peerOut = $c['peer'] !== null && isset($conns[$c['peer']]) ? strlen($conns[$c['peer']]['out']) : 0;
        if ($c['state'] === 'header' || ($c['state'] === 'tunnel' && $peerOut < MAX_BUFFER)) {
            $read[] = $c['s'];
        }
        if ($c['out'] !== '' || $c['state'] === 'connecting') {
            $write[] = $c['s'];
        }
    }
    $except = [];
    if (@stream_select($read, $write, $except, 1) === false) {
        continue;
    }
    $now = microtime(true);

    foreach ($write as $socket) {
        $id = (int) $socket;
        if (! isset($conns[$id])) {
            continue;
        }
        $c = &$conns[$id];
        if ($c['state'] === 'connecting') {
            $client = $c['peer'];
            if (stream_socket_get_name($socket, true) === false || ! isset($conns[$client])) {
                unset($c);
                $conns[$id]['peer'] = null;
                drop($conns, $id);
                if (isset($conns[$client])) {
                    respond($conns, $client, '502 Bad Gateway');
                }

                continue;
            }
            $c['state'] = 'tunnel';
            $c['deadline'] = $now + IDLE_TIMEOUT;
            $conns[$client]['state'] = 'tunnel';
            $conns[$client]['deadline'] = $now + IDLE_TIMEOUT;
            $conns[$client]['out'] .= "HTTP/1.1 200 Connection Established\r\n\r\n";
        }
        if ($c['out'] !== '') {
            $written = @fwrite($socket, $c['out']);
            if ($written === false) {
                unset($c);
                drop($conns, $id);

                continue;
            }
            $c['out'] = substr($c['out'], $written);
        }
        $closing = $c['state'] === 'closing' && $c['out'] === '';
        unset($c);
        if ($closing) {
            drop($conns, $id);
        }
    }

    foreach ($read as $socket) {
        if ($socket === $server) {
            while ($client = @stream_socket_accept($server, 0)) {
                add($conns, $client, 'header', $now + HEADER_TIMEOUT);
            }

            continue;
        }
        $id = (int) $socket;
        if (! isset($conns[$id])) {
            continue;
        }
        $data = @fread($socket, 8192);
        if ($data === '' || $data === false) {
            if (feof($socket) || $data === false) {
                drop($conns, $id);
            }

            continue;
        }
        if ($conns[$id]['state'] === 'tunnel') {
            $conns[$id]['deadline'] = $now + IDLE_TIMEOUT;
            $peer = $conns[$id]['peer'];
            if ($peer !== null && isset($conns[$peer])) {
                $conns[$peer]['out'] .= $data;
                $conns[$peer]['deadline'] = $now + IDLE_TIMEOUT;
            }

            continue;
        }
        $conns[$id]['buf'] .= $data;
        $parsed = parseHeaders($conns[$id]['buf']);
        if ($parsed === null) {
            continue;
        }
        if ($parsed === false) {
            respond($conns, $id, '400 Bad Request');

            continue;
        }
        [$line, $leftover] = $parsed;
        $conns[$id]['buf'] = '';
        $ip = decide($line, $allowed);
        if ($ip === null) {
            respond($conns, $id, '403 Forbidden');

            continue;
        }
        $upstream = @stream_socket_client('tcp://'.$ip.':443', $e, $m, CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
        if (! $upstream) {
            respond($conns, $id, '502 Bad Gateway');

            continue;
        }
        $up = add($conns, $upstream, 'connecting', $now + CONNECT_TIMEOUT, $id);
        $conns[$up]['out'] = $leftover;
        $conns[$id]['state'] = 'waiting';
        $conns[$id]['peer'] = $up;
        $conns[$id]['deadline'] = $now + CONNECT_TIMEOUT;
    }

    foreach (array_keys($conns) as $id) {
        if (! isset($conns[$id]) || $conns[$id]['deadline'] > $now) {
            continue;
        }
        $state = $conns[$id]['state'];
        if ($state === 'header') {
            respond($conns, $id, '400 Bad Request');
        } elseif ($state === 'connecting') {
            $client = $conns[$id]['peer'];
            $conns[$id]['peer'] = null;
            drop($conns, $id);
            if ($client !== null && isset($conns[$client])) {
                $conns[$client]['peer'] = null;
                respond($conns, $client, '502 Bad Gateway');
            }
        } else {
            drop($conns, $id);
        }
    }
}
