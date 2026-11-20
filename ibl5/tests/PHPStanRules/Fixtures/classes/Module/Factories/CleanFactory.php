<?php

declare(strict_types=1);

namespace Module\Factories;

final class CleanFactory
{
    public function __construct(private \mysqli $db)
    {
    }

    public function createService(): \stdClass
    {
        return new \stdClass();
    }

    private function db(): \mysqli
    {
        return $this->db;
    }

    public function database(): \stdClass
    {
        return new \stdClass();
    }
}
