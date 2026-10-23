<?php

declare(strict_types=1);

namespace Tests\WideUnit\SimRecap;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SimRecap\RecapDocument;

/**
 * The expected strings here are transcribed from `bin/lib/sim-recap-exemplar.txt`,
 * which is the format contract — the date rules on its line 1, and the
 * blank-line counts between its blocks. They are written out literally rather than
 * rebuilt with str_repeat() so the test is an independent oracle, not a mirror of
 * the implementation.
 */
final class RecapDocumentTest extends TestCase
{
    /** Exemplar line 1 — 32 '=', the label, 41 '='. */
    private const FEB_26_RULE = '================================ Feb 26 =========================================';

    /** Exemplar line 129 — the same fixed counts, so a shorter label yields a shorter line. */
    private const MAR_1_RULE = '================================ Mar 1 =========================================';

    private const HOST = 'iblhoops.net';

    /** Box-score footer line for a `game()` fixture dated 2008-02-26 (game 1 of the day). */
    private const BOX_FEB_26 = '<https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=2008-02-26&game=1>';

    /** Box-score footer line for a `game()` fixture dated 2008-03-01. */
    private const BOX_MAR_1 = '<https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=2008-03-01&game=1>';

    /** Team-page URL prefix; the fixture's teams are 1 (visitor) and 2 (home). */
    private const TEAM_URL = 'https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=';

    /**
     * @return array<string, mixed>
     */
    private function game(string $date, string $text): array
    {
        return [
            'game_date'        => $date,
            'visitor_teamid'   => 1,
            'home_teamid'      => 2,
            'game_of_that_day' => 1,
            'sort_order'       => 0,
            'recap_text'       => $text,
        ];
    }

    /** A stored document carrying $n exemplar-shaped game headers. */
    private function storedDocumentWith(int $n): string
    {
        $blocks = [];
        for ($i = 1; $i <= $n; $i++) {
            $blocks[] = "**Team A {$i} @ Team B {$i}**\n<@1> · <@2>\n\nGame {$i} prose.";
        }

        return "Intro.\n\n" . implode("\n\n\n", $blocks) . "\n\nOutro.";
    }

    public function testAssemblesTheWholeDocumentInExemplarShape(): void
    {
        $doc = RecapDocument::assemble([
                $this->game('2008-02-26', "**A 100 @ B 114**\n<@1> · <@2>\n\nFirst game."),
                $this->game('2008-02-26', "**C 103 @ D 139**\n<@3> · <@4>\n\nSecond game."),
                $this->game('2008-03-01', "**E 111 @ F 120**\n<@5> · <@6>\n\nThird game."),
            ], self::HOST);

        $expected = self::FEB_26_RULE
            . "\n\n" . "**A 100 @ B 114**\n<@1> · <@2>\n\nFirst game."
            . "\n\n" . self::BOX_FEB_26
            . "\n" . '[A](<' . self::TEAM_URL . '1>)'
            . "\n" . '[B](<' . self::TEAM_URL . '2>)'
            . "\n\n" . "**C 103 @ D 139**\n<@3> · <@4>\n\nSecond game."
            . "\n\n" . self::BOX_FEB_26
            . "\n" . '[C](<' . self::TEAM_URL . '1>)'
            . "\n" . '[D](<' . self::TEAM_URL . '2>)'
            . "\n\n\n" . self::MAR_1_RULE
            . "\n\n" . "**E 111 @ F 120**\n<@5> · <@6>\n\nThird game."
            . "\n\n" . self::BOX_MAR_1
            . "\n" . '[E](<' . self::TEAM_URL . '1>)'
            . "\n" . '[F](<' . self::TEAM_URL . '2>)'
            . "\n";

        self::assertSame($expected, $doc);
    }

    /**
     * The whole point of the class: `recap_text` on the summary row is a ~220-char
     * teaser, and the document is only ever the sum of the games.
     */
    public function testDocumentIsNotTheSummaryTeaser(): void
    {
        $doc = RecapDocument::assemble([$this->game('2008-02-26', 'Only game.')], self::HOST);

        self::assertStringContainsString('Only game.', $doc);
    }

