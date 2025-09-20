<?php

declare(strict_types=1);

namespace App\Rankings;

use function array_map;

final readonly class MarbleRankingsQueryHandler
{
    public function __construct(
        private TeamRepository $teamRepository,
        private GameRepository $gameRepository,
        private MarbleOrchestrator $marbleOrchestrator,
    ) {
    }

    /** @return array{0: int|null, 1: array<RankedTeam>} */
    public function getRankings(int|null $week = null): array
    {
        $teams = $this->teamRepository->getTeams();
        $games = $this->gameRepository->getGames();

        if ($week === null) {
            $week = 3;
        }

        $rankings = array_map(
            static function (Team $team): RankedTeam {
                return new RankedTeam(
                    $team->teamName,
                    $team->conference->value,
                    $team->getMarbles(),
                    $team->getMarbleRank(),
                    $team->subdivision === Subdivision::FCS,
                );
            },
            $this->marbleOrchestrator->getRankedTeams($teams, $games),
        );

        return [$week, $rankings];
    }
}
