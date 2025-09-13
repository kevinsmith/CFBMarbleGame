<?php

declare(strict_types=1);

namespace App\Rankings;

use App\IdentityMap;

final class CachedTeamRepository implements TeamRepository
{
    /** @var IdentityMap<TeamId, Team> */
    private IdentityMap $identityMap;

    public function __construct(
        private readonly TeamRepository $repository,
    ) {
        $this->identityMap = new IdentityMap();
    }

    /** @inheritDoc */
    public function getTeams(): array
    {
        if ($this->identityMap->count() === 0) {
            $teams = $this->repository->getTeams();

            foreach ($teams as $team) {
                $this->identityMap->add($team->id, $team);
            }
        }

        return $this->identityMap->getAll();
    }

    public function getTeam(TeamId $teamId): Team
    {
        $cachedTeam = $this->identityMap->get($teamId);

        if ($cachedTeam !== null) {
            return $cachedTeam;
        }

        $team = $this->repository->getTeam($teamId);
        $this->identityMap->add($teamId, $team);

        return $team;
    }
}