    /**
     * The generator writes header / mentions / prose on three consecutive lines; the
     * exemplar has a blank line between the mentions and the prose. Every recap stored
     * before that contract was tightened needs the repair on read.
     */
    public function testInsertsTheMissingBlankLineAfterTheMentionLine(): void
    {
        $doc = RecapDocument::assemble([$this->game('2008-02-26', "**A 120 @ B 117**\n<@395705560068259840> · <@669349908989345802>\nVancouver's frontcourt.")], self::HOST);

        self::assertStringContainsString(
            "**A 120 @ B 117**\n<@395705560068259840> · <@669349908989345802>\n\nVancouver's frontcourt.",
            $doc
        );
    }

    public function testLeavesAnAlreadyCorrectGameBlockAlone(): void
    {
        $block = "**A 120 @ B 117**\n<@1> · <@2>\n\nProse.";
        $doc   = RecapDocument::assemble([$this->game('2008-02-26', $block)], self::HOST);

        self::assertSame(
            self::FEB_26_RULE . "\n\n" . $block . "\n\n" . self::BOX_FEB_26
                . "\n[A](<" . self::TEAM_URL . "1>)\n[B](<" . self::TEAM_URL . "2>)\n",
            $doc
        );
    }

    /**
     * The repair keys on a line that is *nothing but* mentions, so a second line of
     * ordinary prose is never split away from the paragraph it belongs to.
     */
    public function testDoesNotSplitProseThatIsNotAMentionLine(): void
    {
        $block = "**A 120 @ B 117**\nWashington won by 36.\nAnd it wasn't close.";
        $doc   = RecapDocument::assemble([$this->game('2008-02-26', $block)], self::HOST);

        self::assertStringContainsString($block, $doc);
    }

    /**
     * A single-mention line still counts — a game where only one team has a GM on file
     * is stored the same way, minus the middle dot.
     */
    public function testRepairsASingleMentionLine(): void
    {
        $doc = RecapDocument::assemble([$this->game('2008-02-26', "**A 1 @ B 2**\n<@7>\nProse.")], self::HOST);

        self::assertStringContainsString("<@7>\n\nProse.", $doc);
    }

    /**
     * A summary row whose games never made it into `ibl_sim_game_recaps` must not
     * fatal. With no intro or outro in the document any more, there is nothing to print.
     */
    public function testReturnsAnEmptyStringWhenThereAreNoGames(): void
    {
        self::assertSame('', RecapDocument::assemble([], self::HOST));
    }

    public function testReturnsAnEmptyStringWhenThereIsNothingToAssemble(): void
    {
        self::assertSame('', RecapDocument::assemble([$this->game('2008-02-26', "  \n")], self::HOST));
    }

    public function testSkipsGameRowsWithNoText(): void
    {
        $doc = RecapDocument::assemble([
                $this->game('2008-02-26', ''),
                $this->game('2008-02-26', 'Real game.'),
            ], self::HOST);

        self::assertSame(self::FEB_26_RULE . "\n\nReal game.\n\n" . self::BOX_FEB_26 . "\n", $doc);
    }

    /**
     * A date the DB hands over that PHP cannot parse is printed verbatim: losing the
     * separator entirely would be a worse failure than an odd-looking label.
     */
    public function testPrintsAnUnparseableDateVerbatim(): void
    {
        $doc = RecapDocument::assemble([$this->game('not-a-date', 'Game.')], self::HOST);

        self::assertStringContainsString('================================ not-a-date =', $doc);
    }

    public function testOmitsTheRuleWhenTheDateIsMissing(): void
    {
        $doc = RecapDocument::assemble([$this->game('', 'Game.')], self::HOST);

        self::assertSame("Game.\n", $doc, 'no date means no rule and no box-score line');
    }

    /**
     * Sim-722 shape: game parts are multi-line bare prose with no mention line at
     * index 1. postableText() must return the stored text verbatim — assembling
     * mention-less parts would silently drop every GM's Discord tag.
     */
    public function testPostableTextReturnsStoredTextWhenPartsAreBareProse(): void
    {
        $stored = "Stored monolith including <@7> and <@12> tags.\n\nAll 56 games are here.";
        $game   = $this->game('2026-07-26', "The visitors came out strong.\nThey held the lead."); // line 1 is prose, not a mention

        $result = RecapDocument::postableText([$game], $stored, self::HOST);

        self::assertSame($stored, $result);
    }

