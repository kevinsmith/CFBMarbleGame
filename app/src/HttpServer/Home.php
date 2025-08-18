<?php

declare(strict_types=1);

namespace App\HttpServer;

use function date;

final readonly class Home
{
    public function __invoke(): void
    {
        $title = 'College Football Marble Game';
        $description = 'Simple Rules for a Complex Season';
        $currentYear = date('Y');

        exit(<<<HTML
<!DOCTYPE html>
<html lang="en-US">
<head>
    <title>{$title}</title>
    <meta name="description" content="{$description}">

    <meta name="viewport" content="width=device-width, initial-scale=1" />
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
<h1>{$title}</h1>
<p>{$description}</p>

<p><small>&copy; {$currentYear} Kevin Smith. All credit for the marble game concept goes to <a href="https://x.com/iowahawkblog/status/1706341845326876998">David Burge</a>. This app is <a href="https://github.com/kevinsmith/CFBMarbleGame">open source</a>, licensed under the <a href="https://www.apache.org/licenses/LICENSE-2.0">Apache License, Version 2.0</a>, and provided "AS IS" without warranty of any kind.</small></p>
<p><small>Find out more on <a href="https://github.com/kevinsmith/CFBMarbleGame">GitHub</a> and <a href="https://x.com/CFBMarbleGame">X</a>.</small></p>
</body>
</html>
HTML);
    }
}
