<?php

declare(strict_types=1);

namespace App\Rankings;

use App\Rankings\Games\GameRepository;
use App\Rankings\Teams\Subdivision;
use App\Rankings\Teams\Team;
use App\Rankings\Teams\TeamRepository;
use InvalidArgumentException;

use function array_map;
use function date;
use function in_array;
use function max;

final readonly class MarbleRankingsQueryHandler
{
    public function __construct(
        private TeamRepository $teamRepository,
        private GameRepository $gameRepository,
        private MarbleOrchestrator $marbleOrchestrator,
    ) {
    }

    /** @return array{0: int, 1: int, 2: array<RankedTeam>, 3: int, 4: list<int>} */
    public function getRankings(int|null $season = null, int|null $week = null): array
    {
        $teams = $this->teamRepository->getTeams();
        $seasons = $this->gameRepository->getSeasons();

        if ($season === null) {
            $season = $seasons === [] ? (int) date('Y') : max($seasons);
        } elseif ($seasons !== [] && ! in_array($season, $seasons, true)) {
            throw new InvalidArgumentException('Rankings not yet available for season ' . $season . '.');
        }

        $games = $this->gameRepository->getGames($season);

        $latestWeekWithRankings = 1 + $this->marbleOrchestrator->determineMostRecentContiguousCompleteWeek($games);

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

        return [$week, $latestWeekWithRankings, $rankings, $season, $seasons === [] ? [$season] : $seasons];
    }
}
