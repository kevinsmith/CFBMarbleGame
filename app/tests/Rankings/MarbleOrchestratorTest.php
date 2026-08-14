<?php

declare(strict_types=1);

namespace Tests\Rankings;

use App\Rankings\Games\Game;
use App\Rankings\Games\Winner;
use App\Rankings\MarbleOrchestrator;
use App\Rankings\Teams\Conference;
use App\Rankings\Teams\Subdivision;
use App\Rankings\Teams\Team;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

use function array_map;
use function array_values;

#[CoversClass(MarbleOrchestrator::class)]
final class MarbleOrchestratorTest extends TestCase
{
    private function makeOrchestrator(): MarbleOrchestrator
    {
        return new MarbleOrchestrator(new NullLogger());
    }

    /**
     * @param Team[] $teams
     *
     * @return array<string, int>
     */
    private static function marbleCountsByName(array $teams): array
    {
        $counts = [];

        foreach ($teams as $team) {
            $counts[$team->teamName] = $team->getMarbles();
        }

        return $counts;
    }

    /**
     * @param Team[] $teams
     *
     * @return array<string, int>
     */
    private static function ranksByName(array $teams): array
    {
        $ranks = [];

        foreach ($teams as $team) {
            $ranks[$team->teamName] = $team->getMarbleRank();
        }

        return $ranks;
    }

    /**
     * @param Team[] $teams
     *
     * @return string[]
     */
    private static function teamNames(array $teams): array
    {
        return array_values(
            array_map(
                static fn (Team $team): string => $team->teamName,
                $teams,
            ),
        );
    }

    public function testPreseasonGivesEveryFbsTeamOneHundredMarblesAndTiesShareRank(): void
    {
        $alabama = TeamGameFactory::team(1, 'Alabama', conference: Conference::SEC);
        $vanderbilt = TeamGameFactory::team(2, 'Vanderbilt', conference: Conference::SEC);

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(1, [$alabama, $vanderbilt], []);

        self::assertSame(['Alabama', 'Vanderbilt'], self::teamNames($rankedTeams));
        self::assertSame(['Alabama' => 100, 'Vanderbilt' => 100], self::marbleCountsByName($rankedTeams));
        self::assertSame(['Alabama' => 1, 'Vanderbilt' => 1], self::ranksByName($rankedTeams));
    }

    public function testInitialMarblesAddTenPerPowerConferenceOpponentAndTenForNotreDame(): void
    {
        $alabama = TeamGameFactory::team(1, 'Alabama', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);
        $notreDame = TeamGameFactory::team(3, 'Notre Dame', conference: Conference::FBSIndependents);
        $army = TeamGameFactory::team(4, 'Army', conference: Conference::AmericanAthletic);

        $games = [
            TeamGameFactory::game(10, 1, $alabama, $oklahoma),
            TeamGameFactory::game(11, 1, $alabama, $notreDame),
        ];

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(1, [$alabama, $oklahoma, $notreDame, $army], $games);

        self::assertSame(
            ['Alabama' => 120, 'Notre Dame' => 110, 'Oklahoma' => 110, 'Army' => 100],
            self::marbleCountsByName($rankedTeams),
        );
        self::assertSame(
            ['Alabama' => 1, 'Notre Dame' => 2, 'Oklahoma' => 2, 'Army' => 4],
            self::ranksByName($rankedTeams),
        );
        self::assertSame(['Alabama', 'Notre Dame', 'Oklahoma', 'Army'], self::teamNames($rankedTeams));
    }

    public function testConferenceChampionshipGamesAreExcludedFromInitialMarbleScheduleBonus(): void
    {
        [$texas, $ohioState] = self::makeTexasOhioStatePair();
        $games = [TeamGameFactory::game(10, 15, $texas, $ohioState)];

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(1, [$texas, $ohioState], $games);

        self::assertSame(['Ohio State' => 100, 'Texas' => 100], self::marbleCountsByName($rankedTeams));

        [$texasWeekOne, $ohioStateWeekOne] = self::makeTexasOhioStatePair();
        $weekOneGames = [TeamGameFactory::game(11, 1, $texasWeekOne, $ohioStateWeekOne)];

        $rankedTeamsWithWeekOneGame = $this->makeOrchestrator()->getRankedTeams(1, [$texasWeekOne, $ohioStateWeekOne], $weekOneGames);

        self::assertSame(['Ohio State' => 110, 'Texas' => 110], self::marbleCountsByName($rankedTeamsWithWeekOneGame));
    }

    /** @return array{Team, Team} */
    private static function makeTexasOhioStatePair(): array
    {
        return [
            TeamGameFactory::team(1, 'Texas', conference: Conference::SEC),
            TeamGameFactory::team(2, 'Ohio State', conference: Conference::BigTen),
        ];
    }

    public function testFcsTeamsGetNoInitialMarblesAndAreDropped(): void
    {
        $southDakotaState = TeamGameFactory::team(1, 'South Dakota State', subdivision: Subdivision::FCS, conference: Conference::MVFC);
        $texas = TeamGameFactory::team(2, 'Texas', conference: Conference::SEC);

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(1, [$southDakotaState, $texas], []);

        self::assertSame(['Texas'], self::teamNames($rankedTeams));
        self::assertSame(['Texas' => 100], self::marbleCountsByName($rankedTeams));
    }

    public function testHomeWinTransfersTwentyPercentOfLoserMarbles(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);

