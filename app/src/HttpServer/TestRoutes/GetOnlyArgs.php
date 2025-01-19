<?php

declare(strict_types=1);

namespace App\HttpServer\TestRoutes;

use Sapien\Response;

use function json_encode;

use const JSON_THROW_ON_ERROR;

readonly final class GetOnlyArgs
{
    public function __invoke(string $first, string $second): Response
    {
        return (new Response())
            ->setHeader('Content-Type', 'application/json')
            ->setContent(
                json_encode([
                    'first' => $first,
                    'second' => $second,
                ], JSON_THROW_ON_ERROR),
            );
    }
}
