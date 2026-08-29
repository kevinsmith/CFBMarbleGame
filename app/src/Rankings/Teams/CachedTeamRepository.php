<?php

declare(strict_types=1);

namespace App\Rankings\Teams;

use App\IdentityMap;

final class CachedTeamRepository implements TeamRepository
{
    /** @var IdentityMap<TeamId, Team> */
    private IdentityMap $identityMap;

    private bool $allTeamsLoaded = false;

    public function __construct(
        private readonly TeamRepository $repository,
    ) {
        $this->identityMap = new IdentityMap();
    }

    /** @inheritDoc */
    public function getTeams(): array
    {
        if (! $this->allTeamsLoaded) {
            foreach ($this->repository->getTeams() as $team) {
                if (! $this->identityMap->has($team->id)) {
                    $this->identityMap->add($team->id, $team);
                }
            }

            $this->allTeamsLoaded = true;
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
