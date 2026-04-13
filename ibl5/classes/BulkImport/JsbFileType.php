<?php

declare(strict_types=1);

namespace BulkImport;

/**
 * All JSB engine file types that can be bulk-imported from season archives.
 *
 * Each case defines its iteration mode (cumulative vs snapshot),
 * processing order, verification eligibility, and display label.
 */
enum JsbFileType: string
{
    case Trn = 'trn';
    case Car = 'car';
    case His = 'his';
    case Asw = 'asw';
    case Awa = 'awa';
    case Rcb = 'rcb';
    case Sco = 'sco';
    case Dra = 'dra';
    case Ret = 'ret';
    case Hof = 'hof';
    case Lge = 'lge';
    case Plr = 'plr';
    case Plb = 'plb';

    /**
     * Whether this type uses the final archive per season (cumulative)
     * or processes every archive (snapshot).
     */
    public function iterationMode(): string
    {
        return match ($this) {
            self::Plr, self::Plb => 'snapshot',
            default => 'cumulative',
        };
    }

    /**
     * Sort key for default processing order.
     *
     * Cumulative types run first (faster), snapshot types last (slower).
     * Within cumulative: trn before car (trade data helps PlayerIdResolver).
     */
    public function importOrder(): int
    {
        return match ($this) {
            self::Trn => 10,
            self::Car => 20,
            self::His => 30,
            self::Asw => 40,
            self::Awa => 50,
            self::Rcb => 60,
            self::Sco => 70,
            self::Dra => 80,
            self::Ret => 90,
            self::Hof => 100,
            self::Lge => 110,
            self::Plr => 120,
            self::Plb => 130,
        };
    }

    /**
     * Whether this type supports --verify mode.
     *
     * Cumulative types support verification (check file integrity without importing).
     * Snapshot types change every sim so verification is not meaningful.
     */
    public function supportsVerify(): bool
    {
        return $this->iterationMode() === 'cumulative';
    }

    /**
     * Human-readable label for CLI output.
     */
    public function label(): string
    {
        return match ($this) {
            self::Trn => '.trn (transactions)',
            self::Car => '.car (career stats)',
            self::His => '.his (season history)',
            self::Asw => '.asw (all-star rosters)',
            self::Awa => '.awa (awards)',
            self::Rcb => '.rcb (record book)',
            self::Sco => '.sco (box scores)',
            self::Dra => '.dra (draft results)',
            self::Ret => '.ret (retired players)',
            self::Hof => '.hof (hall of fame)',
            self::Lge => '.lge (league config)',
            self::Plr => '.plr (player snapshots)',
            self::Plb => '.plb (depth charts)',
        };
    }

    /**
     * All valid file type string values, for CLI validation.
     *
     * @return list<string>
     */
    public static function allValid(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    /**
     * All cases sorted by import order.
     *
     * @return list<self>
     */
    public static function sortedByImportOrder(): array
    {
        $cases = self::cases();
        usort($cases, static fn (self $a, self $b): int => $a->importOrder() <=> $b->importOrder());

        return $cases;
    }
}
