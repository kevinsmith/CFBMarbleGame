<?php

declare(strict_types=1);

namespace App\Rankings;

use function array_map;

final readonly class MarbleRankingsQueryHandler
{
    public function __construct(private TeamRepository $teamRepository)
    {
    }

    /** @return RankedTeam[] */
    public function getRankings(): array
    {
        return array_map(
            static function (Team $team): RankedTeam {
                return new RankedTeam(
                    $team->teamName,
                    $team->conference,
                    $team->wins,
                    $team->losses,
                    $team->marbleCount,
                    $team->marbleRank,
                );
            },
            $this->teamRepository->findTeamsWithMarbles(),
        );
    }
}
