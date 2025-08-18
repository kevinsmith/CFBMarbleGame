<?php

declare(strict_types=1);

namespace App\HttpServer;

use RuntimeException;

use function date;
use function dirname;
use function file_exists;
use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class Home
{
    public function __invoke(): void
    {
        $title = 'College Football Marble Game';
        $description = 'Simple Rules for a Complex Season';
        $stylesheet = $this->getStylesheetFilename('styles.css');
        $currentYear = date('Y');

        exit(<<<HTML
<!DOCTYPE html>
<html lang="en-US">
<head>
    <title>{$title}</title>
    <meta name="description" content="{$description}">

    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/{$stylesheet}">
    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <style>
        :root {
          font-family: Inter, 'Helvetica Neue', Arial, sans-serif;
          font-feature-settings: 'liga' 1, 'calt' 1; /* fix for Chrome */
        }
        @supports (font-variation-settings: normal) {
          :root { font-family: InterVariable, 'Helvetica Neue', Arial, sans-serif; }
        }
    </style>
</head>
<body>
<div class="mx-auto min-w-xs max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <main class="py-10">
            <h1 class="text-center text-balance text-3xl font-bold">{$title}</h1>
        </main>
        <footer>
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:max-w-7xl lg:px-8">
                <div class="border-t border-gray-200 py-8 text-center text-pretty text-sm text-gray-500 sm:text-left">
                    <span class="block mb-2">&copy; {$currentYear} Kevin Smith. All credit for the marble game concept goes to <a href="https://x.com/iowahawkblog/status/1706341845326876998">David Burge</a>.</span>
                    <span class="block mb-2">This app is <a href="https://github.com/kevinsmith/CFBMarbleGame">open source</a>, licensed under the <a href="https://www.apache.org/licenses/LICENSE-2.0">Apache License, Version 2.0</a>, and provided "AS IS" without warranty of any kind.</span>
                    <span class="block">Find out more on <a href="https://github.com/kevinsmith/CFBMarbleGame">GitHub</a> and <a href="https://x.com/CFBMarbleGame">X</a>.</span>
                </div>
            </div>
        </footer>
    </div>
</div>
</body>
</html>
HTML);
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
