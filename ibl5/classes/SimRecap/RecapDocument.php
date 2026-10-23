<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Assembles the postable recap document from the parts the generator stored.
 *
 * `ibl_sim_summaries.recap_text` is a ~220-character teaser, not the document — the
 * document only exists as pieces, one row per game in `ibl_sim_game_recaps`. This
 * class is the one place that joins them back into the shape of
 * `bin/lib/sim-recap-exemplar.txt`, which is what a GM actually pastes into Discord:
 * date sections of game blocks, each block ending in a links footer (box score, then
 * the visitor and home team pages) built here from the row's typed fields.
 *
 * Old rows may still carry stored `intro_text` and `outro_text`. They are no longer
 * part of the document.
 *
 * Plain text in, plain text out — no HTML, no escaping. Callers that put the result
 * in a page still run it through HtmlSanitizer::e() like any other stored value.
 */
final class RecapDocument
{
    /** Two blank lines separate one date section from the next. */
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
     * `**Boston Celtics 100 @ Miami Heat 114**` — the bold score header that opens every
     * game block in `bin/lib/sim-recap-exemplar.txt`. Counting these in the stored text
     * is how this class learns how many games the generator actually wrote.
     */
    private const SCORE_HEADER = '/^\*\*.+\s\d+\s@\s.+\s\d+\*\*$/m';

    /** The two team names in a game block's score header, visitor then home. */
    private const HEADER_TEAMS = '/^\*\*(.+)\s\d+\s@\s(.+)\s\d+\*\*$/u';

    /** A bare hostname: no scheme, path, port, userinfo, or whitespace. */
    private const HOST = '/^[a-z0-9.\-]+$/i';

    /** Where footer links point when the deployment has no canonical host configured. */
    public const PRODUCTION_HOST = 'iblhoops.net';

    /** Team page route, the same pattern as Team\TeamTableService. */
    private const TEAM_PAGE = 'modules.php?name=Team&op=team&teamid=';

