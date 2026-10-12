<?php

declare(strict_types=1);

namespace Tests\DataLoader;

use App\DataLoader\ApPollsDataRefresher;
use App\DataLoader\DataRefreshCommand;
use App\DataLoader\GamesDataRefresher;
use App\Rankings\Polls\SqliteApPollRepository;
use App\Rankings\Teams\Conference;
use App\Rankings\Teams\Subdivision;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\SqliteTestDatabase;

use function date;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(DataRefreshCommand::class)]
#[CoversClass(ApPollsDataRefresher::class)]
#[CoversClass(SqliteApPollRepository::class)]
#[UsesClass(Conference::class)]
#[UsesClass(GamesDataRefresher::class)]
#[UsesClass(Subdivision::class)]
final class DataRefreshCommandTest extends TestCase
{
    public function testSuccessfulRefreshReturnsSuccess(): void
    {
        $pdo = SqliteTestDatabase::pdo();
        $tester = $this->makeTester([self::cfbdGame()], $pdo);

        $status = $tester->execute([]);
        $count = $pdo->query('SELECT COUNT(*) AS count FROM games');
        self::assertNotFalse($count);
        $row = $count->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(1, $row['count']);

        self::assertSame(Command::SUCCESS, $status);
        $season = $pdo->query('SELECT season FROM games');
        self::assertNotFalse($season);
        $seasonRow = $season->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($seasonRow);
        self::assertSame((int) date('Y'), $seasonRow['season']);
    }

    public function testYearOptionRefreshesThatSeason(): void
    {
        $pdo = SqliteTestDatabase::pdo();
        $tester = $this->makeTester([self::cfbdGame()], $pdo);

        $status = $tester->execute(['--year' => '2025']);
        $season = $pdo->query('SELECT season FROM games');
        self::assertNotFalse($season);
        $row = $season->fetch(PDO::FETCH_ASSOC);

        self::assertSame(Command::SUCCESS, $status);
        self::assertIsArray($row);
        self::assertSame(2025, $row['season']);
    }

