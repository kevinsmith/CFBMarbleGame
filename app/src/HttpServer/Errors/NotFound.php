<?php

declare(strict_types=1);

namespace App\HttpServer\Errors;

use Sapien\Response;

final readonly class NotFound
{
    public function __invoke(): Response
    {
        return new Response()->setCode(404);
    }
}
