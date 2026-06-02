<?php

declare(strict_types=1);

namespace Tests\RookieOption;

use PHPUnit\Framework\TestCase;
use RookieOption\RookieOptionController;
use RookieOption\Contracts\RookieOptionControllerInterface;

/**
 * RookieOptionControllerTest - Tests for RookieOptionController
 */
class RookieOptionControllerTest extends TestCase
{
    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testImplementsInterface(): void
    {
        self::assertContains(RookieOptionControllerInterface::class, (array) class_implements(RookieOptionController::class));
    }

}
