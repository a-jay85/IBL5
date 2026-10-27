<?php

declare(strict_types=1);

namespace Tests\PlrParser;

/**
 * Builds a tiny fixed-width IBL5.plr (609-byte lines) for the bulk-editor script tests.
 */
final class SyntheticPlrCorpus
{
    public const LINE_LENGTH = 607;
    public const EOL = "\r\n";

    /**
     * Rows: [pid, teamId, exp, bird, currentContractYear, totalContractYears, contractYear1, contractOwnedBy]
     *
     * @var list<array{int, int, int, int, int, int, int, int}>
     */
    private const ROWS = [
        [101, 5, 3, 2, 0, 0, 500, 5],
        [102, 7, 9, 9, 1, 3, 800, 7],
        [103, 4, 5, 6, 0, 2, 300, 4],
        [104, 0, 2, 1, 0, 0, 0, 0],
        [0, 0, 0, 0, 0, 0, 0, 0],
    ];

    public static function line(
        int $pid,
        int $teamId,
        int $exp,
        int $bird,
        int $currentContractYear,
        int $totalContractYears,
        int $contractYear1,
        int $contractOwnedBy,
    ): string {
        $line = str_repeat(' ', self::LINE_LENGTH);
        $line = substr_replace($line, str_pad("Player $pid", 32), 4, 32);
        $line = substr_replace($line, sprintf('%6d', $pid), 38, 6);
        $line = substr_replace($line, sprintf('%2d', $teamId), 44, 2);
        $line = substr_replace($line, sprintf('%2d', $exp), 286, 2);
        $line = substr_replace($line, sprintf('%2d', $bird), 288, 2);
        $line = substr_replace($line, sprintf('%2d', $currentContractYear), 290, 2);
        $line = substr_replace($line, sprintf('%2d', $totalContractYears), 292, 2);
        $line = substr_replace($line, sprintf('%4d', $contractYear1), 298, 4);
        $line = substr_replace($line, sprintf('%2d', $contractOwnedBy), 331, 2);

        return $line . self::EOL;
    }

    public static function corpus(): string
    {
        $out = '';
        foreach (self::ROWS as $row) {
            $out .= self::line(...$row);
        }

        return $out;
    }

    /**
     * Independent expected bytes after one script ran live over corpus().
     *
     * @param 'exp'|'waivers'|'bird' $script
     */
    public static function corpusAfter(string $script): string
    {
        $out = '';
        foreach (self::ROWS as [$pid, $team, $exp, $bird, $cy, $ty, $c1, $own]) {
            if ($script === 'exp' && $pid !== 0) {
                $exp++;
            } elseif ($script === 'waivers' && $team !== 0 && $cy === 0 && $ty === 0) {
                $team = 0;
                $own = 0;
            } elseif ($script === 'bird' && $pid !== 0 && $team !== 0 && $c1 !== 0 && $bird <= $exp) {
                $bird++;
            }
            $out .= self::line($pid, $team, $exp, $bird, $cy, $ty, $c1, $own);
        }

        return $out;
    }
}
