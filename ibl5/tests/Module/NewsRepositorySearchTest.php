<?php

declare(strict_types=1);

namespace Tests\Module;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Topics\News\NewsRepository;

class NewsRepositorySearchTest extends TestCase
{
    private const PREPARED_SQL = 'SELECT sid, title, time FROM nuke_stories WHERE hometext LIKE ? OR bodytext LIKE ? ORDER BY time DESC';

    public function testSearchStoriesMentioningPreparesPinnedSql(): void
    {
        $db = new MockDatabase();
        $db->onQuery('nuke_stories', []);

        (new NewsRepository($db))->searchStoriesMentioning('Kobe');

        $this->assertSame([self::PREPARED_SQL], $db->getPreparedQueries());
        $this->assertSame(['%Kobe%', '%Kobe%'], $db->getLastBoundParams());
    }

    public function testSearchStoriesMentioningEscapesLikeMetacharacters(): void
    {
        $db = new MockDatabase();
        $db->onQuery('nuke_stories', []);

        (new NewsRepository($db))->searchStoriesMentioning('50%_off\\');

        $expected = <<<'BOUND'
        %50\%\_off\\%
        BOUND;
        $this->assertSame([$expected, $expected], $db->getLastBoundParams());
    }

    public function testSearchStoriesMentioningReturnsRows(): void
    {
        $db = new MockDatabase();
        $db->onQuery('nuke_stories', [
            ['sid' => 7, 'title' => 'Big Game', 'time' => '2025-01-15 10:00:00'],
        ]);

        $rows = (new NewsRepository($db))->searchStoriesMentioning('Game');

        $this->assertSame([['sid' => 7, 'title' => 'Big Game', 'time' => '2025-01-15 10:00:00']], $rows);
    }
}
