<?php

declare(strict_types=1);

namespace Tests\PHPStanRules\Fixtures;

class PhpDocOnlyTypes
{
    /** @var \stdClass */
    private $mockDb;

    /** @param int $x */
    public function f($x): void
    {
    }

    /** @return array<int> */
    public function g()
    {
        return [];
    }
}
