<?php

declare(strict_types=1);

namespace Tests\DataLoader;

use App\DataLoader\DataRefreshCommand;
use App\DataLoader\GamesDataRefresher;
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

    /** @param list<array<string, mixed>> $games */
    private function makeTester(array $games, PDO $pdo): CommandTester
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode($games, JSON_THROW_ON_ERROR)),
        ]));

        return new CommandTester(new DataRefreshCommand(new Client(['handler' => $stack]), $pdo));
    }
}
