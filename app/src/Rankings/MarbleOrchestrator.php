<?php

declare(strict_types=1);

namespace App\Rankings;

use Psr\Log\LoggerInterface;

use function array_filter;
use function array_merge;
use function array_values;
use function in_array;
use function ksort;
use function round;
use function uasort;

final readonly class MarbleOrchestrator
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    /**
     * @param Team[] $teams
     * @param Game[] $games
     *
     * @return Team[]
     */
    public function getRankedTeams(int $week, array $teams, array $games): array
    {
        foreach ($teams as $team) {
            $this->doleOutInitialMarbles($team, $games);
        }

        foreach ($this->gamesThroughWeek($week, $games) as $game) {
            $this->awardMarbles($game);
        }

        $teams = $this->removeTeamsWithoutMarbles($teams);

        return $this->applyStandardCompetitionRanking($teams);
    }

    /** @param Game[] $games */
    public function determineMostRecentCompleteWeek(array $games): int
    {
        $gamesByWeek = [];

        foreach ($games as $game) {
            $gamesByWeek[$game->weekNumber][] = $game;
        }

        ksort($gamesByWeek);

        $mostRecentCompleteWeek = 0;

        foreach ($gamesByWeek as $week => $gamesForTheWeek) {
            if ($this->isWeekComplete($gamesForTheWeek)) {
                $mostRecentCompleteWeek = $week;
            }
        }

        return $mostRecentCompleteWeek;
    }

    /** @param Game[] $games */
    private function doleOutInitialMarbles(Team $team, array $games): void
    {
        if ($team->subdivision !== Subdivision::FBS) {
            return;
        }

        $initialMarbles = 100;

        $powerConferences = [
            Conference::BigTen,
            Conference::Big12,
            Conference::ACC,
            Conference::SEC,
        ];

        foreach ($this->getOpponents($team, $games) as $opponent) {
            // Add 10 marbles for each power conference opponent
            if (in_array($opponent->conference, $powerConferences, true)) {
                $initialMarbles += 10;
            }
        }

        $team->receiveMarbles($initialMarbles);
    }

    /**
     * @param Game[] $games
     *
     * @return Team[]
     */
    private function getOpponents(Team $team, array $games): array
    {
        $opponents = [];

        foreach ($games as $game) {
            // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
            if ($game->homeTeam == $team) {
                $opponents[] = $game->awayTeam;
            }

            // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
            if ($game->awayTeam == $team) {
                $opponents[] = $game->homeTeam;
            }
        }

        return $opponents;
    }

    /**
     * @param Team[] $teams
     *
     * @return Team[]
     */
    private function removeTeamsWithoutMarbles(array $teams): array
    {
        return array_values(
            array_filter(
                $teams,
                static function (Team $team): bool {
                    return $team->getMarbles() > 0;
                },
            ),
        );
    }

    /**
     * @param Game[] $games
     *
     * @return Game[]
     */
    private function gamesThroughWeek(int $week, array $games): array
    {
        $gamesByWeek = [];

        foreach ($games as $game) {
            if ($game->weekNumber <= $week) {
                $gamesByWeek[$game->weekNumber][] = $game;
            }
        }

        ksort($gamesByWeek);

        return array_merge(...$gamesByWeek);
    }

    /** @param Game[] $gamesForTheWeek */
    private function isWeekComplete(array $gamesForTheWeek): bool
    {
        foreach ($gamesForTheWeek as $game) {
            if ($game->winner === null) {
                return false;
            }
        }

        return true;
    }

    private function awardMarbles(Game $game): void
    {
        $loggerContext = [
            'game' => [
                'id' => $game->id->id,
                'date' => $game->date->format('Y-m-d'),
                'week' => $game->weekNumber,
                'neutral_site' => $game->neutralSite,
                'winner' => $game->winner?->value,
            ],
        ];

        $winner = $this->getWinner($game);
        $loser = $this->getLoser($game);

        $loggerContext['winner'] = [
            'id' => $winner->id->id,
            'team' => $winner->teamName,
            'subdivision' => $winner->subdivision->name,
            'conference' => $winner->conference->value,
            'marbles_before_game' => $winner->getMarbles(),
        ];
        $loggerContext['loser'] = [
            'id' => $loser->id->id,
            'team' => $loser->teamName,
            'subdivision' => $loser->subdivision->name,
            'conference' => $loser->conference->value,
            'marbles_before_game' => $loser->getMarbles(),
        ];

        if ($loser->subdivision === Subdivision::FCS) {
            // This also means that games involving 2 FCS teams with marbles won't award
            // marbles to the winner. Bug in the algorithm?
            $this->logger->debug('Loser was FCS. No marbles to move.', $loggerContext);

            return;
        }

        $winPercentage = $this->determineWinPercentage($game);

        $loggerContext['win_percentage'] = $winPercentage * 100 . '%';

        $marblesToMove = (int) round($loser->getMarbles() * $winPercentage);

        $loser->giveUpMarbles($marblesToMove);
        $winner->receiveMarbles($marblesToMove);

        $loggerContext['marbles_awarded'] = $marblesToMove;
        $loggerContext['winner']['marbles_after_game'] = $winner->getMarbles();
        $loggerContext['loser']['marbles_after_game'] = $loser->getMarbles();

        $this->logger->debug('Marbles awarded.', $loggerContext);
    }

    private function determineWinPercentage(Game $game): float
    {
        if ($this->getWinner($game)->subdivision === Subdivision::FCS) {
            return 0.25;
        }

        if ($game->neutralSite) {
            return 0.2;
        }

        if ($game->winner === Winner::Away) {
            return 0.25;
        }

        return 0.2;
    }

    private function getWinner(Game $game): Team
    {
        if ($game->winner === Winner::Home) {
            return $game->homeTeam;
        }

        return $game->awayTeam;
    }

    private function getLoser(Game $game): Team
    {
        if ($game->winner === Winner::Home) {
            return $game->awayTeam;
        }

        return $game->homeTeam;
    }

    /**
     * @param Team[] $teams
     *
     * @return Team[]
     */
    private function applyStandardCompetitionRanking(array $teams): array
    {
        $teams = $this->sortByMarblesDescendingThenAlphabetically($teams);

        $rankedTeams = [];
        $currentRank = 1;
        $previousMarbles = null;
        $teamsAtCurrentMarbleCount = 0;

        foreach ($teams as $team) {
            if ($previousMarbles !== null && $team->getMarbles() < $previousMarbles) {
                $currentRank += $teamsAtCurrentMarbleCount;
                $teamsAtCurrentMarbleCount = 0;
            }

            $teamsAtCurrentMarbleCount++;
            $previousMarbles = $team->getMarbles();
            $team->setMarbleRank($currentRank);

            $rankedTeams[] = $team;
        }

        return $rankedTeams;
    }

    /**
     * @param Team[] $teams
     *
     * @return Team[]
     */
    private function sortByMarblesDescendingThenAlphabetically(array $teams): array
    {
        uasort($teams, static function (Team $a, Team $b): int {
            $marbleComparison = $b->getMarbles() <=> $a->getMarbles();

            return $marbleComparison !== 0 ? $marbleComparison : $a->teamName <=> $b->teamName;
        });

        return $teams;
    }
}
