<?php

declare(strict_types=1);

namespace App;

use function array_values;
use function count;
use function is_object;
use function method_exists;
use function property_exists;
use function spl_object_hash;

/**
 * @template TKey
 * @template TEntity
 */
final class IdentityMap
{
    /** @var array<string, TEntity> */
    private array $entities = [];

    /**
     * @param TKey    $key
     * @param TEntity $entity
     */
    public function add(mixed $key, mixed $entity): void
    {
        $this->entities[$this->normalizeKey($key)] = $entity;
    }

    /**
     * @param TKey $key
     *
     * @return TEntity|null
     */
    public function get(mixed $key): mixed
    {
        return $this->entities[$this->normalizeKey($key)] ?? null;
    }

    /** @param TKey $key */
    public function has(mixed $key): bool
    {
        return isset($this->entities[$this->normalizeKey($key)]);
    }

    /** @param TKey $key */
    public function remove(mixed $key): void
    {
        unset($this->entities[$this->normalizeKey($key)]);
    }

    /** @return TEntity[] */
    public function getAll(): array
    {
        return array_values($this->entities);
    }

    public function count(): int
    {
        return count($this->entities);
    }

    /** @param TKey $key */
    private function normalizeKey(mixed $key): string
    {
        if (is_object($key)) {
            // @phpstan-ignore function.alreadyNarrowedType
            if (method_exists($key, '__toString')) {
                // @phpstan-ignore cast.string
                return (string) $key;
            }

            if (property_exists($key, 'id')) {
                // @phpstan-ignore property.notFound, cast.string
                return (string) $key->id;
            }

            return spl_object_hash($key);
        }

        // @phpstan-ignore cast.string
        return (string) $key;
    }
}
