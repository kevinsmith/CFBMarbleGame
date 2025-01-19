<?php

declare(strict_types=1);

namespace App\HttpServer\TestRoutes;

use Sapien\Response;

readonly final class GetPlain
{
    public function __invoke(): Response
    {
        return new Response();
    }
}
