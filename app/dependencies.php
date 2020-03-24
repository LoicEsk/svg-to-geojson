<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\Setup;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get('settings');

            $loggerSettings = $settings['logger'];
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

        EntityManager::class => function (ContainerInterface $container): EntityManager {
            $settings = $container->get('settings');
            
            $config = Setup::createAnnotationMetadataConfiguration(
                $settings['doctrine']['metadata_dirs'],
                $settings['doctrine']['dev_mode']
            );
        
            $config->setMetadataDriverImpl(
                new AnnotationDriver(
                    new AnnotationReader,
                    $settings['doctrine']['metadata_dirs']
                )
            );
        
            $config->setMetadataCacheImpl(
                new FilesystemCache(
                    $settings['doctrine']['cache_dir']
                )
            );
        
            return EntityManager::create(
                $settings['doctrine']['connection'],
                $config
            );
        },

        'view' => function( ContainerInterface $container ) {
            $settings = $container->get('settings');
            $twigTemplatePath = $settings['twig']['templatePath'];
            $twigCachePath = $settings['dev_mode'] ? false : $settings['twig']['cachePath'];

            $loader = new \Twig\Loader\FilesystemLoader( $twigTemplatePath );
            $twig = new \Slim\Views\Twig( $loader , [
                'cache' => $twigCachePath,
                'debug' => true
            ]);
        
            // Instantiate and add Slim specific extension
            // $router = $container->get('router');
            // $uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
            // $twig->addExtension(new \Slim\Views\TwigExtension($router, $uri));
            
            $twig->addExtension(new \Twig\Extension\DebugExtension());
        
            return $twig;
        }
    ]);
};
