<?php

declare(strict_types=1);

namespace Tests\WideUnit\SimRecap;

use PHPUnit\Framework\TestCase;
use SimRecap\RecapDocument;

/**
 * The expected strings here are transcribed from `bin/lib/sim-recap-exemplar.txt`,
 * which is the format contract — the date rules on its lines 15 and 129, and the
 * blank-line counts between its blocks. They are written out literally rather than
 * rebuilt with str_repeat() so the test is an independent oracle, not a mirror of
 * the implementation.
 */
final class RecapDocumentTest extends TestCase
{
    /** Exemplar line 15 — 32 '=', the label, 41 '='. */
    private const FEB_26_RULE = '================================ Feb 26 =========================================';

    /** Exemplar line 129 — the same fixed counts, so a shorter label yields a shorter line. */
    private const MAR_1_RULE = '================================ Mar 1 =========================================';

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
        $doc = RecapDocument::assemble(
            "IBL SIM RECAPS\n====\n\nWHERE THINGS STAND\n\nUtah is still Utah.\n====",
            [
                $this->game('2008-02-26', "**A 100 @ B 114**\n<@1> · <@2>\n\nFirst game."),
                $this->game('2008-02-26', "**C 103 @ D 139**\n<@3> · <@4>\n\nSecond game."),
                $this->game('2008-03-01', "**E 111 @ F 120**\n<@5> · <@6>\n\nThird game."),
            ],
            "====\nEND. 3 games."
        );

        $expected = "IBL SIM RECAPS\n====\n\nWHERE THINGS STAND\n\nUtah is still Utah.\n===="
            . "\n\n\n" . self::FEB_26_RULE
            . "\n\n" . "**A 100 @ B 114**\n<@1> · <@2>\n\nFirst game."
            . "\n\n" . "**C 103 @ D 139**\n<@3> · <@4>\n\nSecond game."
            . "\n\n\n" . self::MAR_1_RULE
            . "\n\n" . "**E 111 @ F 120**\n<@5> · <@6>\n\nThird game."
            . "\n\n\n" . "====\nEND. 3 games.\n";

