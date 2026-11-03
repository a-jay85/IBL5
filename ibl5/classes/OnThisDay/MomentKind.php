<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * Kinds of "on this day" moments. Declaration order is the rank tie-break order.
 */
enum MomentKind: string
{
    case FiftyPointGame = 'fifty_point';
    case TripleDouble = 'triple_double';
    case RecordGame = 'record_game';
    case SeriesClincher = 'series_clincher';
    case Trade = 'trade';
}
