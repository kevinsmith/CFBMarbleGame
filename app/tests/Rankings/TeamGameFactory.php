<?php

declare(strict_types=1);

namespace Tests\Rankings;

use App\Rankings\Games\Game;
use App\Rankings\Games\GameId;
use App\Rankings\Games\Winner;
use App\Rankings\Teams\Conference;
use App\Rankings\Teams\Subdivision;
use App\Rankings\Teams\Team;
use App\Rankings\Teams\TeamId;
use DateTimeImmutable;

final class TeamGameFactory
{
    public static function team(
        int $id,
        string $name,
        Subdivision $subdivision = Subdivision::FBS,
        Conference $conference = Conference::FBSIndependents,
    ): Team {
        return new Team(
            TeamId::fromDatabase($id),
            $name,
            $subdivision,
            $conference,
        );
    }

    public static function game(
        int $id,
        int $weekNumber,
        Team $homeTeam,
        Team $awayTeam,
        Winner|null $winner = null,
        bool $neutralSite = false,
        string $date = '2025-09-01T00:00:00Z',
        int $season = 2025,
    ): Game {
        return new Game(
            GameId::fromDatabase($id),
            new DateTimeImmutable($date),
            $weekNumber,
            $season,
            $neutralSite,
            $homeTeam,
            $awayTeam,
            $winner,
        );
    }
}
