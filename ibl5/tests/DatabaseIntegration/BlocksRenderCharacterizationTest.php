<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Characterization pin for the two homepage leaders blocks.
 *
 * `Season\Season` is class-aliased to the DB-free mock in every PHPUnit run, so the
 * sim window the Chunk_Leaders block sees is the mock's fixed 2024-01-01..2024-01-02.
 * Box rows are seeded around that window.
 */
#[Group('database')]
final class BlocksRenderCharacterizationTest extends DatabaseTestCase
{
    private const IN_WINDOW_DATE = '2024-01-02';
    private const OUT_OF_WINDOW_DATE = '2099-01-20';

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db'], $GLOBALS['leagueContext']);

        parent::tearDown();
    }

    private function renderBlock(string $blockFile): string
    {
        if (!defined('BLOCK_FILE')) {
            define('BLOCK_FILE', true);
        }

        $GLOBALS['mysqli_db'] = $this->db;
        $GLOBALS['leagueContext'] = new \League\LeagueContext();

        $content = $this->includeBlock($blockFile);

        self::assertIsString($content);

        return $content;
    }

    private function includeBlock(string $blockFile): mixed
    {
        $content = null;
        include __DIR__ . '/../../blocks/' . $blockFile;

        return $content;
    }

    public function testChunkLeadersRendersTopScorerFromLastSimWindow(): void
    {
        $this->insertTestPlayer(200137301, 'Chunk InWindow', ['teamid' => 1]);
        $this->insertPlayerBoxscoreRow(self::IN_WINDOW_DATE, 200137301, 'Chunk InWindow', 'PG', 2, 1, 1, points2m: 10);

        $content = $this->renderBlock('block-Chunk_Leaders.php');

        self::assertStringContainsString('Chunk InWindow', $content);
        self::assertStringContainsString('leaders-tabbed__leader-name', $content);
        self::assertStringContainsString('>PTS</button>', $content);
    }

    public function testChunkLeadersExcludesBoxScoresOutsideLastSimWindow(): void
    {
        $this->insertTestPlayer(200137301, 'Chunk InWindow', ['teamid' => 1]);
        $this->insertTestPlayer(200137302, 'Chunk OutWindow', ['teamid' => 2]);
        $this->insertPlayerBoxscoreRow(self::IN_WINDOW_DATE, 200137301, 'Chunk InWindow', 'PG', 2, 1, 1, points2m: 10);
        $this->insertPlayerBoxscoreRow(self::OUT_OF_WINDOW_DATE, 200137302, 'Chunk OutWindow', 'PG', 1, 2, 2, points2m: 40);

        $content = $this->renderBlock('block-Chunk_Leaders.php');

        self::assertStringContainsString('Chunk InWindow', $content);
        self::assertStringNotContainsString('Chunk OutWindow', $content);
    }

    public function testChunkLeadersRendersNoPanelsWhenWindowHasNoGames(): void
    {
        $content = $this->renderBlock('block-Chunk_Leaders.php');

        self::assertStringContainsString('leaders-tabbed__title', $content);
        self::assertStringNotContainsString('role="tabpanel"', $content);
    }

    public function testLeadersBlockRendersNonEmptyContent(): void
    {
        $content = $this->renderBlock('block-Leaders.php');

        self::assertNotSame('', $content);
        self::assertStringContainsString('<div class="leaders-tabbed" id="season-leaders-', $content);
    }

    public function testChunkLeadersRendersEmptyWhenDatabaseGlobalMissing(): void
    {
        if (!defined('BLOCK_FILE')) {
            define('BLOCK_FILE', true);
        }

        $GLOBALS['mysqli_db'] = null;
        $GLOBALS['leagueContext'] = new \League\LeagueContext();

        $content = $this->includeBlock('block-Chunk_Leaders.php');

        self::assertSame('', $content);
    }
}
