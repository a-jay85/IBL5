<?php

declare(strict_types=1);

namespace Debug\Contracts;

interface DebugSessionInterface
{
    public function isDebugAdmin(): bool;

    public function isViewAllExtensionsEnabled(): bool;

    public function toggleViewAllExtensions(): void;
}
