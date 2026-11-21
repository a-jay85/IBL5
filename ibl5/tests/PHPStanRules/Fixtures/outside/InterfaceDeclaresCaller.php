<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures\classes\InterfaceDeclares;

function callFromOutside(SeriesRecordsService $s): string
{
    return $s->getRecordFromMatrix([], 1, 2);
}
