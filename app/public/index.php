<?php

declare(strict_types=1);

use App\Bootstrap;
use App\HttpServer\TempWelcome;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = Bootstrap::init();

$tempWelcome = $container->get(TempWelcome::class);
assert($tempWelcome instanceof TempWelcome);

$tempWelcome();
