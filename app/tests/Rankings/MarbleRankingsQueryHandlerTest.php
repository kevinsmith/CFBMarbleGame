<?php

declare(strict_types=1);

namespace Tests\Rankings;

use App\Rankings\Games\Game;
use App\Rankings\Games\GameRepository;
use App\Rankings\Games\Winner;
use App\Rankings\MarbleOrchestrator;
use App\Rankings\MarbleRankingsQueryHandler;
use App\Rankings\RankedTeam;
use App\Rankings\Teams\Conference;
use App\Rankings\Teams\Subdivision;
use App\Rankings\Teams\Team;
use App\Rankings\Teams\TeamId;
use App\Rankings\Teams\TeamRepository;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(MarbleRankingsQueryHandler::class)]
final class MarbleRankingsQueryHandlerTest extends TestCase
{
    public function testDefaultWeekIsOnePastTheMostRecentCompleteWeek(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);

        $games = [
            TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Home),
        ];

        $handler = $this->makeHandler([$texas, $oklahoma], $games);

        [$week, $latestWeekWithRankings] = $handler->getRankings();

        self::assertSame(2, $week);
        self::assertSame(2, $latestWeekWithRankings);
    }

    public function testDefaultWeekIgnoresAnIncompleteFinalWeek(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);

        $games = [
            TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Home),
            TeamGameFactory::game(11, 2, $oklahoma, $texas, null),
        ];

        $handler = $this->makeHandler([$texas, $oklahoma], $games);

        [$week, $latestWeekWithRankings] = $handler->getRankings();

        self::assertSame(2, $week);
        self::assertSame(2, $latestWeekWithRankings);
    }

    public function testExplicitWeekBelowTheLatestIsUsedForTheRankings(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);

        $games = [
            TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Home),
        ];

        $handler = $this->makeHandler([$texas, $oklahoma], $games);

        [$week, $latestWeekWithRankings, $rankedTeams] = $handler->getRankings(1);

        self::assertSame(1, $week);
        self::assertSame(2, $latestWeekWithRankings);
        self::assertSame(['Oklahoma' => 110, 'Texas' => 110], self::marbleCountsByName($rankedTeams));
    }

    public function testRequestingAWeekBeyondTheLatestThrows(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);

        $games = [
            TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Home),
        ];

        $handler = $this->makeHandler([$texas, $oklahoma], $games);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rankings not yet available for week 3.');

        $handler->getRankings(3);
    }

    public function testTeamsAreMappedToRankedTeams(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);
        $southDakotaState = TeamGameFactory::team(
            3,
            'South Dakota State',
            subdivision: Subdivision::FCS,
            conference: Conference::MVFC,
        );

        $games = [
            TeamGameFactory::game(10, 1, $southDakotaState, $texas, Winner::Home),
        ];

        $handler = $this->makeHandler([$texas, $oklahoma, $southDakotaState], $games);

        [, , $rankedTeams] = $handler->getRankings();

        self::assertEquals(
            [
                new RankedTeam('Oklahoma', 'Big 12', 100, 1, false),
                new RankedTeam('Texas', 'SEC', 75, 2, false),
                new RankedTeam('South Dakota State', 'MVFC', 25, 3, true),
            ],
            $rankedTeams,
        );
    }

    /**
     * @param Team[] $teams
     * @param Game[] $games
     */
    private function makeHandler(array $teams, array $games): MarbleRankingsQueryHandler
    {
        return new MarbleRankingsQueryHandler(
            self::makeTeamRepository($teams),
            self::makeGameRepository($games),
            new MarbleOrchestrator(new NullLogger()),
        );
    }

    /** @param Team[] $teams */
    private static function makeTeamRepository(array $teams): TeamRepository
    {
        return new class ($teams) implements TeamRepository {
            /** @param Team[] $teams */
            public function __construct(private readonly array $teams)
            {
            }

            /** @return Team[] */
            public function getTeams(): array
            {
                return $this->teams;
            }

            public function getTeam(TeamId $teamId): Team
            {
                throw new RuntimeException('Not used.');
            }
        };
    }

    /** @param Game[] $games */
    private static function makeGameRepository(array $games): GameRepository
    {
        return new class ($games) implements GameRepository {
            /** @param Game[] $games */
            public function __construct(private readonly array $games)
            {
            }

            /** @return Game[] */
            public function getGames(): array
            {
                return $this->games;
            }
        };
    }

    /**
     * @param RankedTeam[] $rankedTeams
     *
     * @return array<string, int>
     */
    private static function marbleCountsByName(array $rankedTeams): array
    {
        $counts = [];

        foreach ($rankedTeams as $rankedTeam) {
            $counts[$rankedTeam->teamName] = $rankedTeam->marbleCount;
        }

        return $counts;
    }
}
