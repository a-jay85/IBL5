<?php

declare(strict_types=1);

namespace Tests\UI;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UI\DebugOutput;

#[CoversClass(DebugOutput::class)]
class DebugOutputTest extends TestCase
{
    protected function setUp(): void
    {
        $authStub = $this->createStub(\Auth\AuthService::class);
        $authStub->method('isAdmin')->willReturn(true);
        $GLOBALS['authService'] = $authStub;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['authService']);
    }

    #[Test]
    public function adminRendersPanelWithTitleAndBody(): void
    {
        ob_start();
        DebugOutput::display('a <br> b', 'My Title');
        $output = ob_get_clean();

        $this->assertStringContainsString('<div class="debug-panel">', $output);
        $this->assertStringContainsString('My Title', $output);
        $this->assertStringContainsString('a <br> b', $output);
        $this->assertStringContainsString('toggleDebug', $output);
    }

    #[Test]
    public function nonAdminEmitsNothing(): void
    {
        $authStub = $this->createStub(\Auth\AuthService::class);
        $authStub->method('isAdmin')->willReturn(false);
        $GLOBALS['authService'] = $authStub;

        ob_start();
        DebugOutput::display('secret', 'Should Not Show');
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    #[Test]
    public function outerBufferRemainsIntactAndOrdered(): void
    {
        ob_start();
        echo 'OUTER_BEFORE';
        DebugOutput::display('content', 'Panel');
        echo 'OUTER_AFTER';
        $captured = ob_get_clean();

        $this->assertStringContainsString('OUTER_BEFORE', $captured);
        $this->assertStringContainsString('<div class="debug-panel">', $captured);
        $this->assertStringContainsString('OUTER_AFTER', $captured);
        $this->assertLessThan(
            strpos($captured, '<div class="debug-panel">'),
            strpos($captured, 'OUTER_BEFORE')
        );
        $this->assertGreaterThan(
            strpos($captured, '<div class="debug-panel">'),
            strpos($captured, 'OUTER_AFTER')
        );
    }
}
