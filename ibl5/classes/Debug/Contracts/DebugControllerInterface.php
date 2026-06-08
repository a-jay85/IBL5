<?php

declare(strict_types=1);

namespace Debug\Contracts;

interface DebugControllerInterface
{
    public function handleToggle(): void;
}
