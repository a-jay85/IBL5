<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Updater\UpdaterView;

/**
 * Tests for UpdaterView rendering methods
 *
 * Verifies HTML structure, XSS safety, and correct state rendering
 * for each view method.
 */
class UpdaterViewTest extends TestCase
{
    private UpdaterView $view;

    protected function setUp(): void
    {
        $this->view = new UpdaterView();
    }

    public function testRenderPageOpenReturnsHtmlDoctype(): void
    {
        $result = $this->view->renderPageOpen('/ibl5/themes/IBL/style/style.css');

        $this->assertStringContainsString('<!DOCTYPE html>', $result);
        $this->assertStringContainsString('<html lang="en">', $result);
        $this->assertStringContainsString('Update All The Things', $result);
        $this->assertStringContainsString('style.css', $result);
        $this->assertStringContainsString('ibl-card', $result);
    }

    public function testRenderPageOpenEscapesStylesheetPath(): void
    {
        $result = $this->view->renderPageOpen('/path?a=1&b=2"onclick="alert(1)');

        // Double quotes are escaped so attribute injection is impossible
        $this->assertStringNotContainsString('"onclick="', $result);
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('&quot;', $result);
    }

    public function testRenderInitStatusReturnsCheckmark(): void
    {
        $result = $this->view->renderInitStatus('Season initialized');

        $this->assertStringContainsString('updater-init', $result);
        $this->assertStringContainsString('&#10003;', $result);
        $this->assertStringContainsString('Season initialized', $result);
    }

    public function testRenderInitStatusEscapesXss(): void
    {
        $result = $this->view->renderInitStatus('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderStepStartShowsSpinner(): void
    {
        $result = $this->view->renderStepStart('Updating schedule...');

        $this->assertStringContainsString('updater-step--running', $result);
        $this->assertStringContainsString('updater-step__spinner', $result);
        $this->assertStringContainsString('Updating schedule...', $result);
    }

    public function testRenderStepCompleteShowsCheckmark(): void
    {
        $result = $this->view->renderStepComplete('Schedule updated');

        $this->assertStringContainsString('updater-step--success', $result);
        $this->assertStringContainsString('&#10003;', $result);
        $this->assertStringContainsString('Schedule updated', $result);
    }

    public function testRenderStepCompleteWithDetail(): void
    {
        $result = $this->view->renderStepComplete('Depth charts updated', '3 active DCs extended');

        $this->assertStringContainsString('updater-step__detail', $result);
        $this->assertStringContainsString('3 active DCs extended', $result);
    }

    public function testRenderStepCompleteWithoutDetailOmitsDetailSpan(): void
    {
        $result = $this->view->renderStepComplete('Schedule updated');

        $this->assertStringNotContainsString('updater-step__detail', $result);
    }

    public function testRenderStepErrorShowsXAndAlert(): void
    {
        $result = $this->view->renderStepError('Schedule', 'Connection refused');

        $this->assertStringContainsString('updater-step--error', $result);
        $this->assertStringContainsString('&#10007;', $result);
        $this->assertStringContainsString('Schedule', $result);
        $this->assertStringContainsString('ibl-alert--error', $result);
        $this->assertStringContainsString('Connection refused', $result);
    }

    public function testRenderStepErrorEscapesXss(): void
    {
        $result = $this->view->renderStepError(
            '<img src=x onerror=alert(1)>',
            '<script>alert("xss")</script>'
        );

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderLogShowsCollapsibleTerminal(): void
    {
        $result = $this->view->renderLog("UPDATE ibl_schedule SET ...\nDone.");

        $this->assertStringContainsString('updater-log', $result);
        $this->assertStringContainsString('<details', $result);
        $this->assertStringContainsString('View log output', $result);
        $this->assertStringContainsString('updater-log__pre', $result);
    }

    public function testRenderLogReturnsEmptyStringForEmptyOutput(): void
    {
        $this->assertSame('', $this->view->renderLog(''));
        $this->assertSame('', $this->view->renderLog('   '));
    }

    public function testRenderLogEscapesHtmlInOutput(): void
    {
        $result = $this->view->renderLog('<b>bold</b> & "quotes"');

        $this->assertStringNotContainsString('<b>bold</b>', $result);
        $this->assertStringContainsString('&lt;b&gt;', $result);
    }

    public function testRenderSummaryAllSuccess(): void
    {
        $result = $this->view->renderSummary(5, 0);

        $this->assertStringContainsString('updater-summary', $result);
        $this->assertStringContainsString('ibl-badge--success', $result);
        $this->assertStringContainsString('5 steps completed', $result);
        $this->assertStringNotContainsString('ibl-badge--error', $result);
    }

    public function testRenderSummarySingleStep(): void
    {
        $result = $this->view->renderSummary(1, 0);

        $this->assertStringContainsString('1 step completed', $result);
    }

    public function testRenderSummaryWithErrors(): void
    {
        $result = $this->view->renderSummary(3, 2);

        $this->assertStringContainsString('ibl-badge--success', $result);
        $this->assertStringContainsString('3 succeeded', $result);
        $this->assertStringContainsString('ibl-badge--error', $result);
        $this->assertStringContainsString('2 failed', $result);
    }

    public function testRenderPageCloseReturnsReturnLink(): void
    {
        $result = $this->view->renderPageClose();

        $this->assertStringContainsString('/ibl5/index.php', $result);
        $this->assertStringContainsString('Return to IBL', $result);
        $this->assertStringContainsString('ibl-btn', $result);
        $this->assertStringContainsString('</body></html>', $result);
    }
}