        self::assertSame($expected, $doc);
    }

    /**
     * The whole point of the class: `recap_text` on the summary row is a ~220-char
     * teaser, and the document is only ever the sum of intro + games + outro.
     */
    public function testDocumentIsNotTheSummaryTeaser(): void
    {
        $doc = RecapDocument::assemble('Intro.', [$this->game('2008-02-26', 'Only game.')], 'Outro.');

        self::assertStringContainsString('Only game.', $doc);
        self::assertStringContainsString('Intro.', $doc);
        self::assertStringContainsString('Outro.', $doc);
    }

    /**
     * The generator writes header / mentions / prose on three consecutive lines; the
     * exemplar has a blank line between the mentions and the prose. Every recap stored
     * before that contract was tightened needs the repair on read.
     */
    public function testInsertsTheMissingBlankLineAfterTheMentionLine(): void
    {
        $doc = RecapDocument::assemble(
            null,
            [$this->game('2008-02-26', "**A 120 @ B 117**\n<@395705560068259840> · <@669349908989345802>\nVancouver's frontcourt.")],
            null
        );

        self::assertStringContainsString(
            "**A 120 @ B 117**\n<@395705560068259840> · <@669349908989345802>\n\nVancouver's frontcourt.",
            $doc
        );
    }

    public function testLeavesAnAlreadyCorrectGameBlockAlone(): void
    {
        $block = "**A 120 @ B 117**\n<@1> · <@2>\n\nProse.";
        $doc   = RecapDocument::assemble(null, [$this->game('2008-02-26', $block)], null);

        self::assertSame(self::FEB_26_RULE . "\n\n" . $block . "\n", $doc);
    }

    /**
     * The repair keys on a line that is *nothing but* mentions, so a second line of
     * ordinary prose is never split away from the paragraph it belongs to.
     */
    public function testDoesNotSplitProseThatIsNotAMentionLine(): void
    {
        $block = "**A 120 @ B 117**\nWashington won by 36.\nAnd it wasn't close.";
        $doc   = RecapDocument::assemble(null, [$this->game('2008-02-26', $block)], null);

        self::assertStringContainsString($block, $doc);
    }

    /**
     * A single-mention line still counts — a game where only one team has a GM on file
     * is stored the same way, minus the middle dot.
     */
    public function testRepairsASingleMentionLine(): void
    {
        $doc = RecapDocument::assemble(null, [$this->game('2008-02-26', "**A 1 @ B 2**\n<@7>\nProse.")], null);

        self::assertStringContainsString("<@7>\n\nProse.", $doc);
    }

    /**
     * A summary row whose games never made it into `ibl_sim_game_recaps` must not
     * silently produce a two-line "document" that looks like a complete recap — but it
     * must not fatal either. Intro and outro come through, joined like any two blocks.
     */
    public function testDegradesToIntroAndOutroWhenThereAreNoGames(): void
    {
        self::assertSame("Intro.\n\n\nOutro.\n", RecapDocument::assemble('Intro.', [], 'Outro.'));
    }

    public function testReturnsAnEmptyStringWhenThereIsNothingToAssemble(): void
    {
        self::assertSame('', RecapDocument::assemble(null, [], null));
        self::assertSame('', RecapDocument::assemble('  ', [], "\n"));
    }

    public function testSkipsGameRowsWithNoText(): void
    {
        $doc = RecapDocument::assemble(
            null,
            [
                $this->game('2008-02-26', ''),
                $this->game('2008-02-26', 'Real game.'),
            ],
            null
        );

        self::assertSame(self::FEB_26_RULE . "\n\nReal game.\n", $doc);
    }

    /**
     * A date the DB hands over that PHP cannot parse is printed verbatim: losing the
     * separator entirely would be a worse failure than an odd-looking label.
     */
    public function testPrintsAnUnparseableDateVerbatim(): void
    {
        $doc = RecapDocument::assemble(null, [$this->game('not-a-date', 'Game.')], null);

        self::assertStringContainsString('================================ not-a-date =', $doc);
    }

    public function testOmitsTheRuleWhenTheDateIsMissing(): void
    {
        $doc = RecapDocument::assemble(null, [$this->game('', 'Game.')], null);

        self::assertSame("Game.\n", $doc);
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

        $result = RecapDocument::postableText('Intro.', [$game], 'Outro.', $stored);

        self::assertSame($stored, $result);
    }

    /**
     * Sim-721 shape: every part carries a mention line at index 1. Assembly is
     * preferred — the stored text is a ~220-char teaser, not the document.
     */
    public function testPostableTextReturnsAssembledDocumentWhenPartsAreWellFormed(): void
    {
        $game   = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\nGame prose.");
        $result = RecapDocument::postableText('Intro.', [$game], 'Outro.', 'Short teaser.');

        self::assertSame(RecapDocument::assemble('Intro.', [$game], 'Outro.'), $result);
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

        self::assertSame($stored, RecapDocument::postableText('Intro.', $parts, 'Outro.', $stored));
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
            RecapDocument::assemble('Intro.', $parts, 'Outro.'),
            RecapDocument::postableText('Intro.', $parts, 'Outro.', $stored)
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

        self::assertSame($stored, RecapDocument::postableText('Intro.', $parts, 'Outro.', $stored));
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

        self::assertSame($stored, RecapDocument::postableText('Intro.', $parts, 'Outro.', $stored));
    }

    public function testPostableTextTreatsThePinnedExemplarAsA46GameDocument(): void
    {
        $exemplar = file_get_contents(dirname(__DIR__, 4) . '/bin/lib/sim-recap-exemplar.txt');
        self::assertIsString($exemplar);

        $parts = [];
        for ($i = 1; $i <= 45; $i++) {
            $parts[] = $this->game('2026-02-26', "**A {$i} @ B {$i}**\n<@1> · <@2>\nGame prose.");
        }

        self::assertSame($exemplar, RecapDocument::postableText('Intro.', $parts, 'Outro.', $exemplar));
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

        $result = RecapDocument::postableText('Intro.', [$wellFormed, $bareProse], 'Outro.', 'Stored.');

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
        // No intro, no outro, no games → assemble() returns '' → $document === '' guard fires.
        self::assertSame('Stored.', RecapDocument::postableText(null, [], null, 'Stored.'));

        // Intro + outro, no games → assemble() is non-empty but gamesCarryTheirMentionLines([]) is false.
        self::assertSame('Stored.', RecapDocument::postableText('Intro.', [], 'Outro.', 'Stored.'));
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

        $result = RecapDocument::postableText('Intro.', [$empty, $whitespace, $wellFormed], 'Outro.', 'Stored.');

        self::assertSame(RecapDocument::assemble('Intro.', [$empty, $whitespace, $wellFormed], 'Outro.'), $result);
    }

    /**
     * When stored text is empty, null, or whitespace, the assembled document is
     * returned regardless of whether parts carry mention lines — there is nothing
     * to fall back to.
     */
    public function testPostableTextReturnsAssembledDocumentWhenStoredTextIsEmpty(): void
    {
        $game      = $this->game('2026-02-26', 'Bare prose with no mention line.');
        $assembled = RecapDocument::assemble(null, [$game], null);

        self::assertSame($assembled, RecapDocument::postableText(null, [$game], null, null));
        self::assertSame($assembled, RecapDocument::postableText(null, [$game], null, ''));
        self::assertSame($assembled, RecapDocument::postableText(null, [$game], null, '   '));
    }

    /**
     * A single mention at index 1 with no middle dot is well-formed — the regex
     * /^<@\d+>(?:\s*·\s*<@\d+>)*$/u matches a bare <@id> with zero repetitions.
     */
    public function testPostableTextCountsSingleMentionAsWellFormed(): void
    {
        $game   = $this->game('2026-02-26', "**A 100 @ B 114**\n<@7>\nGame prose.");
        $result = RecapDocument::postableText('Intro.', [$game], 'Outro.', 'Stored.');

        self::assertSame(RecapDocument::assemble('Intro.', [$game], 'Outro.'), $result);
    }

    /**
     * A part already in the exemplar's four-line shape (header / mentions / blank /
     * prose) passes the mention-line check at index 1. This is the shape
     * normalizeGameBlock() produces; the regression guard ensures a round-trip is stable.
     */
    public function testPostableTextCountsAlreadyNormalizedPartAsWellFormed(): void
    {
        $game   = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\n\nGame prose.");
        $result = RecapDocument::postableText('Intro.', [$game], 'Outro.', 'Stored.');

        self::assertSame(RecapDocument::assemble('Intro.', [$game], 'Outro.'), $result);
    }

    /**
     * When there are no parts, no intro, no outro, and no stored text (null, empty,
     * or whitespace), postableText() returns '' — Copy shows nothing rather than an
     * undefined value.
     */
    public function testPostableTextReturnsEmptyStringWhenThereIsNothingAtAll(): void
    {
        self::assertSame('', RecapDocument::postableText(null, [], null, null));
        self::assertSame('', RecapDocument::postableText(null, [], null, ''));
        self::assertSame('', RecapDocument::postableText(null, [], null, '   '));
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

        self::assertSame(46, $count, 'The pinned exemplar carries 46 game headers');
    }
}