    /**
     * @param list<array<string, mixed>> $gameRecaps From SimSummaryRepository::findDisplayableGameRecaps(), already in sort order
     */
    public static function assemble(array $gameRecaps, string $host): string
    {
        $host   = self::resolveHost($host);
        $blocks = [];

        foreach (self::groupByDate($gameRecaps, $host) as $section) {
            $rule     = $section['date'] === '' ? '' : self::dateRule($section['date']) . self::INNER_GAP;
            $blocks[] = $rule . implode(self::INNER_GAP, $section['texts']);
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
     * Sim 725 (2026-08-02) failed the other way: the parts were well formed but there
     * were only 7 of them, because all 49 rows had been stored with `game_of_that_day`
     * = 1 and the displayable filter matched one game per date. Shape alone said
     * "assemble". So the count of score headers in the stored text is a second,
     * independent floor on how many games the assembly must contain.
     *
     * `assemble()` deliberately stays shape-tolerant (it is a pure formatter and cannot
     * see the stored text), which is why the choice lives here instead.
     *
     * @param list<array<string, mixed>> $gameRecaps From SimSummaryRepository::findDisplayableGameRecaps(), already in sort order
     */
    public static function postableText(array $gameRecaps, ?string $storedText, string $host): string
    {
        $document = self::assemble($gameRecaps, $host);
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

        // Shape is not completeness. The parts are only the games that survived
        // SimSummaryRepository::findDisplayableGameRecaps(); a bad `game_of_that_day`
        // index makes that filter drop games silently. Sim 725 (2026-08-02) stored 49
        // games, 7 survived the filter, and all 7 were well formed — so the shape guard
        // below voted to assemble and would have posted 7 games in place of 49.
        //
        // The stored text is the generator's own record of how many games it wrote, so
        // any shortfall against it means the assembly is missing games. Sibling of
        // gamesCarryTheirMentionLines(), not a replacement: that guard catches degraded
        // parts, this one catches absent ones. Either firing prefers the stored text.
        if (self::renderedGameCount($gameRecaps) < self::countScoreHeaders($stored)) {
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
     * usable parts would otherwise return an empty document in place of the stored text.
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
     * How many games `assemble()` will actually render. groupByDate() drops rows whose
     * text cleans to empty, so a raw count($gameRecaps) would overstate the document and
     * let a shortfall through — the count must use the same basis assembly does.
     *
     * @param list<array<string, mixed>> $gameRecaps
     */
    private static function renderedGameCount(array $gameRecaps): int
    {
        $rendered = 0;

        foreach ($gameRecaps as $game) {
            if (self::clean($game['recap_text'] ?? null) !== '') {
                $rendered++;
            }
        }

        return $rendered;
    }

    /**
     * Score headers in the stored document. CRLF is normalized first because the `m`
     * anchor would otherwise leave a `\r` before the line end and match nothing. A
     * teaser-shaped `recap_text` has no headers and yields 0, which is what makes the
     * completeness guard inert for sims that never stored a full document.
     */
    private static function countScoreHeaders(string $text): int
    {
        $count = preg_match_all(self::SCORE_HEADER, str_replace("\r\n", "\n", $text));

        return $count === false ? 0 : $count;
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
    private static function groupByDate(array $gameRecaps, string $host): array
    {
        $sections = [];
        foreach ($gameRecaps as $game) {
            $text = self::clean($game['recap_text'] ?? null);
            if ($text === '') {
                continue;
            }

            $date  = self::clean($game['game_date'] ?? null);
            $last  = count($sections) - 1;
            $block = self::gameBlock($game, $host);

            if ($last >= 0 && $sections[$last]['date'] === $date) {
                $sections[$last]['texts'][] = $block;
                continue;
            }

            $sections[] = ['date' => $date, 'texts' => [$block]];
        }

        return $sections;
    }

    /**
     * One game exactly as the document renders it: the stored block with the
     * mention-line gap repaired, then the links footer. '' when the row has no text.
     * SimRecapPayload measures the per-block length cap through this method.
     *
     * @param array<string, mixed> $game
     */
    public static function gameBlock(array $game, string $host): string
    {
        $host = self::resolveHost($host);
        $text = self::clean($game['recap_text'] ?? null);
        if ($text === '') {
            return '';
        }

        $block  = self::normalizeGameBlock($text);
        $footer = self::linksFooter($game, $block, $host);

        return $footer === '' ? $block : $block . self::INNER_GAP . $footer;
    }

    /**
     * '' means the deployment has no canonical host, so links point at production.
     * Anything else must be a bare hostname, because it is pasted into every URL.
     */
    private static function resolveHost(string $host): string
    {
        if ($host === '') {
            return self::PRODUCTION_HOST;
        }

        if (preg_match(self::HOST, $host) !== 1) {
            throw new \InvalidArgumentException('canonical host must be a bare hostname');
        }

        return $host;
    }

    /**
     * Box score as a raw angle-bracketed URL, then the visitor and home team pages as
     * masked links, one per line. Built only from typed row fields and the stored
     * score header, never from generator prose. A line whose inputs are invalid is
     * left out, and all three missing gives ''.
     *
     * @param array<string, mixed> $game
     */
    private static function linksFooter(array $game, string $block, string $host): string
    {
        $base  = 'https://' . $host . '/ibl5/';
        $lines = [];

        $date   = self::clean($game['game_date'] ?? null);
        $gotd   = is_int($game['game_of_that_day'] ?? null) ? $game['game_of_that_day'] : 0;
        $boxId  = is_int($game['box_id'] ?? null) ? $game['box_id'] : 0;
        $boxUrl = \Utilities\BoxScoreUrlBuilder::buildUrl($date, $gotd, $boxId);
        if ($boxUrl !== '') {
            $lines[] = '<' . $base . (str_starts_with($boxUrl, './') ? substr($boxUrl, 2) : $boxUrl) . '>';
        }

        $header = explode("\n", $block, 2)[0];
        if (preg_match(self::HEADER_TEAMS, $header, $teams) === 1) {
            foreach ([[$teams[1], 'visitor_teamid'], [$teams[2], 'home_teamid']] as [$rawName, $idKey]) {
                $name   = trim(str_replace(['[', ']', '<', '>'], '', $rawName));
                $teamId = $game[$idKey] ?? null;
                if ($name !== '' && is_int($teamId) && $teamId >= 1) {
                    $lines[] = '[' . $name . '](<' . $base . self::TEAM_PAGE . $teamId . '>)';
                }
            }
        }

        return implode("\n", $lines);
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
