<?php

declare(strict_types=1);

namespace App\Rankings;

use App\DateFormat;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use PDO;
use RuntimeException;
use Throwable;

use function count;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * @phpstan-type CfbdApiGamesResponse array<array{
 *     id: int,
 *     season: int,
 *     week: int,
 *     seasonType: string,
 *     startDate: string,
 *     startTimeTBD: bool,
 *     completed: bool,
 *     neutralSite: bool,
 *     conferenceGame: bool,
 *     attendance: ?int,
 *     venueId: int,
 *     venue: string,
 *     homeId: int,
 *     homeTeam: string,
 *     homeClassification: string,
 *     homeConference: string,
 *     homePoints: int,
 *     homeLineScores: array<int, int>,
 *     homePostgameWinProbability: float,
 *     homePregameElo: ?int,
 *     homePostgameElo: ?int,
 *     awayId: int,
 *     awayTeam: string,
 *     awayClassification: string,
 *     awayConference: string,
 *     awayPoints: int,
 *     awayLineScores: array<int, int>,
 *     awayPostgameWinProbability: float,
 *     awayPregameElo: ?int,
 *     awayPostgameElo: ?int,
 *     excitementIndex: float,
 *     highlights: string,
 *     notes: ?string
 * }>
 * @phpstan-type TeamArray array{
 *     name: string,
 *     subdivision: Subdivision,
 *     conference: Conference
 * }
 * @phpstan-type GameArray array{
 *     date: DateTimeImmutable,
 *     season_type: SeasonType,
 *     week_number: int,
 *     neutral_site: int,
 *     home_team_cfbd_id: int,
 *     away_team_cfbd_id: int,
 *     home_team_points: ?int,
 *     away_team_points: ?int
 * }
 */
