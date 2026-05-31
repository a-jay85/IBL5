<?php

declare(strict_types=1);

final class FakeConnection
{
    public function query(string $sql): void
    {
    }
}

function nonMysqliQuery(FakeConnection $conn): void
{
    $conn->query('SELECT 1');
}
