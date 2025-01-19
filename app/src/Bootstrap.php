<?php

declare(strict_types=1);

namespace App;

use Monolog\ErrorHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;

use function assert;
use function getenv;

final readonly class Bootstrap
{
    public static function init(): ContainerInterface
    {
        $container = Container::init();

        if (getenv('DEBUG_MODE')) {
            $isJsonRequest = ($_SERVER['CONTENT_TYPE'] ?? '') === 'application/json';

            $errorHandler = $container->get(WhoopsErrorHandler::class);
            assert($errorHandler instanceof WhoopsErrorHandler);

            if ($isJsonRequest) {
                $errorHandler->registerJsonResponder();
            } else {
                $errorHandler->registerHtmlResponder();
            }
        }

        /** @phpstan-ignore argument.type */
        ErrorHandler::register($container->get(Logger::class));

        return $container;
    }
}
