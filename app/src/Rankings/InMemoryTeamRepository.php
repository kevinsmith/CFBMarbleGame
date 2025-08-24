<?php

declare(strict_types=1);

namespace App\Rankings;

use function array_filter;
use function file_get_contents;
use function in_array;
use function json_decode;
use function uasort;

use const JSON_THROW_ON_ERROR;

final class InMemoryTeamRepository implements TeamRepository
{
    /** @var array<string> */
    private array $teams;

    /** @var array<string|int> */
    private array $games;

    public function __construct()
    {
        $this->init();
    }

    /** @inheritDoc */
    public function findTeamsWithMarbles(): array
    {
        $teamsWithMarbles = array_filter(
            $this->teams,
            static function (array $team) {
                return $team['starting_marbles'] > 0;
            },
        );

        // Sort teams by marbles in descending order, then alphabetically
        uasort($teamsWithMarbles, static function ($a, $b) {
            $marbleComparison = $b['starting_marbles'] <=> $a['starting_marbles'];

            return $marbleComparison !== 0 ? $marbleComparison : $a['name'] <=> $b['name'];
        });

        return $this->applyStandardCompetitionRanking($teamsWithMarbles);
    }

    private function init(): void
    {
        $rawJson = file_get_contents(__DIR__ . '/games_2025.json');

        $data = json_decode($rawJson, true, flags: JSON_THROW_ON_ERROR);

        foreach ($data as $game) {
            $this->games[$game['id']] = [
                'date' => $game['date'],
                'season_type' => $game['season_type'],
                'week_number' => $game['week'],
                'home_id' => $game['home_id'],
                'away_id' => $game['away_id'],
            ];

            $this->teams[$game['home_id']] = [
                'name' => $game['home_name'],
                'subdivision' => $game['home_subdivision'],
                'conference' => $game['home_conference'],
            ];

            $this->teams[$game['away_id']] = [
                'name' => $game['away_name'],
                'subdivision' => $game['away_subdivision'],
                'conference' => $game['away_conference'],
            ];
        }

        foreach ($this->teams as $teamId => $team) {
            $this->calculateStartingMarbles($teamId);
        }
    }

    private function calculateStartingMarbles(int $teamId): void
    {
        if ($this->teams[$teamId]['subdivision'] !== 'fbs') {
            $this->teams[$teamId]['starting_marbles'] = 0;

            return;
        }

        $this->teams[$teamId]['starting_marbles'] = 100;

        // Add 10 marbles for each P4 opponent
        $powerConferences = ['Big Ten', 'Big 12', 'ACC', 'SEC'];

        foreach ($this->games as $game) {
            $opponentId = null;

            if ($game['home_id'] === $teamId) {
                $opponentId = $game['away_id'];
            } elseif ($game['away_id'] === $teamId) {
                $opponentId = $game['home_id'];
            }

            if (
                $opponentId && isset($this->teams[$opponentId]) &&
                in_array($this->teams[$opponentId]['conference'], $powerConferences)
            ) {
                $this->teams[$teamId]['starting_marbles'] += 10;
            }
        }
    }

    /**
     * @param array<string> $sortedTeamsWithMarbles
     *
     * @return Team[]
     */
    private function applyStandardCompetitionRanking(array $sortedTeamsWithMarbles): array
    {
        $rankedTeams = [];
        $currentRank = 1;
        $previousMarbles = null;
        $teamsAtCurrentMarbleCount = 0;

        foreach ($sortedTeamsWithMarbles as $team) {
            if ($previousMarbles !== null && $team['starting_marbles'] < $previousMarbles) {
                $currentRank += $teamsAtCurrentMarbleCount;
                $teamsAtCurrentMarbleCount = 0;
            }

            $teamsAtCurrentMarbleCount++;
            $previousMarbles = $team['starting_marbles'];

            $rankedTeams[] = new Team(
                $team['name'],
                $team['conference'],
                0,
                0,
                $team['starting_marbles'],
                $currentRank,
            );
        }

        return $rankedTeams;
    }
}
