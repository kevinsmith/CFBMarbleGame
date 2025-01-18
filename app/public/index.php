<?php

declare(strict_types=1);

use App\Container;
use App\HttpServer\TempWelcome;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = Container::init();

$tempWelcome = $container->get(TempWelcome::class);
assert($tempWelcome instanceof TempWelcome);

$tempWelcome();
