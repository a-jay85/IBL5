<?php

declare(strict_types=1);

namespace TradeSuggestions\Profile;

use TradeSuggestions\Projection\ProjectedSeason;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TeamDepth;

/**
 * One team's lineup at one horizon offset (F6). Only players under contract
 * at the offset are in it. Starters come from `SlotMapper::depthFor()`, which
 * falls back to minutes order when the depth chart is unreliable (F7).
 */
final readonly class SeasonLineup
{
    /**
     * @param array<string, list<int>> $rotation slot => pids in minutes order
     * @param array<int, ProjectionPlayer> $players pid => player on the roster at the offset
     * @param array<int, float> $minutesWeight pid => minutes weight
     */
    public function __construct(
        public int $tid,
        public int $offset,
        public TeamDepth $depth,
        public array $rotation,
        public array $players,
        public bool $chartReliable,
        public array $minutesWeight,
    ) {
    }

    /**
     * @param array<int, array{player: ProjectionPlayer, season: ProjectedSeason}> $projected
     */
    public static function build(int $tid, int $offset, array $projected): self
    {
        $players = [];
        $adapted = [];
        foreach ($projected as $entry) {
            if (!$entry['season']->onRoster) {
                continue;
            }
            $players[$entry['player']->pid] = $entry['player'];
            $adapted[] = LineupAdapter::toSuggestionPlayer($entry['player'], $entry['season']);
        }

        $depth = SlotMapper::depthFor($adapted);
        $ordered = SlotMapper::byMinutes($adapted);
        $rotation = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $rotation[$slot] = array_map(
                static fn ($p): int => $p->pid,
                SlotMapper::rotationAt($ordered, $slot)
            );
        }

        $weights = [];
        foreach ($players as $pid => $player) {
            $weights[$pid] = self::weightFor($player, $depth->chartReliable);
        }

        return new self($tid, $offset, $depth, $rotation, $players, $depth->chartReliable, $weights);
    }

    public function starterPid(string $slot): ?int
    {
        return $this->depth->starterAt($slot)?->pid;
    }

    /**
     * Rotation players at the slot who start nowhere.
     *
     * @return list<int>
     */
    public function benchPids(string $slot): array
    {
        return array_values(array_filter(
            $this->rotation[$slot] ?? [],
            fn (int $pid): bool => $this->depth->slotOf($pid) === null
        ));
    }

    /**
     * dcMinutes when the chart is reliable; otherwise minutes per game from
     * the production line, or 0.0 with no sample.
     */
    private static function weightFor(ProjectionPlayer $player, bool $chartReliable): float
    {
        if ($chartReliable) {
            return (float) $player->dcMinutes;
        }
        if ($player->production === null) {
            return 0.0;
        }

        return $player->production->minutes / max(1, $player->production->games);
    }
}
