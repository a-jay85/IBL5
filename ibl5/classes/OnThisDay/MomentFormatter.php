<?php

declare(strict_types=1);

namespace OnThisDay;

use Utilities\BoxScoreUrlBuilder;

/**
 * Copy for On This Day moments as segments with optional app-relative URLs.
 *
 * The homepage view and the Discord line both render segments(), so the two
 * surfaces never drift. Pure and HTML-free: each surface does its own escaping.
 */
final class MomentFormatter
{
    private const ROUND_LABELS = [
        1 => 'first round',
        2 => 'conference semifinals',
        3 => 'conference finals',
        4 => 'IBL Finals',
    ];

    private const FALLBACK_ROUND_LABEL = 'playoffs';

    /** Absolute base for Discord links; Discord posts only from production. */
    private const SITE_BASE_URL = 'https://www.iblhoops.net/ibl5/';

    private const DISCORD_MAX_LENGTH = 1900;

    /**
     * The moment sentence as text runs. Separators are their own unlinked
     * segments, so no link text starts or ends with a space.
     *
     * @return list<MomentSegment>
     */
    public function segments(Moment $m): array
    {
        $box = $this->boxScoreUrl($m);

        return match ($m->kind) {
            MomentKind::FiftyPointGame => $this->playerSentence($m, new MomentSegment(sprintf(
                'scored %d points against the %s',
                $m->facts['points'] ?? 0,
                $m->opponentName,
            ), $box)),
            MomentKind::TripleDouble => $this->playerSentence($m, new MomentSegment(sprintf(
                'posted a triple-double against the %s: %d pts, %d reb, %d ast',
                $m->opponentName,
                $m->facts['points'] ?? 0,
                $m->facts['rebounds'] ?? 0,
                $m->facts['assists'] ?? 0,
            ), $box)),
            MomentKind::RecordGame => $this->playerSentence($m, new MomentSegment(sprintf(
                'set the league single-game %s record with %d against the %s',
                $m->statLabel ?? 'stat',
                $m->facts['value'] ?? 0,
                $m->opponentName,
            ), $box)),
            MomentKind::SeriesClincher => [new MomentSegment(sprintf(
                'The %s closed out the %s %d-%d in the %s',
                $m->teamName,
                $m->opponentName,
                $m->facts['wins'] ?? 0,
                $m->facts['losses'] ?? 0,
                self::ROUND_LABELS[$m->facts['round'] ?? 0] ?? self::FALLBACK_ROUND_LABEL,
            ), $box)],
            MomentKind::Trade => [
                new MomentSegment($m->playerName, $this->playerUrl($m->facts['mover_pid'] ?? 0)),
                new MomentSegment(' '),
                new MomentSegment($this->describeTrade($m), $box),
            ],
        };
    }

    /**
     * The plain sentence: every segment's text joined.
     */
    public function describe(Moment $m): string
    {
        return implode('', array_map(static fn (MomentSegment $s): string => $s->text, $this->segments($m)));
    }

    public function seasonsAgoLabel(int $n): string
    {
        return $n === 1 ? '1 season ago' : $n . ' seasons ago';
    }

    public function dateLabel(string $ymd): string
    {
        return (new \DateTimeImmutable($ymd))->format('M j, Y');
    }

    /**
     * One-line Discord post with masked links `[text](<absolute url>)`; the
     * angle brackets suppress link embeds. Markdown characters in the text are
     * escaped. Every `@` gets a zero-width space so a name can never ping
     * @everyone or @here. When the linked line would pass 1900 characters, the
     * plain line is posted instead and cut to 1900, so a cut never splits a
     * link and the post stays under Discord's 2000-character cap.
     */
    public function discordLine(Moment $m, int $seasonsAgo): string
    {
        $prefix = sprintf(
            'On this day in IBL history (%s, %s): ',
            $this->seasonsAgoLabel($seasonsAgo),
            $this->dateLabel($m->date),
        );

        $body = '';
        foreach ($this->segments($m) as $segment) {
            $text = self::escapeDiscordMarkdown($segment->text);
            $body .= $segment->url === null
                ? $text
                : '[' . $text . '](<' . self::SITE_BASE_URL . $segment->url . '>)';
        }

        $linked = str_replace('@', "@\u{200B}", $prefix . $body . '.');
        if (mb_strlen($linked) <= self::DISCORD_MAX_LENGTH) {
            return $linked;
        }

        return mb_substr(
            str_replace('@', "@\u{200B}", $prefix . $this->describe($m) . '.'),
            0,
            self::DISCORD_MAX_LENGTH,
        );
    }

    /**
     * "Player (Team)" linked to the player page, a separator, then the predicate.
     *
     * @return list<MomentSegment>
     */
    private function playerSentence(Moment $m, MomentSegment $predicate): array
    {
        return [
            new MomentSegment($m->playerName . ' (' . $m->teamName . ')', $this->playerUrl($m->pid)),
            new MomentSegment(' '),
            $predicate,
        ];
    }

    private function playerUrl(int $pid): ?string
    {
        return $pid > 0 ? 'modules.php?name=Player&pa=showpage&pid=' . $pid : null;
    }

    /**
     * The moment's box score, opened on the moment team (the scorer's team, the
     * series winner, or the trade's new team). Null when the ordinal is unknown.
     */
    private function boxScoreUrl(Moment $m): ?string
    {
        $base = BoxScoreUrlBuilder::buildUrl($m->date, $m->gameOfThatDay);

        return $base === '' ? null : $base . '&team=' . $m->teamId;
    }

    /**
     * Trade predicate: the text after the player name.
     */
    private function describeTrade(Moment $m): string
    {
        $text = sprintf('moved from the %s to the %s', $m->opponentName, $m->teamName);

        $others = ($m->facts['players_moved'] ?? 1) - 1;
        if ($others > 0) {
            $text .= ' with ' . $others . ' other ' . ($others === 1 ? 'player' : 'players');
        }

        return $text;
    }

    private static function escapeDiscordMarkdown(string $s): string
    {
        return strtr($s, [
            '\\' => '\\\\',
            '*' => '\\*',
            '_' => '\\_',
            '~' => '\\~',
            '|' => '\\|',
            '[' => '\\[',
            ']' => '\\]',
            '`' => '\\`',
        ]);
    }
}
