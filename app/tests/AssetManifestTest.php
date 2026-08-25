<?php

declare(strict_types=1);

namespace Tests;

use App\AssetManifest;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

#[CoversClass(AssetManifest::class)]
final class AssetManifestTest extends TestCase
{
    public function testStylesheetReturnsTheMappedFilename(): void
    {
        $path = $this->writeManifest('{"styles.css":"styles.a3c7e2ff48.css"}');

        try {
            $manifest = new AssetManifest($path);

            self::assertSame('styles.a3c7e2ff48.css', $manifest->stylesheet('styles.css'));
        } finally {
            unlink($path);
        }
    }

    public function testMissingFileThrows(): void
    {
        $path = sys_get_temp_dir() . '/missing-asset-manifest-' . uniqid('', true) . '.json';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Manifest file not found at ' . $path);

        new AssetManifest($path)->stylesheet('styles.css');
    }

    public function testUnknownStylesheetThrows(): void
    {
        $path = $this->writeManifest('{"other.css":"other.hash.css"}');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unknown stylesheet styles.css');

            new AssetManifest($path)->stylesheet('styles.css');
        } finally {
            unlink($path);
        }
    }

    public function testInvalidJsonThrows(): void
    {
        $path = $this->writeManifest('{');

        try {
            $this->expectException(JsonException::class);

            new AssetManifest($path)->stylesheet('styles.css');
        } finally {
            unlink($path);
        }
    }

    private function writeManifest(string $contents): string
    {
        $path = sys_get_temp_dir() . '/asset-manifest-' . uniqid('', true) . '.json';
        file_put_contents($path, $contents);

        return $path;
    }
}
