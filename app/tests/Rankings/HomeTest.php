<?php

declare(strict_types=1);

namespace Tests\Rankings;

use App\IdentityMap;
use App\Rankings\Games\Game;
use App\Rankings\Games\GameId;
use App\Rankings\Games\SqliteGameRepository;
use App\Rankings\Home;
use App\Rankings\MarbleOrchestrator;
use App\Rankings\MarbleRankingsQueryHandler;
use App\Rankings\RankedTeam;
use App\Rankings\Teams\CachedTeamRepository;
use App\Rankings\Teams\Conference;
use App\Rankings\Teams\SqliteTeamRepository;
use App\Rankings\Teams\Subdivision;
use App\Rankings\Teams\Team;
use App\Rankings\Teams\TeamId;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Sapien\Request;
use Sapien\Response;
use Tests\SqliteTestDatabase;

#[CoversClass(Home::class)]
#[UsesClass(CachedTeamRepository::class)]
#[UsesClass(Conference::class)]
#[UsesClass(Game::class)]
#[UsesClass(GameId::class)]
#[UsesClass(IdentityMap::class)]
#[UsesClass(MarbleOrchestrator::class)]
#[UsesClass(MarbleRankingsQueryHandler::class)]
#[UsesClass(RankedTeam::class)]
#[UsesClass(SqliteGameRepository::class)]
#[UsesClass(SqliteTeamRepository::class)]
#[UsesClass(Subdivision::class)]
#[UsesClass(Team::class)]
#[UsesClass(TeamId::class)]
final class HomeTest extends TestCase
{
    private PDO $pdo;

    private Home $home;

    protected function setUp(): void
    {
        $this->pdo = SqliteTestDatabase::pdo();
        $teams = new CachedTeamRepository(new SqliteTeamRepository($this->pdo));
        $this->home = new Home(
            new MarbleRankingsQueryHandler(
                $teams,
                new SqliteGameRepository($this->pdo, $teams),
                new MarbleOrchestrator(new NullLogger()),
            ),
            'styles.test.css',
        );
    }

    public function testDefaultPageReturnsRankingsHtml(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'Oklahoma', 'FBS', 'Big 12');

        $html = $this->html(($this->home)($this->request()));

        self::assertStringContainsString('Preseason', $html);
        self::assertStringContainsString('value="/?week=1" selected', $html);
    }

    public function testRequestedWeekIsSelectedInTheDropdown(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'Oklahoma', 'FBS', 'Big 12');
        $this->insertGame(10, 1, 1, 2, 'home');

        $html = $this->html(($this->home)($this->request(['week' => '1'])));

        self::assertStringContainsString('value="/?week=1" selected', $html);
        self::assertStringContainsString('value="/?week=2"', $html);
        self::assertStringNotContainsString('value="/?week=2" selected', $html);
    }

    public function testNonNumericWeekReturns404(): void
    {
        $response = ($this->home)($this->request(['week' => 'abc']));

        self::assertSame(404, $response->getCode());
    }

    public function testUnavailableWeekReturns404(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'Oklahoma', 'FBS', 'Big 12');

        $response = ($this->home)($this->request(['week' => '3']));

        self::assertSame(404, $response->getCode());
    }

    public function testWeekZeroIsTreatedAsTheDefaultWeek(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'Oklahoma', 'FBS', 'Big 12');

        $html = $this->html(($this->home)($this->request(['week' => '0'])));

        self::assertStringContainsString('value="/?week=1" selected', $html);
    }

    public function testWeekSeventeenIsLabeledFinal(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'Oklahoma', 'FBS', 'Big 12');

        for ($week = 1; $week <= 16; $week++) {
            $this->insertGame(10 + $week, $week, 1, 2, 'home');
        }

        $html = $this->html(($this->home)($this->request()));

        self::assertStringContainsString('>Final</option>', $html);
    }

    public function testFcsTeamsAreMarkedWithAFootnote(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'South Dakota State', 'FCS', 'MVFC');
        $this->insertGame(10, 1, 2, 1, 'home');

        $html = $this->html(($this->home)($this->request()));

        self::assertStringContainsString('South Dakota State*', $html);
    }

    /** @param array<string, string> $query */
    private function request(array $query = []): Request
    {
        return new Request(['_GET' => $query]);
    }

    private function html(Response $response): string
    {
        $html = $response->getContent();
        self::assertIsString($html);

        return $html;
    }

    private function insertTeam(int $id, string $name, string $subdivision, string $conference): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO teams (id, name, subdivision, conference) VALUES (:id, :name, :subdivision, :conference)',
        );
        $statement->execute([
            'id' => $id,
            'name' => $name,
            'subdivision' => $subdivision,
            'conference' => $conference,
        ]);
    }

    private function insertGame(int $id, int $weekNumber, int $homeTeamId, int $awayTeamId, string $winner): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO games (id, date, week_number, neutral_site, home_team_id, away_team_id, winner)
             VALUES (:id, :date, :week_number, 0, :home_team_id, :away_team_id, :winner)',
        );
        $statement->execute([
            'id' => $id,
            'date' => '2025-09-01T00:00:00Z',
            'week_number' => $weekNumber,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'winner' => $winner,
        ]);
    }
}
