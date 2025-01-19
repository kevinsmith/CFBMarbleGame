<?php

declare(strict_types=1);

namespace App\HttpServer\TestRoutes;

use Sapien\Request;
use Sapien\Response;

use function json_encode;

use const JSON_THROW_ON_ERROR;

readonly final class GetOnlyQueryParam
{
    public function __invoke(Request $request): Response
    {
        $param = $request->query['param'] ?? '';

        return (new Response())
            ->setHeader('Content-Type', 'application/json')
            ->setContent(
                json_encode(['param' => $param], JSON_THROW_ON_ERROR),
            );
    }
}
