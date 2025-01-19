<?php

declare(strict_types=1);

namespace App;

use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

final readonly class WhoopsErrorHandler
{
    public function __construct(
        private Run $whoops,
        private PrettyPageHandler $prettyPageHandler,
        private JsonResponseHandler $jsonResponseHandler,
    ) {
    }

    public function registerHtmlResponder(): void
    {
        $this->whoops->pushHandler($this->prettyPageHandler);

        $this->whoops->register();
    }

    public function registerJsonResponder(): void
    {
        $this->jsonResponseHandler->addTraceToOutput(true);
        $this->whoops->pushHandler($this->jsonResponseHandler);

        $this->whoops->register();
    }
}
