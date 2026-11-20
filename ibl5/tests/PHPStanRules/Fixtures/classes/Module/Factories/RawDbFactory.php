<?php

declare(strict_types=1);

namespace Module\Factories;

final class RawDbFactory
{
    public function __construct(private \mysqli $db)
    {
    }

    public function db(): \mysqli
    {
        return $this->db;
    }
}
