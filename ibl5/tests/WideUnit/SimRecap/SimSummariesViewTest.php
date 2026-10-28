<?php

declare(strict_types=1);

namespace Tests\WideUnit\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * The fixed page chrome around every golden body. `$inner` starts at the blank line
     * that follows the h1 and ends just before the "Return to IBL" link.
     */
    private function goldenPage(string $inner): string
    {
        return "<!DOCTYPE html>\n"
            . "<html lang=\"en\">\n"
            . "<head>\n"
            . "    <meta charset=\"UTF-8\">\n"
            . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n"
            . "    <title>Sim Recaps</title>\n"
            . '    ' . \PageLayout\PageLayout::renderFontPreconnectLinks() . "\n"
            . "    <link rel=\"stylesheet\" href=\"/ibl5/themes/IBL/style/style.css\">\n"
            . "</head>\n"
            . "<body>\n"
            . "<div class=\"updater\">\n"
            . "    <h1 class=\"updater__title\">Sim Recaps</h1>\n"
            . "\n"
            . $inner
            . "    <a href=\"/ibl5/index.php\" class=\"updater__return underline\">Return to IBL</a>\n"
            . "</div>\n"
            . "</body>\n"
            . "</html>\n"
            . '        ';
    }

    /** The index table with `$rowsHtml` as its tbody content; ends after `</table>` and its newline. */
    private function goldenTable(string $rowsHtml): string
    {
        return "    <table class=\"ibl-data-table\">\n"
            . "        <thead>\n"
            . "            <tr>\n"
            . "                <th>Sim</th>\n"
            . "                <th>Status</th>\n"
            . "                <th>Attempts</th>\n"
            . "                <th>Generated</th>\n"
            . "                <th>Created</th>\n"
            . "                <th>Body</th>\n"
            . "            </tr>\n"
            . "        </thead>\n"
            . "        <tbody>\n"
            . $rowsHtml
            . "        </tbody>\n"
            . "    </table>\n";
    }

    private function goldenEmptyRows(): string
    {
        return "            <tr>\n"
            . "                <td colspan=\"6\">No sim recaps have been generated yet.</td>\n"
            . "            </tr>\n";
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

    public function testNeverRendersThemes(): void
    {
        $html = $this->view->render(
            [],
            $this->recapRow('Body.', '["comeback-theme-marker"]'),
            [],
            null
        );

        self::assertStringNotContainsString('Themes', $html);
        self::assertStringNotContainsString('comeback-theme-marker', $html);
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
        // 3 occurrences in the assembled document inside the textarea (intro + game + outro).
        self::assertSame(3, substr_count($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
    }

    /**
     * The recap prose appears once, inside the textarea. A second copy as page
     * paragraphs above it duplicated the whole recap on the page.
     */
    public function testRecapProseRendersOnlyInTheTextarea(): void
    {
        $html = $this->view->render(
            [],
            $this->recapRow('Body.', null, "Intro line one\nIntro line two", "Outro line one\nOutro line two"),
            [['game_date' => '2025-01-01', 'visitor_teamid' => 1, 'home_teamid' => 2, 'game_of_that_day' => 1, 'sort_order' => 0, 'recap_text' => "**A 1 @ B 2**\n<@1> · <@2>\nGame prose."]],
            null
        );

        self::assertSame(1, substr_count($html, 'Intro line one'));
        self::assertSame(1, substr_count($html, 'Game prose.'));
        self::assertSame(1, substr_count($html, 'Outro line one'));
        self::assertStringNotContainsString('id="recap-intro"', $html);
        self::assertStringNotContainsString('id="recap-games"', $html);
        self::assertStringNotContainsString('id="recap-outro"', $html);
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
            ['game_date' => '2025-01-01', 'visitor_teamid' => 1, 'home_teamid' => 2, 'game_of_that_day' => 1, 'sort_order' => 0, 'recap_text' => "**A 1 @ B 2**\n<@1> · <@2>\nFirst game recap."],
            ['game_date' => '2025-01-02', 'visitor_teamid' => 3, 'home_teamid' => 4, 'game_of_that_day' => 1, 'sort_order' => 1, 'recap_text' => "**A 1 @ B 2**\n<@3> · <@4>\nSecond game recap."],
            ['game_date' => '2025-01-03', 'visitor_teamid' => 5, 'home_teamid' => 6, 'game_of_that_day' => 1, 'sort_order' => 2, 'recap_text' => "**A 1 @ B 2**\n<@5> · <@6>\nThird game recap."],
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

    public function testGoldenEmptyIndexNoRecap(): void
    {
        $html = $this->view->render([], null, [], null);

        $expected = $this->goldenPage("\n" . $this->goldenTable($this->goldenEmptyRows()) . "\n\n");

        self::assertSame($expected, $html);
    }

    /**
     * @return array<string, array{0: string|null, 1: int|null, 2: string}>
     */
    public static function errorNoticeProvider(): array
    {
        return [
            'malformed' => ['malformed', null, 'Invalid sim number.'],
            'notfound with sim' => ['notfound', 12345, 'No recap stored for sim 12345.'],
            'notfound with null sim' => ['notfound', null, 'No recap stored for sim .'],
        ];
    }

    #[DataProvider('errorNoticeProvider')]
    public function testGoldenErrorNotice(string $error, ?int $requestedSim, string $message): void
    {
        $html = $this->view->render([], null, [], $error, $requestedSim);

        $expected = $this->goldenPage(
            '    <p class="ibl-card" id="recap-error">' . $message . "</p>\n"
            . "\n"
            . $this->goldenTable($this->goldenEmptyRows())
            . "\n\n"
        );

        self::assertSame($expected, $html);
    }

    public function testGoldenFullRecapWithEveryBranchAndHostilePayloads(): void
    {
        $rows = [
            [
                'sim'          => 689,
                'status'       => 'done<script>x</script>',
                'attempts'     => 2,
                'generated_at' => '2026-03-01 10:05:00',
                'created_at'   => '2026-03-01 09:00:00',
                'recap_length' => 1234,
            ],
            [
                'sim'          => 688,
                'status'       => 'pending',
                'attempts'     => 1,
                'generated_at' => null,
                'created_at'   => null,
                'recap_length' => null,
            ],
        ];
        $intro = "Intro <b>bold</b> & \"quoted\"";
        $outro = 'Outro </textarea><script>alert(1)</script>';
        $body  = "Teaser <script>alert('x')</script>";
        $recap = [
            'sim'          => 689,
            'status'       => 'done"><img src=x onerror=alert(1)>',
            'attempts'     => 2,
            'generated_at' => '2026-03-01 10:05:00',
            'themes_used'  => '["Trade <deadline>","O\'Brien\'s run"]',
            'intro_text'   => $intro,
            'outro_text'   => $outro,
            'recap_text'   => $body,
        ];
        $gameRecaps = [
            [
                'game_date'      => '2026-02-20',
                'visitor_teamid' => 5,
                'home_teamid'    => 12,
                'recap_text'     => 'Game one <@5> text & more',
            ],
            [
                'game_date'      => null,
                'visitor_teamid' => 'abc',
                'home_teamid'    => null,
                'recap_text'     => 'Game "two"',
            ],
        ];
        $orphanedGames = [
            [
                'game_date'        => '2026-02-21',
                'visitor_teamid'   => 3,
                'home_teamid'      => 9,
                'game_of_that_day' => 2,
            ],
            [
                'game_date'        => '<x>',
                'visitor_teamid'   => null,
                'home_teamid'      => 'h<',
                'game_of_that_day' => null,
            ],
        ];

        $html = $this->view->render($rows, $recap, $gameRecaps, null, null, $orphanedGames);

        $document = \SimRecap\RecapDocument::postableText($intro, $gameRecaps, $outro, $body);

        $indexRows = <<<'HTML'
            <tr>
                <td><a href="simSummaries.php?sim=689">689</a></td>
                <td>done&lt;script&gt;x&lt;/script&gt;</td>
                <td>2</td>
                <td>2026-03-01 10:05:00</td>
                <td>2026-03-01 09:00:00</td>
                <td>1234 bytes</td>
            </tr>
            <tr>
                <td><a href="simSummaries.php?sim=688">688</a></td>
                <td>pending</td>
                <td>1</td>
                <td>—</td>
                <td>—</td>
                <td>—</td>
            </tr>

HTML;
        $section = <<<'HTML'
    <section class="ibl-card" id="recap-panel">
        <h2 class="ibl-title">Sim 689</h2>
        <p>Status: done&quot;&gt;&lt;img src=x onerror=alert(1)&gt; · Attempts: 2 · Generated: 2026-03-01 10:05:00</p>
        <div id="recap-orphan-warning" class="ibl-card bg-yellow-50 border border-yellow-400 text-yellow-900">
            <p><strong>Warning:</strong> 2 games in this sim recap could not be matched to a box score and are omitted from the postable document. Unresolvable keys:</p>
            <ul>
            <li class="recap-orphan">2026-02-21 · team 3 at team 9 · game 2 of that day</li>
            <li class="recap-orphan">&lt;x&gt; · team  at team h&lt; · game  of that day</li>
            </ul>
        </div>
        <textarea id="recap-body" readonly rows="24" cols="100">{{DOC}}</textarea>
        <p>
            <button type="button" id="recap-copy">Copy</button>
            <span id="recap-copied" hidden>Copied</span>
            <a id="recap-download" href="simSummaries.php?sim=689&amp;format=txt">Download raw text</a>
        </p>
        <script>
            document.getElementById('recap-copy').addEventListener('click', function () {
                navigator.clipboard.writeText(document.getElementById('recap-body').value);
                document.getElementById('recap-copied').hidden = false;
            });
        </script>
    </section>

HTML;

        $expected = $this->goldenPage(
            "\n"
            . $this->goldenTable($indexRows)
            . "\n"
            . str_replace('{{DOC}}', \Security\HtmlSanitizer::e($document), $section)
            . "\n"
        );

        self::assertSame($expected, $html);
    }

    public function testGoldenMissingRecapTextWithSingleOrphan(): void
    {
        $orphan = [
            'game_date'        => '2026-02-21',
            'visitor_teamid'   => 3,
            'home_teamid'      => 9,
            'game_of_that_day' => 1,
        ];

        $html = $this->view->render([], $this->recapRow(null, '{"not":"a list"'), [], null, null, [$orphan]);

        $section = <<<'HTML'
    <section class="ibl-card" id="recap-panel">
        <h2 class="ibl-title">Sim 689</h2>
        <p>Status: pending · Attempts: 1 · Generated: 2026-03-01 10:05:00</p>
        <div id="recap-orphan-warning" class="ibl-card bg-yellow-50 border border-yellow-400 text-yellow-900">
            <p><strong>Warning:</strong> 1 game in this sim recap could not be matched to a box score and is omitted from the postable document. Unresolvable keys:</p>
            <ul>
            <li class="recap-orphan">2026-02-21 · team 3 at team 9 · game 1 of that day</li>
            </ul>
        </div>
        <p id="recap-missing">No recap text stored yet — status: pending.</p>
    </section>

HTML;

        $expected = $this->goldenPage(
            "\n"
            . $this->goldenTable($this->goldenEmptyRows())
            . "\n"
            . $section
            . "\n"
        );

        self::assertSame($expected, $html);
    }

    public function testViewHoldsNoStateAndTakesNoCollaborators(): void
    {
        $reflection = new \ReflectionClass(SimSummariesView::class);

        self::assertSame([], $reflection->getProperties(), 'A DB handle or session would have to live in a property');
        self::assertNull($reflection->getConstructor(), 'Nothing can be injected');
        self::assertSame(
            ['render'],
            array_map(
                static fn (\ReflectionMethod $m): string => $m->getName(),
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC)
            ),
            'render() is the only public entry point'
        );
    }
}
