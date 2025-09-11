<?php

declare(strict_types=1);

namespace App\Rankings;

final readonly class Game
{
    public function __construct(
        public GameId $id,
        public Team $homeTeam,
        public Team $awayTeam,
    ) {
    }
}
