<?php

declare(strict_types=1);

namespace App\Rankings\Teams;

interface TeamRepository
{
    /** @return Team[] */
    public function getTeams(): array;

    public function getTeam(TeamId $teamId): Team;
}
