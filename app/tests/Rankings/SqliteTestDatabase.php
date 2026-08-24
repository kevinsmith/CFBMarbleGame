<?php

declare(strict_types=1);

namespace Tests\Rankings;

use PDO;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

use function dirname;

final class SqliteTestDatabase
{
    public static function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $config = new Config([
            'paths' => [
                'migrations' => dirname(__DIR__, 2) . '/config/phinx/migrations',
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'test' => [
                    'connection' => $pdo,
                    'name' => ':memory:',
                ],
            ],
            'version_order' => 'creation',
        ]);

        $manager = new Manager($config, new StringInput(''), new NullOutput());
        $manager->migrate('test');
    }
}
