<?php

declare(strict_types=1);

$dbPath = getenv('DB_PATH');

$pdo = new PDO('sqlite:' . $dbPath);

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/seeds',
    ],
    'environments' => [
        'default_environment' => 'default',
        'default_migration_table' => 'phinxlog',
        'default' => [
            'name'          => $dbPath,
            'connection'    => $pdo,

        ],
    ],
    'version_order' => 'creation',
];
