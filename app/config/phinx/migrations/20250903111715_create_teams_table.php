<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTeamsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
        CREATE TABLE teams (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            subdivision TEXT NOT NULL,
            conference TEXT,
            cfbd_id INTEGER UNIQUE
        );
        SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE teams');
    }
}
