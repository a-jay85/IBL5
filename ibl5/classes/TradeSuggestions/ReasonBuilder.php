<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Builds the plain-language "why" lines for one side of a deal. Pure.
 *
 * Every line is computed from the deal's own numbers: names, ovr, age, years
 * left, rank on the new roster, position counts, pick year/round, salary. Each
 * candidate line carries a strength; options() returns them strongest first so
 * the caller can take the first one and still pick a different line for the
 * other side. The view escapes the result.
 */
final class ReasonBuilder
{
    public const int VETERAN_AGE = 28;

    public function __construct(private readonly AssetValuer $valuer = new AssetValuer())
    {
    }

    /**
     * @param list<SuggestionPlayer> $inPlayers
     * @param list<SuggestionPlayer> $outPlayers
     * @param list<SuggestionPick> $inPicks
     * @return non-empty-list<string> Strongest first
     */
    public function options(
        TeamMode $mode,
        TeamAssets $after,
        array $inPlayers,
        array $outPlayers,
        array $inPicks,
        TeamScore $beforeScore,
        TeamScore $afterScore,
    ): array {
        /** @var list<array{float, string}> $options */
        $options = [];

        usort($inPlayers, static fn (SuggestionPlayer $a, SuggestionPlayer $b): int => [$b->ovr, $a->pid] <=> [$a->ovr, $b->pid]);
        usort($outPlayers, static fn (SuggestionPlayer $a, SuggestionPlayer $b): int => [$b->ovr, $a->pid] <=> [$a->ovr, $b->pid]);
        $best = $inPlayers[0] ?? null;

        if ($best !== null) {
            $rank = self::rankOn($after, $best);
            $value = $this->valuer->playerValue($best, $mode);
            if ($rank <= TeamModeGuesser::ROTATION_SIZE) {
                $options[] = [$value, 'Adds ' . self::describe($best) . ', who would be the team\'s '
                    . ($rank === 1 ? 'best player' : self::ordinal($rank) . '-best player') . '.'];
            }

            $outBest = $outPlayers[0] ?? null;
            if (count($outPlayers) >= 2 && count($inPlayers) === 1 && $outBest !== null && $best->ovr > $outBest->ovr) {
                $options[] = [$value + 5, 'Turns ' . self::nameList($outPlayers) . ' into one better player, '
                    . $best->name . ' (ovr ' . $best->ovr . ').'];
            }

            if (count($inPlayers) >= 2 && count($outPlayers) <= 1) {
                $allRotation = true;
                $sum = 0.0;
                foreach ($inPlayers as $player) {
                    $allRotation = $allRotation && self::rankOn($after, $player) <= TeamModeGuesser::ROTATION_SIZE;
                    $sum += $this->valuer->playerValue($player, $mode);
                }
                if ($allRotation) {
                    $options[] = [$sum, 'Adds two rotation players, ' . self::nameList($inPlayers)
                        . ($outBest !== null ? ', for ' . $outBest->name . ' (ovr ' . $outBest->ovr . ')' : '') . '.'];
                }
            }

            $options[] = [$value / 4, 'Adds ' . self::describe($best) . '.'];
        }

        foreach (TeamNeedsScorer::GROUPS as $group) {
            $holeBefore = $beforeScore->positionHoles[$group] ?? 0;
            $holeAfter = $afterScore->positionHoles[$group] ?? 0;
            if ($holeAfter < $holeBefore) {
                $options[] = [3.0 * ($holeBefore - $holeAfter), 'Fills a gap at ' . $group . ': its top eight goes from '
                    . ($beforeScore->groupCounts[$group] ?? 0) . ' to ' . ($afterScore->groupCounts[$group] ?? 0) . ' ' . $group . 's.'];
            }
        }

        foreach ($inPicks as $pick) {
            $options[] = [$this->valuer->pickValue($pick, $mode), 'Adds a ' . $pick->year . ' '
                . ($pick->round === 1 ? 'first' : 'second') . '-round pick.'];
        }

        $cleared = self::futureSalary($outPlayers) - self::futureSalary($inPlayers);
        if ($cleared > 0) {
            $strength = $mode === TeamMode::Rebuild ? $cleared * AssetValuer::REBUILD_SALARY_COST : 0.0;
            $options[] = [$strength, 'Frees $' . \BasketballStats\StatsFormatter::formatTotal($cleared) . 'K of salary owed in later contract years.'];
        }

        if ($mode === TeamMode::Rebuild) {
            $youngestIn = self::youngest($inPlayers);
            foreach ($outPlayers as $player) {
                $getsYounger = $inPicks !== [] || ($youngestIn !== null && $youngestIn->age <= $player->age - 3);
                if ($player->age >= self::VETERAN_AGE && $getsYounger) {
                    $options[] = [2.0, 'Turns ' . $player->name . ' (age ' . $player->age . ', '
                        . self::yearsLeft($player) . ') into younger value.'];
                    break;
                }
            }
        }

        usort($options, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
        $texts = array_values(array_unique(array_map(static fn (array $o): string => $o[1], $options)));

        return $texts === [] ? ['Balances the roster.'] : $texts;
    }

    /**
     * 1-based ovr rank of $player on $roster (players with a higher ovr come first).
     */
    private static function rankOn(TeamAssets $roster, SuggestionPlayer $player): int
    {
        $rank = 1;
        foreach ($roster->players as $other) {
            if ($other->pid !== $player->pid && $other->ovr > $player->ovr) {
                $rank++;
            }
        }

        return $rank;
    }

    private static function describe(SuggestionPlayer $p): string
    {
        return $p->name . ' (ovr ' . $p->ovr . ', age ' . $p->age . ', ' . self::yearsLeft($p) . ')';
    }

    public static function yearsLeft(SuggestionPlayer $p): string
    {
        return $p->yearsRemaining . ($p->yearsRemaining === 1 ? ' year' : ' years') . ' left on contract';
    }

    /**
     * @param list<SuggestionPlayer> $players
     */
    private static function nameList(array $players): string
    {
        return implode(' and ', array_map(
            static fn (SuggestionPlayer $p): string => $p->name . ' (ovr ' . $p->ovr . ')',
            $players
        ));
    }

    /**
     * @param list<SuggestionPlayer> $players
     */
    private static function youngest(array $players): ?SuggestionPlayer
    {
        $youngest = null;
        foreach ($players as $player) {
            if ($youngest === null || $player->age < $youngest->age) {
                $youngest = $player;
            }
        }

        return $youngest;
    }

    /**
     * @param list<SuggestionPlayer> $players
     */
    private static function futureSalary(array $players): int
    {
        $total = 0;
        foreach ($players as $player) {
            $total += $player->futureSalary;
        }

        return $total;
    }

    private static function ordinal(int $n): string
    {
        return $n . match ($n) {
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
