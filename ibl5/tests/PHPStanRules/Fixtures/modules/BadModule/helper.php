<?php

declare(strict_types=1);

$services = new \stdClass();
$stmt = $services->db()->prepare('SELECT 1');
