<?php

declare(strict_types=1);

namespace App\Rankings\Polls;

interface ApPollRepository
{
    /** @return array<int, int> Ranks indexed by internal team ID. */
    public function getRanks(int $season, int $week): array;
}
