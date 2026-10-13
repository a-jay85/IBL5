<?php

declare(strict_types=1);

namespace Tests\Database;

use Database\SqlPlaceholders;
use PHPUnit\Framework\TestCase;

final class SqlPlaceholdersTest extends TestCase
{
    public function testPadPidListPadsToWidth(): void
    {
        $padded = SqlPlaceholders::padPidList([5, 7, 9]);

        $this->assertCount(SqlPlaceholders::PID_LIST_WIDTH, $padded);
        $this->assertSame([5, 7, 9], array_slice($padded, 0, 3));
        $this->assertSame([-1], array_values(array_unique(array_slice($padded, 3))));
    }

    public function testPadPidListRejectsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SqlPlaceholders::padPidList([]);
    }

    public function testPadPidListRejectsOverflow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SqlPlaceholders::padPidList(range(1, SqlPlaceholders::PID_LIST_WIDTH + 1));
    }

    public function testPidListConstantsAgreeWithWidth(): void
    {
        $this->assertSame(SqlPlaceholders::PID_LIST_WIDTH, substr_count(SqlPlaceholders::PID_LIST, '?'));
        $this->assertSame(SqlPlaceholders::PID_LIST_WIDTH, strlen(SqlPlaceholders::PID_LIST_TYPES));
        $this->assertSame(str_repeat('i', SqlPlaceholders::PID_LIST_WIDTH), SqlPlaceholders::PID_LIST_TYPES);
    }
}
