<?php

declare(strict_types=1);

namespace JsbParser;

/**
 * JSB writes an apostrophe in a player name as a double quote in the award
 * text it emits (backlog#1828). No IBL player name contains a double quote,
 * so every ibl_awards writer maps it back to an apostrophe before storing.
 * HTML entities are left as they are; LeadersHtmParser keeps its no-decode contract.
 */
final class PlayerNameNormalizer
{
    public static function normalize(string $name): string
    {
        return str_replace('"', "'", $name);
    }
}
