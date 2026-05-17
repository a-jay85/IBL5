<?php

declare(strict_types=1);

namespace Player\Stats\Views;

enum PlayerSeasonTableMode: string
{
    case AVERAGES = 'averages';
    case TOTALS = 'totals';
}
