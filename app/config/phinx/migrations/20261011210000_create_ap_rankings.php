<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateApRankings extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
        CREATE TABLE ap_rankings (
            season INTEGER NOT NULL,
            week INTEGER NOT NULL,
            cfbd_id INTEGER NOT NULL,
            rank INTEGER NOT NULL CHECK(rank > 0),
            PRIMARY KEY (season, week, cfbd_id)
        );
        SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE ap_rankings');
    }
}
