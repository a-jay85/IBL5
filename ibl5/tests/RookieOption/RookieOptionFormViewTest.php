<?php

declare(strict_types=1);

namespace Tests\RookieOption;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Player\Player;
use RookieOption\RookieOptionFormView;

/**
 * Tests for RookieOptionFormView
 */
#[AllowMockObjectsWithoutExpectations]
class RookieOptionFormViewTest extends TestCase
{
    private RookieOptionFormView $view;

    protected function setUp(): void
    {
        $this->view = new RookieOptionFormView();
    }

    /**
     * Create a mock Player with the given properties.
     */
    private function createPlayerMock(int $playerID = 123, string $position = 'PG', string $name = 'Test Player'): Player
    {
        $mockPlayer = $this->createMock(Player::class);
        $mockPlayer->playerID = $playerID;
        $mockPlayer->position = $position;
        $mockPlayer->name = $name;

        return $mockPlayer;
    }

    /**
     * Test rendering form returns string with proper HTML escaping
     */
    public function testRenderFormReturnsStringWithEscapedHtml(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500);

        $this->assertIsString($output);
        $this->assertStringContainsString('PG Test Player', $output);
        $this->assertStringContainsString('500', $output);
        $this->assertStringContainsString('Test Team', $output);
        $this->assertStringContainsString('images/player/123.jpg', $output);
        $this->assertStringContainsString('name="teamname"', $output);
        $this->assertStringContainsString('name="playerID"', $output);
        $this->assertStringContainsString('name="rookieOptionValue"', $output);
    }

    /**
     * Test rendering form escapes potentially malicious HTML
     */
    public function testRenderFormEscapesMaliciousHtml(): void
    {
        $mockPlayer = $this->createPlayerMock(123, 'PG', '<script>alert("xss")</script>');

        $output = $this->view->renderForm($mockPlayer, '<script>bad</script>', 500);

        $this->assertStringNotContainsString('<script>alert("xss")</script>', $output);
        $this->assertStringNotContainsString('<script>bad</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    /**
     * Test form uses design system classes
     */
    public function testRenderFormUsesDesignSystemClasses(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500);

        $this->assertStringContainsString('ibl-card', $output);
        $this->assertStringContainsString('ibl-card__header', $output);
        $this->assertStringContainsString('ibl-card__title', $output);
        $this->assertStringContainsString('ibl-card__body', $output);
        $this->assertStringContainsString('ibl-btn ibl-btn--primary', $output);
    }

    /**
     * Test form does not contain deprecated HTML
     */
    public function testRenderFormHasNoDeprecatedHtml(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500);

        $this->assertStringNotContainsString('<b>', $output);
        $this->assertStringNotContainsString('<center>', $output);
        $this->assertStringNotContainsString('align=left', $output);
        $this->assertStringNotContainsString('rookieoption.php', $output);
    }

    /**
     * Test form action points to module switch handler
     */
    public function testRenderFormActionUsesModuleHandler(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500);

        $this->assertStringContainsString('processrookieoption', $output);
        $this->assertStringContainsString('modules.php', $output);
    }

    /**
     * Test form renders warning card
     */
    public function testRenderFormIncludesWarningCard(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500);

        $this->assertStringContainsString('ibl-alert ibl-alert--warning', $output);
        $this->assertStringContainsString('contract extension', $output);
        $this->assertStringContainsString('free agent', $output);
    }

    /**
     * Test error banner renders when error param is provided
     */
    public function testRenderFormShowsErrorBanner(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500, 'Something went wrong');

        $this->assertStringContainsString('ibl-alert ibl-alert--error', $output);
        $this->assertStringContainsString('Something went wrong', $output);
    }

    /**
     * Test error banner escapes HTML
     */
    public function testRenderFormErrorBannerEscapesHtml(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500, '<script>xss</script>');

        $this->assertStringNotContainsString('<script>xss</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    /**
     * Test success result banner renders
     */
    public function testRenderFormShowsSuccessBanner(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500, null, 'rookie_option_success');

        $this->assertStringContainsString('ibl-alert ibl-alert--success', $output);
        $this->assertStringContainsString('exercised successfully', $output);
    }

    /**
     * Test email failed result banner renders
     */
    public function testRenderFormShowsEmailFailedBanner(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500, null, 'email_failed');

        $this->assertStringContainsString('ibl-alert--warning', $output);
        $this->assertStringContainsString('notification email failed', $output);
    }

    /**
     * Test no banner renders when no error or result params
     */
    public function testRenderFormNoBannerWithoutParams(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500);

        $this->assertStringNotContainsString('ibl-alert--error', $output);
        $this->assertStringNotContainsString('ibl-alert--success', $output);
        // Note: ibl-alert--warning is expected (the exercise consequences warning card)
    }

    /**
     * Test unknown result param renders no banner
     */
    public function testRenderFormUnknownResultNoBanner(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500, null, 'unknown_result');

        $this->assertStringNotContainsString('ibl-alert--success', $output);
        // The warning alert for exercise consequences should still be present
        $this->assertStringContainsString('ibl-alert--warning', $output);
    }

    /**
     * Test form uses flex layout for player image
     */
    public function testRenderFormUsesFlexLayout(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500);

        $this->assertStringContainsString('display: flex', $output);
        $this->assertStringContainsString('border-radius: 0.375rem', $output);
    }

    /**
     * Test form includes hidden from field when origin is provided
     */
    public function testRenderFormIncludesFromField(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500, null, null, 'fa');

        $this->assertStringContainsString('name="from"', $output);
        $this->assertStringContainsString('value="fa"', $output);
    }

    /**
     * Test form includes empty from field when no origin provided
     */
    public function testRenderFormIncludesEmptyFromFieldWhenNull(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500);

        $this->assertStringContainsString('name="from"', $output);
    }

    /**
     * Test from field escapes HTML
     */
    public function testRenderFormFromFieldEscapesHtml(): void
    {
        $mockPlayer = $this->createPlayerMock();

        $output = $this->view->renderForm($mockPlayer, 'Test Team', 500, null, null, '<script>xss</script>');

        $this->assertStringNotContainsString('<script>xss</script>', $output);
        $this->assertStringContainsString('name="from"', $output);
    }
}
