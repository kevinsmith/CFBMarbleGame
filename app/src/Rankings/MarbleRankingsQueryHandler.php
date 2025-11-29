<?php

declare(strict_types=1);

namespace App\Rankings;

use App\Rankings\Games\GameRepository;
use App\Rankings\Teams\Subdivision;
use App\Rankings\Teams\Team;
use App\Rankings\Teams\TeamRepository;
use InvalidArgumentException;

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

        $latestWeekWithRankings = 1 + $this->marbleOrchestrator->determineMostRecentWeekWithAllGamesCompleted($games);

        if ($week === null) {
            $week = $latestWeekWithRankings;
        } elseif ($week > $latestWeekWithRankings) {
            throw new InvalidArgumentException('Rankings not yet available for week ' . $week . '.');
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
            $this->marbleOrchestrator->getRankedTeams($week, $teams, $games),
        );

        return [$week, $rankings];
    }
}