    /**
     * Sim-721 shape: every part carries a mention line at index 1. Assembly is
     * preferred — the stored text is a ~220-char teaser, not the document.
     */
    public function testPostableTextReturnsAssembledDocumentWhenPartsAreWellFormed(): void
    {
        $game   = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\nGame prose.");
        $result = RecapDocument::postableText([$game], 'Short teaser.', self::HOST);

        self::assertSame(RecapDocument::assemble([$game], self::HOST), $result);
    }

    /**
     * Sim-725 shape. Seven parts survived the displayable filter and every one of them
     * is well formed, so gamesCarryTheirMentionLines() votes for assembly. The stored
     * document holds all 49 games. Assembling would post 7 and discard 42.
     */
    public function testPostableTextPrefersStoredTextWhenPartsAreShortOfTheStoredDocument(): void
    {
        $stored = $this->storedDocumentWith(49);
        $parts  = [];
        for ($i = 1; $i <= 7; $i++) {
            $parts[] = $this->game('2026-02-26', "**A {$i} @ B {$i}**\n<@1> · <@2>\nGame prose.");
        }

        self::assertSame($stored, RecapDocument::postableText($parts, $stored, self::HOST));
    }

    /**
     * A complete, well-formed sim: 49 parts against a 49-header stored document. Nothing
     * is missing and nothing is degraded, so the assembly wins. Without this the guard
     * could be tightened into always preferring stored text and no test would notice.
     */
    public function testPostableTextReturnsAssembledDocumentWhenEveryStoredGameIsPresent(): void
    {
        $stored = $this->storedDocumentWith(49);
        $parts  = [];
        for ($i = 1; $i <= 49; $i++) {
            $parts[] = $this->game('2026-02-26', "**A {$i} @ B {$i}**\n<@1> · <@2>\nGame prose.");
        }

        self::assertSame(
            RecapDocument::assemble($parts, self::HOST),
            RecapDocument::postableText($parts, $stored, self::HOST)
        );
    }

    /**
     * Boundary: one game short (48 parts vs 49 headers in the stored document).
     * The completeness guard fires and prefers the stored text.
     */
    public function testPostableTextPrefersStoredTextWhenOneGameIsMissing(): void
    {
        $stored = $this->storedDocumentWith(49);
        $parts  = [];
        for ($i = 1; $i <= 48; $i++) {
            $parts[] = $this->game('2026-02-26', "**A {$i} @ B {$i}**\n<@1> · <@2>\nGame prose.");
        }

        self::assertSame($stored, RecapDocument::postableText($parts, $stored, self::HOST));
    }

    public function testPostableTextCountsRenderedGamesNotRawRowsAgainstTheStoredDocument(): void
    {
        $stored = $this->storedDocumentWith(6);
        $parts  = [];
        for ($i = 1; $i <= 5; $i++) {
            $parts[] = $this->game('2026-02-26', "**A {$i} @ B {$i}**\n<@1> · <@2>\nGame prose.");
        }
        $parts[] = $this->game('2026-02-26', '');
        $parts[] = $this->game('2026-02-26', '   ');

        self::assertSame($stored, RecapDocument::postableText($parts, $stored, self::HOST));
    }

    public function testPostableTextTreatsThePinnedExemplarAsAnEightGameDocument(): void
    {
        $exemplar = file_get_contents(dirname(__DIR__, 4) . '/bin/lib/sim-recap-exemplar.txt');
        self::assertIsString($exemplar);

        $parts = [];
        for ($i = 1; $i <= 7; $i++) {
            $parts[] = $this->game('2026-02-26', "**A {$i} @ B {$i}**\n<@1> · <@2>\nGame prose.");
        }

        self::assertSame($exemplar, RecapDocument::postableText($parts, $exemplar, self::HOST));
    }

