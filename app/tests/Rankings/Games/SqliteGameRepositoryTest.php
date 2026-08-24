<?php

declare(strict_types=1);

namespace Tests\Rankings\Games;

use App\DateFormat;
use App\Rankings\Games\SqliteGameRepository;
use App\Rankings\Games\Winner;
use App\Rankings\Teams\CachedTeamRepository;
use App\Rankings\Teams\SqliteTeamRepository;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\SqliteTestDatabase;

#[CoversClass(SqliteGameRepository::class)]
final class SqliteGameRepositoryTest extends TestCase
{
    private PDO $pdo;

    private CachedTeamRepository $teamRepository;

    private SqliteGameRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = SqliteTestDatabase::pdo();
        $this->teamRepository = new CachedTeamRepository(new SqliteTeamRepository($this->pdo));
        $this->repository = new SqliteGameRepository($this->pdo, $this->teamRepository);
    }

    public function testGetGamesReturnsAnEmptyListWhenThereAreNoGames(): void
    {
        self::assertSame([], $this->repository->getGames());
    }

    public function testGetGamesMapsEachRow(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'Oklahoma', 'FBS', 'Big 12');
        $this->insertGame(10, '2025-09-06T19:00:00Z', 1, 0, 1, 2, 'home');
        $this->insertGame(11, '2025-09-13T16:00:00Z', 2, 1, 2, 1, null);
        $this->insertGame(12, '2025-09-20T19:00:00Z', 3, 0, 1, 2, 'away');

        $games = $this->repository->getGames();

        self::assertCount(3, $games);
        self::assertSame(10, $games[0]->id->id);
        self::assertSame('2025-09-06T19:00:00Z', $games[0]->date->format(DateFormat::SQLITE));
        self::assertSame(1, $games[0]->weekNumber);
        self::assertFalse($games[0]->neutralSite);
        self::assertSame(1, $games[0]->homeTeam->id->id);
        self::assertSame('Texas', $games[0]->homeTeam->teamName);
        self::assertSame(2, $games[0]->awayTeam->id->id);
        self::assertSame('Oklahoma', $games[0]->awayTeam->teamName);
        self::assertSame(Winner::Home, $games[0]->winner);
        self::assertSame(11, $games[1]->id->id);
        self::assertSame(2, $games[1]->weekNumber);
        self::assertTrue($games[1]->neutralSite);
        self::assertNull($games[1]->winner);
        self::assertSame(12, $games[2]->id->id);
        self::assertSame(Winner::Away, $games[2]->winner);
    }

    public function testGetGamesUsesTheCachedTeamInstancesForHomeAndAway(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'Oklahoma', 'FBS', 'Big 12');
        $this->insertGame(10, '2025-09-06T19:00:00Z', 1, 0, 1, 2, 'home');

        $games = $this->repository->getGames();
        $teams = $this->teamRepository->getTeams();

        self::assertSame($teams[0], $games[0]->homeTeam);
        self::assertSame($teams[1], $games[0]->awayTeam);
    }

    public function testGetGamesThrowsWhenTheDateCannotBeParsed(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'Oklahoma', 'FBS', 'Big 12');
        $this->insertGame(10, 'not-a-date', 1, 0, 1, 2, null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to parse game date: not-a-date');

        $this->repository->getGames();
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

    private function insertGame(
        int $id,
        string $date,
        int $weekNumber,
        int $neutralSite,
        int $homeTeamId,
        int $awayTeamId,
        string|null $winner,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO games (id, date, week_number, neutral_site, home_team_id, away_team_id, winner)
             VALUES (:id, :date, :week_number, :neutral_site, :home_team_id, :away_team_id, :winner)',
        );
        $statement->execute([
            'id' => $id,
            'date' => $date,
            'week_number' => $weekNumber,
            'neutral_site' => $neutralSite,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'winner' => $winner,
        ]);
    }
}
