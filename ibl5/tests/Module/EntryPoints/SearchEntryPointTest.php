<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Integration tests for modules/Search/index.php entry point.
 *
 * This module reads params from $GLOBALS (not $_GET directly), because
 * mainfile.php extracts $_REQUEST into $GLOBALS. Parameters: $query, $type,
 * $topic, $category, $author, $days, $min, $qlen — all using intval() or string cast.
 *
 * NOTE: Short queries (strlen < 3) trigger `header("Location: ..."); exit;`
 * which would kill PHPUnit. We test the error message path via $qlen=1 instead.
 */
class SearchEntryPointTest extends ModuleEntryPointTestCase
{
    public function testNoParamsShowsSearchForm(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Search');

        $this->assertNotEmpty($output);
        // No search query when no query param provided
        $this->assertQueryNotExecuted('LIKE');
    }

    public function testQlenFlagShowsErrorMessage(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Search', [], [], ['qlen' => '1']);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('3 characters', $output);
    }

    public function testValidQueryExecutesSearch(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Search', [], [], ['query' => 'test player']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testEmptyQueryDoesNotSearch(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Search', [], [], ['query' => '']);

        $this->assertNotEmpty($output);
        $this->assertQueryNotExecuted('LIKE');
    }

    public function testNonNumericDaysParamCastsToZero(): void
    {
        // intval('abc') === 0, meaning no date filter applied
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Search', [], [], [
            'query' => 'test player',
            'days' => 'abc',
        ]);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testNonNumericTopicCastsToZero(): void
    {
        // intval('invalid') === 0, meaning no topic filter
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Search', [], [], [
            'query' => 'test player',
            'topic' => 'invalid',
        ]);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_stories');
    }

    public function testTypeUsersDispatchesToUserSearch(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Search', [], [], [
            'query' => 'testuser',
            'type' => 'users',
        ]);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_users');
    }

    public function testNonNumericMinParamCastsToZero(): void
    {
        // intval('abc') === 0, meaning pagination starts at 0
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Search', [], [], [
            'query' => 'test player',
            'min' => 'abc',
        ]);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('nuke_stories');
    }
}
