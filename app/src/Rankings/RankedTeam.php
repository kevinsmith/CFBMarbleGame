<?php

declare(strict_types=1);

namespace App\Rankings;

final readonly class RankedTeam
{
    public function __construct(
        public string $teamName,
        public string $conference,
        public int $wins,
        public int $losses,
        public int $marbleCount,
        public int $marbleRank,
    ) {
    }
}
