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
            "IBL SIM RECAPS\n====",
            [
                $this->game('2008-02-26', "**A 100 @ B 114**\n<@1> · <@2>\n\nFirst game."),
                $this->game('2008-02-26', "**C 103 @ D 139**\n<@3> · <@4>\n\nSecond game."),
                $this->game('2008-03-01', "**E 111 @ F 120**\n<@5> · <@6>\n\nThird game."),
            ],
            "====\n\nWHERE THINGS STAND\n\nUtah is still Utah.\n\n====\nEND. 3 games."
        );

        $expected = "IBL SIM RECAPS\n===="
            . "\n\n\n" . self::FEB_26_RULE
            . "\n\n" . "**A 100 @ B 114**\n<@1> · <@2>\n\nFirst game."
            . "\n\n" . "**C 103 @ D 139**\n<@3> · <@4>\n\nSecond game."
            . "\n\n\n" . self::MAR_1_RULE
            . "\n\n" . "**E 111 @ F 120**\n<@5> · <@6>\n\nThird game."
            . "\n\n\n" . "====\n\nWHERE THINGS STAND\n\nUtah is still Utah.\n\n====\nEND. 3 games.\n";

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
     * The game parts as they come out of storage, one per game, every one well formed.
     *
     * @return list<array<string, mixed>>
     */
    private function wellFormedGames(int $n): array
    {
        $parts = [];
        for ($i = 1; $i <= $n; $i++) {
            $parts[] = $this->game('2026-02-26', "**A {$i} @ B {$i}**\n<@1> · <@2>\nGame {$i} prose.");
        }

        return $parts;
    }

    /** The outro is the last block: nothing but the trailing newline follows it. */
    private static function assertEndsWithOutro(string $outro, string $document): void
    {
        self::assertStringEndsWith("\n\n\n" . $outro . "\n", $document);
    }

    /**
     * Sim-722 shape: game parts are bare prose with no mention line. They still go in
     * the box; the page warns about the missing tags (gamesMissingMentionLines()).
     * The stored text is never swapped in.
     */
    public function testPostableTextKeepsBareProseGamesInTheDocument(): void
    {
        $stored = "Stored monolith including <@7> and <@12> tags.\n\nAll 56 games are here.";
        $game   = $this->game('2026-07-26', "The visitors came out strong.\nThey held the lead.");

        $result = RecapDocument::postableText('Intro.', [$game], 'Outro.', $stored);

        self::assertSame(RecapDocument::assemble('Intro.', [$game], 'Outro.'), $result);
        self::assertStringContainsString('The visitors came out strong.', $result);
        self::assertStringNotContainsString('Stored monolith', $result);
        self::assertEndsWithOutro('Outro.', $result);
    }

    public function testPostableTextReturnsAssembledDocumentWhenPartsAreWellFormed(): void
    {
        $game   = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\nGame prose.");
        $result = RecapDocument::postableText('Intro.', [$game], 'Outro.', 'Short teaser.');

        self::assertSame(RecapDocument::assemble('Intro.', [$game], 'Outro.'), $result);
    }

    /**
     * Sim-725 shape at the document level: the stored text names 49 games and the
     * parts hold 49. Every game is in the document, the outro is last, and the stored
     * text is not used. Before the owner decision, a header count above the part count
     * swapped the whole document for the stored text.
     */
    public function testPostableTextPutsEveryGameInTheDocumentWithTheOutroLast(): void
    {
        $parts  = $this->wellFormedGames(49);
        $result = RecapDocument::postableText('Intro.', $parts, 'WHERE THINGS STAND outro.', $this->storedDocumentWith(60));

        for ($i = 1; $i <= 49; $i++) {
            self::assertStringContainsString("**A {$i} @ B {$i}**", $result);
        }
        self::assertStringStartsWith('Intro.', $result);
        self::assertEndsWithOutro('WHERE THINGS STAND outro.', $result);
    }

    /**
     * One game short of the stored document used to fall back to the stored text. Now
     * the assembled document is returned.
     */
    public function testPostableTextIgnoresAShortfallAgainstTheStoredDocument(): void
    {
        $stored = $this->storedDocumentWith(49);
        $parts  = $this->wellFormedGames(48);

        self::assertSame(
            RecapDocument::assemble('Intro.', $parts, 'Outro.'),
            RecapDocument::postableText('Intro.', $parts, 'Outro.', $stored)
        );
    }

    public function testPostableTextIgnoresThePinnedExemplarAsStoredText(): void
    {
        $exemplar = file_get_contents(dirname(__DIR__, 4) . '/bin/lib/sim-recap-exemplar.txt');
        self::assertIsString($exemplar);

        $parts = $this->wellFormedGames(7);

        self::assertSame(
            RecapDocument::assemble('Intro.', $parts, 'Outro.'),
            RecapDocument::postableText('Intro.', $parts, 'Outro.', $exemplar)
        );
    }

    /**
     * One game carries its mention line and one does not. Both go in the document,
     * in order, with the outro last.
     */
    public function testPostableTextKeepsAGameMissingItsMentionLine(): void
    {
        $wellFormed = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\nGame prose.");
        $untagged   = $this->game('2026-02-26', "**C 90 @ D 88**\nNo mention line here.");

        $result = RecapDocument::postableText('Intro.', [$wellFormed, $untagged], 'Outro.', 'Stored.');

        self::assertSame(RecapDocument::assemble('Intro.', [$wellFormed, $untagged], 'Outro.'), $result);
        self::assertStringContainsString('**C 90 @ D 88**', $result);
        self::assertLessThan(strpos($result, '**C 90 @ D 88**'), strpos($result, '**A 100 @ B 114**'));
        self::assertEndsWithOutro('Outro.', $result);
    }

    /**
     * Legacy rows: no intro, no game rows, no outro. The stored text is the only
     * thing there is, so it is returned verbatim.
     */
    public function testPostableTextShowsTheStoredTeaserForALegacyRow(): void
    {
        self::assertSame('Stored.', RecapDocument::postableText(null, [], null, 'Stored.'));
        self::assertSame("  Teaser.\n", RecapDocument::postableText(null, [], null, "  Teaser.\n"));
        // Rows whose text is empty count as no rows.
        self::assertSame('Stored.', RecapDocument::postableText(null, [$this->game('2026-02-26', '  ')], null, 'Stored.'));
    }

    /**
     * Intro and outro but no game rows: the assembled document is returned. The stored
     * text is only for rows with no parts at all.
     */
    public function testPostableTextPrefersIntroAndOutroOverTheStoredTeaser(): void
    {
        self::assertSame(
            RecapDocument::assemble('Intro.', [], 'Outro.'),
            RecapDocument::postableText('Intro.', [], 'Outro.', 'Stored.')
        );
    }

    public function testPostableTextSkipsEmptyGameRows(): void
    {
        $empty      = $this->game('2026-02-26', '');
        $whitespace = $this->game('2026-02-26', '   ');
        $wellFormed = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\nGame prose.");

        $result = RecapDocument::postableText('Intro.', [$empty, $whitespace, $wellFormed], 'Outro.', 'Stored.');

        self::assertSame(RecapDocument::assemble('Intro.', [$empty, $whitespace, $wellFormed], 'Outro.'), $result);
    }

    public function testPostableTextReturnsAssembledDocumentWhenStoredTextIsEmpty(): void
    {
        $game      = $this->game('2026-02-26', 'Bare prose with no mention line.');
        $assembled = RecapDocument::assemble(null, [$game], null);

        self::assertSame($assembled, RecapDocument::postableText(null, [$game], null, null));
        self::assertSame($assembled, RecapDocument::postableText(null, [$game], null, ''));
        self::assertSame($assembled, RecapDocument::postableText(null, [$game], null, '   '));
    }

    /**
     * When there are no parts and no stored text (null, empty, or whitespace),
     * postableText() returns '' so Copy shows nothing rather than an undefined value.
     */
    public function testPostableTextReturnsEmptyStringWhenThereIsNothingAtAll(): void
    {
        self::assertSame('', RecapDocument::postableText(null, [], null, null));
        self::assertSame('', RecapDocument::postableText(null, [], null, ''));
        self::assertSame('', RecapDocument::postableText(null, [], null, '   '));
    }

    public function testListsNoGamesWhenEveryGameCarriesItsMentionLine(): void
    {
        $games = [
            $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\nGame prose."),
            $this->game('2026-02-26', "**C 90 @ D 88**\n<@7>\n\nAlready normalized."),
            $this->game('2026-02-26', ''),
        ];

        self::assertSame([], RecapDocument::gamesMissingMentionLines($games));
    }

    /**
     * A game with a score header is named by the header. A game with no header is
     * named by its date and team ids. A one-line game has no mention line at all.
     */
    public function testNamesEachGameMissingItsMentionLine(): void
    {
        $tagged     = $this->game('2026-02-26', "**A 100 @ B 114**\n<@1> · <@2>\nGame prose.");
        $headerOnly = $this->game('2026-02-26', "**Boston Celtics 100 @ Miami Heat 114**\nProse, no tags.");
        $bareProse  = [
            'game_date'      => '2026-02-27',
            'visitor_teamid' => 5,
            'home_teamid'    => 12,
            'recap_text'     => "The visitors came out strong.\nThey held the lead.",
        ];
        $oneLine    = $this->game('2026-02-28', 'Single-line prose.');

        self::assertSame(
            [
                'Boston Celtics 100 @ Miami Heat 114',
                '2026-02-27 · team 5 at team 12',
                '2026-02-28 · team 1 at team 2',
            ],
            RecapDocument::gamesMissingMentionLines([$tagged, $headerOnly, $bareProse, $oneLine])
        );
    }

    public function testNamesAGameWithMissingKeyColumnsWithoutFailing(): void
    {
        $game = ['visitor_teamid' => null, 'recap_text' => 'Prose only.'];

        self::assertSame([' · team  at team '], RecapDocument::gamesMissingMentionLines([$game]));
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
}
