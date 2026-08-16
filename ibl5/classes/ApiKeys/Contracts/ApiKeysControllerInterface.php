<?php

declare(strict_types=1);

namespace ApiKeys\Contracts;

interface ApiKeysControllerInterface
{
    public function handle(string $op, mixed $user): void;
}
