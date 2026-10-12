<?php

declare(strict_types=1);

namespace Fixtures\Lookalike;

class BaseMysqliRepository
{
}

final class LookalikeCallsBeginTransaction extends BaseMysqliRepository
{
    public function runTransaction(\mysqli $db): void
    {
        $db->begin_transaction();
    }
}
