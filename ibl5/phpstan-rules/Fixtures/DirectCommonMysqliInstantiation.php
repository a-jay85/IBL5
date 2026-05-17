<?php

declare(strict_types=1);

namespace PHPStanRules\Fixtures;

$db = new \mysqli();
$repo = new \Services\CommonMysqliRepository($db);
