<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Pure formatting of the injuries-channel lines posted by the sim publisher.
 *
 * @phpstan-type InjuryLine array{name:string,teamAbbrev:string,injuryDescription:string,injuryGamesMissed:int}
 */
final class InjuryLineFormatter
{
    /**
     * Day header with no zero padding, for example "2009-01-04" -> "**1/4/09**".
     */
    public static function formatHeader(string $ymd): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
        if ($date === false) {
            throw new \InvalidArgumentException('Expected a Y-m-d date, got: ' . $ymd);
        }

        return '**' . $date->format('n/j/y') . '**';
    }

    /**
     * One injury line, for example "-B.Portis, MIN - left shoulder strain (4) <@123456789012345678>".
     * The mention is appended only when the team's GM snowflake is known.
     *
     * @param InjuryLine $injury
     */
    public static function formatLine(array $injury, ?string $gmSnowflake): string
    {
        $name = trim($injury['name']);
        $spacePos = strpos($name, ' ');
        if ($spacePos === false) {
            $shortName = $name;
        } else {
            $shortName = mb_substr($name, 0, 1) . '.' . substr($name, $spacePos + 1);
        }

        $line = '-' . $shortName . ', ' . $injury['teamAbbrev'] . ' - ' . $injury['injuryDescription']
            . ' (' . $injury['injuryGamesMissed'] . ')';
        if ($gmSnowflake !== null) {
            $line .= ' <@' . $gmSnowflake . '>';
        }

        return $line;
    }
}
