<?php

namespace App\Container;

use Http\Adapter\Guzzle6\Client;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;

class HttpClientFactory
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null) : ClientInterface
    {
        $cliOptions = [ 'connect_timeout' => 60, 'read_timeout' => 600, 'timeout' => 3600 ];
        $webOptions = [ 'connect_timeout' => 10, 'read_timeout' => 10, 'timeout' => 60 ];

        return Client::createWithConfig(PHP_SAPI === 'cli' ? $cliOptions : $webOptions);
    }
}
