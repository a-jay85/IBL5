<?php

declare(strict_types=1);

namespace Tests\Bootstrap;

use Tests\WideUnit\WideUnitTestCase;

require_once __DIR__ . '/../../classes/Bootstrap/LegacyFunctions.php';
require_once __DIR__ . '/../Module/EntryPoints/theme-stubs.php';

final class BlocksFunctionTest extends WideUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // sets up $this->mockDb and injects $GLOBALS['mysqli_db']
        $GLOBALS['prefix'] = 'nuke';
        $GLOBALS['multilingual'] = 0;
        $GLOBALS['currentlang'] = 'english';
        $GLOBALS['storynum'] = 10;
        $GLOBALS['user'] = '';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['prefix'], $GLOBALS['multilingual'], $GLOBALS['currentlang'], $GLOBALS['storynum'], $GLOBALS['user']);
        parent::tearDown();
    }

    public function testExpiredActionDeleteFiresParameterizedUpdate(): void
    {
        $this->mockDb->onQuery('_blocks', [[
            'bid' => 7, 'bkey' => '', 'title' => 'X', 'content' => 'c',
            'url' => null, 'blockfile' => null, 'view' => 0,
            'expire' => 1, 'action' => 'd', 'subscription' => 0,
        ]]);

        $this->captureOutput(static fn () => blocks('Center'));

        $this->assertQueryExecuted('UPDATE nuke_blocks SET active');
        $queries = $this->getExecutedQueries();
        $updateQuery = '';
        foreach ($queries as $q) {
            if (stripos($q, 'UPDATE nuke_blocks') !== false) {
                $updateQuery = $q;
                break;
            }
        }
        self::assertStringContainsString('bid = 7', $updateQuery);
        $this->assertQueryNotExecuted('DELETE FROM nuke_blocks');
    }

    public function testExpiredActionRemoveFiresParameterizedDelete(): void
    {
        $this->mockDb->onQuery('_blocks', [[
            'bid' => 7, 'bkey' => '', 'title' => 'X', 'content' => 'c',
            'url' => null, 'blockfile' => null, 'view' => 0,
            'expire' => 1, 'action' => 'r', 'subscription' => 0,
        ]]);

        $this->captureOutput(static fn () => blocks('Center'));

        $this->assertQueryExecuted('DELETE FROM nuke_blocks');
        $queries = $this->getExecutedQueries();
        $deleteQuery = '';
        foreach ($queries as $q) {
            if (stripos($q, 'DELETE FROM nuke_blocks') !== false) {
                $deleteQuery = $q;
                break;
            }
        }
        self::assertStringContainsString('bid = 7', $deleteQuery);
        $this->assertQueryNotExecuted('UPDATE nuke_blocks SET active');
    }

    public function testActiveBlockSelectIsParameterized(): void
    {
        $this->mockDb->onQuery('_blocks', [[
            'bid' => 3, 'bkey' => '', 'title' => 'T', 'content' => 'body',
            'url' => null, 'blockfile' => null, 'view' => 0,
            'expire' => 0, 'action' => '', 'subscription' => 0,
        ]]);

        $this->captureOutput(static fn () => blocks('Center'));

        $queries = $this->getExecutedQueries();
        $selectQuery = '';
        foreach ($queries as $q) {
            if (stripos($q, 'SELECT') !== false && stripos($q, 'nuke_blocks') !== false) {
                $selectQuery = $q;
                break;
            }
        }
        self::assertNotEmpty($selectQuery, 'Expected a SELECT on nuke_blocks to be executed');
        self::assertStringContainsString("bposition = 'c'", $selectQuery);
    }
}
