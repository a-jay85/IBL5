<?php

declare(strict_types=1);

namespace ComparePlayers\Contracts;

interface ComparePlayersControllerInterface
{
    public function main(mixed $user): void;
}
