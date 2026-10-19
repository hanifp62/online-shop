<?php

// No framework or database dependencies: this runs before Laravel bootstrap.
function amadisGuard(): string
{
    $approved = '/private/var/folders/3n/g8p6ncc153dcc72zmv1bff9m0000gn/T/opencode';
    $root = getenv('AMADIS_ROOT');
    $database = getenv('DB_DATABASE');
    if (! $root || realpath($root) !== $root || dirname($root) !== $approved
        || ! preg_match('/^amadis-[a-f0-9]{24}$/D', basename($root))
        || ! is_file($root.'/identity.json') || is_link($root.'/identity.json')) {
        throw new RuntimeException('Isolation rejected: explicit canonical Amadis root required.');
    }
    $identity = json_decode(file_get_contents($root.'/identity.json'), true, flags: JSON_THROW_ON_ERROR);
    $expected = $root.'/database.sqlite';
    $memoryTest = getenv('AMADIS_TEST_MEMORY') === '1' && $database === ':memory:';
    if (($identity['root'] ?? null) !== $root || ($identity['database'] ?? null) !== $expected
        || getenv('AMADIS_DATABASE') !== $expected
        || (! $memoryTest && ($database !== $expected || realpath($database) !== $expected || is_link($database)))
        || getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_URL') !== '') {
        throw new RuntimeException('Isolation rejected: explicit SQLite identity required.');
    }
    foreach (['LARAVEL_STORAGE_PATH', 'APP_CONFIG_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE', 'VIEW_COMPILED_PATH'] as $key) {
        if (! str_starts_with((string) getenv($key), $root.'/')) {
            throw new RuntimeException("Isolation rejected: $key outside root.");
        }
    }

    return $root;
}
