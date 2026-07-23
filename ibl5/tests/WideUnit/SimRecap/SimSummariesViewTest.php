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
    private function recapRow(?string $text, ?string $themes = null, ?string $introText = null, ?string $outroText = null): array
    {
        return [
            'sim'           => 689,
            'status'        => $text === null ? 'pending' : 'done',
            'recap_text'    => $text,
            'themes_used'   => $themes,
            'intro_text'    => $introText,
            'outro_text'    => $outroText,
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
            [],
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
            [],
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
            [],
            null
        );

        self::assertStringNotContainsString('onerror=alert(1)>', $html, 'Themes must be escaped, never raw');
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);

        $malformed = $this->view->render([], $this->recapRow('Body.', '"not-an-array"'), [], null);
        self::assertStringContainsString('Themes: —', $malformed);
        self::assertStringNotContainsString('not-an-array', $malformed);
    }

    public function testRendersTheEmptyState(): void
    {
        $html = $this->view->render([], null, [], null);

        self::assertStringContainsString('No sim recaps have been generated yet.', $html);
        self::assertStringNotContainsString('simSummaries.php?sim=', $html, 'No data rows means no sim links');
    }

    public function testOmitsTheDownloadLinkWhenRecapTextIsNull(): void
    {
        $html = $this->view->render([], $this->recapRow(null), [], null);

        self::assertStringNotContainsString('format=txt', $html);
        self::assertStringNotContainsString('<textarea', $html);
        self::assertStringContainsString('No recap text stored yet — status: pending.', $html);
    }

    public function testRendersTheErrorNotices(): void
    {
        self::assertStringContainsString('Invalid sim number.', $this->view->render([], null, [], 'malformed'));
        self::assertStringContainsString(
            'No recap stored for sim 999999.',
            $this->view->render([], null, [], 'notfound', 999999)
        );
    }

    public function testRendersTheIndexBodyLengthWithoutTheBody(): void
    {
        $html = $this->view->render([$this->indexRow(689, 42), $this->indexRow(688, null)], null, [], null);

        self::assertStringContainsString('42 bytes', $html);
        self::assertStringContainsString('<td>—</td>', $html);
    }

    public function testEscapesAllLlmFieldsInsteadOfEmittingThem(): void
    {
        $xss = '<script>alert(1)</script>';
        $gameRecap = [
            'game_date'        => '2025-01-01',
            'visitor_teamid'   => 1,
            'home_teamid'      => 2,
            'game_of_that_day' => 1,
            'sort_order'       => 0,
            'recap_text'       => $xss,
        ];
        $html = $this->view->render(
            [],
            $this->recapRow('Body.', null, $xss, $xss),
            [$gameRecap],
            null
        );

        // None of the LLM-sourced payloads may appear unescaped anywhere in the output.
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        // All three occurrences (intro, game recap, outro) must be entity-encoded.
        self::assertSame(3, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
    }

    public function testOmitsPerGameListWhenRecapTextIsNull(): void
    {
        // When a row has no recap_text the caller passes [] for $gameRecaps;
        // the view must not render the game list regardless.
        $html = $this->view->render([], $this->recapRow(null), [], null);

        self::assertStringNotContainsString('id="recap-games"', $html);
        self::assertStringNotContainsString('<ol', $html);
    }

    public function testRendersPerGameRecapsInTheOrderGiven(): void
    {
        $recaps = [
            ['game_date' => '2025-01-01', 'visitor_teamid' => 1, 'home_teamid' => 2, 'game_of_that_day' => 1, 'sort_order' => 0, 'recap_text' => 'First game recap.'],
            ['game_date' => '2025-01-02', 'visitor_teamid' => 3, 'home_teamid' => 4, 'game_of_that_day' => 1, 'sort_order' => 1, 'recap_text' => 'Second game recap.'],
            ['game_date' => '2025-01-03', 'visitor_teamid' => 5, 'home_teamid' => 6, 'game_of_that_day' => 1, 'sort_order' => 2, 'recap_text' => 'Third game recap.'],
        ];
        $html = $this->view->render([], $this->recapRow('Body.'), $recaps, null);

        $firstPos  = strpos($html, 'First game recap.');
        $secondPos = strpos($html, 'Second game recap.');
        $thirdPos  = strpos($html, 'Third game recap.');
        self::assertIsInt($firstPos);
        self::assertIsInt($secondPos);
        self::assertIsInt($thirdPos);
        self::assertLessThan($secondPos, $firstPos, 'Game recaps must appear in the order the caller provides');
        self::assertLessThan($thirdPos, $secondPos, 'Game recaps must appear in the order the caller provides');
    }
}
