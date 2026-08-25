<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

use function file_exists;
use function file_get_contents;
use function json_decode;

use const JSON_THROW_ON_ERROR;

final readonly class AssetManifest
{
    public function __construct(private string $path)
    {
    }

    public function stylesheet(string $name): string
    {
        if (! file_exists($this->path)) {
            throw new RuntimeException('Manifest file not found at ' . $this->path);
        }

        /** @var array<string, string> $manifest */
        $manifest = json_decode(file_get_contents($this->path) ?: '', true, flags: JSON_THROW_ON_ERROR);

        if (! isset($manifest[$name])) {
            throw new RuntimeException('Unknown stylesheet ' . $name);
        }

        return $manifest[$name];
    }
}
