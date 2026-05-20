<?php

declare(strict_types=1);

class MysqliPropertyInService
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }
}
