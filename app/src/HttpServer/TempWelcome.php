<?php

declare(strict_types=1);

namespace App\HttpServer;

final readonly class TempWelcome
{
    public function __invoke(): void
    {
        exit(<<<'HTML'
<!DOCTYPE html>
<html lang="en-US">
<head>
    <title>CFB Marble Game</title>
</head>
<body>

<h1>CFB Marble Game</h1>
<p>Simple Rules for a Complex Season</p>
<p><a href="https://x.com/CFBMarbleGame">More</a></p>

</body>
</html>
HTML);
    }
}
