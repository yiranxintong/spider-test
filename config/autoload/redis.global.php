<?php

declare(strict_types=1);

use \App\Container;

return [
    'dependencies' => [
        'factories' => [
            Redis::class => Container\RedisFactory::class,
        ],
    ],
    'redis'       => [
        'host' => getenv('REDIS_HOST') ?: 'localhost',
        'port' => getenv('REDIS_PORT') ?: 6379,
        'password' => getenv('REDIS_PASSWORD') ?: '',
        'database' => getenv('REDIS_DATABASE') ?: 0,
    ],
];
