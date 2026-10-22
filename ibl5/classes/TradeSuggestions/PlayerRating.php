<?php

declare(strict_types=1);

namespace TradeSuggestions;

/**
 * Overall player rating (0-100) for the trade suggestion engine. Pure.
 *
 * Same measure the contract-demand code uses to price a player
 * (Negotiation\ExtensionContractDemandCalculator): each of the 21 ratings as a
 * percentage of the league maximum (NegotiationRepository::getMarketMaximums()).
 * Here the 21 percentages are averaged, so a player at the league max in every
 * rating is 100. On prod data rostered players run about 35-80 and track salary.
 *
 * talent/skill/intangibles are coarse 1-5 scouting grades and are not used.
 *
 * @phpstan-type MarketMaximums array{fga: int, fgp: int, fta: int, ftp: int, tga: int, tgp: int, orb: int, drb: int, ast: int, stl: int, tov: int, blk: int, foul: int, oo: int, od: int, do: int, dd: int, po: int, pd: int, to: int, td: int}
 */
final class PlayerRating
{
    /**
     * Market-maximum key => ibl_plr column.
     *
     * @var array<string, string>
     */
    public const array COLUMNS = [
        'fga' => 'r_fga', 'fgp' => 'r_fgp', 'fta' => 'r_fta', 'ftp' => 'r_ftp',
        'tga' => 'r_3ga', 'tgp' => 'r_3gp', 'orb' => 'r_orb', 'drb' => 'r_drb',
        'ast' => 'r_ast', 'stl' => 'r_stl', 'tov' => 'r_tvr', 'blk' => 'r_blk',
        'foul' => 'r_foul', 'oo' => 'oo', 'od' => 'od', 'do' => 'r_drive_off',
        'dd' => 'dd', 'po' => 'po', 'pd' => 'pd', 'to' => 'r_trans_off', 'td' => 'td',
    ];

    /**
     * @param array<string, mixed> $row ibl_plr row holding every COLUMNS column
     * @param MarketMaximums $maximums
     */
    public static function overall(array $row, array $maximums): int
    {
        $sum = 0.0;
        foreach (self::COLUMNS as $key => $column) {
            $max = $maximums[$key] ?? 0;
            $value = $row[$column] ?? 0;
            if ($max > 0 && is_numeric($value)) {
                $sum += min(1.0, (float) $value / $max);
            }
        }

        return (int) round($sum / count(self::COLUMNS) * 100);
    }
}
