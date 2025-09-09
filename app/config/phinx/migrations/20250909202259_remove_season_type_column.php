<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveSeasonTypeColumn extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
        ALTER TABLE games DROP COLUMN season_type;
        SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
        ALTER TABLE games ADD COLUMN season_type TEXT NOT NULL;
        SQL);
    }
}