    /**
     * One game carries its mention line; the other is a single-line string with no
     * index-1 entry at all. gamesCarryTheirMentionLines() is all-or-nothing — the
     * mixed batch must fall back to the stored text.
     */
    public function testPostableTextIsAllOrNothingAcrossGames(): void
    {
        $wellFormed = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\nGame prose.");
        $bareProse  = $this->game('2026-02-26', 'Single-line prose.');

        $result = RecapDocument::postableText([$wellFormed, $bareProse], 'Stored.', self::HOST);

        self::assertSame('Stored.', $result);
    }

    /**
     * Zero game rows: the assembled document is either empty (no intro/outro) or
     * carries no game sections (intro+outro only). Both paths fall back to the stored
     * text — via the $document === '' guard when parts are absent entirely, and via
     * gamesCarryTheirMentionLines() returning false when intro+outro assembles but
     * the game check still fails.
     */
    public function testPostableTextReturnsStoredTextWhenNoGameParts(): void
    {
        // No games → assemble() returns '' → $document === '' guard fires.
        self::assertSame('Stored.', RecapDocument::postableText([], 'Stored.', self::HOST));
    }

    /**
     * Empty-string and whitespace-only game rows are skipped by gamesCarryTheirMentionLines().
     * The one remaining non-empty row carries a mention line, so assembly is preferred.
     */
    public function testPostableTextSkipsEmptyGameRowsWhenCheckingMentionLines(): void
    {
        $empty      = $this->game('2026-02-26', '');
        $whitespace = $this->game('2026-02-26', '   ');
        $wellFormed = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\nGame prose.");

        $result = RecapDocument::postableText([$empty, $whitespace, $wellFormed], 'Stored.', self::HOST);

        self::assertSame(RecapDocument::assemble([$empty, $whitespace, $wellFormed], self::HOST), $result);
    }

    /**
     * When stored text is empty, null, or whitespace, the assembled document is
     * returned regardless of whether parts carry mention lines — there is nothing
     * to fall back to.
     */
    public function testPostableTextReturnsAssembledDocumentWhenStoredTextIsEmpty(): void
    {
        $game      = $this->game('2026-02-26', 'Bare prose with no mention line.');
        $assembled = RecapDocument::assemble([$game], self::HOST);

        self::assertSame($assembled, RecapDocument::postableText([$game], null, self::HOST));
        self::assertSame($assembled, RecapDocument::postableText([$game], '', self::HOST));
        self::assertSame($assembled, RecapDocument::postableText([$game], '   ', self::HOST));
    }

    /**
     * A single mention at index 1 with no middle dot is well-formed — the regex
     * /^<@\d+>(?:\s*·\s*<@\d+>)*$/u matches a bare <@id> with zero repetitions.
     */
    public function testPostableTextCountsSingleMentionAsWellFormed(): void
    {
        $game   = $this->game('2026-02-26', "**A 100 @ B 114**\n<@7>\nGame prose.");
        $result = RecapDocument::postableText([$game], 'Stored.', self::HOST);

        self::assertSame(RecapDocument::assemble([$game], self::HOST), $result);
    }

    /**
     * A part already in the exemplar's four-line shape (header / mentions / blank /
     * prose) passes the mention-line check at index 1. This is the shape
     * normalizeGameBlock() produces; the regression guard ensures a round-trip is stable.
     */
    public function testPostableTextCountsAlreadyNormalizedPartAsWellFormed(): void
    {
        $game   = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\n\nGame prose.");
        $result = RecapDocument::postableText([$game], 'Stored.', self::HOST);

        self::assertSame(RecapDocument::assemble([$game], self::HOST), $result);
    }

    /**
     * When there are no parts, no intro, no outro, and no stored text (null, empty,
     * or whitespace), postableText() returns '' — Copy shows nothing rather than an
     * undefined value.
     */
    public function testPostableTextReturnsEmptyStringWhenThereIsNothingAtAll(): void
    {
        self::assertSame('', RecapDocument::postableText([], null, self::HOST));
        self::assertSame('', RecapDocument::postableText([], '', self::HOST));
        self::assertSame('', RecapDocument::postableText([], '   ', self::HOST));
    }

