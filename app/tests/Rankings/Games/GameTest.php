<?php

declare(strict_types=1);

namespace Tests\Rankings\Games;

use App\Rankings\Games\Game;
use App\Rankings\Games\GameId;
use App\Rankings\Games\Winner;
use App\Rankings\Teams\Team;
use App\Rankings\Teams\TeamId;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Rankings\TeamGameFactory;

#[CoversClass(Game::class)]
#[UsesClass(GameId::class)]
#[UsesClass(Team::class)]
#[UsesClass(TeamId::class)]
final class GameTest extends TestCase
{
    public function testHomeWinMakesTheHomeTeamTheWinner(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas');
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma');

        $game = TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Home);

        self::assertSame($texas, $game->winningTeam());
        self::assertSame($oklahoma, $game->losingTeam());
    }

    public function testAwayWinMakesTheAwayTeamTheWinner(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas');
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma');

        $game = TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Away);

        self::assertSame($oklahoma, $game->winningTeam());
        self::assertSame($texas, $game->losingTeam());
    }

    public function testUnplayedGameHasNoWinner(): void
    {
        $game = TeamGameFactory::game(10, 1, TeamGameFactory::team(1, 'Texas'), TeamGameFactory::team(2, 'Oklahoma'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Game 10 has no winner.');

        $game->losingTeam();
    }
}
