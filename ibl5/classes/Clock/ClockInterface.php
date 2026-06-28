<?php

declare(strict_types=1);

<<<<<<<< HEAD:ibl5/classes/Clock/ClockInterface.php
namespace Clock;
========
namespace Api\Contracts;
>>>>>>>> 030b8d1d6 (refactor: Api/ interface coverage + Contracts flatten (findings 2.31, 2.32)):ibl5/classes/Api/Contracts/ClockInterface.php

interface ClockInterface
{
    public function now(): int;
}
