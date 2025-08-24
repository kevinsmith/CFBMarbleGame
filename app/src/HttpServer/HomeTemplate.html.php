<?php
/**
 * @var string $title
 * @var string $description
 * @var string $stylesheet
 * @var string $currentYear
 */
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <title><?=$title?></title>
    <meta name="description" content="<?=$description?>">

    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="/<?=$stylesheet?>">
    <link rel="preconnect" href="https://rsms.me/">
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
</head>
<body>
<div class="mx-auto min-w-xs max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-3xl">
        <main class="py-10">
            <div class="flex justify-center">
                <a href="/" class="inline-block">
                    <img src="/logo.png" alt="<?=$title?>" class="max-w-48 h-auto">
                </a>
            </div>
            <div class="prose mt-12">
                <ol>
                    <li>
                        <p>Every FBS school starts off with 100 marbles, plus 10 bonus marbles for every P4 opponent on their schedule</p>
                    </li>
                    <li>
                        <p>Beat your opponent at your home place or neutral site (<a href="https://x.com/iowahawkblog/status/1827118895842664563?s=46" rel="nofollow">including conference championships</a>), take 20% of their marbles; beat your opponent at their home place, take 25% of their marbles</p>
                    </li>
                    <li>
                        <p>Fractional marbles are rounded to nearest whole number. For example, if you beat an opponent with 210 marbles at their place, you would get 25% of their 210, 52.5, which would be rounded up to 53.</p>
                    </li>
                    <li>
                        <p>If you beat an FCS opponent, you get nothing, If you lose to an FCS opponent, they get 25% of your marbles. Because FU coward.</p>
                    </li>
                </ol>
            </div>
        </main>
        <footer>
            <div class="border-t border-gray-200 py-8 text-center text-pretty text-sm text-gray-500 sm:text-left">
                <span class="block mb-2">&copy; <?=$currentYear?> Kevin Smith. All credit for the marble game concept goes to <a href="https://x.com/iowahawkblog/status/1706341845326876998">David Burge</a>.</span>
                <span class="block mb-2">This app is <a href="https://github.com/kevinsmith/CFBMarbleGame">open source</a>, licensed under the <a href="https://www.apache.org/licenses/LICENSE-2.0">Apache License, Version 2.0</a>, and provided "AS IS" without warranty of any kind.</span>
                <span class="block">Find out more on <a href="https://github.com/kevinsmith/CFBMarbleGame">GitHub</a> and <a href="https://x.com/CFBMarbleGame">X</a>.</span>
            </div>
        </footer>
    </div>
</div>
</body>
</html>
