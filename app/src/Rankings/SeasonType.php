<?php

declare(strict_types=1);

namespace App\Rankings;

use InvalidArgumentException;

use function mb_strtolower;
use function trim;

enum SeasonType
{
    case Regular;
    case Postseason;

    public static function fromString(string $value): self
    {
        return match (mb_strtolower(trim($value))) {
            'regular' => self::Regular,
            'postseason' => self::Postseason,
            default => throw new InvalidArgumentException('Unknown season type: ' . $value),
        };
    }
}
