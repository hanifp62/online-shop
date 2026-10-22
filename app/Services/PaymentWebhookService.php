<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentDeadLetter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifikasi notifikasi HTTP Midtrans: signature, konfirmasi ulang lewat Status API,
 * lalu ledger idempoten + transisi `paid` dalam satu transaksi.
 */
class PaymentWebhookService
{
    private const PROVIDER = 'midtrans';

    private const CIRCUIT_FAILURE_THRESHOLD = 5;

    private const CIRCUIT_OPEN_MINUTES = 1;

    public function __construct(private CheckoutService $checkout) {}

    /** @return int HTTP status code */
    public function handle(string $rawBody, array $n): int
    {
        return $this->process($rawBody, $n);
    }

    /** Replay passes an immutable selected observation; null is exclusively the live-ingress path. */
    private function process(string $rawBody, array $n, ?array $replayObservation = null): int
    {
        $serverKey = (string) config('services.midtrans.server_key');
        if ($serverKey === '') {
            Log::error('midtrans.server_key kosong; notifikasi ditolak.');

            return 503;
        }

        if (strlen($rawBody) > 16_384) {
            return 413;
        }

        foreach (['order_id', 'status_code', 'gross_amount', 'signature_key', 'transaction_id', 'transaction_status'] as $field) {
            if (! isset($n[$field]) || ! is_scalar($n[$field]) || (string) $n[$field] === '') {
                return 422;
            }
        }
        $orderId = (string) $n['order_id'];
        if (! ctype_digit($orderId) || strlen((string) $n['transaction_id']) > 64) {
            return 422;
        }

        if ($replayObservation !== null && ($replayObservation['order_id'] === null
            || (int) $replayObservation['order_id'] !== (int) $orderId)) {
            return 422;
        }

        // JSON angka desimal harus dipertahankan byte-for-byte untuk material signature.
        $raw = json_decode($rawBody, true, 512, JSON_BIGINT_AS_STRING);
        if (! is_array($raw) || ! preg_match('/"gross_amount"\s*:\s*("(?:\\\\.|[^"\\\\])*"|-?\d+(?:\.\d+)?)/', $rawBody, $match)) {
            return 422;
        }
        $grossAmount = $match[1][0] === '"' ? json_decode($match[1]) : $match[1];
        if (! is_string($grossAmount) || $grossAmount === '') {
            return 422;
        }
        $expected = hash('sha512', $orderId.$n['status_code'].$grossAmount.$serverKey);
        if (! hash_equals($expected, (string) $n['signature_key'])) {
            Log::warning('midtrans.signature tidak valid', ['order_id' => $orderId]);

            return 403;
        }

        if (Cache::get('midtrans:circuit_open_until', 0) > now()->timestamp) {
            Log::warning('midtrans.circuit_open; request ditolak sementara', ['order_id' => $orderId]);
            if ($replayObservation === null) {
                $this->deadLetter($orderId, (string) $n['transaction_id'], $rawBody, 'circuit_open');
            }

            return 503;
        }

        // Observe the hold BEFORE network I/O. A newer failure must not be acknowledged by this response.
        $observation = $replayObservation ?? PaymentDeadLetter::where('provider', self::PROVIDER)
            ->where('order_id', (int) $orderId)->where('transaction_id', (string) $n['transaction_id'])
            ->whereNull('resolved_at')->first(['id', 'order_id', 'attempts'])?->only(['id', 'order_id', 'attempts']);
        $status = $this->fetchStatus($orderId, $serverKey);
        if ($status === null) {
            $this->recordUpstreamFailure();
            if ($replayObservation === null) {
                $this->deadLetter($orderId, (string) $n['transaction_id'], $rawBody, 'status_api_unavailable');
            }

            return 503;
        }

        $this->recordUpstreamSuccess();

        $currency = $status['currency'] ?? 'IDR';
        $amount = $this->toRupiah($status['gross_amount'] ?? null);
        if ((string) ($status['order_id'] ?? '') !== $orderId
            || (string) ($status['transaction_id'] ?? '') !== (string) $n['transaction_id']
            || $currency !== 'IDR') {
            Log::warning('midtrans.status integritas tidak cocok', ['order_id' => $orderId]);

            return 422;
        }

        $eventStatus = (string) ($status['transaction_status'] ?? '');
        if ($eventStatus === '') {
            return 422;
        }
        $success = $eventStatus === 'settlement'
            || ($eventStatus === 'capture' && ($status['fraud_status'] ?? null) === 'accept');

        try {
            DB::transaction(function () use ($orderId, $status, $eventStatus, $amount, $success, $observation) {
                // record() acquires the order lock; keep it through the version-aware acknowledgement.
                $this->record($orderId, $status, $eventStatus, $amount, $success);
                if ($observation) {
                    PaymentDeadLetter::whereKey($observation['id'])->where('order_id', $observation['order_id'])
                        ->where('attempts', $observation['attempts'])->whereNull('resolved_at')
                        ->update(['resolved_at' => now()]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Notifikasi paralel yang sama sudah tercatat; aman dianggap duplikat.
        }

        return 200;
    }

    /** Replay owns failure accounting; HTTP 200 alone never resolves a hold. */
    public function replay(PaymentDeadLetter $letter): ?int
    {
        $id = $letter->id;
        $attempts = $letter->attempts;
        $orderId = $letter->order_id;
        if (! PaymentDeadLetter::eligibleForReplay()->whereKey($id)->where('order_id', $orderId)->where('attempts', $attempts)->exists()) {
            return null;
        }
        $rawBody = $letter->raw_body;
        $decoded = json_decode($rawBody, true);
        $status = $this->process($rawBody, is_array($decoded) ? $decoded : [], [
            'id' => $id, 'order_id' => $orderId, 'attempts' => $attempts,
        ]);

        DB::transaction(function () use ($id, $attempts, $orderId, $status) {
            Order::whereKey($orderId)->lockForUpdate()->first();
            // The ONLY replay failure writer, including circuit-open/upstream 503.
            // Live ingress may have advanced the version; never overwrite its body/reason/version.
            $next = $attempts + 1;
            $reason = $status === 200 ? 'replay_unresolved_200' : 'replay_http_'.$status;
            PaymentDeadLetter::whereKey($id)->where('order_id', $orderId)->where('attempts', $attempts)
                ->whereNull('resolved_at')->update([
                    'attempts' => $next,
                    'reason' => ($next >= PaymentDeadLetter::MAX_REPLAY_ATTEMPTS ? 'quarantined:' : '').$reason,
                    'last_attempted_at' => now(),
                ]);
        });

        return $status;
    }

    /** Notifikasi bertanda tangan sah yang gagal diproses disimpan untuk diputar ulang. */
    private function deadLetter(string $orderId, string $transactionId, string $rawBody, string $reason): void
    {
        DB::transaction(function () use ($orderId, $transactionId, $rawBody, $reason) {
            $order = Order::whereKey((int) $orderId)->lockForUpdate()->first();
            $letter = PaymentDeadLetter::where('provider', self::PROVIDER)
                ->where('transaction_id', $transactionId)
                ->whereNull('resolved_at')
                ->first();

            if ($letter) {
                $letter->update(['attempts' => $letter->attempts + 1, 'reason' => $reason, 'raw_body' => $rawBody, 'last_attempted_at' => now()]);

                return;
            }

            PaymentDeadLetter::create([
                'order_id' => $order?->id,
                'provider' => self::PROVIDER,
                'reason' => $reason,
                'transaction_id' => $transactionId,
                'attempts' => 1,
                'raw_body' => $rawBody,
                'last_attempted_at' => now(),
            ]);
        });
    }

    private function record(string $orderId, array $status, string $eventStatus, ?int $amount, bool $success): void
    {
        $order = Order::whereKey((int) $orderId)->lockForUpdate()->first();

        // Preserve immutable observations without collapsing challenge -> accept.
        $ledgerEvent = $eventStatus === 'capture' && ! $success
            ? 'capture_'.(in_array($status['fraud_status'] ?? '', ['challenge', 'deny'], true) ? $status['fraud_status'] : 'unconfirmed')
            : $eventStatus;
        // Older ledgers stored challenge under plain capture; never mutate that row.
        if ($eventStatus === 'capture' && $success && Payment::where('provider', self::PROVIDER)
            ->where('provider_transaction_id', (string) $status['transaction_id'])
            ->where('event_status', 'capture')->where('outcome', 'recorded')->exists()) {
            $ledgerEvent = 'capture_accept';
        }
        $exists = Payment::where('provider', self::PROVIDER)
            ->where('provider_transaction_id', (string) $status['transaction_id'])
            ->where('event_status', $ledgerEvent)
            ->exists();
        if ($exists) {
            return;
        }

        $outcome = 'recorded';
        if (! $order) {
            $outcome = 'unknown_order';
            Log::warning('midtrans.order tidak ditemukan', ['order_id' => $orderId]);
        } elseif ($success) {
            if ($amount === null || $amount !== (int) $order->total) {
                $outcome = 'amount_mismatch';
                Log::warning('midtrans.nominal tidak cocok', ['order_id' => $orderId]);
            } elseif ($order->payments()->where('provider', self::PROVIDER)
                ->where('provider_transaction_id', (string) $status['transaction_id'])
                ->where('outcome', 'paid')->where('amount', $amount)->exists()) {
                $outcome = 'reconciled';
            } elseif ($order->status !== 'pending') {
                $outcome = 'order_not_payable';
                Log::alert('midtrans.pembayaran sukses pada order tidak pending; perlu refund manual', [
                    'order_id' => $orderId, 'order_status' => $order->status,
                ]);
            } elseif ($this->checkout->markPaid($order)) {
                $outcome = 'paid';
            } else {
                $outcome = 'order_not_payable';
            }
        }

        Payment::create([
            'order_id' => $order?->id,
            'provider' => self::PROVIDER,
            'provider_transaction_id' => (string) $status['transaction_id'],
            'provider_order_id' => $orderId,
            'event_status' => $ledgerEvent,
            'amount' => $amount ?? 0,
            'outcome' => $outcome,
            'payload' => collect($status)->only([
                'order_id', 'transaction_id', 'transaction_status', 'fraud_status',
                'gross_amount', 'currency', 'status_code', 'payment_type', 'transaction_time',
            ])->all(),
        ]);
    }

    private function fetchStatus(string $orderId, string $serverKey): ?array
    {
        $base = config('services.midtrans.is_production') ? 'https://api.midtrans.com' : 'https://api.sandbox.midtrans.com';
        $attempts = 0;
        $maxAttempts = 2;

        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                $response = Http::withBasicAuth($serverKey, '')->connectTimeout(5)->timeout(5)->get("{$base}/v2/{$orderId}/status");

                return $response->successful() && is_array($response->json()) ? $response->json() : null;
            } catch (ConnectionException) {
                if ($attempts >= $maxAttempts) {
                    return null;
                }
                usleep(200_000);
            }
        }

        return null;
    }

    /** Kegagalan Status API beruntun menaikkan penghitung; ambang membuka circuit. */
    private function recordUpstreamFailure(): void
    {
        Cache::add('midtrans:consecutive_failures', 0, now()->addMinutes(15));
        $failures = (int) Cache::increment('midtrans:consecutive_failures');

        if ($failures >= self::CIRCUIT_FAILURE_THRESHOLD) {
            Cache::put('midtrans:circuit_open_until', now()->addMinutes(self::CIRCUIT_OPEN_MINUTES)->timestamp, now()->addMinutes(self::CIRCUIT_OPEN_MINUTES + 1));
            Log::critical('midtrans.circuit dibuka; Status API gagal beruntun', ['failures' => $failures]);
        }
    }

    private function recordUpstreamSuccess(): void
    {
        Cache::forget('midtrans:consecutive_failures');
    }

    /** "250000" atau "250000.00" → int rupiah; selain itu null (dianggap tidak cocok). */
    private function toRupiah(mixed $value): ?int
    {
        if (! is_scalar($value) || ! preg_match('/^(\d+)(\.00)?$/', (string) $value, $m)) {
            return null;
        }

        return (int) $m[1];
    }
}
