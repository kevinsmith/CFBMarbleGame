<?php

declare(strict_types=1);

namespace App\Rankings\Teams;

use InvalidArgumentException;

use function mb_strtoupper;
use function trim;

enum Subdivision
{
    case FBS;
    case FCS;

    public static function fromString(string $value): self
    {
        return match (mb_strtoupper(trim($value))) {
            'FBS' => self::FBS,
            'FCS' => self::FCS,
            default => throw new InvalidArgumentException('Unknown subdivision: ' . $value),
        };
    }
}
