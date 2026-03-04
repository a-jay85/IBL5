<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\UpdaterViewInterface;
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

    public function testImplementsUpdaterViewInterface(): void
    {
        $this->assertInstanceOf(UpdaterViewInterface::class, $this->view);
    }

    public function testRenderPageOpenReturnsHtmlDoctype(): void
    {
        $result = $this->view->renderPageOpen('/ibl5/themes/IBL/style/style.css');

        $this->assertStringContainsString('<!DOCTYPE html>', $result);
        $this->assertStringContainsString('<html lang="en">', $result);
        $this->assertStringContainsString('Update All The Things', $result);
        $this->assertStringContainsString('style.css', $result);
        $this->assertStringContainsString('updater__title', $result);
    }

    public function testRenderPageOpenEscapesStylesheetPath(): void
    {
        $result = $this->view->renderPageOpen('/path?a=1&b=2"onclick="alert(1)');

        // Double quotes are escaped so attribute injection is impossible
        $this->assertStringNotContainsString('"onclick="', $result);
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('&quot;', $result);
    }

    public function testRenderSectionOpenReturnsLabelledSection(): void
    {
        $result = $this->view->renderSectionOpen('Initialization');

        $this->assertStringContainsString('updater-section', $result);
        $this->assertStringContainsString('updater-section__label', $result);
        $this->assertStringContainsString('Initialization', $result);
        $this->assertStringContainsString('<section', $result);
    }

    public function testRenderSectionOpenEscapesXss(): void
    {
        $result = $this->view->renderSectionOpen('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderSectionCloseReturnsSectionTag(): void
    {
        $result = $this->view->renderSectionClose();

        $this->assertSame('</section>', $result);
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

    public function testRenderStepErrorShowsXAndErrorMessage(): void
    {
        $result = $this->view->renderStepError('Schedule', 'Connection refused');

        $this->assertStringContainsString('updater-step--error', $result);
        $this->assertStringContainsString('&#10007;', $result);
        $this->assertStringContainsString('Schedule', $result);
        $this->assertStringContainsString('updater-step__error', $result);
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

    public function testRenderLogShowsInlineContent(): void
    {
        $result = $this->view->renderLog("UPDATE ibl_schedule SET ...\nDone.");

        $this->assertStringContainsString('updater-log', $result);
        $this->assertStringNotContainsString('updater-log__body', $result);
        $this->assertStringNotContainsString('<details', $result);
    }

    public function testRenderLogReturnsEmptyStringForEmptyOutput(): void
    {
        $this->assertSame('', $this->view->renderLog(''));
        $this->assertSame('', $this->view->renderLog('   '));
    }

    public function testRenderLogRendersHtmlFromTrustedUpdaters(): void
    {
        $result = $this->view->renderLog('<p>Updating the ibl_schedule database table...</p>');

        $this->assertStringContainsString('<p>Updating the ibl_schedule database table...</p>', $result);
    }

    public function testRenderSummaryAllSuccess(): void
    {
        $result = $this->view->renderSummary(5, 0);

        $this->assertStringContainsString('updater-summary', $result);
        $this->assertStringContainsString('updater-summary__status--success', $result);
        $this->assertStringContainsString('5 steps completed', $result);
        $this->assertStringNotContainsString('updater-summary__status--error', $result);
    }

    public function testRenderSummarySingleStep(): void
    {
        $result = $this->view->renderSummary(1, 0);

        $this->assertStringContainsString('1 step completed', $result);
    }

    public function testRenderSummaryWithErrors(): void
    {
        $result = $this->view->renderSummary(3, 2);

        $this->assertStringContainsString('updater-summary__status--success', $result);
        $this->assertStringContainsString('3 succeeded', $result);
        $this->assertStringContainsString('updater-summary__status--error', $result);
        $this->assertStringContainsString('2 failed', $result);
    }

    public function testRenderInlineHtmlWrapsContent(): void
    {
        $result = $this->view->renderInlineHtml('<div class="ibl-card">Content</div>');

        $this->assertStringContainsString('updater-log', $result);
        $this->assertStringContainsString('<div class="ibl-card">Content</div>', $result);
    }

    public function testRenderInlineHtmlReturnsEmptyForBlank(): void
    {
        $this->assertSame('', $this->view->renderInlineHtml(''));
        $this->assertSame('', $this->view->renderInlineHtml('   '));
    }

    public function testRenderInlineHtmlPreservesTrustedHtml(): void
    {
        $html = '<div class="ibl-alert ibl-alert--success">.lge imported</div>';
        $result = $this->view->renderInlineHtml($html);

        $this->assertStringContainsString('.lge imported', $result);
        $this->assertStringContainsString('ibl-alert--success', $result);
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
