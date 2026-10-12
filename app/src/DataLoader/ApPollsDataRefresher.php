<?php

declare(strict_types=1);

namespace App\DataLoader;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PDO;
use Throwable;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * @phpstan-type PollWeeks list<array{
 *     season: int,
 *     seasonType: string,
 *     week: int,
 *     polls: list<array{poll: string, ranks: list<array{teamId: int, rank: int|null}>}>
 * }>
 */
final readonly class ApPollsDataRefresher
{
    public function __construct(private Client $client, private PDO $pdo)
    {
    }

    public function pullAndStoreFreshData(int $season): void
    {
        $response = $this->client->get('/rankings', [
            RequestOptions::QUERY => ['year' => $season, 'seasonType' => 'regular'],
        ]);
        /** @var PollWeeks $weeks */
        $weeks = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM ap_rankings WHERE season = :season AND week = :week');
            $insert = $this->pdo->prepare(
                'INSERT INTO ap_rankings (season, week, cfbd_id, rank) VALUES (:season, :week, :cfbd_id, :rank)',
            );

            foreach ($weeks as $week) {
                foreach ($week['polls'] as $poll) {
                    if ($poll['poll'] !== 'AP Top 25') {
                        continue;
                    }

                    $delete->execute(['season' => $season, 'week' => $week['week']]);

                    foreach ($poll['ranks'] as $rank) {
                        if ($rank['rank'] === null) {
                            continue;
                        }

                        $insert->execute([
                            'season' => $season,
                            'week' => $week['week'],
                            'cfbd_id' => $rank['teamId'],
                            'rank' => $rank['rank'],
                        ]);
                    }
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
