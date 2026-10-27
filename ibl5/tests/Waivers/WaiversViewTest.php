<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\WaiversView;

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
    // RENDER WAIVER FORM TESTS
    // ============================================

    public function testRenderWaiverFormReturnsContent(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertNotSame('', $output);
    }

    public function testRenderWaiverFormContainsTeamName(): void
    {
        $output = $this->view->renderWaiverForm(
            'Boston Celtics',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringContainsString('Boston Celtics', $output);
    }

    public function testRenderWaiverFormContainsForm(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringContainsString('<form', $output);
    }

    public function testRenderWaiverFormContainsRosterSpots(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            3,
            2
        );

        $this->assertStringContainsString('3 OPEN SPOTS', $output);
        $this->assertStringContainsString('2 HEALTHY SPOTS', $output);
    }

    public function testRenderWaiverFormShowsErrorMessage(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5,
            null,
            'Player cannot be dropped'
        );

        $this->assertStringContainsString('Player cannot be dropped', $output);
        $this->assertStringContainsString('ibl-alert--error', $output);
    }

    public function testRenderWaiverFormShowsSuccessAddBanner(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'add',
            [],
            5,
            5,
            'player_added'
        );

        $this->assertStringContainsString('ibl-alert--success', $output);
        $this->assertStringContainsString('Player successfully signed from waivers.', $output);
    }

    public function testRenderWaiverFormShowsSuccessDropBanner(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5,
            'player_dropped'
        );

        $this->assertStringContainsString('ibl-alert--success', $output);
        $this->assertStringContainsString('Player successfully dropped to waivers.', $output);
    }

    public function testRenderWaiverFormEscapesTeamName(): void
    {
        $output = $this->view->renderWaiverForm(
            '<script>alert("xss")</script>',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringNotContainsString('<script>', $output);
    }

    public function testRenderWaiverFormUsesDesignSystemClasses(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringContainsString('ibl-card', $output);
        $this->assertStringContainsString('ibl-card__header', $output);
        $this->assertStringContainsString('ibl-card__body', $output);
        $this->assertStringContainsString('ibl-select', $output);
        $this->assertStringContainsString('ibl-card__title', $output);
    }

    public function testRenderWaiverFormNoCustomWaiversClasses(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [],
            5,
            5
        );

        $this->assertStringNotContainsString('waivers-form-card', $output);
        $this->assertStringNotContainsString('waivers-form-header', $output);
        $this->assertStringNotContainsString('waivers-form-body', $output);
        $this->assertStringNotContainsString('waivers-select', $output);
        $this->assertStringNotContainsString('waivers-team-title', $output);
    }

    // ============================================
    // RENDER WAIVERS CLOSED TESTS
    // ============================================

    public function testRenderWaiversClosedReturnsString(): void
    {
        $output = $this->view->renderWaiversClosed();

        $this->assertIsString($output);
        $this->assertStringContainsString('waivers', strtolower($output));
    }

    public function testPlayerOptionsRenderAsOptionTags(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'add',
            [
                ['playerID' => 42, 'playerName' => 'Jane Doe', 'contract' => '103'],
                ['playerID' => 43, 'playerName' => 'John Roe', 'contract' => '205'],
            ],
            5,
            5
        );

        $this->assertStringContainsString('<option value="42">Jane Doe 103</option>', $output);
        // Numeric values only: excludes the empty-value "Select player..." placeholder.
        $this->assertSame(2, preg_match_all('/<option value="\d+">/', $output));
        $this->assertStringNotContainsString('&lt;option', $output);
    }

    public function testRenderWaiverFormRendersOneOptionPerPlayer(): void
    {
        $output = $this->view->renderWaiverForm(
            'Test Team',
            1,
            'waive',
            [
                ['playerID' => 1, 'playerName' => 'A', 'contract' => '100'],
                ['playerID' => 2, 'playerName' => 'B', 'contract' => '200'],
            ],
            5,
            5
        );

        $this->assertStringContainsString('<option value="1">A 100</option>', $output);
        $this->assertStringContainsString('<option value="2">B 200</option>', $output);
    }

    public function testRenderWaiverFormOmitsClientRosterSlotHiddenInputs(): void
    {
        $html = $this->view->renderWaiverForm('Test Team', 1, 'add', [], 5, 3);

        $this->assertStringContainsString('name="Action"', $html);
        $this->assertStringNotContainsString('name="rosterslots"', $html);
        $this->assertStringNotContainsString('name="healthyrosterslots"', $html);
    }

    public function testRenderWaiverFormHeaderShowsPassedSlotCounts(): void
    {
        $html = $this->view->renderWaiverForm('Test Team', 1, 'add', [], 5, 3);

        $this->assertStringContainsString('5 OPEN SPOTS / 3 HEALTHY SPOTS', $html);
    }

    public function testBuildPlayerOptionHasNoWaitTimeParameter(): void
    {
        $m = new \ReflectionMethod(\Waivers\WaiversView::class, 'buildPlayerOption');

        $this->assertSame(
            ['playerID', 'playerName', 'contract'],
            array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $m->getParameters())
        );
    }
}
