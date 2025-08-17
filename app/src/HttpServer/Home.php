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
