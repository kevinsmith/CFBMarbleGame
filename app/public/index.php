<?php

declare(strict_types=1);

use App\Bootstrap;
use App\HttpServer\FrontController;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$container = Bootstrap::init();

$frontController = $container->get(FrontController::class);
assert($frontController instanceof FrontController);

$frontController->run();