    /**
     * The completeness guard counts game headers by shape. If the shape is wrong the
     * count silently goes to zero and the guard goes inert, which is a failure mode the
     * guard itself cannot report. The pinned exemplar is the independent oracle.
     */
    public function testScoreHeaderPatternMatchesEveryGameInThePinnedExemplar(): void
    {
        $exemplar = file_get_contents(dirname(__DIR__, 4) . '/bin/lib/sim-recap-exemplar.txt');
        self::assertIsString($exemplar);

        $count = preg_match_all('/^\*\*.+\s\d+\s@\s.+\s\d+\*\*$/m', str_replace("\r\n", "\n", $exemplar));

        self::assertSame(8, $count, 'The pinned exemplar carries 8 game headers');
    }

    public function testAppendsLinksFooterAfterProseInExactLayout(): void
    {
        $game = [
            'game_date'        => '2008-02-26',
            'visitor_teamid'   => 7,
            'home_teamid'      => 12,
            'game_of_that_day' => 3,
            'recap_text'       => "**Celtics 100 @ Heat 114**\n<@111> · <@222>\n\nProse.",
        ];

        $expected = "**Celtics 100 @ Heat 114**\n"
            . "<@111> · <@222>\n"
            . "\n"
            . "Prose.\n"
            . "\n"
            . "<https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=2008-02-26&game=3>\n"
            . "[Celtics](<https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=7>)\n"
            . "[Heat](<https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=12>)";

        self::assertSame($expected, RecapDocument::gameBlock($game, self::HOST));
    }

