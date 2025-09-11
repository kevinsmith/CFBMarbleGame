<?php

declare(strict_types=1);

namespace App\Rankings;

use InvalidArgumentException;

final class Team
{
    private int $initialMarbles = 0;

    private int $marbleRank;

    public function __construct(
        public readonly TeamId $id,
        public readonly string $teamName,
        public readonly Subdivision $subdivision,
        public readonly Conference $conference,
    ) {
    }

    public function receiveInitialMarbles(int $marbles): void
    {
        if ($marbles < 0) {
            throw new InvalidArgumentException('Marbles cannot be negative');
        }

        $this->initialMarbles = $marbles;
    }

    public function getMarbles(): int
    {
        return $this->initialMarbles;
    }

    public function setMarbleRank(int $rank): void
    {
        if ($rank < 0) {
            throw new InvalidArgumentException('Rank cannot be negative');
        }

        $this->marbleRank = $rank;
    }

    public function getMarbleRank(): int
    {
        return $this->marbleRank;
    }
}
