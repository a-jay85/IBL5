<?php

declare(strict_types=1);

final class NonRepositoryCallsBeginTransaction
{
    public function runTransaction(\mysqli $db): void
    {
        $db->begin_transaction();
    }
}
