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
    private const PREPARED_SQL = 'SELECT sid, title, time FROM nuke_stories WHERE hometext LIKE ? OR bodytext LIKE ? ORDER BY time DESC';

    private function runArticles(string $player): string
    {
        $_REQUEST['player'] = $player;
        $GLOBALS['mysqli_db'] = $this->mockDb;
        ob_start();
        require __DIR__ . '/../../../modules/Player/articles.php';

        return (string) ob_get_clean();
    }

    /** Runs the module with $_REQUEST['player'] left unset. */
    private function runArticlesWithoutPlayerKey(): string
    {
        unset($_REQUEST['player']);
        $GLOBALS['mysqli_db'] = $this->mockDb;
        ob_start();
        require __DIR__ . '/../../../modules/Player/articles.php';

        return (string) ob_get_clean();
    }

    private function runArticlesWithRawPlayer(mixed $player): string
    {
        $_REQUEST['player'] = $player;
        $GLOBALS['mysqli_db'] = $this->mockDb;
        ob_start();
        require __DIR__ . '/../../../modules/Player/articles.php';

        return (string) ob_get_clean();
    }

    private function articlesSource(): string
    {
        $source = file_get_contents(__DIR__ . '/../../../modules/Player/articles.php');
        $this->assertIsString($source);

        return $source;
    }

    private static function anchor(string $sid, string $title, string $time): string
    {
        return "\n* <a href=\"modules.php?name=News&amp;file=article&amp;sid={$sid}&amp;mode=&amp;order=0&amp;thold=0\">{$title}</a> ({$time})<br>";
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

    /** Pins `empty('0')` being true: the string '0' counts as no player. */
    public function testPlayerZeroRendersNoticeAndRunsNoQuery(): void
    {
        $output = $this->runArticles('0');
        $this->assertStringContainsString('No player specified', $output);
        $this->assertSame([], $this->mockDb->getExecutedQueries());
        $this->assertSame([], $this->mockDb->getPreparedQueries());
    }

    public function testMissingPlayerKeyRendersNoticeAndRunsNoQuery(): void
    {
        $output = $this->runArticlesWithoutPlayerKey();
        $this->assertStringContainsString('No player specified', $output);
        $this->assertSame([], $this->mockDb->getExecutedQueries());
        $this->assertSame([], $this->mockDb->getPreparedQueries());
    }

    public function testWhitespacePaddedZeroRendersNotice(): void
    {
        $output = $this->runArticles(' 0 ');
        $this->assertStringContainsString('No player specified', $output);
        $this->assertSame([], $this->mockDb->getExecutedQueries());
        $this->assertSame([], $this->mockDb->getPreparedQueries());
    }

    public function testRendersFullAnchorLinesForMultipleRows(): void
    {
        $this->mockDb->onQuery('nuke_stories', [
            ['sid' => 7, 'title' => 'Big <b>Game</b>', 'time' => '2025-01-15 10:00:00'],
            ['sid' => 12, 'title' => 'Second', 'time' => '2025-01-16 11:30:00'],
        ]);
        $output = $this->runArticles('Test Player');
        $this->assertSame(
            '<small>'
            . self::anchor('7', 'Big &lt;b&gt;Game&lt;/b&gt;', '2025-01-15 10:00:00')
            . self::anchor('12', 'Second', '2025-01-16 11:30:00')
            . '</small>',
            $output
        );
    }

    public function testNullTitleAndTimeRenderAsEmptyStrings(): void
    {
        $this->mockDb->onQuery('nuke_stories', [
            ['sid' => 8, 'title' => null, 'time' => null],
        ]);
        $output = $this->runArticles('Test Player');
        $this->assertSame('<small>' . self::anchor('8', '', '') . '</small>', $output);
    }

    public function testTimeAndTitleAreHtmlEscapedWithEntQuotes(): void
    {
        $this->mockDb->onQuery('nuke_stories', [
            ['sid' => 9, 'title' => "O'Neal \"MVP\" <i>", 'time' => '2025 "x" <y> \'z\''],
        ]);
        $output = $this->runArticles('Test Player');
        $this->assertStringContainsString('O&#039;Neal &quot;MVP&quot; &lt;i&gt;', $output);
        $this->assertStringContainsString('(2025 &quot;x&quot; &lt;y&gt; &#039;z&#039;)', $output);
        $this->assertStringNotContainsString('<i>', $output);
    }

    public function testNumericStringSidRendersSameBytes(): void
    {
        $this->mockDb->onQuery('nuke_stories', [
            ['sid' => '7', 'title' => 'T', 'time' => '2025-01-01 00:00:00'],
        ]);
        $output = $this->runArticles('Test Player');
        $this->assertStringContainsString('sid=7&amp;mode=', $output);
    }

    public function testNoRowsRendersEmptySmallWrapper(): void
    {
        $this->mockDb->onQuery('nuke_stories', []);
        $output = $this->runArticles('Test Player');
        $this->assertSame('<small></small>', $output);
    }

    public function testPreparedSqlLiteralAndBoundParamsAreUnchanged(): void
    {
        $this->mockDb->onQuery('nuke_stories', []);
        $this->runArticles('Kobe');
        $this->assertSame([self::PREPARED_SQL], $this->mockDb->getPreparedQueries());
        $this->assertSame(['%Kobe%', '%Kobe%'], $this->lastBoundParams());
    }

    public function testArticlesSourcePinsPreparedStatementAndEscapingCallSites(): void
    {
        $source = $this->articlesSource();

        $this->assertSame(1, substr_count($source, '->prepare('));
        $this->assertStringContainsString('$query = "' . self::PREPARED_SQL . '";', $source);
        $this->assertStringContainsString('$stmt->bind_param(\'ss\', $searchTerm, $searchTerm);', $source);
        $this->assertSame(3, substr_count($source, 'htmlspecialchars('));
        $this->assertSame(3, substr_count($source, ", ENT_QUOTES, 'UTF-8')"));
        $this->assertStringContainsString(
            '* <a href=\"modules.php?name=News&amp;file=article&amp;sid=$sid&amp;mode=&amp;order=0&amp;thold=0\">$title</a> ($time)<br>";',
            $source
        );
        $this->assertStringNotContainsString('real_escape_string', $source);
        $this->assertStringNotContainsString('->query(', $source);
        $this->assertStringNotContainsString('$player . ', $source);
        $this->assertStringNotContainsString('. $player', $source);
        $this->assertStringContainsString('\'%\' . addcslashes($player, \'\\\\%_\') . \'%\'', $source);
    }

    /** A `player[]=x` request value is an array; it takes the no-player path instead of a TypeError. */
    public function testArrayPlayerInputRendersNoticeAndRunsNoQuery(): void
    {
        $output = $this->runArticlesWithRawPlayer(['x']);

        $this->assertStringContainsString('No player specified', $output);
        $this->assertSame([], $this->mockDb->getPreparedQueries());
        $this->assertSame([], $this->mockDb->getExecutedQueries());
    }

    public function testFalseResultRendersErrorNoticeWithoutRows(): void
    {
        $stmt = new class {
            public function bind_param(string $types, string ...$vars): bool
            {
                return true;
            }

            public function execute(): bool
            {
                return true;
            }

            public function get_result(): false
            {
                return false;
            }

            public function close(): bool
            {
                return true;
            }
        };
        $GLOBALS['mysqli_db'] = new class ($stmt) {
            public function __construct(private object $stmt)
            {
            }

            public function prepare(string $query): object
            {
                return $this->stmt;
            }
        };
        $_REQUEST['player'] = 'Kobe';
        ob_start();
        require __DIR__ . '/../../../modules/Player/articles.php';
        $output = (string) ob_get_clean();

        $this->assertSame('<small>Error searching for articles.</small>', $output);
    }
}
