<?php

declare(strict_types=1);

class MysqliPropertyInView
{
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }
}
