<?php

declare(strict_types=1);

namespace App\Rankings\Games;

use App\DateFormat;
use App\Rankings\Teams\TeamId;
use App\Rankings\Teams\TeamRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;

use function array_map;

final readonly class SqliteGameRepository implements GameRepository
{
    public function __construct(
        private PDO $pdo,
        private TeamRepository $teamRepository,
    ) {
    }

    /** @inheritDoc */
    public function getGames(int $season): array
    {
        $query = $this->pdo->prepare(
            'SELECT id, date, week_number, season, neutral_site, home_team_id, away_team_id, winner
             FROM games WHERE season = :season',
        );

        if ($query === false) {
            throw new RuntimeException('Failed to fetch games from database');
        }

        $query->execute(['season' => $season]);

        $games = [];

        // Load all teams to build up the cache in the team repository
        $this->teamRepository->getTeams();

        /** @var array{id: int, date: string, week_number: int, season: int, neutral_site: int, home_team_id: int, away_team_id: int, winner: ?string} $row */
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
                (int) $row['season'],
                (bool) $row['neutral_site'],
                $this->teamRepository->getTeam(TeamId::fromDatabase($row['home_team_id'])),
                $this->teamRepository->getTeam(TeamId::fromDatabase($row['away_team_id'])),
                $winner,
            );
        }

        return $games;
    }

    /** @inheritDoc */
    public function getSeasons(): array
    {
        $query = $this->pdo->query('SELECT DISTINCT season FROM games ORDER BY season');

        if ($query === false) {
            throw new RuntimeException('Failed to fetch seasons from database');
        }

        /** @var list<int|string> $seasons */
        $seasons = $query->fetchAll(PDO::FETCH_COLUMN);

        return array_map(static fn (int|string $season): int => (int) $season, $seasons);
    }
}
