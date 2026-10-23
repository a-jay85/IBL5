<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Maps positions to lineup slots and reads each team's starters from the
 * GM's depth chart. Pure; no I/O.
 */
final class SlotMapper
{
    public const array SLOTS = ['PG', 'SG', 'SF', 'PF', 'C'];

    /**
     * G covers PG and SG, F covers SF and PF, GF covers SG and SF. An empty
     * position covers nothing.
     *
     * @return list<string>
     */
    public static function slotsForPos(string $pos): array
    {
        return match ($pos) {
            'PG', 'SG', 'SF', 'PF', 'C' => [$pos],
            'G' => ['PG', 'SG'],
            'F' => ['SF', 'PF'],
            'GF' => ['SG', 'SF'],
            default => [],
        };
    }

    public static function eligible(SuggestionPlayer $p, string $slot): bool
    {
        return in_array($slot, self::slotsForPos($p->pos), true);
    }

    /**
     * A chart is reliable when every slot has exactly one depth-1 player and
     * no player starts at two slots. Then the chart's starters stand,
     * whatever their pos. Otherwise each slot takes the first unassigned
     * eligible player by dcMinutes desc, salary desc, pid asc.
     *
     * @param list<SuggestionPlayer> $players
     */
    public static function depthFor(array $players): TeamDepth
    {
        $chart = [];
        $reliable = true;
        $startsAt = [];
        foreach (self::SLOTS as $slot) {
            $atSlot = array_values(array_filter(
                $players,
                static fn (SuggestionPlayer $p): bool => ($p->depth[$slot] ?? 0) === 1,
            ));
            if (count($atSlot) !== 1) {
                $reliable = false;
                break;
            }
            $pid = $atSlot[0]->pid;
            if (isset($startsAt[$pid])) {
                $reliable = false;
                break;
            }
            $startsAt[$pid] = true;
            $chart[$slot] = $atSlot[0];
        }

        if ($reliable) {
            return new TeamDepth($chart, true);
        }

        $ordered = self::byMinutes($players);
        $starters = [];
        $used = [];
        foreach (self::SLOTS as $slot) {
            $starters[$slot] = null;
            foreach ($ordered as $player) {
                if (!isset($used[$player->pid]) && self::eligible($player, $slot)) {
                    $starters[$slot] = $player;
                    $used[$player->pid] = true;
                    break;
                }
            }
        }

        return new TeamDepth($starters, false);
    }

    /**
     * Players with a real rotation role (dcMinutes >= REAL_MINUTES) who can play $slot.
     *
     * @param list<SuggestionPlayer> $players
     * @return list<SuggestionPlayer>
     */
    public static function rotationAt(array $players, string $slot): array
    {
        return array_values(array_filter(
            $players,
            static fn (SuggestionPlayer $p): bool => $p->dcMinutes >= NeedThresholds::REAL_MINUTES && self::eligible($p, $slot),
        ));
    }

    /**
     * dcMinutes desc, then currentSalary desc, then pid asc.
     *
     * @param list<SuggestionPlayer> $players
     * @return list<SuggestionPlayer>
     */
    public static function byMinutes(array $players): array
    {
        usort($players, static fn (SuggestionPlayer $a, SuggestionPlayer $b): int
            => [$b->dcMinutes, $b->currentSalary, $a->pid] <=> [$a->dcMinutes, $a->currentSalary, $b->pid]);

        return $players;
    }
}
