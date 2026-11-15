<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

use League\League;
use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\Profile\RankUtility;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Valuation\RookieControl;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\SideView;

/**
 * Writes one side's reason to say yes, quoting the deal's own numbers.
 *
 * It reads the side's positive gain components at its heaviest season (and
 * this season, when that differs), keeps the largest `MAX_CLAUSES`, and turns
 * each into a clause with a real rank, salary or pick in it. When neither of
 * those seasons has a positive component, it reads the other seasons, heaviest
 * first, and names the season in each clause. The first received player
 * leads: name, age, seasons left, and injury when he is hurt. A side with no
 * positive component in any season gets null, which the ranker's callback
 * treats as no motive.
 */
final class ReasonWriter
{
    public const int MAX_CLAUSES = 2;
    public const int MAX_LENGTH = 160;
    public const string CLAUSE_SEPARATOR = '; ';
    public const string LEAD_SEPARATOR = ': ';

    /** Clauses that don't name their season themselves; a fallback clause gets one. */
    private const array SEASON_LABELLED_KEYS = ['starter_slots', 'bench_slots', 'profile_dimensions', 'balance'];

    /** Plain labels for the team-profile dimensions. Unit 5's card reads this map too. */
    public const array DIMENSION_LABELS = [
        'r_drive_off' => 'drive offense',
        'r_trans_off' => 'transition offense',
        'dd' => 'drive defense',
        'td' => 'transition defense',
        'r_stl' => 'steals',
        'r_blk' => 'blocks',
        'r_orb' => 'offensive rebounding',
        'r_drb' => 'defensive rebounding',
        'r_fgp' => 'field goal shooting',
        'r_ftp' => 'free throw shooting',
        'r_3gp' => 'three-point shooting',
        'r_ast' => 'passing',
        'oo' => 'outside offense',
        'po' => 'post offense',
        'od' => 'outside defense',
        'pd' => 'post defense',
        'r_tvr' => 'ball security',
        'r_foul' => 'fouling',
        'r_fga' => 'shot volume',
        'r_fta' => 'free throw volume',
        'r_3ga' => 'three-point volume',
        'pts' => 'scoring',
        'reb' => 'rebounds',
        'ast' => 'assists',
        'stl' => 'steals per 36',
        'blk' => 'blocks per 36',
        'tov' => 'turnovers',
        'fg_pct' => 'field goal percentage',
        'tp_pct' => 'three-point percentage',
        'ft_pct' => 'free throw percentage',
        'tpa_rate' => 'three-point attempt rate',
        ProfileDimension::OFFENSE_MEAN => 'offense',
        ProfileDimension::DEFENSE_MEAN => 'defense',
    ];

    private const array INJURY_LABELS = [
        'back_soon' => 'back soon',
        'back_for_playoffs' => 'back for the playoffs',
        'out_for_year' => 'out for the year',
    ];

    /**
     * @param array<int, string> $teamNames tid => name, for pick labels
     */
    public static function forSide(DealView $deal, SideView $side, array $teamNames): ?string
    {
        $clauses = [];
        $top = self::topComponents($side, [self::heaviestOffset($side->gain->seasonWeights()), 0]);
        $fallback = $top === [];
        if ($fallback) {
            $top = self::topComponents($side, self::offsetsByWeight($side->gain->seasonWeights()));
        }
        foreach ($top as [$offset, $key]) {
            $clause = self::clause($side, $key, $offset, $teamNames);
            if ($clause !== null && $fallback && in_array($key, self::SEASON_LABELLED_KEYS, true)) {
                $clause .= ' ' . self::season($offset);
            }
            if ($clause !== null) {
                $clauses[] = $clause;
            }
            if (count($clauses) >= self::MAX_CLAUSES) {
                break;
            }
        }
        if ($clauses === []) {
            return null;
        }

        return self::fit(self::lead($side), $clauses);
    }

    /**
     * Every season offset, heaviest weight first; the lower offset on a tie.
     *
     * @param list<float> $weights
     * @return list<int>
     */
    private static function offsetsByWeight(array $weights): array
    {
        $offsets = array_keys($weights);
        usort($offsets, static fn (int $a, int $b): int => [$weights[$b], $a] <=> [$weights[$a], $b]);

        return $offsets;
    }

