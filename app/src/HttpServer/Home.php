<?php

declare(strict_types=1);

namespace App\HttpServer;

final readonly class Home
{
    public function __invoke(): void
    {
        $description = 'Simple Rules for a Complex Season';

        exit(<<<HTML
<!DOCTYPE html>
<html lang="en-US">
<head>
    <title>CFB Marble Game</title>
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
<h1>CFB Marble Game</h1>
<p>{$description}</p>
<p><a href="https://x.com/CFBMarbleGame">More</a></p>
</body>
</html>
HTML);
    }
}
