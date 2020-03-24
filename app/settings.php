<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;
use Dotenv\Dotenv;

define('APP_ROOT', __DIR__ . "/..");

return function (ContainerBuilder $containerBuilder) {

    // lecture .env
    if(!file_exists( APP_ROOT . '/.env')) copy( APP_ROOT . '/exemple.env', APP_ROOT . '/.env' ); // création du .env s'il n'existe pas
    $dotenv = Dotenv::createImmutable( APP_ROOT );
    $dotenv->load();

    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => [
            'dev_mode' => getenv( 'debug' ),
            'displayErrorDetails' => true, // Should be set to false in production
            'logger' => [
                'name' => 'slim-app',
                'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                'level' => Logger::DEBUG,
            ],
            'doctrine' => [
                // if true, metadata caching is forcefully disabled
                'dev_mode' => true,
    
                // path where the compiled metadata info will be cached
                // make sure the path exists and it is writable
                'cache_dir' => APP_ROOT . '/var/doctrine',
    
                // you should add any other path containing annotated entity classes
                'metadata_dirs' => [APP_ROOT . '/src/Domain'],
    
                'connection' => [
                    'driver'    => 'pdo_mysql',
                    'host'      => getenv('db_host'),
                    'port'      => 3306,
                    'dbname'    => getenv('db_name'),
                    'user'      => getenv('db_user'),
                    'password'  => getenv('db_pwd'),
                    'charset'   => 'utf8'
                ]
            ], 
            'twig'  => [
                'templatePath' => APP_ROOT . '/templates/twig',
                'cachePath'     => APP_ROOT . '/var/cache/twig'
            ]
        ],
    ]);
};
