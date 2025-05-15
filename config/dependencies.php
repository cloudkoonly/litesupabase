<?php

global $settings;

use DI\ContainerBuilder;
use Litesupabase\Library\Cache;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Litesupabase\Library\DB;
use Slim\Views\PhpRenderer;
use Slim\Middleware\Session;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get('settings');
            $root = $settings['root'];
            $loggerName = $settings['LOGGER_NAME'];
            $loggerFile = $settings['LOGGER_PATH'];
            $logger = new Logger($loggerName);
            $processor = new UidProcessor();
            $logger->pushProcessor($processor);
            $processor = new IntrospectionProcessor();
            $logger->pushProcessor($processor);
            $processor = new WebProcessor();
            $logger->pushProcessor($processor);
            $handler = new StreamHandler($root . $loggerFile);
            $logger->pushHandler($handler);
            return $logger;
        },
        PhpRenderer::class => function (ContainerInterface $c) {
            $root = $c->get('settings')['root'];
            return new PhpRenderer($root.'templates');
        },
        DB::class => function (ContainerInterface $c) {
            $settings = $c->get('settings');
            $host     = $settings['DB_HOST'];
            $dbname   = $settings['DB_NAME'];
            $user     = $settings['DB_USER'];
            $pass     = $settings['DB_PASS'];
            $port     = $settings['DB_PORT'];
            return new DB($host, $user, $pass, $dbname, $port);
        },
        Session::class => function (ContainerInterface $c) {
            return new Session([
                'name' => 'dummy_session',
                'autorefresh' => true,
                'lifetime' => '1 day',
            ]);
        },
        Cache::class => function (ContainerInterface $c) {
            $settings = $c->get('settings');
            $config = [
                'driver' => $settings['CACHE_DRIVER'],
                'file' => [
                    'path' => $settings['root'] . $settings['CACHE_PATH'],
                ],
                'redis' => [
                    'host' => $settings['CACHE_HOST'],
                    'port' => $settings['CACHE_PORT'],
                    'database' => $settings['CACHE_DB'],
                    'timeout' => $settings['CACHE_TIMEOUT'],
                ],
            ];
            return new Cache($config);
        },
    ]);
};
