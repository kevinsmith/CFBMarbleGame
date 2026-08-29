<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSeasonToGames extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
        ALTER TABLE games ADD COLUMN season INTEGER NOT NULL DEFAULT 2025;
        SQL);

        $this->execute(<<<'SQL'
        CREATE TABLE games_season (
            id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL,
            week_number INTEGER NOT NULL,
            season INTEGER NOT NULL,
            neutral_site INTEGER NOT NULL,
            home_team_id INTEGER NOT NULL,
            away_team_id INTEGER NOT NULL,
            winner TEXT CHECK(winner IN ('home', 'away') OR winner IS NULL),
            cfbd_id INTEGER UNIQUE
        );
        SQL);

        $this->execute(<<<'SQL'
        INSERT INTO games_season (id, date, week_number, season, neutral_site, home_team_id, away_team_id, winner, cfbd_id)
        SELECT id, date, week_number, season, neutral_site, home_team_id, away_team_id, winner, cfbd_id FROM games;
        SQL);

        $this->execute('DROP TABLE games');
        $this->execute('ALTER TABLE games_season RENAME TO games');
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
        ALTER TABLE games DROP COLUMN season;
        SQL);
    }
}
