<?php

declare(strict_types=1);

namespace App\Rankings;

use InvalidArgumentException;
use Sapien\Request;
use Sapien\Response;

use function ctype_digit;
use function date;
use function ob_get_clean;
use function ob_start;

final readonly class Home
{
    public function __construct(
        private MarbleRankingsQueryHandler $queryHandler,
        private string $stylesheet,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $title = 'College Football Marble Game';
        $description = 'Simple Rules for a Complex Season';
        $stylesheet = $this->stylesheet;
        $currentYear = date('Y');

        try {
            $week = $this->parseWeekParam($request);

            [$week, $latestRankingsWeek, $rankings] = $this->queryHandler->getRankings($week);
        } catch (InvalidArgumentException) {
            return new Response()->setCode(404);
        }

        $weekLabels = $this->generateWeekLabels($latestRankingsWeek);

        ob_start();
        include __DIR__ . '/HomeTemplate.html.php';
        $html = ob_get_clean();

        return new Response()->setContent($html);
    }

    private function parseWeekParam(Request $request): int|null
    {
        $week = $request->query['week'] ?? null;

        if (empty($week)) {
            return null;
        }

        if (ctype_digit($week)) {
            return (int) $week;
        }

        throw new InvalidArgumentException('Invalid week parameter');
    }

    /** @return array<int, string> */
    private function generateWeekLabels(int $latestRankingsWeek): array
    {
        $weekLabels = [];

        for ($i = 1; $i <= $latestRankingsWeek; $i++) {
            if ($i === 1) {
                $weekLabels[$i] = 'Preseason';
            } elseif ($i === 17) {
                $weekLabels[$i] = 'Final';
            } else {
                $weekLabels[$i] = 'Week ' . $i;
            }
        }

        return $weekLabels;
    }
}
