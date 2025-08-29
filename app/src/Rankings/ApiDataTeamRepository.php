<?php

declare(strict_types=1);

namespace App\Rankings;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

use function array_filter;
use function assert;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function json_decode;
use function uasort;

use const JSON_THROW_ON_ERROR;

final class ApiDataTeamRepository implements TeamRepository
{
    /** @var array<int, array<string, mixed>> */
    private array $teams;

    /** @var array<int, array<string, mixed>> */
    private array $games;

    public function __construct(private Client $cfbdApi)
    {
        $this->init();
    }

    /** @inheritDoc */
    public function findTeamsWithMarbles(): array
    {
        $teamsWithMarbles = array_filter(
            $this->teams,
            static function (array $team): bool {
                return $team['starting_marbles'] > 0;
            },
        );

        // Sort teams by marbles in descending order, then alphabetically
        uasort($teamsWithMarbles, static function (array $a, array $b): int {
            $marbleComparison = $b['starting_marbles'] <=> $a['starting_marbles'];

            return $marbleComparison !== 0 ? $marbleComparison : $a['name'] <=> $b['name'];
        });

        return $this->applyStandardCompetitionRanking($teamsWithMarbles);
    }

    private function init(): void
    {
        $apiResponse = $this->cfbdApi->get(
            '/games',
            [
                RequestOptions::QUERY => [
                    'year' => '2025',
                    'classification' => 'fbs',
                ],
            ],
        );

        $data = json_decode($apiResponse->getBody()->getContents(), true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($data));

        foreach ($data as $game) {
            assert(is_array($game));

            $gameId = $game['id'];
            assert(is_int($gameId));

            $this->games[(int) $gameId] = [
                'date' => $game['startDate'],
                'season_type' => $game['seasonType'],
                'week_number' => $game['week'],
                'home_id' => $game['homeId'],
                'away_id' => $game['awayId'],
            ];

            $homeId = $game['homeId'];
            assert(is_numeric($homeId));

            $this->teams[(int) $homeId] = [
                'name' => $game['homeTeam'],
                'subdivision' => $game['homeClassification'],
                'conference' => $game['homeConference'],
            ];

            $awayId = $game['awayId'];
            assert(is_numeric($awayId));

            $this->teams[(int) $awayId] = [
                'name' => $game['awayTeam'],
                'subdivision' => $game['awayClassification'],
                'conference' => $game['awayConference'],
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
     * @param array<int, array<string, mixed>> $sortedTeamsWithMarbles
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

            assert(is_string($team['name']));
            assert(is_string($team['conference']));
            assert(is_int($team['starting_marbles']));

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
