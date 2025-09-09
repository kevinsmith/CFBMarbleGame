<?php

declare(strict_types=1);

namespace App\Rankings;

use PDO;
use RuntimeException;

use function array_filter;
use function in_array;
use function uasort;

final class SqliteTeamRepository implements TeamRepository
{
    /** @var array<int, array{name: string, subdivision: Subdivision, conference: Conference, starting_marbles: int}> */
    private array $teams;

    /** @var array<int, array{week_number: int, home_id: int, away_id: int}> */
    private array $games;

    public function __construct(
        private PDO $pdo,
    ) {
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
        $this->loadTeamsFromDatabase();
        $this->loadGamesFromDatabase();

        foreach ($this->teams as $teamId => $team) {
            $this->calculateStartingMarbles($teamId);
        }
    }

    private function loadTeamsFromDatabase(): void
    {
        $stmt = $this->pdo->prepare('SELECT id, name, subdivision, conference, cfbd_id FROM teams WHERE subdivision = :subdivision');
        $stmt->execute(['subdivision' => Subdivision::FBS->name]);

        $this->teams = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var array{id: int, name: string, subdivision: string, conference: string, cfbd_id: int} $row */
            $this->teams[(int) $row['id']] = [
                'name' => $row['name'],
                'subdivision' => Subdivision::fromString($row['subdivision']),
                'conference' => Conference::fromString($row['conference']),
                'starting_marbles' => 0,
            ];
        }
    }

    private function loadGamesFromDatabase(): void
    {
        $query = $this->pdo->query(
            'SELECT id, cfbd_id, date, week_number, home_team_id, away_team_id FROM games',
            PDO::FETCH_ASSOC,
        );

        if ($query === false) {
            throw new RuntimeException('Failed to fetch games from database');
        }

        $this->games = [];

        foreach ($query as $row) {
            /** @var array{id: int, date: string, week_number: int, home_team_id: int, away_team_id: int} $row */
            $this->games[(int) $row['id']] = [
                'week_number' => (int) $row['week_number'],
                'home_id' => (int) $row['home_team_id'],
                'away_id' => (int) $row['away_team_id'],
            ];
        }
    }

    private function calculateStartingMarbles(int $teamId): void
    {
        if ($this->teams[$teamId]['subdivision']->name !== Subdivision::FBS->name) {
            $this->teams[$teamId]['starting_marbles'] = 0;

            return;
        }

        $this->teams[$teamId]['starting_marbles'] = 100;

        // Add 10 marbles for each P4 opponent
        $powerConferences = [
            Conference::BigTen->value,
            Conference::Big12->value,
            Conference::ACC->value,
            Conference::SEC->value,
        ];

        foreach ($this->games as $game) {
            $opponentId = null;

            if ($game['home_id'] === $teamId) {
                $opponentId = $game['away_id'];
            } elseif ($game['away_id'] === $teamId) {
                $opponentId = $game['home_id'];
            }

            if (
                $opponentId && isset($this->teams[$opponentId]) &&
                in_array($this->teams[$opponentId]['conference']->value, $powerConferences, true)
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

        /** @var array{name: string, conference: Conference, starting_marbles: int} $team */
        foreach ($sortedTeamsWithMarbles as $team) {
            if ($previousMarbles !== null && $team['starting_marbles'] < $previousMarbles) {
                $currentRank += $teamsAtCurrentMarbleCount;
                $teamsAtCurrentMarbleCount = 0;
            }

            $teamsAtCurrentMarbleCount++;
            $previousMarbles = $team['starting_marbles'];

            $rankedTeams[] = new Team(
                $team['name'],
                $team['conference']->value,
                0,
                0,
                $team['starting_marbles'],
                $currentRank,
            );
        }

        return $rankedTeams;
    }
}
