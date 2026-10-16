<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;

/**
 * Structural checks over the player view HTML snapshots.
 *
 * Each snapshot is pinned byte-equal to its renderer by that renderer's own
 * test, so asserting on the files asserts on renderer output.
 */
final class PlayerViewTableMarkupTest extends TestCase
{
    private const PLAYER_TABLE_XPATH = "//table[contains(concat(' ', normalize-space(@class), ' '), ' player-view-table ')"
        . " or contains(concat(' ', normalize-space(@class), ' '), ' stats-table ')]";

    public function testNoColumnHeaderOutsideTheadInPlayerViewTables(): void
    {
        $theadHeaderCount = 0;

        foreach ($this->loadSnapshots() as $file => $xpath) {
            $tables = $xpath->query(self::PLAYER_TABLE_XPATH);
            $this->assertNotFalse($tables, $file);

            foreach ($tables as $table) {
                $strayHeaders = $xpath->query('.//th[not(ancestor::thead)]', $table);
                $this->assertNotFalse($strayHeaders, $file);
                $this->assertSame(0, $strayHeaders->length, "{$file}: <th> outside <thead>");

                $theadHeaders = $xpath->query('.//thead//th', $table);
                $this->assertNotFalse($theadHeaders, $file);
                $theadHeaderCount += $theadHeaders->length;
            }
        }

        $this->assertGreaterThan(0, $theadHeaderCount, 'No <thead> <th> found in any snapshot');
    }

    public function testCareerRowsOnlyInTfoot(): void
    {
        $tfootRowCount = 0;

        foreach ($this->loadSnapshots() as $file => $xpath) {
            $strayCareerRows = $xpath->query(
                "//tr[contains(concat(' ', normalize-space(@class), ' '), ' career-row ')][not(ancestor::tfoot)]"
            );
            $this->assertNotFalse($strayCareerRows, $file);
            $this->assertSame(0, $strayCareerRows->length, "{$file}: career row outside <tfoot>");

            $tfootRows = $xpath->query('//tfoot//tr');
            $this->assertNotFalse($tfootRows, $file);
            $tfootRowCount += $tfootRows->length;
        }

        $this->assertGreaterThan(0, $tfootRowCount, 'No <tfoot> rows found in any snapshot');
    }

    /**
     * @return array<string, \DOMXPath>
     */
    private function loadSnapshots(): array
    {
        $files = [];
        foreach ([__DIR__ . '/__snapshots__/*.html', __DIR__ . '/../Stats/Views/__snapshots__/*.html'] as $pattern) {
            $matches = glob($pattern);
            $this->assertIsArray($matches, $pattern);
            $files = array_merge($files, $matches);
        }
        $this->assertNotEmpty($files, 'No snapshot files found');

        $previous = libxml_use_internal_errors(true);
        $xpaths = [];
        foreach ($files as $file) {
            $html = file_get_contents($file);
            $this->assertIsString($html, $file);

            $doc = new \DOMDocument();
            $doc->loadHTML('<html><body>' . $html . '</body></html>');
            $xpaths[basename($file)] = new \DOMXPath($doc);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xpaths;
    }
}