    /**
     * Positive components at the given offsets, largest first, one entry per
     * key.
     *
     * @param list<int> $offsets
     * @return array<string, array{int, value-of<SideGain::KEYS>}> key => [offset, key]
     */
    private static function topComponents(SideView $side, array $offsets): array
    {
        $components = $side->gain->components();
        $entries = [];
        foreach ($offsets as $offset) {
            foreach (SideGain::KEYS as $key) {
                $v = $components[$offset][$key] ?? 0.0;
                if ($v > 0.0) {
                    $entries[] = ['offset' => $offset, 'key' => $key, 'v' => $v];
                }
            }
        }
        usort($entries, static fn (array $a, array $b): int => $b['v'] <=> $a['v']);

        $out = [];
        foreach ($entries as $e) {
            $out[$e['key']] ??= [$e['offset'], $e['key']];
        }

        return $out;
    }

    /**
     * The offset with the largest season weight; the lowest offset on a tie.
     *
     * @param list<float> $weights
     */
    private static function heaviestOffset(array $weights): int
    {
        return array_keys($weights, max([0.0, ...$weights]), true)[0];
    }

    /**
     * @param value-of<SideGain::KEYS> $key
     * @param array<int, string> $teamNames
     */
    private static function clause(SideView $side, string $key, int $o, array $teamNames): ?string
    {
        return match ($key) {
            'starter_slots' => self::starterClause($side, $o),
            'bench_slots' => self::benchClause($side, $o),
            'profile_dimensions' => self::dimensionClause($side, $o),
            'balance' => self::balanceClause($side, $o),
            'payroll' => self::payrollClause($side, $o),
            'cap_room' => self::capRoomClause($side, $o),
            'picks' => self::pickClause($side, $teamNames),
            'roster_spots' => 'opens a roster spot',
            'contracts' => self::contractClause($side, $o),
        };
    }

    private static function starterClause(SideView $side, int $o): ?string
    {
        $delta = $side->delta[$o] ?? null;
        if ($delta === null) {
            return null;
        }
        $best = null;
        $bestMove = 0;
        foreach ($delta->starterRanks as $slot => $pair) {
            if ($pair['after'] === null) {
                continue;
            }
            $move = ($pair['before'] ?? SideGain::EMPTY_SLOT_RANK) - $pair['after'];
            if ($move > $bestMove) {
                $bestMove = $move;
                $best = $slot;
            }
        }
        if ($best === null) {
            return null;
        }
        $pair = $delta->starterRanks[$best];

        return $pair['before'] === null
            ? 'no ' . $best . ' starter to rank ' . $pair['after']
            : $best . ' starter rank ' . $pair['before'] . ' to ' . $pair['after'];
    }

    private static function benchClause(SideView $side, int $o): ?string
    {
        $before = $side->before[$o] ?? null;
        $after = $side->after[$o] ?? null;
        if ($before === null || $after === null) {
            return null;
        }
        $best = null;
        $bestMove = 0;
        foreach ($after->bestBenchRank as $slot => $rank) {
            if ($rank === null) {
                continue;
            }
            $move = ($before->bestBenchRank[$slot] ?? SideGain::EMPTY_SLOT_RANK) - $rank;
            if ($move > $bestMove) {
                $bestMove = $move;
                $best = $slot;
            }
        }
        if ($best === null) {
            return null;
        }
        $from = $before->bestBenchRank[$best] ?? null;

        return $from === null
            ? 'no ' . $best . ' bench to best bench rank ' . $after->bestBenchRank[$best]
            : $best . ' best bench rank ' . $from . ' to ' . $after->bestBenchRank[$best];
    }

    private static function dimensionClause(SideView $side, int $o): ?string
    {
        $delta = $side->delta[$o] ?? null;
        if ($delta === null) {
            return null;
        }
        // Quote the dimension that improved most (a lower rank is better), so
        // the clause never names a dimension the deal made worse.
        $key = null;
        $bestGain = 0.0;
        foreach ($delta->dimensionRanks as $k => $p) {
            $g = RankUtility::gain($p['before'], $p['after']);
            if ($g > $bestGain) {
                $bestGain = $g;
                $key = $k;
            }
        }
        if ($key === null) {
            return null;
        }
        $pair = $delta->dimensionRanks[$key];

        return (self::DIMENSION_LABELS[$key] ?? $key) . ' rank ' . $pair['before'] . ' to ' . $pair['after'];
    }

    private static function balanceClause(SideView $side, int $o): ?string
    {
        $before = $side->before[$o] ?? null;
        $after = $side->after[$o] ?? null;
        if ($before === null || $after === null) {
            return null;
        }

        return 'offense and defense rank gap ' . abs($before->profile->balance()) . ' to ' . abs($after->profile->balance());
    }

