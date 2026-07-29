<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Assembles the postable recap document from the parts the generator stored.
 *
 * `ibl_sim_summaries.recap_text` is a ~220-character teaser, not the document — the
 * document only exists as pieces: `intro_text`, one row per game in
 * `ibl_sim_game_recaps`, and `outro_text`. This class is the one place that joins
 * them back into the shape of `bin/lib/sim-recap-exemplar.txt`, which is what a GM
 * actually pastes into Discord.
 *
 * Plain text in, plain text out — no HTML, no escaping. Callers that put the result
 * in a page still run it through HtmlSanitizer::e() like any other stored value.
 */
final class RecapDocument
{
    /**
     * Two blank lines separate the top-level blocks: intro, each date section, outro.
     * Exemplar lines 12-15 (intro rule → blank → blank → date rule) and 263-266.
     */
    private const BLOCK_GAP = "\n\n\n";

    /**
     * One blank line separates the pieces inside a date section — the rule from the
     * first game, and each game from the next. Exemplar lines 15-17 and 20-22.
     */
    private const INNER_GAP = "\n\n";

    /**
     * The date rule is fixed-count, not width-normalized: the exemplar's own rules are
     * 81 chars for "Feb 26" and 80 for "Mar 1". Padding them to a common width would
     * diverge from the format the exemplar defines.
     */
    private const RULE_PREFIX = 32;
    private const RULE_SUFFIX = 41;

    /** `<@123> · <@456>` — the GM mention line the generator writes under each score header. */
    private const MENTION_LINE = '/^<@\d+>(?:\s*·\s*<@\d+>)*$/u';

    /**
     * @param list<array<string, mixed>> $gameRecaps From SimSummaryRepository::findDisplayableGameRecaps(), already in sort order
     */
    public static function assemble(?string $intro, array $gameRecaps, ?string $outro): string
    {
        $blocks = [];

        $introText = self::clean($intro);
        if ($introText !== '') {
            $blocks[] = $introText;
        }

        foreach (self::groupByDate($gameRecaps) as $section) {
            $rule     = $section['date'] === '' ? '' : self::dateRule($section['date']) . self::INNER_GAP;
            $blocks[] = $rule . implode(self::INNER_GAP, $section['texts']);
        }

        $outroText = self::clean($outro);
        if ($outroText !== '') {
            $blocks[] = $outroText;
        }

        return $blocks === [] ? '' : implode(self::BLOCK_GAP, $blocks) . "\n";
    }

    /**
     * What the GM actually pastes — the assembled document when the stored parts carry
     * the format, the stored `recap_text` when they don't.
     *
     * The generator is not guaranteed to have written the shape `bin/sim-recap-prompt`
     * asks for. Sim 722 (2026-07-26) stored 56 per-game parts that were bare prose: no
     * `**Away 110 @ Home 104**` score header and no `<@id> · <@id>` mention line, while
     * its `recap_text` held the complete 25K document including all 112 mentions.
     * Assembling those parts produces a document that reads fine and silently drops
     * every GM's Discord tag — worse than what the league had before this class existed.
     * So when the parts are degraded, fall back to the stored text.
     *
     * `assemble()` deliberately stays shape-tolerant (it is a pure formatter and cannot
     * see the stored text), which is why the choice lives here instead.
     *
     * @param list<array<string, mixed>> $gameRecaps From SimSummaryRepository::findDisplayableGameRecaps(), already in sort order
     */
    public static function postableText(?string $intro, array $gameRecaps, ?string $outro, ?string $storedText): string
    {
        $document = self::assemble($intro, $gameRecaps, $outro);
        $stored   = is_string($storedText) ? $storedText : '';

        // Nothing to fall back to — the assembled document is all there is, even if degraded.
        if (trim($stored) === '') {
            return $document;
        }

        // No parts at all: the stored text, returned verbatim. Trimming would change the
        // exported bytes, so the emptiness test above is the only place `trim()` is used.
        if ($document === '') {
            return $stored;
        }

        return self::gamesCarryTheirMentionLines($gameRecaps) ? $document : $stored;
    }

    /**
     * All-or-nothing per sim, not per game: a run where only some games carry their
     * mentions means the generator was inconsistent, and assembling it would leave
     * exactly the GMs of the malformed games un-notified — the silent failure this
     * guard exists to prevent.
     *
     * At least one non-empty part is required. A sim with a full stored document and no
     * usable parts would otherwise assemble an intro+outro that looks like a complete
     * recap with every game missing.
     *
     * The mention line is checked at index 1 alone, independently of what follows, so a
     * part already in the exemplar's header/mentions/blank/prose shape still counts.
     *
     * @param list<array<string, mixed>> $gameRecaps
     */
    private static function gamesCarryTheirMentionLines(array $gameRecaps): bool
    {
        $sawAGame = false;

        foreach ($gameRecaps as $game) {
            $text = self::clean($game['recap_text'] ?? null);
            if ($text === '') {
                continue;
            }

            $sawAGame = true;
            $lines    = explode("\n", str_replace("\r\n", "\n", $text));

            if (!isset($lines[1]) || preg_match(self::MENTION_LINE, $lines[1]) !== 1) {
                return false;
            }
        }

        return $sawAGame;
    }

    /**
     * Games keep the order the caller gave them; a new section starts only when the
     * date changes, so each date gets exactly one rule. A map keyed by date would be
     * shorter but PHP coerces numeric-looking keys to int, so the sections are a list.
     * Rows with no usable text are dropped — an empty block is a stray blank line.
     *
     * @param  list<array<string, mixed>>                 $gameRecaps
     * @return list<array{date: string, texts: list<string>}>
     */
    private static function groupByDate(array $gameRecaps): array
    {
        $sections = [];
        foreach ($gameRecaps as $game) {
            $text = self::clean($game['recap_text'] ?? null);
            if ($text === '') {
                continue;
            }

            $date  = self::clean($game['game_date'] ?? null);
            $last  = count($sections) - 1;
            $block = self::normalizeGameBlock($text);

            if ($last >= 0 && $sections[$last]['date'] === $date) {
                $sections[$last]['texts'][] = $block;
                continue;
            }

            $sections[] = ['date' => $date, 'texts' => [$block]];
        }

        return $sections;
    }

    /**
     * The generator writes the score header, the mention line and the prose on three
     * consecutive lines; the exemplar puts a blank line between the mentions and the
     * prose (lines 17-20). Rather than leave every already-stored recap a line off the
     * format, repair it on read. The match is deliberately narrow — only a line that is
     * nothing but `<@id>` mentions counts, so ordinary prose is never split.
     */
    private static function normalizeGameBlock(string $text): string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $text));

        if (count($lines) >= 3 && $lines[2] !== '' && preg_match(self::MENTION_LINE, $lines[1]) === 1) {
            array_splice($lines, 2, 0, ['']);
        }

        return implode("\n", $lines);
    }

    private static function dateRule(string $date): string
    {
        return str_repeat('=', self::RULE_PREFIX)
            . ' ' . self::dateLabel($date) . ' '
            . str_repeat('=', self::RULE_SUFFIX);
    }

    /**
     * `Mar 5`, not `Mar 05` — the exemplar's own rules read "Mar 1", "Mar 4". A date the
     * DB gave us that PHP cannot parse is printed verbatim rather than dropped.
     */
    private static function dateLabel(string $date): string
    {
        $parsed = date_create_immutable($date);

        return $parsed === false ? $date : $parsed->format('M j');
    }

    private static function clean(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
