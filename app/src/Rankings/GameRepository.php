<?php

declare(strict_types=1);

namespace App\Rankings;

interface GameRepository
{
    /** @return Game[] */
    public function getGames(): array;
}
