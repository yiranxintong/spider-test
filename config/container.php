<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;
use Laminas\Di;
use Laminas\Di\Container;

// Load configuration
$config = require __DIR__ . '/config.php';

$dependencies                       = $config['dependencies'];
$dependencies['services']['config'] = $config;

// Build container
$serviceManager = new ServiceManager($dependencies);

$serviceManager->setFactory(Di\ConfigInterface::class, Container\ConfigFactory::class);
$serviceManager->setFactory(Di\InjectorInterface::class, Container\InjectorFactory::class);

return $serviceManager;
