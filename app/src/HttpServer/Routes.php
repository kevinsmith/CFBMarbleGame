<?php

declare(strict_types=1);

namespace App\HttpServer;

use App\HttpServer\TestRoutes\GetArgsAndQueryParam;
use App\HttpServer\TestRoutes\GetOnlyArgs;
use App\HttpServer\TestRoutes\GetOnlyQueryParam;
use App\HttpServer\TestRoutes\GetPlain;
use FastRoute\RouteCollector;

final readonly class Routes
{
    public function __invoke(RouteCollector $r): void
    {
        $r->get('/', TempWelcome::class);

        $r->addGroup('/QZfvbotRlJ/test-routes', static function (RouteCollector $r): void {
            $r->get('/plain', GetPlain::class);
            $r->get('/only-args/{first}/{second}', GetOnlyArgs::class);
            $r->get('/only-query-param', GetOnlyQueryParam::class);
            $r->get('/args-and-query-param/{required}[/{optional:\d+}]', GetArgsAndQueryParam::class);
        });
    }
}
