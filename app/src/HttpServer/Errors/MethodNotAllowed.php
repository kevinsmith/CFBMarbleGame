<?php

declare(strict_types=1);

namespace App\HttpServer\Errors;

use Sapien\Response;

use function implode;

final readonly class MethodNotAllowed
{
    /** @param string[] $allow */
    public function __invoke(array $allow): Response
    {
        $allowed = implode(',', $allow);

        return new Response()
            ->setCode(405)
            ->setHeader('Allow', $allowed);
    }
}
