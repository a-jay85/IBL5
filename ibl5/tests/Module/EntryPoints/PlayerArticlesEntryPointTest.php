<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Pins modules/Player/articles.php: the search term is bound, never concatenated,
 * and LIKE metacharacters are escaped without mysqli string escaping.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PlayerArticlesEntryPointTest extends ModuleEntryPointTestCase
{
    private function runArticles(string $player): string
    {
        $_REQUEST['player'] = $player;
        $GLOBALS['mysqli_db'] = $this->mockDb;
        ob_start();
        require __DIR__ . '/../../../modules/Player/articles.php';

        return (string) ob_get_clean();
    }

    /** @return list<mixed> */
    private function lastBoundParams(): array
    {
        return $this->mockDb->getLastBoundParams();
    }

    public function testEmptyPlayerRendersNoticeAndRunsNoQuery(): void
    {
        $output = $this->runArticles('   ');
        $this->assertStringContainsString('No player specified', $output);
        $this->assertSame([], $this->mockDb->getExecutedQueries());
    }

    public function testApostropheRoundTripsWithoutMysqliEscaping(): void
    {
        $this->mockDb->onQuery('nuke_stories', []);
        $this->runArticles("Shaquille O'Neal");
        $this->assertSame(["%Shaquille O'Neal%", "%Shaquille O'Neal%"], $this->lastBoundParams());
    }

    public function testBackslashAndWildcardsAreEscapedForLike(): void
    {
        $this->mockDb->onQuery('nuke_stories', []);
        $this->runArticles('100% a_b c\\d');
        $expected = '%100\\% a\\_b c\\\\d%';
        $this->assertSame([$expected, $expected], $this->lastBoundParams());
    }

    public function testInjectionPayloadNeverReachesSqlText(): void
    {
        $this->mockDb->onQuery('nuke_stories', []);
        $payload = "x' OR 1=1; DROP TABLE nuke_stories; -- ";
        $this->runArticles($payload);

        $prepared = $this->mockDb->getPreparedQueries();
        $this->assertCount(1, $prepared);
        $this->assertStringContainsString('hometext LIKE ? OR bodytext LIKE ?', $prepared[0]);
        $this->assertStringNotContainsString('DROP TABLE', $prepared[0]);

        // trim() drops the trailing space; addcslashes escapes the `_` in nuke_stories.
        $expected = "%x' OR 1=1; DROP TABLE nuke\\_stories; --%";
        $this->assertSame([$expected, $expected], $this->lastBoundParams());
    }

    public function testRendersMatchingArticleRows(): void
    {
        $this->mockDb->onQuery('nuke_stories', [
            ['sid' => 7, 'title' => 'Big <b>Game</b>', 'time' => '2025-01-15 10:00:00'],
        ]);
        $output = $this->runArticles('Test Player');
        $this->assertStringContainsString('sid=7', $output);
        $this->assertStringContainsString('Big &lt;b&gt;Game&lt;/b&gt;', $output);
    }
}
