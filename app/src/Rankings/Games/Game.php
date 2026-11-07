<?php

declare(strict_types=1);

namespace App\Rankings\Games;

use App\Rankings\Teams\Team;
use DateTimeInterface;
use LogicException;

final readonly class Game
{
    public function __construct(
        public GameId $id,
        public DateTimeInterface $date,
        public int $weekNumber,
        public int $season,
        public bool $neutralSite,
        public Team $homeTeam,
        public Team $awayTeam,
        public Winner|null $winner,
    ) {
    }

    public function winningTeam(): Team
    {
        return match ($this->winner) {
            Winner::Home => $this->homeTeam,
            Winner::Away => $this->awayTeam,
            null => throw new LogicException('Game ' . $this->id->id . ' has no winner.'),
        };
    }

    public function losingTeam(): Team
    {
        return $this->winningTeam() === $this->homeTeam ? $this->awayTeam : $this->homeTeam;
    }
}
