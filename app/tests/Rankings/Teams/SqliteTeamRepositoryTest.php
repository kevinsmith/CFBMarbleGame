<?php

declare(strict_types=1);

namespace Tests\Rankings\Teams;

use App\Rankings\Teams\Conference;
use App\Rankings\Teams\SqliteTeamRepository;
use App\Rankings\Teams\Subdivision;
use App\Rankings\Teams\TeamId;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Rankings\SqliteTestDatabase;

#[CoversClass(SqliteTeamRepository::class)]
final class SqliteTeamRepositoryTest extends TestCase
{
    private PDO $pdo;

    private SqliteTeamRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = SqliteTestDatabase::pdo();
        $this->repository = new SqliteTeamRepository($this->pdo);
    }

    public function testGetTeamsReturnsAnEmptyListWhenThereAreNoTeams(): void
    {
        self::assertSame([], $this->repository->getTeams());
    }

    public function testGetTeamsMapsEachRowToATeam(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'South Dakota State', 'FCS', 'MVFC');

        $teams = $this->repository->getTeams();

        self::assertCount(2, $teams);
        self::assertSame(1, $teams[0]->id->id);
        self::assertSame('Texas', $teams[0]->teamName);
        self::assertSame(Subdivision::FBS, $teams[0]->subdivision);
        self::assertSame(Conference::SEC, $teams[0]->conference);
        self::assertSame(2, $teams[1]->id->id);
        self::assertSame('South Dakota State', $teams[1]->teamName);
        self::assertSame(Subdivision::FCS, $teams[1]->subdivision);
        self::assertSame(Conference::MVFC, $teams[1]->conference);
    }

    public function testGetTeamMapsTheMatchingRow(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');
        $this->insertTeam(2, 'Oklahoma', 'FBS', 'Big 12');

        $team = $this->repository->getTeam(TeamId::fromDatabase(2));

        self::assertSame(2, $team->id->id);
        self::assertSame('Oklahoma', $team->teamName);
        self::assertSame(Subdivision::FBS, $team->subdivision);
        self::assertSame(Conference::Big12, $team->conference);
    }

    public function testGetTeamThrowsWhenTheTeamDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Team not found. ID: 99');

        $this->repository->getTeam(TeamId::fromDatabase(99));
    }

    public function testGetTeamReturnsANewInstanceOnEachCall(): void
    {
        $this->insertTeam(1, 'Texas', 'FBS', 'SEC');

        $first = $this->repository->getTeam(TeamId::fromDatabase(1));
        $second = $this->repository->getTeam(TeamId::fromDatabase(1));

        self::assertEquals($first->id, $second->id);
        self::assertNotSame($first, $second);
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
}
