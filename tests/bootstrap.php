<?php

require dirname(__DIR__).'/vendor/autoload.php';

// Never let a development config cache or inherited shell DB settings reach tests.
$isolatedCache = __DIR__.'/.disabled-config-cache.php';
if (file_exists($isolatedCache)) {
    throw new RuntimeException('Remove the unexpected test configuration cache before running tests.');
}
foreach ([
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => $isolatedCache,
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'BROADCAST_CONNECTION' => 'null',
] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
