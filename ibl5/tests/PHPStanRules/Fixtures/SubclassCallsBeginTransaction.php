<?php

declare(strict_types=1);

final class SubclassCallsBeginTransaction extends \BaseMysqliRepository
{
    public function runTransaction(\mysqli $db): void
    {
        $db->begin_transaction();
    }
}
