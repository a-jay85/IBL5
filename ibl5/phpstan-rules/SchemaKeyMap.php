<?php

declare(strict_types=1);

namespace PHPStanRules;

/**
 * Usable row keys per base table, parsed from the mysqldump schema file
 * (ibl5/docs/schema/current-schema.sql, written by bin/regen-schema-dump).
 *
 * A key is usable when no two rows can share its value: every PRIMARY KEY, and
 * every UNIQUE key whose columns are all NOT NULL. A UNIQUE key over a nullable
 * column is dropped, because MySQL lets many NULL rows share it.
 *
 * Views appear in the dump as /*!50001 CREATE VIEW ... *\/ and never match the
 * CREATE TABLE pattern, so keysFor() returns null for them. A missing or
 * unreadable dump yields an empty map, so every table reads as absent.
 *
 * Used by OrderByRequiresUniqueTiebreakerRule (ADR-0083 and its follow-up ADR).
 */
final class SchemaKeyMap
{
    private const KEY_WORDS = ['primary', 'unique', 'key', 'index', 'constraint', 'fulltext', 'spatial', 'check', 'foreign'];

    /** @var array<string, self> per-process cache keyed by path */
    private static array $cache = [];

    /** @param array<string, list<list<string>>> $keysByTable lowercased table => usable keys */
    private function __construct(private readonly array $keysByTable)
    {
    }

    /**
     * Unreadable or missing file => empty map (every table absent).
     */
    public static function fromSchemaDump(string $path): self
    {
        if (isset(self::$cache[$path])) {
            return self::$cache[$path];
        }

        $sql = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($sql === false) {
            return self::$cache[$path] = new self([]);
        }

        return self::$cache[$path] = new self(self::parse($sql));
    }

    /**
     * @return list<list<string>>|null null when the table is absent from the dump
     */
    public function keysFor(string $table): ?array
    {
        return $this->keysByTable[strtolower($table)] ?? null;
    }

    /**
     * @return array<string, list<list<string>>>
     */
    private static function parse(string $sql): array
    {
        // One block per base table. Views are /*!50001 CREATE VIEW */ and never match.
        preg_match_all(
            '/^CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`? \((.*?)^\)[^\n]*;/ms',
            $sql,
            $blocks,
            PREG_SET_ORDER,
        );

        $keysByTable = [];
        foreach ($blocks as $block) {
            $lines = explode("\n", $block[2]);

            // Pass 1: NOT NULL columns.
            $notNull = [];
            foreach ($lines as $line) {
                if (preg_match('/^\s*`?(\w+)`?\s+(.*)$/', $line, $col) !== 1) {
                    continue;
                }
                $name = strtolower($col[1]);
                if (!str_starts_with(ltrim($line), '`') && in_array($name, self::KEY_WORDS, true)) {
                    continue;
                }
                $notNull[$name] = preg_match('/\bNOT NULL\b/i', $col[2]) === 1;
            }

            // Pass 2: usable keys.
            $keys = [];
            foreach ($lines as $line) {
                if (preg_match('/^\s*PRIMARY KEY\s*\((.+)\)/i', $line, $m) === 1) {
                    $keys[] = self::columnList($m[1]);
                    continue;
                }
                if (preg_match('/^\s*UNIQUE (?:KEY|INDEX)\s*(?:`?\w+`?\s*)?\((.+)\)/i', $line, $m) === 1) {
                    $columns = self::columnList($m[1]);
                    $allNotNull = true;
                    foreach ($columns as $column) {
                        if (($notNull[$column] ?? false) !== true) {
                            $allNotNull = false;
                            break;
                        }
                    }
                    if ($allNotNull) {
                        $keys[] = $columns;
                    }
                }
            }

            $keysByTable[strtolower($block[1])] = $keys;
        }

        return $keysByTable;
    }

    /**
     * @return list<string>
     */
    private static function columnList(string $list): array
    {
        $columns = [];
        foreach (explode(',', $list) as $part) {
            $part = str_replace('`', '', $part);
            $part = (string) preg_replace('/\(\d+\)\s*$/', '', trim($part));
            $part = strtolower(trim($part));
            if ($part !== '') {
                $columns[] = $part;
            }
        }

        return $columns;
    }
}
