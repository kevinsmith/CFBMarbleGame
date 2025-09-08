<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGamesTable extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
        CREATE TABLE games (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            season_type TEXT NOT NULL,
            week_number INTEGER NOT NULL,
            neutral_site INTEGER NOT NULL,
            home_team_id INTEGER NOT NULL,
            away_team_id INTEGER NOT NULL,
            winner_team_id INTEGER,
            cfbd_id INTEGER UNIQUE
        );
        SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE games');
    }
}
