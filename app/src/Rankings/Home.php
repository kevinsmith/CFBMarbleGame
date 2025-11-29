<?php

declare(strict_types=1);

namespace App\Rankings;

use InvalidArgumentException;
use RuntimeException;
use Sapien\Request;
use Sapien\Response;

use function ctype_digit;
use function date;
use function dirname;
use function file_exists;
use function file_get_contents;
use function json_decode;
use function ob_get_clean;
use function ob_start;

use const JSON_THROW_ON_ERROR;

final readonly class Home
{
    public function __construct(
        private MarbleRankingsQueryHandler $queryHandler,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $title = 'College Football Marble Game';
        $description = 'Simple Rules for a Complex Season';
        $stylesheet = $this->getStylesheetFilename('styles.css');
        $currentYear = date('Y');

        try {
            $week = $this->parseWeekParam($request);

            [$week, $rankings] = $this->queryHandler->getRankings($week);
        } catch (InvalidArgumentException) {
            return new Response()->setCode(404);
        }

        if ($week < 2) {
            $rankingsWeek = 'Preseason';
        } elseif ($week === 17) {
            $rankingsWeek = 'Final';
        } else {
            $rankingsWeek = 'Week ' . $week;
        }

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

    private function getStylesheetFilename(string $stylesheet, string $manifestPath = 'dist/asset-manifest.json'): string
    {
        $manifestPath = dirname(__DIR__, 2) . '/' . $manifestPath;

        if (! file_exists($manifestPath)) {
            throw new RuntimeException('Manifest file not found at ' . $manifestPath);
        }

        /** @var string[] $manifest */
        $manifest = json_decode(file_get_contents($manifestPath) ?: '', true, flags: JSON_THROW_ON_ERROR);

        if (! isset($manifest[$stylesheet])) {
            throw new RuntimeException('Unknown stylesheet ' . $stylesheet);
        }

        return $manifest[$stylesheet];
    }
}
