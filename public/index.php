<?php

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

try {
    // Load system settings and environment variables
    $setting = require __DIR__ . '/../config/settings.php';
    $root = $setting['root']??__DIR__.'/../';
    if (file_exists($root. '.env')) {
        $dotEnv = Dotenv::createImmutable($root);
        $dotEnv->load();
    }
    $settings = array_merge($_ENV,$setting);

    // Create container
    $containerBuilder = new ContainerBuilder();
    $containerBuilder->useAttributes(true);
    $containerBuilder->addDefinitions(['settings' => $settings]);
    $dependencies = require __DIR__ . '/../config/dependencies.php';
    $dependencies($containerBuilder);
    $container = $containerBuilder->build();

    // Create app
    $app = AppFactory::createFromContainer($container);

    // Register routes
    $routes = require __DIR__ . '/../config/routes.php';
    $routes($app);
    $app->addRoutingMiddleware();

    /**
     * Add Error Middleware
     *
     * @param bool                  $displayErrorDetails -> Should be set to false in production
     * @param bool                  $logErrors -> Parameter is passed to the default ErrorHandler
     * @param bool                  $logErrorDetails -> Display error details in error log
     * @param LoggerInterface|null  $logger -> Optional PSR-3 Logger
     *
     * Note: This middleware should be added last. It will not handle any exceptions/errors
     * for middleware added after it.
     */
    $app->addErrorMiddleware(true, true, true);
    $app->run();
} catch (Exception $e) {
    die("Error loading application:".$e->getMessage());
}
