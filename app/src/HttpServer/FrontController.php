<?php

declare(strict_types=1);

namespace App\HttpServer;

use Sapien\Request;

final readonly class FrontController
{
    public function __construct(
        private RequestHandler $requestHandler,
        private ResponseHandler $responseHandler,
    ) {
    }

    public function run(): void
    {
        $this->responseHandler->handleResponse(
            $this->requestHandler->handleRequest(
                new Request(),
            ),
        );
    }
}
