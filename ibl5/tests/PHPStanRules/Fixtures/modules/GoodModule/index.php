<?php

declare(strict_types=1);

$services = new \stdClass();
$repository = $services->repository();
$rows = $repository->query('SELECT 1');
$handle = $services->db();
