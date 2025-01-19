<?php

declare(strict_types=1);

namespace App\HttpServer;

use Sapien\Response;

final readonly class ResponseHandler
{
    public function handleResponse(Response $response): void
    {
        $response->send();
    }
}
