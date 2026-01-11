<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\WaiversView;
use Waivers\Contracts\WaiversViewInterface;

/**
 * WaiversViewTest - Tests for WaiversView
 */
class WaiversViewTest extends TestCase
{
    private WaiversView $view;

    protected function setUp(): void
    {
        $this->view = new WaiversView();
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $view = new WaiversView();

        $this->assertInstanceOf(WaiversView::class, $view);
    }

    public function testImplementsInterface(): void
    {
        $view = new WaiversView();

        $this->assertInstanceOf(WaiversViewInterface::class, $view);
    }

    // ============================================
    // METHOD EXISTENCE TESTS
    // ============================================

    public function testHasRenderWaiverFormMethod(): void
    {
        $this->assertTrue(method_exists($this->view, 'renderWaiverForm'));
    }

    // ============================================
    // RENDER WAIVER FORM TESTS
    // ============================================

    public function testRenderWaiverFormOutputsContent(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'drop',
            [],
            5,
            5,
            ''
        );
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
    }

    public function testRenderWaiverFormContainsTeamName(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Boston Celtics',
            1,
            'drop',
            [],
            5,
            5,
            ''
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('Boston Celtics', $output);
    }

    public function testRenderWaiverFormContainsForm(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'drop',
            [],
            5,
            5,
            ''
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('<form', $output);
    }

    public function testRenderWaiverFormContainsRosterSpots(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'drop',
            [],
            3,
            2,
            ''
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('3 EMPTY ROSTER SPOTS', $output);
        $this->assertStringContainsString('2 HEALTHY ROSTER SPOTS', $output);
    }

    public function testRenderWaiverFormShowsErrorMessage(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            'Test Team',
            1,
            'drop',
            [],
            5,
            5,
            'Player cannot be dropped'
        );
        $output = ob_get_clean();

        $this->assertStringContainsString('Player cannot be dropped', $output);
    }

    public function testRenderWaiverFormEscapesTeamName(): void
    {
        ob_start();
        $this->view->renderWaiverForm(
            '<script>alert("xss")</script>',
            1,
            'drop',
            [],
            5,
            5,
            ''
        );
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
    }
}
