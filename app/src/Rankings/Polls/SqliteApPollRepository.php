<?php

declare(strict_types=1);

namespace App\Rankings\Polls;

use PDO;

final readonly class SqliteApPollRepository implements ApPollRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int, int> */
    public function getRanks(int $season, int $week): array
    {
        $statement = $this->pdo->prepare(
            'SELECT teams.id, ap_rankings.rank FROM ap_rankings
             INNER JOIN teams ON teams.cfbd_id = ap_rankings.cfbd_id
             WHERE season = :season AND week = :week',
        );
        $statement->execute(['season' => $season, 'week' => $week]);
        $ranks = [];

        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            /** @var array{id: int, rank: int} $row */
            $ranks[(int) $row['id']] = (int) $row['rank'];
        }

        return $ranks;
    }
}
