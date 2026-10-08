<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\TestCase;

/**
 * Ratchet blocking new copies of the player-to-team JOIN (backlog 13.12).
 *
 * `Repositories\PlayerTeamJoinQuery` owns the aliased clause
 * (`ibl_team_info t ON p.teamid = t.teamid`). Any other file under
 * `ibl5/classes/` that spells it out fails here. The unaliased form is frozen
 * to a single allowlisted file, so the allowlist can only shrink.
 *
 * Each hit is reported as `relative/path.php:LINE`, for example
 * ContractList/ContractListRepository.php:29 if that site regrew its own copy.
 *
 * Known blind spot: a copy written with a different alias (for example `ti`)
 * is not matched.
 */
class PlayerTeamJoinRatchetTest extends TestCase
{
    private const ALIASED_JOIN = '/ibl_team_info`?\s+t\s+ON\s+(?:p\.teamid\s*=\s*t\.teamid|t\.teamid\s*=\s*p\.teamid)/i';

    private const UNALIASED_JOIN = '/`?ibl_plr`?\.`?teamid`?\s*=\s*`?ibl_team_info`?\.`?teamid`?|`?ibl_team_info`?\.`?teamid`?\s*=\s*`?ibl_plr`?\.`?teamid`?/i';

    private const TRAIT_FILE = 'Repositories/PlayerTeamJoinQuery.php';

    /**
     * PlayerSearchRepository joins on unaliased table names because its
     * QueryConditions builder writes the WHERE against `ibl_plr` columns, so it
     * cannot take the `p`/`t` alias fragment without rewriting that builder
     * (backlog 13.12, documented exception).
     */
    private const UNALIASED_ALLOWLIST = ['PlayerSearch/PlayerSearchRepository.php'];

    public function testNoAliasedPlayerTeamJoinOutsideTrait(): void
    {
        $files = self::loadClassFiles();
        unset($files[self::TRAIT_FILE]);

        $hits = self::findMatches(self::ALIASED_JOIN, $files);

        $this->assertSame(
            [],
            $hits,
            "Aliased player-to-team JOIN copied outside the trait:\n  " . implode("\n  ", $hits)
            . "\nUse playerTeamLeftJoin() / playerTeamInnerJoin() from Repositories\\PlayerTeamJoinQuery."
        );
    }

    public function testTraitIsTheOnlyAliasedJoinSource(): void
    {
        $files = self::loadClassFiles();
        $this->assertArrayHasKey(self::TRAIT_FILE, $files);

        $hits = self::findMatches(self::ALIASED_JOIN, [self::TRAIT_FILE => $files[self::TRAIT_FILE]]);

        // Each fragment contributes its return literal plus its PHPDoc literal type.
        $this->assertCount(4, $hits, implode("\n", $hits));
    }

    public function testUnaliasedPlayerTeamJoinOnlyInAllowlistedFile(): void
    {
        $hits = self::findMatches(self::UNALIASED_JOIN, self::loadClassFiles());
        $paths = array_values(array_unique(array_map(
            static fn (string $hit): string => substr($hit, 0, (int) strrpos($hit, ':')),
            $hits
        )));

        $this->assertSame(self::UNALIASED_ALLOWLIST, $paths);
    }

    public function testMatcherFlagsEveryCopyShape(): void
    {
        $aliased = [
            'LEFT JOIN `ibl_team_info` t ON p.teamid = t.teamid',
            'JOIN ibl_team_info t ON p.teamid = t.teamid',
            'INNER JOIN `ibl_team_info` t ON t.teamid = p.teamid',
            "LEFT JOIN ibl_team_info t\n    ON p.teamid=t.teamid",
        ];
        foreach ($aliased as $fixture) {
            $this->assertCount(1, self::findMatches(self::ALIASED_JOIN, ['fixture.php' => $fixture]), $fixture);
        }

        $unaliased = 'LEFT JOIN `ibl_team_info` ON `ibl_plr`.`teamid` = `ibl_team_info`.`teamid`';
        $this->assertCount(1, self::findMatches(self::UNALIASED_JOIN, ['fixture.php' => $unaliased]));
    }

    public function testMatcherIgnoresOtherTeamInfoJoins(): void
    {
        $others = [
            'JOIN `ibl_team_info` t ON bs.name = t.team_name',
            'JOIN ibl_team_info t ON h.teamid = t.teamid',
            'LEFT JOIN ibl_team_info t ON s.teamid = t.teamid',
            // Known blind spot: a different alias is not matched.
            'JOIN ibl_team_info ti ON p.teamid = ti.teamid',
        ];
        foreach ($others as $fixture) {
            $files = ['fixture.php' => $fixture];
            $this->assertSame([], self::findMatches(self::ALIASED_JOIN, $files), $fixture);
            $this->assertSame([], self::findMatches(self::UNALIASED_JOIN, $files), $fixture);
        }
    }

    /**
     * @param array<string, string> $files relative path => file contents
     * @return list<string> sorted `relative/path.php:LINE` for every match
     */
    private static function findMatches(string $pattern, array $files): array
    {
        $hits = [];
        foreach ($files as $path => $contents) {
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }
            foreach ($matches[0] as [, $offset]) {
                $hits[] = $path . ':' . (substr_count(substr($contents, 0, $offset), "\n") + 1);
            }
        }
        sort($hits);

        return $hits;
    }

    /**
     * @return array<string, string> path relative to classes/ => file contents
     */
    private static function loadClassFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/classes';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            if ($contents === false) {
                self::fail('Could not read ' . $file->getPathname());
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $files[$relative] = $contents;
        }

        return $files;
    }
}
