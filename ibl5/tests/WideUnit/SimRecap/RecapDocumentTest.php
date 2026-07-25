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
}
