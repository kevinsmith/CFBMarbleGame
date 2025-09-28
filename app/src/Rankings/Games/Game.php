<?php

declare(strict_types=1);

namespace App\Rankings\Games;

use App\Rankings\Teams\Team;
use DateTimeInterface;

final readonly class Game
{
    public function __construct(
        public GameId $id,
        public DateTimeInterface $date,
        public int $weekNumber,
        public bool $neutralSite,
        public Team $homeTeam,
        public Team $awayTeam,
        public Winner|null $winner,
    ) {
    }
}