    public function testScoreHeaderAndMentionLineCarryNoUrl(): void
    {
        $doc = RecapDocument::assemble([
            $this->game('2008-02-26', "**A 100 @ B 114**\n<@1> · <@2>\n\nFirst game."),
            $this->game('2008-02-26', "**C 103 @ D 139**\n<@3> · <@4>\n\nSecond game."),
        ], self::HOST);

        $headerLines = [];
        $lines       = explode("\n", $doc);
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, '**')) {
                $headerLines[] = [$line, $lines[$i + 1] ?? ''];
            }
        }

        self::assertCount(2, $headerLines);
        foreach ($headerLines as [$header, $mentions]) {
            self::assertMatchesRegularExpression('/^\*\*.+\s\d+\s@\s.+\s\d+\*\*$/', $header);
            foreach ([$header, $mentions] as $line) {
                self::assertStringNotContainsString('http', $line);
                self::assertStringNotContainsString('](', $line);
            }
        }
    }

    public function testAssembledDocumentOpensWithTheFirstDateRule(): void
    {
        $doc = RecapDocument::assemble([$this->game('2008-02-26', "**A 1 @ B 2**\n<@1>\n\nProse.")], self::HOST);

        self::assertStringStartsWith('================================ ', $doc);
    }

    /** With no game index, the box id gives the legacy `box{id}.htm` page, with the builder's `./` removed. */
    public function testBoxScoreLinkFallsBackToLegacyBoxPageWithoutDotSlash(): void
    {
        $game = ['game_of_that_day' => 0, 'box_id' => 55] + $this->game('2008-02-26', 'Only game.');

        self::assertSame(
            "Only game.\n\n<https://iblhoops.net/ibl5/ibl/IBL/box55.htm>",
            RecapDocument::gameBlock($game, self::HOST)
        );
    }

    public function testOmitsBoxScoreLineWhenNoGameIndexOrBoxId(): void
    {
        $game = ['game_of_that_day' => 0] + $this->game('2008-02-26', "**A 1 @ B 2**\n<@1>\n\nProse.");

        self::assertSame(
            "**A 1 @ B 2**\n<@1>\n\nProse.\n\n"
                . "[A](<https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=1>)\n"
                . "[B](<https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=2>)",
            RecapDocument::gameBlock($game, self::HOST)
        );
    }

    public function testOmitsTeamLinesWhenHeaderDoesNotParse(): void
    {
        self::assertSame(
            "Only game.\n\n" . self::BOX_FEB_26,
            RecapDocument::gameBlock($this->game('2008-02-26', 'Only game.'), self::HOST)
        );
    }

    public function testOmitsTeamLineForNonIntOrNonPositiveTeamId(): void
    {
        $game = ['visitor_teamid' => '7', 'home_teamid' => 0]
            + $this->game('2008-02-26', "**A 1 @ B 2**\n<@1>\n\nProse.");

        self::assertSame(
            "**A 1 @ B 2**\n<@1>\n\nProse.\n\n" . self::BOX_FEB_26,
            RecapDocument::gameBlock($game, self::HOST)
        );
    }

    public function testStripsMarkdownBracketsFromTeamLinkText(): void
    {
        $block = RecapDocument::gameBlock(
            $this->game('2008-02-26', "**Ce]lt<ics 1 @ He[at 2**\n<@1>\n\nProse."),
            self::HOST
        );

        self::assertStringEndsWith(
            "\n[Celtics](<https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=1>)"
                . "\n[Heat](<https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=2>)",
            $block
        );
    }

    public function testBoxScoreUrlEncodesTheDate(): void
    {
        $block = RecapDocument::gameBlock($this->game('2008-02-26&x=1', 'Only game.'), self::HOST);

        self::assertStringContainsString(
            "\n<https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=2008-02-26%26x%3D1&game=1>",
            $block
        );
    }

    #[DataProvider('badHostProvider')]
    public function testRejectsAHostThatIsNotABareHostname(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RecapDocument::assemble([$this->game('2008-02-26', 'Only game.')], $host);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badHostProvider(): array
    {
        return [
            'path'       => ['evil.com/x'],
            'userinfo'   => ['a@b.com'],
            'port'       => ['host:8080'],
            'whitespace' => ['two words'],
        ];
    }

    public function testEmptyHostFallsBackToProductionHost(): void
    {
        $game = $this->game('2008-02-26', "**A 1 @ B 2**\n<@1>\n\nProse.");

        self::assertSame(
            RecapDocument::gameBlock($game, RecapDocument::PRODUCTION_HOST),
            RecapDocument::gameBlock($game, '')
        );
        self::assertStringContainsString('<https://iblhoops.net/ibl5/', RecapDocument::gameBlock($game, ''));
    }

    public function testGameBlockReturnsEmptyStringForBlankText(): void
    {
        self::assertSame('', RecapDocument::gameBlock($this->game('2008-02-26', '   '), self::HOST));
    }

    private function exemplar(): string
    {
        $exemplar = file_get_contents(dirname(__DIR__, 4) . '/bin/lib/sim-recap-exemplar.txt');
        self::assertIsString($exemplar);

        return str_replace("\r\n", "\n", $exemplar);
    }

    public function testExemplarHasNoIntroOrOutro(): void
    {
        $exemplar = $this->exemplar();

        self::assertStringStartsWith(str_repeat('=', 32) . ' ', $exemplar);
        self::assertStringNotContainsString('WHERE THINGS STAND', $exemplar);
        self::assertStringNotContainsString('END —', $exemplar);
    }

    public function testExemplarPassesTheWikipediaLinkPolicy(): void
    {
        self::assertNull(\SimRecap\WikipediaLinkPolicy::violation($this->exemplar()));
    }

    public function testExemplarGameAssemblesWithLinksFooter(): void
    {
        $lines = explode("\n", $this->exemplar());
        $start = array_search('**Houston Rockets 103 @ Washington Bullets 139**', $lines, true);
        self::assertIsInt($start);

        $block = implode("\n", array_slice($lines, $start, 4));
        $game  = [
            'game_date'        => '2008-02-26',
            'visitor_teamid'   => 10,
            'home_teamid'      => 20,
            'game_of_that_day' => 1,
            'sort_order'       => 0,
            'recap_text'       => $block,
        ];

        $assembled = RecapDocument::gameBlock($game, self::HOST);

        self::assertStringEndsWith(
            "\n\n<https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date=2008-02-26&game=1>"
            . "\n[Houston Rockets](<https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=10>)"
            . "\n[Washington Bullets](<https://iblhoops.net/ibl5/modules.php?name=Team&op=team&teamid=20>)",
            $assembled
        );
        $firstTwo = implode("\n", array_slice(explode("\n", $assembled), 0, 2));
        self::assertStringNotContainsString('http', $firstTwo);
    }
}
