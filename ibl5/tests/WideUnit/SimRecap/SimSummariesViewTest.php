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
            // Deliberately well-formed (score header + mention line), so postableText()
            // picks the assembled document. A bare-prose payload would route the textarea
            // to the stored teaser and leave the assembled path — the one that concatenates
            // every LLM field — untested by this, the only XSS test covering it.
            'recap_text'       => "**A 1 @ B 2**\n<@1> · <@2>\n" . $xss,
        ];
        $html = $this->view->render(
            [],
            $this->recapRow('Body.', null, $xss, $xss),
            [$gameRecap],
            null
        );

        // None of the LLM-sourced payloads may appear unescaped anywhere in the output.
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        // 3 occurrences in the per-field display panels (intro <p>, game <p>, outro <p>)
        // plus 3 more in the assembled document inside the textarea (intro + game + outro) = 6.
        self::assertSame(6, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
    }

    /**
     * The recap arrives as plain text whose newlines carry its shape (score header,
     * mention line, then prose). HTML collapses those newlines, so the three prose
     * paragraphs must opt into `white-space: pre-line`. Escaping is the reason this
     * is a CSS class and not nl2br(): `<br>` would have to be echoed unescaped, and
     * LLM prose never goes through HtmlSanitizer::trusted().
     */
    public function testProseParagraphsPreserveNewlines(): void
    {
        $html = $this->view->render(
            [],
            $this->recapRow('Body.', null, "Intro line one\nIntro line two", "Outro line one\nOutro line two"),
            [['game_date' => '2025-01-01', 'visitor_teamid' => 1, 'home_teamid' => 2, 'game_of_that_day' => 1, 'sort_order' => 0, 'recap_text' => "**A 1 @ B 2**\nGame prose."]],
            null
        );

        self::assertStringContainsString('<p id="recap-intro" class="whitespace-pre-line">', $html);
        self::assertStringContainsString('<p class="recap-game__text whitespace-pre-line">', $html);
        self::assertStringContainsString('<p id="recap-outro" class="whitespace-pre-line">', $html);
        // The newlines themselves must survive escaping — pre-line has nothing to act on otherwise.
        self::assertStringContainsString("Intro line one\nIntro line two", $html);
        self::assertStringContainsString("**A 1 @ B 2**\nGame prose.", $html);
        self::assertStringContainsString("Outro line one\nOutro line two", $html);
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

    /**
     * The textarea must carry the full assembled document (intro + date rules + game prose +
     * outro), not merely the ~220-char teaser stored in recap_text.
     */
    public function testTextareaCarriesAssembledDocumentNotTeaser(): void
    {
        $game1 = ['game_date' => '2008-02-26', 'visitor_teamid' => 1, 'home_teamid' => 2, 'game_of_that_day' => 1, 'sort_order' => 0, 'recap_text' => "**A 100 @ B 114**\n<@1> · <@2>\nFirst game prose here."];
        $game2 = ['game_date' => '2008-03-01', 'visitor_teamid' => 3, 'home_teamid' => 4, 'game_of_that_day' => 1, 'sort_order' => 1, 'recap_text' => "**C 90 @ D 110**\n<@3> · <@4>\nSecond game prose here."];
        $html  = $this->view->render(
            [],
            $this->recapRow('Teaser body.', null, 'Intro text.', 'Outro text.'),
            [$game1, $game2],
            null
        );

        $markerOpen  = '<textarea id="recap-body" readonly rows="24" cols="100">';
        $markerClose = '</textarea>';
        $startOffset = strpos($html, $markerOpen);
        self::assertIsInt($startOffset);
        $bodyStart    = $startOffset + strlen($markerOpen);
        $bodyEnd      = strpos($html, $markerClose, $bodyStart);
        self::assertIsInt($bodyEnd);
        $textareaBody = substr($html, $bodyStart, $bodyEnd - $bodyStart);

        // The assembled document is present: game prose and the date separator rule.
        self::assertStringContainsString('First game prose here.', $textareaBody);
        self::assertStringContainsString('================================ Feb 26 =', $textareaBody);
        // The textarea holds more than the teaser alone.
        self::assertNotSame('Teaser body.', $textareaBody);
    }

    /**
     * When there is nothing to assemble (no intro, no games, no outro), the textarea
     * must fall back to the teaser so Copy is never empty.
     */
    public function testTextareaFallsBackToTeaserWhenAssemblyIsEmpty(): void
    {
        $html = $this->view->render(
            [],
            $this->recapRow('Fallback teaser.'),
            [],
            null
        );

        $markerOpen  = '<textarea id="recap-body" readonly rows="24" cols="100">';
        $markerClose = '</textarea>';
        $startOffset = strpos($html, $markerOpen);
        self::assertIsInt($startOffset);
        $bodyStart    = $startOffset + strlen($markerOpen);
        $bodyEnd      = strpos($html, $markerClose, $bodyStart);
        self::assertIsInt($bodyEnd);
        $textareaBody = substr($html, $bodyStart, $bodyEnd - $bodyStart);

        self::assertStringContainsString('Fallback teaser.', $textareaBody);
    }

    private function textareaBody(string $html): string
    {
        $markerOpen  = '<textarea id="recap-body" readonly rows="24" cols="100">';
        $markerClose = '</textarea>';
        $startOffset = strpos($html, $markerOpen);
        self::assertIsInt($startOffset, 'Expected recap-body textarea in rendered output');
        $bodyStart = $startOffset + strlen($markerOpen);
        $bodyEnd   = strpos($html, $markerClose, $bodyStart);
        self::assertIsInt($bodyEnd, 'Expected closing </textarea> after recap-body');
        return substr($html, $bodyStart, $bodyEnd - $bodyStart);
    }

    /**
     * Sim-722 shape: per-game parts are bare prose with no mention line at index 1.
     * postableText() falls back to the stored monolith so no GM Discord tag is dropped.
     * HtmlSanitizer::e() escapes the '<@' markers: '<@5>' → '&lt;@5&gt;'.
     */
    public function testTextareaShowsStoredMonolithWhenPartsAreBareProse(): void
    {
        $game = [
            'game_date'        => '2026-07-26',
            'visitor_teamid'   => 5,
            'home_teamid'      => 12,
            'game_of_that_day' => 1,
            'sort_order'       => 0,
            'recap_text'       => "The visiting team came out strong.\nThey held the lead all night.",
        ];
        $stored = "Full monolith with <@5> and <@12> mention tags stored in recap_text.";
        $html = $this->view->render(
            [],
            $this->recapRow($stored, null, 'Intro text.', 'Outro text.'),
            [$game],
            null
        );
        $body = $this->textareaBody($html);
        // The stored text is used verbatim; the escaped '<@' markers must appear.
        self::assertStringContainsString('&lt;@5&gt;', $body);
        // No date rule — the stored monolith is not re-assembled.
        self::assertStringNotContainsString('================================', $body);
    }

    /**
     * Sim-721 shape: every per-game part carries a mention line at index 1.
     * postableText() uses the assembled document; the date rule and escaped GM tags
     * must reach the textarea — this is the 722-fix regression guard.
     */
    public function testTextareaShowsAssembledDocumentWhenPartsAreWellFormed(): void
    {
        $game = [
            'game_date'        => '2026-07-21',
            'visitor_teamid'   => 3,
            'home_teamid'      => 7,
            'game_of_that_day' => 1,
            'sort_order'       => 0,
            'recap_text'       => "**Away 110 @ Home 104**\n<@3> · <@7>\nGame prose goes here.",
        ];
        $html = $this->view->render(
            [],
            $this->recapRow('Short teaser.', null, 'Intro text.', 'Outro text.'),
            [$game],
            null
        );
        $body = $this->textareaBody($html);
        // Assembled document: date rule present.
        self::assertStringContainsString('================================ Jul 21 =', $body);
        // GM mention tags survive into the textarea (escaped by HtmlSanitizer::e()).
        self::assertStringContainsString('&lt;@3&gt;', $body);
        // The short teaser is not the textarea content.
        self::assertNotSame('Short teaser.', $body);
    }

    public function testRendersOrphanWarningWhenGamesAreUnmatched(): void
    {
        $orphan1 = ['game_date' => '2026-03-11', 'visitor_teamid' => 5, 'home_teamid' => 3, 'game_of_that_day' => 0, 'sort_order' => 1, 'box_id' => null];
        $orphan2 = ['game_date' => '2026-03-12', 'visitor_teamid' => 7, 'home_teamid' => 9, 'game_of_that_day' => 1, 'sort_order' => 2, 'box_id' => null];
        $html = $this->view->render([], $this->recapRow('Body.'), [], null, null, [$orphan1, $orphan2]);

        self::assertStringContainsString('id="recap-orphan-warning"', $html);
        self::assertStringContainsString('2 games', $html);
        self::assertSame(2, substr_count($html, 'class="recap-orphan"'));
        self::assertStringContainsString('2026-03-11', $html);
        self::assertStringContainsString('2026-03-12', $html);
        self::assertStringContainsString('team 5', $html);
        self::assertStringContainsString('team 3', $html);
    }

    public function testDoesNotRenderOrphanWarningWhenNoGamesAreOrphaned(): void
    {
        $htmlEmpty = $this->view->render([], $this->recapRow('Body.'), [], null, null, []);
        self::assertStringNotContainsString('recap-orphan-warning', $htmlEmpty);

        $htmlFiveArgs = $this->view->render([], $this->recapRow('Body.'), [], null, null);
        self::assertStringNotContainsString('recap-orphan-warning', $htmlFiveArgs);
    }

    public function testEscapesOrphanKeyValues(): void
    {
        $orphan = [
            'game_date'        => '<script>alert(1)</script>',
            'visitor_teamid'   => '"><img src=x onerror=alert(1)>',
            'home_teamid'      => 2,
            'game_of_that_day' => 0,
            'sort_order'       => 0,
            'box_id'           => null,
        ];
        $html = $this->view->render([], $this->recapRow('Body.'), [], null, null, [$orphan]);

        self::assertStringContainsString('&lt;script&gt;', $html, 'game_date script tag must be escaped');
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&quot;&gt;&lt;img', $html, 'visitor_teamid breakout payload must be escaped');
        self::assertStringNotContainsString('onerror=alert(1)>', $html);
    }

    public function testRendersSingularCopyForASingleOrphan(): void
    {
        $orphan = ['game_date' => '2026-03-11', 'visitor_teamid' => 5, 'home_teamid' => 3, 'game_of_that_day' => 0, 'sort_order' => 0, 'box_id' => null];
        $html = $this->view->render([], $this->recapRow('Body.'), [], null, null, [$orphan]);

        self::assertStringContainsString('1 game', $html, 'Single orphan must render "1 game"');
        self::assertStringNotContainsString('1 games', $html, 'Must not render "1 games"');
        self::assertStringContainsString('is omitted', $html, 'Single orphan must render "is omitted"');
        self::assertStringNotContainsString('are omitted', $html);
    }

    public function testToleratesAnOrphanRowWithMissingKeyColumns(): void
    {
        $orphan = ['visitor_teamid' => 5, 'home_teamid' => null, 'game_of_that_day' => 0, 'sort_order' => 0, 'box_id' => null];
        $html = $this->view->render([], $this->recapRow('Body.'), [], null, null, [$orphan]);

        self::assertStringContainsString('class="recap-orphan"', $html, 'The orphan list item must still render');
    }
}
