<?php

declare(strict_types=1);

namespace Tests\WideUnit\SimRecap;

use PHPUnit\Framework\TestCase;
use SimRecap\SimSummariesView;

/**
 * The View is a pure function of its arguments — no DB, no superglobals.
 */
final class SimSummariesViewTest extends TestCase
{
    private SimSummariesView $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new SimSummariesView();
    }

    /**
     * @return array<string, mixed>
     */
    private function indexRow(int $sim, ?int $length = 42): array
    {
        return [
            'sim'          => $sim,
            'status'       => $length === null ? 'pending' : 'done',
            'attempts'     => 1,
            'generated_at' => '2026-02-22 10:05:00',
            'created_at'   => '2026-02-22 09:00:00',
            'recap_length' => $length,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recapRow(?string $text, ?string $themes = null): array
    {
        return [
            'sim'           => 689,
            'status'        => $text === null ? 'pending' : 'done',
            'recap_text'    => $text,
            'themes_used'   => $themes,
            'attempts'      => 1,
            'claimed_at'    => null,
            'blocked_until' => null,
            'generated_at'  => '2026-03-01 10:05:00',
        ];
    }

    public function testRendersEveryRowInTheOrderGiven(): void
    {
        $html = $this->view->render(
            [$this->indexRow(689), $this->indexRow(688), $this->indexRow(687)],
            null,
            null
        );

        self::assertSame(4, substr_count($html, '<tr>'), 'One header row plus three data rows');
        $first  = strpos($html, 'sim=689');
        $second = strpos($html, 'sim=688');
        $third  = strpos($html, 'sim=687');
        self::assertIsInt($first);
        self::assertIsInt($second);
        self::assertIsInt($third);
        self::assertLessThan($second, $first, 'The View must not re-sort the rows it is given');
        self::assertLessThan($third, $second, 'The View must not re-sort the rows it is given');
    }

    public function testEscapesRecapTextInsteadOfEmittingIt(): void
    {
        $html = $this->view->render(
            [],
            $this->recapRow('</textarea><script>alert(1)</script>'),
            null
        );

        // The only <script> in the output is the View's own copy-button script.
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringNotContainsString('</textarea><script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testNeverRendersRawThemesJson(): void
    {
        $html = $this->view->render(
            [],
            $this->recapRow('Body.', '["<img src=x onerror=alert(1)>"]'),
            null
        );

        self::assertStringNotContainsString('onerror=alert(1)>', $html, 'Themes must be escaped, never raw');
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);

        $malformed = $this->view->render([], $this->recapRow('Body.', '"not-an-array"'), null);
        self::assertStringContainsString('Themes: —', $malformed);
        self::assertStringNotContainsString('not-an-array', $malformed);
    }

    public function testRendersTheEmptyState(): void
    {
        $html = $this->view->render([], null, null);

        self::assertStringContainsString('No sim recaps have been generated yet.', $html);
        self::assertStringNotContainsString('simSummaries.php?sim=', $html, 'No data rows means no sim links');
    }

    public function testOmitsTheDownloadLinkWhenRecapTextIsNull(): void
    {
        $html = $this->view->render([], $this->recapRow(null), null);

        self::assertStringNotContainsString('format=txt', $html);
        self::assertStringNotContainsString('<textarea', $html);
        self::assertStringContainsString('No recap text stored yet — status: pending.', $html);
    }

    public function testRendersTheErrorNotices(): void
    {
        self::assertStringContainsString('Invalid sim number.', $this->view->render([], null, 'malformed'));
        self::assertStringContainsString(
            'No recap stored for sim 999999.',
            $this->view->render([], null, 'notfound', 999999)
        );
    }

    public function testRendersTheIndexBodyLengthWithoutTheBody(): void
    {
        $html = $this->view->render([$this->indexRow(689, 42), $this->indexRow(688, null)], null, null);

        self::assertStringContainsString('42 bytes', $html);
        self::assertStringContainsString('<td>—</td>', $html);
    }
}
