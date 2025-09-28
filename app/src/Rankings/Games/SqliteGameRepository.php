<?php

declare(strict_types=1);

namespace App\Rankings\Games;

use App\DateFormat;
use App\Rankings\Teams\TeamId;
use App\Rankings\Teams\TeamRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final readonly class SqliteGameRepository implements GameRepository
{
    public function __construct(
        private PDO $pdo,
        private TeamRepository $teamRepository,
    ) {
    }

    /** @inheritDoc */
    public function getGames(): array
    {
        $query = $this->pdo->query(
            'SELECT id, date, week_number, neutral_site, home_team_id, away_team_id, winner FROM games',
            PDO::FETCH_ASSOC,
        );

        if ($query === false) {
            throw new RuntimeException('Failed to fetch games from database');
        }

        $games = [];

        // Load all teams to build up the cache in the team repository
        $this->teamRepository->getTeams();

        /** @var array{id: int, date: string, week_number: int, neutral_site: int, home_team_id: int, away_team_id: int, winner: ?string} $row */
        foreach ($query as $row) {
            $gameDate = DateTimeImmutable::createFromFormat(DateFormat::SQLITE, $row['date']);

            if ($gameDate === false) {
                throw new RuntimeException('Failed to parse game date: ' . $row['date']);
            }

            $winner = null;

            if ($row['winner'] !== null) {
                $winner = Winner::from($row['winner']);
            }

            $games[] = new Game(
                GameId::fromDatabase($row['id']),
                $gameDate,
                (int) $row['week_number'],
                (bool) $row['neutral_site'],
                $this->teamRepository->getTeam(TeamId::fromDatabase($row['home_team_id'])),
                $this->teamRepository->getTeam(TeamId::fromDatabase($row['away_team_id'])),
                $winner,
            );
        }

        return $games;
    }
}
