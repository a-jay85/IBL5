<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * One season of box-score totals for a player, from ibl_plr (current season)
 * or ibl_hist (last season). These are reporting facts; nothing folds them
 * into a single quality number.
 */
final readonly class ProductionLine
{
    public function __construct(
        public int $games,
        public int $minutes,
        public int $points,
        public int $rebounds,
        public int $assists,
        public int $steals,
        public int $blocks,
        public int $turnovers,
        public int $fgm,
        public int $fga,
        public int $ftm,
        public int $fta,
        public int $tgm,
        public int $tga,
        public ProductionSource $source,
    ) {
    }

    /**
     * ibl_plr has no stats_pts. stats_fgm already counts threes, so points are
     * 2 * fgm + 3gm + ftm.
     *
     * @param array<string, mixed> $row
     */
    public static function fromCurrentRow(array $row): self
    {
        $fgm = self::int($row, 'stats_fgm');
        $tgm = self::int($row, 'stats_3gm');
        $ftm = self::int($row, 'stats_ftm');

        return new self(
            games: self::int($row, 'stats_gm'),
            minutes: self::int($row, 'stats_min'),
            points: 2 * $fgm + $tgm + $ftm,
            rebounds: self::int($row, 'stats_orb') + self::int($row, 'stats_drb'),
            assists: self::int($row, 'stats_ast'),
            steals: self::int($row, 'stats_stl'),
            blocks: self::int($row, 'stats_blk'),
            turnovers: self::int($row, 'stats_tvr'),
            fgm: $fgm,
            fga: self::int($row, 'stats_fga'),
            ftm: $ftm,
            fta: self::int($row, 'stats_fta'),
            tgm: $tgm,
            tga: self::int($row, 'stats_3ga'),
            source: ProductionSource::CurrentSeason,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromHistRow(array $row): self
    {
        return new self(
            games: self::int($row, 'games'),
            minutes: self::int($row, 'minutes'),
            points: self::int($row, 'pts'),
            rebounds: self::int($row, 'reb'),
            assists: self::int($row, 'ast'),
            steals: self::int($row, 'stl'),
            blocks: self::int($row, 'blk'),
            turnovers: self::int($row, 'tvr'),
            fgm: self::int($row, 'fgm'),
            fga: self::int($row, 'fga'),
            ftm: self::int($row, 'ftm'),
            fta: self::int($row, 'fta'),
            tgm: self::int($row, 'tgm'),
            tga: self::int($row, 'tga'),
            source: ProductionSource::LastSeason,
        );
    }

    /**
     * The sample rule: the current line once it has enough minutes, else last
     * season's line if that one does, else no production at all.
     */
    public static function choose(?self $current, ?self $lastSeason): ?self
    {
        if ($current !== null && $current->minutes >= NeedThresholds::MIN_SAMPLE_MINUTES) {
            return $current;
        }
        if ($lastSeason !== null && $lastSeason->minutes >= NeedThresholds::MIN_SAMPLE_MINUTES) {
            return $lastSeason;
        }

        return null;
    }

    public function pointsPer36(): float
    {
        return $this->per36($this->points);
    }

    public function reboundsPer36(): float
    {
        return $this->per36($this->rebounds);
    }

    public function assistsPer36(): float
    {
        return $this->per36($this->assists);
    }

    public function pointsPerGame(): float
    {
        return $this->perGame($this->points);
    }

    public function reboundsPerGame(): float
    {
        return $this->perGame($this->rebounds);
    }

    public function assistsPerGame(): float
    {
        return $this->perGame($this->assists);
    }

    private function per36(int $total): float
    {
        if ($this->minutes === 0) {
            return 0.0;
        }

        return $total * 36 / $this->minutes;
    }

    private function perGame(int $total): float
    {
        if ($this->games === 0) {
            return 0.0;
        }

        return $total / $this->games;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }
}
