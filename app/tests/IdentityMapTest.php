<?php

declare(strict_types=1);

namespace Tests;

use App\IdentityMap;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentityMap::class)]
final class IdentityMapTest extends TestCase
{
    public function testAddGetHasRemoveAndCount(): void
    {
        $map = new IdentityMap();

        self::assertFalse($map->has('foo'));
        self::assertSame(0, $map->count());

        $map->add('foo', 'bar');

        self::assertTrue($map->has('foo'));
        self::assertSame('bar', $map->get('foo'));
        self::assertSame(1, $map->count());

        $map->remove('foo');

        self::assertFalse($map->has('foo'));
        self::assertNull($map->get('foo'));
        self::assertSame(0, $map->count());
    }

    public function testObjectKeysAreNormalizedById(): void
    {
        $map = new IdentityMap();
        $first = new class (1) {
            public function __construct(public readonly int $id)
            {
            }
        };
        $second = new class (1) {
            public function __construct(public readonly int $id)
            {
            }
        };

        $map->add($first, 'one');
        $map->add($second, 'two');

        self::assertSame('two', $map->get($first));
        self::assertSame('two', $map->get($second));
        self::assertSame(1, $map->count());
        self::assertSame(['two'], $map->getAll());
    }

    public function testObjectsWithoutIdOrStringCastAreKeyedByObjectHash(): void
    {
        $map = new IdentityMap();
        $first = new class {
        };
        $second = new class {
        };

        $map->add($first, 'one');
        $map->add($second, 'two');

        self::assertSame('one', $map->get($first));
        self::assertSame('two', $map->get($second));
        self::assertSame(2, $map->count());
    }
}
