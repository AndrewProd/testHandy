<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;

return [
    'default' => env('LOG_CHANNEL', 'stderr'),

    'channels' => [
        'stderr' => [
            'driver'  => 'monolog',
            'handler' => StreamHandler::class,
            'with'    => [
                'stream' => 'php://stderr',
            ],
            'level'   => env('LOG_LEVEL', 'debug'),
        ],

        'null' => [
            'driver'  => 'monolog',
            'handler' => NullHandler::class,
        ],
    ],
];
