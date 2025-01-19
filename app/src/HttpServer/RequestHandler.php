<?php

declare(strict_types=1);

namespace App\HttpServer;

use App\HttpServer\Errors\MethodNotAllowed;
use App\HttpServer\Errors\NotFound;
use DI\Container;
use FastRoute\Dispatcher;
use Invoker\Invoker;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\ResolverChain;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Sapien\Request;
use Sapien\Response;

use function assert;
use function is_callable;
use function rawurldecode;
use function sprintf;

final readonly class RequestHandler
{
    public function __construct(
        private Dispatcher $dispatcher,
        private ContainerInterface $container,
        private NotFound $notFound,
        private MethodNotAllowed $methodNotAllowed,
    ) {
    }

    public function handleRequest(Request $request): Response
    {
        $routeInfo = $this->dispatcher->dispatch(
            $request->method->name ?? '',
            rawurldecode($request->url->path ?? ''),
        );

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $callable  = $this->notFound;
                $arguments = [];
                break;

            case Dispatcher::METHOD_NOT_ALLOWED:
                $callable  = $this->methodNotAllowed;
                $arguments = [$routeInfo[1]];
                break;

            default:
                /** @phpstan-ignore argument.type */
                $callable = $this->container->get($routeInfo[1]);
                assert(is_callable($callable));
                $arguments = $routeInfo[2];
                break;
        }

        $response = $this->getInvoker($request)
            /** @phpstan-ignore argument.type */
            ->call($callable, $arguments);

        if (! ($response instanceof Response)) {
            throw new RuntimeException(sprintf(
                'Action response must be %s',
                Response::class,
            ));
        }

        return $response;
    }

    private function getInvoker(Request $request): Invoker
    {
        $invoker           = new Invoker();
        $parameterResolver = $invoker->getParameterResolver();

        if (! $parameterResolver instanceof ResolverChain) {
            throw new RuntimeException('Variable $parameterResolver must be instance of ' . ResolverChain::class);
        }

        $parameterResolver->prependResolver(
            new TypeHintContainerResolver(
                new Container([Request::class => $request]),
            ),
        );

        return $invoker;
    }
}
