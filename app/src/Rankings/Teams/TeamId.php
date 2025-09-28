<?php

declare(strict_types=1);

namespace App\Rankings\Teams;

final readonly class TeamId
{
    private function __construct(public int $id)
    {
    }

    public static function fromDatabase(int $id): self
    {
        return new self($id);
    }
}
