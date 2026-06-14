<?php

declare(strict_types=1);

namespace Tests\CapWhatIf;

use CapWhatIf\CapWhatIfView;
use CapWhatIf\Contracts\CapWhatIfViewInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see CapWhatIfView} HTML rendering — title/form/select structure,
 * output escaping, the GET (no-CSRF) decision, and the over-cap flag class.
 *
 * @covers \CapWhatIf\CapWhatIfView
 */
class CapWhatIfViewTest extends TestCase
{
    private CapWhatIfViewInterface $view;

    protected function setUp(): void
    {
        $this->view = new CapWhatIfView();
    }

    /**
     * @param array<string, bool> $overCap
     * @return array{baseline: array{spent: array<string,int>, space: array<string,int>}, scenario: array{spent: array<string,int>, space: array<string,int>}, overCap: array<string, bool>, waivedName: ?string, years: int, salary: int}
     */
    private function scenarioData(array $overCap = [], ?string $waivedName = null): array
    {
        $zeros = ['year1' => 0, 'year2' => 0, 'year3' => 0, 'year4' => 0, 'year5' => 0, 'year6' => 0];

        return [
            'baseline' => ['spent' => $zeros, 'space' => $zeros],
            'scenario' => ['spent' => $zeros, 'space' => $zeros],
            'overCap' => $overCap,
            'waivedName' => $waivedName,
            'years' => 1,
            'salary' => 0,
        ];
    }

    public function testRendersTitleFormAndOptionPerRosterPlayer(): void
    {
        $roster = [
            ['pid' => 10, 'name' => 'Alpha Guard'],
            ['pid' => 20, 'name' => 'Beta Forward'],
        ];

        $output = $this->view->render($this->scenarioData(), $roster, 2025, 2026);

        $this->assertStringContainsString('<h2 class="ibl-title">Cap Calculator</h2>', $output);
        $this->assertStringContainsString('<form method="get">', $output);
        $this->assertStringContainsString('value="10"', $output);
        $this->assertStringContainsString('Alpha Guard', $output);
        $this->assertStringContainsString('value="20"', $output);
        $this->assertStringContainsString('Beta Forward', $output);
        // One <option> per roster player, plus the leading "none" option.
        $this->assertSame(3, substr_count($output, '<option'));
    }

    public function testEscapesPlayerName(): void
    {
        $roster = [['pid' => 1, 'name' => '<script>x</script>']];

        $output = $this->view->render($this->scenarioData(), $roster, 2025, 2026);

        $this->assertStringNotContainsString('<script>x</script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testEmitsNoCsrfTokenField(): void
    {
        $output = $this->view->render($this->scenarioData(), [], 2025, 2026);

        $this->assertStringNotContainsString('_csrf_token', $output);
        $this->assertStringNotContainsString('csrf_token', $output);
    }

    public function testOverCapYearCarriesFlagClass(): void
    {
        $flagged = $this->view->render($this->scenarioData(['year1' => true]), [], 2025, 2026);
        $this->assertStringContainsString('class="ibl-stat-highlight"', $flagged);

        $clean = $this->view->render($this->scenarioData(['year1' => false]), [], 2025, 2026);
        $this->assertStringNotContainsString('class="ibl-stat-highlight"', $clean);
    }

    public function testRendersWaivedNameNoticeWhenPresent(): void
    {
        $output = $this->view->render($this->scenarioData([], 'Waived Player'), [], 2025, 2026);
        $this->assertStringContainsString('Waiving: Waived Player', $output);
    }
}