    public function testNonNumericYearReturnsFailure(): void
    {
        $tester = $this->makeTester([self::cfbdGame()], SqliteTestDatabase::pdo());

        $status = $tester->execute(['--year' => 'abc']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Error: Year must be a positive integer.', $tester->getDisplay());
    }

    public function testFailedRefreshReturnsFailureAndPrintsTheError(): void
    {
        $tester = $this->makeTester(
            [self::cfbdGame(['startDate' => 'not-a-date'])],
            SqliteTestDatabase::pdo(),
        );

        $status = $tester->execute([]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('Error: Failed to parse game date: not-a-date', $tester->getDisplay());
    }

    public function testRefreshStoresOnlyApRanksAndReplacesPublishedPolls(): void
    {
        $pdo = SqliteTestDatabase::pdo();
        $polls = [
            [
                'season' => 2025,
                'seasonType' => 'regular',
                'week' => 1,
                'polls' => [
                    [
                        'poll' => 'AP Top 25',
                        'ranks' => [
                            ['teamId' => 251, 'school' => 'Texas Longhorns', 'rank' => 1],
                            ['teamId' => 201, 'school' => 'Oklahoma Sooners', 'rank' => 18],
                            ['teamId' => 239, 'school' => 'Baylor', 'rank' => null],
                        ],
                    ],
                    ['poll' => 'Coaches Poll', 'ranks' => [['teamId' => 251, 'school' => 'Texas', 'rank' => 3]]],
                ],
            ],
            [
                'season' => 2025,
                'seasonType' => 'regular',
                'week' => 2,
                'polls' => [['poll' => 'AP Top 25', 'ranks' => [['teamId' => 251, 'school' => 'Texas', 'rank' => 7]]]],
            ],
        ];

        $tester = $this->makeTester([self::cfbdGame()], $pdo, $polls);
        self::assertSame(Command::SUCCESS, $tester->execute(['--year' => '2025']));

        $rankings = new SqliteApPollRepository($pdo);
        self::assertEquals([1 => 1, 2 => 18], $rankings->getRanks(2025, 1));
        self::assertSame([1 => 7], $rankings->getRanks(2025, 2));
        self::assertSame([], $rankings->getRanks(2025, 3));
        self::assertSame([], $rankings->getRanks(2026, 1));

        $polls[0]['polls'][0]['ranks'] = [['teamId' => 251, 'school' => 'Texas', 'rank' => 2]];
        $tester = $this->makeTester([self::cfbdGame()], $pdo, $polls);
        self::assertSame(Command::SUCCESS, $tester->execute(['--year' => '2025']));
        self::assertSame([1 => 2], $rankings->getRanks(2025, 1));

        $tester = $this->makeTester([self::cfbdGame()], $pdo);
        self::assertSame(Command::SUCCESS, $tester->execute(['--year' => '2026']));
        self::assertSame([], $rankings->getRanks(2026, 1));
        self::assertSame([1 => 2], $rankings->getRanks(2025, 1));
    }

    public function testFailedApPollReplacementPreservesThePreviousRanks(): void
    {
        $pdo = SqliteTestDatabase::pdo();
        $pdo->exec(<<<'SQL'
        INSERT INTO ap_rankings (season, week, cfbd_id, rank) VALUES
            (2025, 2, 251, 7),
            (2025, 2, 201, 18);
        CREATE TRIGGER fail_ap_rank_insert BEFORE INSERT ON ap_rankings
        WHEN NEW.cfbd_id = 201
        BEGIN
            SELECT RAISE(ABORT, 'Test poll write failure');
        END;
        SQL);
        $polls = [
            [
                'season' => 2025,
                'seasonType' => 'regular',
                'week' => 2,
                'polls' => [
                    [
                        'poll' => 'AP Top 25',
                        'ranks' => [
                            ['teamId' => 251, 'school' => 'Texas', 'rank' => 2],
                            ['teamId' => 239, 'school' => 'Baylor', 'rank' => 10],
                            ['teamId' => 201, 'school' => 'Oklahoma', 'rank' => 15],
                        ],
                    ],
                ],
            ],
        ];
        $tester = $this->makeTester([self::cfbdGame()], $pdo, $polls);

        self::assertSame(Command::FAILURE, $tester->execute(['--year' => '2025']));
        self::assertStringContainsString('Test poll write failure', $tester->getDisplay());
        self::assertEquals([1 => 7, 2 => 18], new SqliteApPollRepository($pdo)->getRanks(2025, 2));
        $storedRanks = $pdo->query('SELECT cfbd_id, rank FROM ap_rankings WHERE season = 2025 AND week = 2');
        self::assertNotFalse($storedRanks);
        self::assertEquals([251 => 7, 201 => 18], $storedRanks->fetchAll(PDO::FETCH_KEY_PAIR));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function cfbdGame(array $overrides = []): array
    {
        return $overrides + [
            'id' => 401000001,
            'startDate' => '2025-09-06T19:00:00.000Z',
            'week' => 1,
            'neutralSite' => false,
            'homeId' => 251,
            'homeTeam' => 'Texas',
            'homeClassification' => 'fbs',
            'homeConference' => 'SEC',
            'homePoints' => 28,
            'awayId' => 201,
            'awayTeam' => 'Oklahoma',
            'awayClassification' => 'fbs',
            'awayConference' => 'Big 12',
            'awayPoints' => 14,
        ];
    }

    /**
     * @param list<array<string, mixed>> $games
     * @param list<array<string, mixed>> $polls
     */
    private function makeTester(array $games, PDO $pdo, array $polls = []): CommandTester
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode($games, JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode($polls, JSON_THROW_ON_ERROR)),
        ]));
        $client = new Client(['handler' => $stack]);

        return new CommandTester(new DataRefreshCommand($client, $pdo));
    }
}
