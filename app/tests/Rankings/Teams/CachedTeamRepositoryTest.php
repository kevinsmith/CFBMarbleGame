<?php

declare(strict_types=1);

namespace Tests\Rankings\Teams;

use App\Rankings\Teams\CachedTeamRepository;
use App\Rankings\Teams\Conference;
use App\Rankings\Teams\Team;
use App\Rankings\Teams\TeamId;
use App\Rankings\Teams\TeamRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Rankings\TeamGameFactory;

#[CoversClass(CachedTeamRepository::class)]
final class CachedTeamRepositoryTest extends TestCase
{
    public function testGetTeamsLoadsFromTheInnerRepositoryWhenTheCacheIsEmpty(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);
        $inner = self::makeInnerRepository([$texas, $oklahoma]);

        $teams = (new CachedTeamRepository($inner))->getTeams();

        self::assertSame(1, $inner->getTeamsCalls);
        self::assertSame([$texas, $oklahoma], $teams);
    }

    public function testGetTeamsDoesNotCallTheInnerRepositoryAgain(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $inner = self::makeInnerRepository([$texas]);
        $repository = new CachedTeamRepository($inner);

        $repository->getTeams();
        $teams = $repository->getTeams();

        self::assertSame(1, $inner->getTeamsCalls);
        self::assertSame([$texas], $teams);
    }

    public function testGetTeamLoadsFromTheInnerRepositoryWhenTheTeamIsNotCached(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);
        $inner = self::makeInnerRepository([$texas, $oklahoma]);
        $repository = new CachedTeamRepository($inner);

        $repository->getTeam(TeamId::fromDatabase(1));
        $uncached = $repository->getTeam(TeamId::fromDatabase(2));

        self::assertSame(2, $inner->getTeamCalls);
        self::assertSame($oklahoma, $uncached);
    }

    public function testGetTeamReturnsTheCachedInstanceWithoutCallingTheInnerRepository(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);
        $inner = self::makeInnerRepository([$texas, $oklahoma]);
        $repository = new CachedTeamRepository($inner);

        $first = $repository->getTeam(TeamId::fromDatabase(1));
        $second = $repository->getTeam(TeamId::fromDatabase(1));
        $uncached = $repository->getTeam(TeamId::fromDatabase(2));

        self::assertSame(2, $inner->getTeamCalls);
        self::assertSame($texas, $first);
        self::assertSame($first, $second);
        self::assertSame($oklahoma, $uncached);
    }

    public function testGetTeamAfterGetTeamsReturnsTheSameInstance(): void
    {
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $inner = self::makeInnerRepository([$texas]);
        $repository = new CachedTeamRepository($inner);

        $repository->getTeams();
        $team = $repository->getTeam(TeamId::fromDatabase(1));

        self::assertSame(0, $inner->getTeamCalls);
        self::assertSame($texas, $team);
    }

    public function testGetTeamsAfterGetTeamDoesNotLoadTheRemainingTeams(): void
    {
        // BUG: getTeams() after getTeam() does not load the remaining teams.
        // The cache count is not zero, so this method skips the inner load.
        // Do not fix this bug until the characterization test suite is
        // complete. Update this test when you fix the bug.
        $texas = TeamGameFactory::team(1, 'Texas', conference: Conference::SEC);
        $oklahoma = TeamGameFactory::team(2, 'Oklahoma', conference: Conference::Big12);
        $inner = self::makeInnerRepository([$texas, $oklahoma]);
        $repository = new CachedTeamRepository($inner);

        $repository->getTeam(TeamId::fromDatabase(1));
        $teams = $repository->getTeams();

        self::assertSame(0, $inner->getTeamsCalls);
        self::assertSame([$texas], $teams);
    }

    public function testGetTeamsWithNoTeamsKeepsCallingTheInnerRepository(): void
    {
        $inner = self::makeInnerRepository([]);
        $repository = new CachedTeamRepository($inner);

        self::assertSame([], $repository->getTeams());
        self::assertSame([], $repository->getTeams());
        self::assertSame(2, $inner->getTeamsCalls);
    }

    /**
     * @param Team[] $teams
     *
     * @return TeamRepository&object{getTeamsCalls: int, getTeamCalls: int}
     */
    private static function makeInnerRepository(array $teams): TeamRepository
    {
        return new class ($teams) implements TeamRepository {
            public int $getTeamsCalls = 0;

            public int $getTeamCalls = 0;

            /** @param Team[] $teams */
            public function __construct(private readonly array $teams)
            {
            }

            /** @return Team[] */
            public function getTeams(): array
            {
                $this->getTeamsCalls++;

                return $this->teams;
            }

            public function getTeam(TeamId $teamId): Team
            {
                $this->getTeamCalls++;

                foreach ($this->teams as $team) {
                    if ($team->id->id === $teamId->id) {
                        return $team;
                    }
                }

                throw new RuntimeException('Not found.');
            }
        };
    }
}
