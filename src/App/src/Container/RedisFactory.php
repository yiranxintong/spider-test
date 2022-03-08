<?php

namespace App\Container;

use Psr\Container\ContainerInterface;
use Redis;

class RedisFactory
{
    public function __invoke(ContainerInterface $container): Redis
    {
        $config = $container->has('config') ? $container->get('config') : [];
        $config = $config['redis'] ?? [];

        $redis = new Redis();
        $redis->connect($config['host'], $config['port']);
        if (!empty($config['password'])) {
            $redis->auth($config['password']);
        }
        $redis->select($config['database']);

        return $redis;
    }
}