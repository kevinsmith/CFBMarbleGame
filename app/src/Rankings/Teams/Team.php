<?php

declare(strict_types=1);

namespace App\Rankings\Teams;

use InvalidArgumentException;

final class Team
{
    private int $marbles = 0;

    private int $marbleRank;

    public function __construct(
        public readonly TeamId $id,
        public readonly string $teamName,
        public readonly Subdivision $subdivision,
        public readonly Conference $conference,
    ) {
    }

    public function receiveMarbles(int $marbles): void
    {
        if ($marbles < 0) {
            throw new InvalidArgumentException('Marbles cannot be negative');
        }

        $this->marbles += $marbles;
    }

    public function giveUpMarbles(int $marbles): void
    {
        if ($marbles < 0) {
            throw new InvalidArgumentException('Marbles cannot be negative');
        }

        $this->marbles -= $marbles;
    }

    public function getMarbles(): int
    {
        return $this->marbles;
    }

    public function setMarbleRank(int $rank): void
    {
        // BUG: this guard does not match the ranking rules.
        // Standard competition ranking starts at 1.
        // A rank of 0 is not valid, but this guard accepts 0.
        // Do not fix this bug until the characterization test suite is
        // complete.
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
