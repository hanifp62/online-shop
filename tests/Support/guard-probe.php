<?php

use Illuminate\Database\ConfigurationUrlParser;
use Tests\TestCase;

// Minimal probe: no kernel, no .env, no PDO. Resolves a fixture connection array from the process env only.
require dirname(__DIR__, 2).'/vendor/autoload.php';

$config = array_filter([
    'driver' => getenv('PROBE_DRIVER') ?: null,
    'database' => getenv('PROBE_DATABASE') ?: null,
    'url' => getenv('PROBE_URL') ?: null,
    'read' => getenv('PROBE_READ') ? ['host' => 'x'] : null,
]);
$config = (new ConfigurationUrlParser)->parseConfiguration($config);

try {
    TestCase::validateTestDatabase($config, getenv('ZAFIRA_SAFE_MYSQL_TESTS') === '1');
    echo "ACCEPTED\n";
} catch (RuntimeException $e) {
    echo "REJECTED\n";
}
// This script never creates an application or a database connection.
