<?php

declare(strict_types=1);

namespace App\Rankings;

enum Winner: string
{
    case Home = 'home';
    case Away = 'away';
}
