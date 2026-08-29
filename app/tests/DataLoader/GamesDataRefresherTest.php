<?php

declare(strict_types=1);

namespace Tests\DataLoader;

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
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Tests\SqliteTestDatabase;

use function json_encode;
use function parse_str;

use const JSON_THROW_ON_ERROR;

#[CoversClass(GamesDataRefresher::class)]
#[UsesClass(Conference::class)]
#[UsesClass(Subdivision::class)]
final class GamesDataRefresherTest extends TestCase
{
    private PDO $pdo;

    private RequestInterface|null $lastRequest = null;

    protected function setUp(): void
    {
        $this->pdo = SqliteTestDatabase::pdo();
        $this->lastRequest = null;
    }

    public function testFetchUsesTheRequestedYearRegularSeasonFbsQuery(): void
    {
        $this->makeRefresher([self::cfbdGame()])->pullAndStoreFreshData(2025);

        self::assertInstanceOf(RequestInterface::class, $this->lastRequest);
        $query = [];
        parse_str($this->lastRequest->getUri()->getQuery(), $query);

        self::assertSame('/games', $this->lastRequest->getUri()->getPath());
        self::assertSame(
            [
                'year' => '2025',
                'classification' => 'fbs',
                'seasonType' => 'regular',
            ],
            $query,
        );
    }

    public function testStoresTeamsAndGamesFromTheApiResponse(): void
    {
        $this->makeRefresher([
            self::cfbdGame([
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
            ]),
        ])->pullAndStoreFreshData(2025);

        $teams = $this->fetchAll('SELECT id, name, subdivision, conference, cfbd_id FROM teams ORDER BY cfbd_id');
        $games = $this->fetchAll(
            'SELECT date, week_number, season, neutral_site, home_team_id, away_team_id, winner, cfbd_id FROM games',
        );

        self::assertSame('Oklahoma', $teams[0]['name']);
        self::assertSame('FBS', $teams[0]['subdivision']);
        self::assertSame('Big 12', $teams[0]['conference']);
        self::assertSame(201, $teams[0]['cfbd_id']);
        self::assertSame('Texas', $teams[1]['name']);
        self::assertSame('FBS', $teams[1]['subdivision']);
        self::assertSame('SEC', $teams[1]['conference']);
        self::assertSame(251, $teams[1]['cfbd_id']);
        self::assertSame(
            [
                [
                    'date' => '2025-09-06T19:00:00Z',
                    'week_number' => 1,
                    'season' => 2025,
                    'neutral_site' => 0,
                    'home_team_id' => $teams[1]['id'],
                    'away_team_id' => $teams[0]['id'],
                    'winner' => 'home',
                    'cfbd_id' => 401000001,
                ],
            ],
            $games,
        );
    }

    public function testUpsertsTeamsAndGamesOnCfbdIdConflict(): void
    {
        $this->makeRefresher([
            self::cfbdGame([
                'id' => 401000001,
                'homeConference' => 'SEC',
                'homePoints' => 28,
                'awayPoints' => 14,
                'week' => 1,
            ]),
        ])->pullAndStoreFreshData(2025);
        $this->makeRefresher([
            self::cfbdGame([
                'id' => 401000001,
                'homeConference' => 'Big 12',
                'homePoints' => 10,
                'awayPoints' => 17,
                'week' => 2,
            ]),
        ])->pullAndStoreFreshData(2025);

        $texas = $this->fetchAll("SELECT conference FROM teams WHERE name = 'Texas'");
        $game = $this->fetchAll('SELECT week_number, winner FROM games WHERE cfbd_id = 401000001');

        self::assertSame('Big 12', $texas[0]['conference']);
        self::assertSame(['week_number' => 2, 'winner' => 'away'], $game[0]);
        self::assertSame(2, $this->countRows('teams'));
        self::assertSame(1, $this->countRows('games'));
    }

    public function testNullOrTiedPointsLeaveTheWinnerUnset(): void
    {
        $this->makeRefresher([
            self::cfbdGame([
                'id' => 401000001,
                'homePoints' => null,
                'awayPoints' => null,
            ]),
            self::cfbdGame([
                'id' => 401000002,
                'week' => 2,
                'homePoints' => 21,
                'awayPoints' => 21,
            ]),
        ])->pullAndStoreFreshData(2025);

        $winners = $this->fetchAll('SELECT cfbd_id, winner FROM games ORDER BY cfbd_id');

        self::assertSame(
            [
                ['cfbd_id' => 401000001, 'winner' => null],
                ['cfbd_id' => 401000002, 'winner' => null],
            ],
            $winners,
        );
    }

    public function testANeutralSiteGameIsStoredAsNeutral(): void
    {
        $this->makeRefresher([
            self::cfbdGame([
                'id' => 401000099,
                'neutralSite' => true,
            ]),
        ])->pullAndStoreFreshData(2025);

        $games = $this->fetchAll('SELECT neutral_site FROM games WHERE cfbd_id = 401000099');

        self::assertSame(1, $games[0]['neutral_site']);
    }

    public function testSamHoustonHomeGamesAreForcedOffNeutralSite(): void
    {
        $samHoustonIds = [401757224, 401757279, 401757284, 401757300, 401757311];
        $games = [];

        foreach ($samHoustonIds as $index => $cfbdId) {
            $games[] = self::cfbdGame([
                'id' => $cfbdId,
                'week' => $index + 1,
                'neutralSite' => true,
                'homeId' => 2534,
                'homeTeam' => 'Sam Houston',
                'homeConference' => 'Conference USA',
                'awayId' => 251,
                'awayTeam' => 'Texas',
                'awayConference' => 'SEC',
            ]);
        }

        $this->makeRefresher($games)->pullAndStoreFreshData(2025);

        $rows = $this->fetchAll('SELECT cfbd_id, neutral_site FROM games ORDER BY cfbd_id');

        self::assertSame(
            [
                ['cfbd_id' => 401757224, 'neutral_site' => 0],
                ['cfbd_id' => 401757279, 'neutral_site' => 0],
                ['cfbd_id' => 401757284, 'neutral_site' => 0],
                ['cfbd_id' => 401757300, 'neutral_site' => 0],
                ['cfbd_id' => 401757311, 'neutral_site' => 0],
            ],
            $rows,
        );
    }

    public function testInvalidStartDateThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse game date: not-a-date');

        $this->makeRefresher([self::cfbdGame(['startDate' => 'not-a-date'])])->pullAndStoreFreshData(2025);
    }

    /** @return list<array<string, mixed>> */
    private function fetchAll(string $sql): array
    {
        $statement = $this->pdo->query($sql);
        self::assertNotFalse($statement);

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    private function countRows(string $table): int
    {
        $rows = $this->fetchAll('SELECT COUNT(*) AS count FROM ' . $table);
        self::assertIsNumeric($rows[0]['count']);

        return (int) $rows[0]['count'];
    }

    /** @param list<array<string, mixed>> $games */
    private function makeRefresher(array $games): GamesDataRefresher
    {
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode($games, JSON_THROW_ON_ERROR)),
        ]));
        $stack->push(function (callable $handler): callable {
            return function (RequestInterface $request, array $options) use ($handler) {
                $this->lastRequest = $request;

                return $handler($request, $options);
            };
        });

        return new GamesDataRefresher(
            new Client(['handler' => $stack]),
            $this->pdo,
            new NullLogger(),
        );
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
}