final readonly class GamesDataRetriever
{
    public function __construct(
        private Client $cfbdApiClient,
        private PDO $pdo,
    ) {
    }

    public function pullAndStoreFreshData(): void
    {
        $data = $this->fetchFromCfbdApi();

        [$games, $teams] = $this->extractGamesAndTeams($data);

        $this->saveTeams($teams);

        $this->saveGames($games);
    }

    /** @return CfbdApiGamesResponse */
    private function fetchFromCfbdApi(): array
    {
        $apiResponse = $this->cfbdApiClient->get(
            '/games',
            [
                RequestOptions::QUERY => [
                    'year' => '2025',
                    'classification' => 'fbs',
                ],
            ],
        )->getBody()->getContents();

        /** @var CfbdApiGamesResponse $data */
        $data = json_decode($apiResponse, true, flags: JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * @param CfbdApiGamesResponse $data
     *
     * @return array{0: GameArray[], 1: TeamArray[]}
     */
    private function extractGamesAndTeams(array $data): array
    {
        $games = [];
        $teams = [];

        foreach ($data as $game) {
            $gameId = (int) $game['id'];

            $gameDate = DateTimeImmutable::createFromFormat(DateFormat::CFBDAPI, $game['startDate']);
            if ($gameDate === false) {
                throw new RuntimeException('Failed to parse game date: ' . $game['startDate']);
            }

            $games[$gameId] = [
                'date' => $gameDate,
                'season_type' => SeasonType::fromString($game['seasonType']),
                'week_number' => (int) $game['week'],
                'neutral_site' => (int) $game['neutralSite'],
                'home_team_cfbd_id' => (int) $game['homeId'],
                'away_team_cfbd_id' => (int) $game['awayId'],
                'home_team_points' => $game['homePoints'],
                'away_team_points' => $game['awayPoints'],
            ];

            $homeId = (int) $game['homeId'];

            $teams[$homeId] = [
                'name' => $game['homeTeam'],
                'subdivision' => Subdivision::fromString($game['homeClassification']),
                'conference' => Conference::fromString($game['homeConference']),
            ];

            $awayId = (int) $game['awayId'];

            $teams[$awayId] = [
                'name' => $game['awayTeam'],
                'subdivision' => Subdivision::fromString($game['awayClassification']),
                'conference' => Conference::fromString($game['awayConference']),
            ];
        }

        return [$games, $teams];
    }

    /** @param TeamArray[] $teams */
    private function saveTeams(array $teams): void
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO teams (name, subdivision, conference, cfbd_id)
                VALUES (:name, :subdivision, :conference, :cfbd_id)
            ON CONFLICT(cfbd_id) DO UPDATE SET
                name = :name,
                subdivision = :subdivision,
                conference = :conference;
            SQL);

            foreach ($teams as $cfbdId => $team) {
                $stmt->execute([
                    'name' => $team['name'],
                    'subdivision' => $team['subdivision']->name,
                    'conference' => $team['conference']->value,
                    'cfbd_id' => $cfbdId,
                ]);
            }

            $this->pdo->commit();

            echo 'Inserted or updated ' . count($teams) . ' teams successfully';
        } catch (Throwable $e) {
            $this->pdo->rollback();
            echo 'Error: ' . $e->getMessage();
        }
    }

    /** @return array<int, int> mapping of CFBD ID to internal team ID */
    private function getTeamIdMapping(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, cfbd_id FROM teams');
        $stmt->execute();

        $mapping = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            /** @var array{cfbd_id: int, id: int} $row */
            $mapping[(int) $row['cfbd_id']] = (int) $row['id'];
        }

        return $mapping;
    }

    /** @param GameArray[] $games */
    private function saveGames(array $games): void
    {
        $teamIdMapping = $this->getTeamIdMapping();

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO games (date, season_type, week_number, neutral_site, home_team_id, away_team_id, winner_team_id, cfbd_id)
                VALUES (:date, :season_type, :week_number, :neutral_site, :home_team_id, :away_team_id, :winner_team_id, :cfbd_id)
            ON CONFLICT(cfbd_id) DO UPDATE SET
                date = :date,
                season_type = :season_type,
                week_number = :week_number,
                neutral_site = :neutral_site,
                home_team_id = :home_team_id,
                away_team_id = :away_team_id,
                winner_team_id = :winner_team_id,
                cfbd_id = :cfbd_id;
            SQL);

            foreach ($games as $cfbdId => $game) {
                $homeTeamId = $teamIdMapping[$game['home_team_cfbd_id']] ?? null;
                $awayTeamId = $teamIdMapping[$game['away_team_cfbd_id']] ?? null;

                if ($homeTeamId === null) {
                    throw new RuntimeException('Home team with CFBD ID ' . $game['home_team_cfbd_id'] . ' not found in teams table for game ' . $cfbdId);
                }

                if ($awayTeamId === null) {
                    throw new RuntimeException('Away team with CFBD ID ' . $game['away_team_cfbd_id'] . ' not found in teams table for game ' . $cfbdId);
                }

                $stmt->execute([
                    'season_type' => $game['season_type']->name,
                    'date' => $game['date']->format(DateFormat::SQLITE),
                    'week_number' => $game['week_number'],
                    'neutral_site' => $game['neutral_site'],
                    'home_team_id' => $homeTeamId,
                    'away_team_id' => $awayTeamId,
                    'winner_team_id' => $this->determineWinnerTeamId($game, $homeTeamId, $awayTeamId),
                    'cfbd_id' => $cfbdId,
                ]);
            }

            $this->pdo->commit();

            echo 'Inserted or updated ' . count($games) . ' games successfully';
        } catch (Throwable $e) {
            $this->pdo->rollback();
            echo 'Error: ' . $e->getMessage();
        }
    }

    /** @param GameArray $game */
    private function determineWinnerTeamId(array $game, int $homeTeamId, int $awayTeamId): int|null
    {
        if ($game['home_team_points'] === null || $game['away_team_points'] === null) {
            return null;
        }

        if ($game['home_team_points'] > $game['away_team_points']) {
            return $homeTeamId;
        }

        if ($game['home_team_points'] < $game['away_team_points']) {
            return $awayTeamId;
        }

        throw new RuntimeException('Impossible condition: home and away team points are equal');
    }
}
