<?php

declare(strict_types=1);

namespace App\Rankings;

use PDO;
use RuntimeException;

final readonly class SqliteGameRepository implements GameRepository
{
    public function __construct(
        private PDO $pdo,
        private SqliteTeamRepository $teamRepository,
    ) {
    }

    /** @inheritDoc */
    public function getGames(): array
    {
        $query = $this->pdo->query(
            'SELECT id, home_team_id, away_team_id FROM games',
            PDO::FETCH_ASSOC,
        );

        if ($query === false) {
            throw new RuntimeException('Failed to fetch games from database');
        }

        $games = [];

        // Load all teams to build up the cache in the team repository
        $this->teamRepository->getTeams();

        /** @var array{id: int, home_team_id: int, away_team_id: int} $row */
        foreach ($query as $row) {
            $games[] = new Game(
                GameId::fromDatabase($row['id']),
                $this->teamRepository->getTeam(TeamId::fromDatabase($row['home_team_id'])),
                $this->teamRepository->getTeam(TeamId::fromDatabase($row['away_team_id'])),
            );
        }

        return $games;
    }
}
