<?php

declare(strict_types=1);

$pipes = [];
$p = proc_open('echo test', [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
$exit = proc_close($p);
if ($exit !== 0) {
    exit(1);
}
