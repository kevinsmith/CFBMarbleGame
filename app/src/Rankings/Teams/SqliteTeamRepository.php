<?php

declare(strict_types=1);

namespace App\Rankings\Teams;

use PDO;
use RuntimeException;

/**
 * @phpstan-type TeamRow array{
 *     id: int,
 *     name: string,
 *     subdivision: string,
 *     conference: string
 * }
 */
final class SqliteTeamRepository implements TeamRepository
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /** @inheritDoc */
    public function getTeams(): array
    {
        $query = $this->pdo->query(
            'SELECT id, name, subdivision, conference FROM teams',
            PDO::FETCH_ASSOC,
        );

        if ($query === false) {
            throw new RuntimeException('Failed to fetch teams from database');
        }

        $teams = [];

        /** @var TeamRow $row */
        foreach ($query as $row) {
            $teams[] = new Team(
                TeamId::fromDatabase($row['id']),
                $row['name'],
                Subdivision::fromString($row['subdivision']),
                Conference::fromString($row['conference']),
            );
        }

        return $teams;
    }

    public function getTeam(TeamId $teamId): Team
    {
        $stmt = $this->pdo->prepare('SELECT id, name, subdivision, conference FROM teams WHERE id = :id LIMIT 1');

        $stmt->execute(['id' => $teamId->id]);

        /** @var false|TeamRow $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException('Team not found. ID: ' . $teamId->id);
        }

        return new Team(
            TeamId::fromDatabase($row['id']),
            $row['name'],
            Subdivision::fromString($row['subdivision']),
            Conference::fromString($row['conference']),
        );
    }
}
