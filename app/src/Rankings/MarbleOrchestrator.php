<?php

declare(strict_types=1);

namespace App\Rankings;

use function array_filter;
use function array_values;
use function in_array;
use function uasort;

final readonly class MarbleOrchestrator
{
    /**
     * @param Team[] $teams
     * @param Game[] $games
     *
     * @return Team[]
     */
    public function getRankedTeams(array $teams, array $games): array
    {
        foreach ($teams as $team) {
            $this->doleOutInitialMarbles($team, $games);
        }

        $teams = $this->removeTeamsWithoutMarbles($teams);

        return $this->applyStandardCompetitionRanking($teams);
    }

    /** @param Game[] $games */
    private function doleOutInitialMarbles(Team $team, array $games): void
    {
        if ($team->subdivision !== Subdivision::FBS) {
            return;
        }

        $initialMarbles = 100;

        $powerConferences = [
            Conference::BigTen,
            Conference::Big12,
            Conference::ACC,
            Conference::SEC,
        ];

        foreach ($this->getOpponents($team, $games) as $opponent) {
            // Add 10 marbles for each power conference opponent
            if (in_array($opponent->conference, $powerConferences, true)) {
                $initialMarbles += 10;
            }
        }

        $team->receiveInitialMarbles($initialMarbles);
    }

    /**
     * @param Game[] $games
     *
     * @return Team[]
     */
    private function getOpponents(Team $team, array $games): array
    {
        $opponents = [];

        foreach ($games as $game) {
            // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
            if ($game->homeTeam == $team) {
                $opponents[] = $game->awayTeam;
            }

            // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
            if ($game->awayTeam == $team) {
                $opponents[] = $game->homeTeam;
            }
        }

        return $opponents;
    }

    /**
     * @param Team[] $teams
     *
     * @return Team[]
     */
    private function removeTeamsWithoutMarbles(array $teams): array
    {
        return array_values(
            array_filter(
                $teams,
                static function (Team $team): bool {
                    return $team->getMarbles() > 0;
                },
            ),
        );
    }

    /**
     * @param Team[] $teams
     *
     * @return Team[]
     */
    private function applyStandardCompetitionRanking(array $teams): array
    {
        $teams = $this->sortByMarblesDescendingThenAlphabetically($teams);

        $rankedTeams = [];
        $currentRank = 1;
        $previousMarbles = null;
        $teamsAtCurrentMarbleCount = 0;

        foreach ($teams as $team) {
            if ($previousMarbles !== null && $team->getMarbles() < $previousMarbles) {
                $currentRank += $teamsAtCurrentMarbleCount;
                $teamsAtCurrentMarbleCount = 0;
            }

            $teamsAtCurrentMarbleCount++;
            $previousMarbles = $team->getMarbles();
            $team->setMarbleRank($currentRank);

            $rankedTeams[] = $team;
        }

        return $rankedTeams;
    }

    /**
     * @param Team[] $teams
     *
     * @return Team[]
     */
    private function sortByMarblesDescendingThenAlphabetically(array $teams): array
    {
        uasort($teams, static function (Team $a, Team $b): int {
            $marbleComparison = $b->getMarbles() <=> $a->getMarbles();

            return $marbleComparison !== 0 ? $marbleComparison : $a->teamName <=> $b->teamName;
        });

        return $teams;
    }
}
