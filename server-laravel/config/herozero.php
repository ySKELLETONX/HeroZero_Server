<?php

/**
 * Configuracao do servidor Hero Zero (emulado) — portada de server/config.php.
 * O banco e o nosso MySQL em Docker (server/docker/docker-compose.yml, porta 3308).
 */
return [
    'db' => [
        'host'    => env('HZ_DB_HOST', '127.0.0.1'),
        'port'    => (int) env('HZ_DB_PORT', 3308),
        'name'    => env('HZ_DB_NAME', 'herozero'),
        'user'    => env('HZ_DB_USER', 'root'),
        'pass'    => env('HZ_DB_PASS', 'herozero'),
        'charset' => 'utf8mb4',
    ],
];
