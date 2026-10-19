<?php

declare(strict_types=1);

namespace OnThisDay;

/**
 * Plain-text copy for "on this day" moments.
 *
 * Pure and HTML-free: the homepage view escapes the result and the Discord
 * line uses it as-is, so the two surfaces never drift.
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

    public function describe(Moment $m): string
    {
        return match ($m->kind) {
            MomentKind::FiftyPointGame => sprintf(
                '%s (%s) scored %d points against the %s',
                $m->playerName,
                $m->teamName,
                $m->facts['points'] ?? 0,
                $m->opponentName,
            ),
            MomentKind::TripleDouble => sprintf(
                '%s (%s) posted a triple-double against the %s: %d pts, %d reb, %d ast',
                $m->playerName,
                $m->teamName,
                $m->opponentName,
                $m->facts['points'] ?? 0,
                $m->facts['rebounds'] ?? 0,
                $m->facts['assists'] ?? 0,
            ),
            MomentKind::RecordGame => sprintf(
                '%s (%s) set the league single-game %s record with %d against the %s',
                $m->playerName,
                $m->teamName,
                $m->statLabel ?? 'stat',
                $m->facts['value'] ?? 0,
                $m->opponentName,
            ),
            MomentKind::SeriesClincher => sprintf(
                'The %s closed out the %s %d-%d in the %s',
                $m->teamName,
                $m->opponentName,
                $m->facts['wins'] ?? 0,
                $m->facts['losses'] ?? 0,
                self::ROUND_LABELS[$m->facts['round'] ?? 0] ?? self::FALLBACK_ROUND_LABEL,
            ),
            MomentKind::Trade => $this->describeTrade($m),
        };
    }

    public function seasonsAgoLabel(int $n): string
    {
        return $n === 1 ? '1 season ago' : $n . ' seasons ago';
    }

    public function dateLabel(string $ymd): string
    {
        return (new \DateTimeImmutable($ymd))->format('M j, Y');
    }

    private function describeTrade(Moment $m): string
    {
        $text = sprintf('%s moved from the %s to the %s', $m->playerName, $m->opponentName, $m->teamName);

        $others = ($m->facts['players_moved'] ?? 1) - 1;
        if ($others > 0) {
            $text .= ' with ' . $others . ' other ' . ($others === 1 ? 'player' : 'players');
        }

        return $text;
    }
}