    private static function payrollClause(SideView $side, int $o): ?string
    {
        $delta = $side->delta[$o] ?? null;
        if ($delta === null) {
            return null;
        }

        return 'payroll ' . $delta->payroll['before'] . ' to ' . $delta->payroll['after'] . ' ' . self::season($o);
    }

    private static function capRoomClause(SideView $side, int $o): ?string
    {
        $delta = $side->delta[$o] ?? null;
        if ($delta === null) {
            return null;
        }

        return (League::HARD_CAP_MAX - $delta->payroll['after']) . ' under the hard cap ' . self::season($o);
    }

    /**
     * @param array<int, string> $teamNames
     */
    private static function pickClause(SideView $side, array $teamNames): ?string
    {
        $best = null;
        foreach ($side->receivedPicks as $pick) {
            if ($best === null || ($side->pickWorthById[$pick->pickId] ?? 0.0) > ($side->pickWorthById[$best->pickId] ?? 0.0)) {
                $best = $pick;
            }
        }
        if ($best === null) {
            return null;
        }
        $team = $teamNames[$best->originalTid] ?? null;

        return $best->year . ' round ' . $best->round . ' pick' . ($team === null ? '' : ' (' . $team . ')');
    }

    /**
     * The contracts component turns positive three ways: a received player
     * paid under his worth, a sent player paid over his, or a received
     * rookie-scale contract.
     */
    private static function contractClause(SideView $side, int $o): ?string
    {
        $under = null;
        foreach ($side->received as $p) {
            $s = self::surplus($side, $p, $o);
            if ($s > 0 && ($under === null || $s > $under[1])) {
                $under = [$p, $s];
            }
        }
        if ($under !== null) {
            return $under[0]->name . ' ' . $under[1] . ' under fair salary';
        }

        $over = null;
        foreach ($side->sent as $p) {
            $s = self::surplus($side, $p, $o);
            if ($s < 0 && ($over === null || $s < $over[1])) {
                $over = [$p, $s];
            }
        }
        if ($over !== null) {
            return 'sheds ' . $over[0]->name . ', ' . abs($over[1]) . ' over fair salary';
        }

        foreach ($side->received as $p) {
            if (RookieControl::isRookieScale($p->draftRound, $p->exp)) {
                return $p->name . ' on a rookie-scale contract';
            }
        }

        return null;
    }

    /** The player's contract surplus at the offset; a player with no entry has none. */
    private static function surplus(SideView $side, ProjectionPlayer $p, int $o): int
    {
        return $side->surplusByPid[$p->pid][$o] ?? 0;
    }

    /**
     * "Name, age, n seasons left", with the injury in brackets when he is hurt.
     */
    private static function lead(SideView $side): ?string
    {
        $p = $side->received[0] ?? null;
        if ($p === null) {
            return null;
        }
        $status = $side->injuryByPid[$p->pid] ?? InjuryStatus::Healthy;
        $injury = self::INJURY_LABELS[$status->value] ?? null;
        $years = self::seasonsLeft($p);

        return $p->name
            . ($injury === null ? '' : ' (' . $injury . ')')
            . ', ' . $p->age . ', ' . $years . ($years === 1 ? ' season left' : ' seasons left');
    }

    private static function seasonsLeft(ProjectionPlayer $p): int
    {
        $n = 0;
        while ($p->salaryAt($n) > 0) {
            $n++;
        }

        return $n;
    }

    private static function season(int $o): string
    {
        return match ($o) {
            0 => 'this season',
            1 => 'next season',
            default => 'in ' . $o . ' seasons',
        };
    }

    /**
     * Joins the lead and clauses, then drops clauses from the end until the
     * text fits `MAX_LENGTH`. A single clause still too long drops the lead,
     * then cuts at the last space.
     *
     * @param non-empty-list<string> $clauses
     */
    private static function fit(?string $lead, array $clauses): string
    {
        while (true) {
            $text = ($lead === null ? '' : $lead . self::LEAD_SEPARATOR) . implode(self::CLAUSE_SEPARATOR, $clauses);
            if (mb_strlen($text) <= self::MAX_LENGTH) {
                return $text;
            }
            if (count($clauses) > 1) {
                array_pop($clauses);
                continue;
            }
            if ($lead !== null) {
                $lead = null;
                continue;
            }
            $cut = mb_substr($text, 0, self::MAX_LENGTH);
            $space = mb_strrpos($cut, ' ');

            return $space === false ? $cut : mb_substr($cut, 0, $space);
        }
    }
}
