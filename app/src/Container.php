<?php

declare(strict_types=1);

namespace App;

use App\HttpServer\Routes;
use DI\ContainerBuilder;
use FastRoute\Dispatcher;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\WebProcessor;
use Psr\Container\ContainerInterface;

use function FastRoute\simpleDispatcher;
use function getenv;

final readonly class Container
{
    public static function init(): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(false);
        $builder->addDefinitions(self::definitions());

        return $builder->build();
    }

    /** @return array<string, mixed> */
    private static function definitions(): array
    {
        return [
            Dispatcher::class => static function (ContainerInterface $c) {
                return simpleDispatcher($c->get(Routes::class));
            },
            Logger::class => static function () {
                $serverData                = $_SERVER;
                $serverData['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];

                return new Logger('app')
                    ->pushHandler(new StreamHandler(
                        'php://stdout',
                        getenv('LOG_LEVEL') ?: Level::Notice,
                    ))
                    ->pushProcessor(new WebProcessor($serverData));
            },
        ];
    }
}