        $games = [TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Home)];

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(2, [$texas, $oklahoma], $games);

        self::assertSame(['Texas' => 132, 'Oklahoma' => 88], self::marbleCountsByName($rankedTeams));
        self::assertSame(['Texas' => 1, 'Oklahoma' => 2], self::ranksByName($rankedTeams));
    }

    public function testAwayWinTransfersTwentyFivePercentRoundedHalfAwayFromZero(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);

        $games = [TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Away)];

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(2, [$texas, $oklahoma], $games);

        self::assertSame(['Oklahoma' => 138, 'Texas' => 82], self::marbleCountsByName($rankedTeams));
    }

    public function testNeutralSiteGameTransfersTwentyPercentRegardlessOfWinningLocation(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);

        $games = [TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Away, neutralSite: true)];

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(2, [$texas, $oklahoma], $games);

        self::assertSame(['Oklahoma' => 132, 'Texas' => 88], self::marbleCountsByName($rankedTeams));
    }

    public function testFbsWinOverFcsOpponentTransfersNothing(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $southDakotaState = TeamGameFactory::team(2, 'South Dakota State', subdivision: Subdivision::FCS, conference: Conference::MVFC);

        $games = [TeamGameFactory::game(10, 1, $texas, $southDakotaState, Winner::Home)];

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(2, [$texas, $southDakotaState], $games);

        self::assertSame(['Texas' => 100], self::marbleCountsByName($rankedTeams));
    }

    public function testFcsWinOverFbsOpponentTransfersTwentyFivePercentAndFcsTeamSurvives(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $southDakotaState = TeamGameFactory::team(2, 'South Dakota State', subdivision: Subdivision::FCS, conference: Conference::MVFC);

        $games = [TeamGameFactory::game(10, 1, $texas, $southDakotaState, Winner::Away)];

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(2, [$texas, $southDakotaState], $games);

        self::assertSame(['Texas' => 75, 'South Dakota State' => 25], self::marbleCountsByName($rankedTeams));
    }

    public function testGamesAreOnlyAppliedUpToTheWeekBeforeTheRequestedWeek(): void
    {
        [$weekTwoTeams, $games] = self::makeTexasOklahomaGeorgiaLsuFixture();
        $weekTwoView = $this->makeOrchestrator()->getRankedTeams(2, $weekTwoTeams, $games);

        self::assertSame(
            ['Texas' => 144, 'Georgia' => 120, 'LSU' => 120, 'Oklahoma' => 96],
            self::marbleCountsByName($weekTwoView),
        );

        [$weekThreeTeams, $games] = self::makeTexasOklahomaGeorgiaLsuFixture();
        $weekThreeView = $this->makeOrchestrator()->getRankedTeams(3, $weekThreeTeams, $games);

        self::assertSame(
            ['Georgia' => 144, 'Texas' => 144, 'LSU' => 96, 'Oklahoma' => 96],
            self::marbleCountsByName($weekThreeView),
        );
    }

    public function testGamesApplyCumulativelyAcrossWeeks(): void
    {
        [$teams, $games] = self::makeTexasOklahomaGeorgiaLsuFixture();

        $rankedTeams = $this->makeOrchestrator()->getRankedTeams(5, $teams, $games);

        self::assertSame(
            ['Texas' => 173, 'Georgia' => 115, 'Oklahoma' => 115, 'LSU' => 77],
            self::marbleCountsByName($rankedTeams),
        );
    }

    /** @return array{0: Team[], 1: Game[]} */
    private static function makeTexasOklahomaGeorgiaLsuFixture(): array
    {
        $teams = [
            TeamGameFactory::team(1, 'Texas', conference: Conference::SEC),
            TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12),
            TeamGameFactory::team(3, 'Georgia', conference: Conference::SEC),
            TeamGameFactory::team(4, 'LSU', conference: Conference::SEC),
        ];

        $games = [
            TeamGameFactory::game(10, 1, $teams[0], $teams[1], Winner::Home),
            TeamGameFactory::game(11, 2, $teams[2], $teams[3], Winner::Home),
            TeamGameFactory::game(12, 3, $teams[0], $teams[2], Winner::Home),
            TeamGameFactory::game(13, 4, $teams[1], $teams[3], Winner::Home),
        ];

        return [$teams, $games];
    }

    /** @param Game[] $games */
    #[DataProvider('provideMostRecentCompleteWeekScenarios')]
    public function testDetermineMostRecentWeekWithAllGamesCompleted(int $expectedWeek, array $games): void
    {
        self::assertSame($expectedWeek, $this->makeOrchestrator()->determineMostRecentWeekWithAllGamesCompleted($games));
    }

    /** @return iterable<string, array{0: int, 1: Game[]}> */
    public static function provideMostRecentCompleteWeekScenarios(): iterable
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);
        $georgia = TeamGameFactory::team(3, 'Georgia', conference: Conference::SEC);
        $lsu = TeamGameFactory::team(4, 'LSU', conference: Conference::SEC);

        yield 'no games' => [
            0,
            [],
        ];

        yield 'single complete week' => [
            1,
            [TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Home)],
        ];

        yield 'incomplete final week stops progression' => [
            1,
            [
                TeamGameFactory::game(10, 1, $texas, $oklahoma, Winner::Home),
                TeamGameFactory::game(11, 2, $georgia, $lsu, null),
            ],
        ];

        // BUG: determineMostRecentWeekWithAllGamesCompleted does not enforce
        // contiguity. It counts a complete week even when an earlier week is
        // incomplete. The unfinished game from the incomplete week is then
        // applied and the away team receives the marbles as the fallback
        // winner. Do not fix this bug until the characterization test suite is
        // complete. Update the expectation of this scenario when you fix the
        // bug.
        yield 'a complete week after an incomplete week is still counted' => [
            2,
            [
                TeamGameFactory::game(10, 1, $texas, $oklahoma, null),
                TeamGameFactory::game(11, 2, $georgia, $lsu, Winner::Home),
            ],
        ];
    }
}
