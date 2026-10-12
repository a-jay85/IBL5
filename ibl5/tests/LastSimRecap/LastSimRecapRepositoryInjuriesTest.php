<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use Database\SqlPlaceholders;
use LastSimRecap\LastSimRecapRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

final class LastSimRecapRepositoryInjuriesTest extends TestCase
{
    public function testEmptyPlayerListRunsNoQuery(): void
    {
        $db = new MockDatabase();
        $repo = new LastSimRecapRepository($db);

        $this->assertSame([], $repo->getActiveInjuriesForPlayers([], '2030-04-10'));
        $this->assertSame([], $db->getPreparedQueries());
    }

    public function testSmallRosterRunsOneQueryBoundToPaddedPidList(): void
    {
        $db = new MockDatabase();
        $repo = new LastSimRecapRepository($db);

        $repo->getActiveInjuriesForPlayers([5, 7], '2030-04-10');

        $prepared = $db->getPreparedQueries();
        $this->assertCount(1, $prepared);
        $this->assertStringContainsString('t.pid IN (' . SqlPlaceholders::PID_LIST . ')', $prepared[0]);
        $this->assertSame(
            ['2030-04-10', '2030-04-10', ...SqlPlaceholders::padPidList([5, 7]), '2030-04-10', '2030-04-10'],
            $db->getLastBoundParams()
        );
    }

    public function testRosterLargerThanListWidthRunsOneQueryPerGroup(): void
    {
        $db = new MockDatabase();
        $repo = new LastSimRecapRepository($db);

        $repo->getActiveInjuriesForPlayers(range(1, SqlPlaceholders::PID_LIST_WIDTH + 6), '2030-04-10');

        $prepared = $db->getPreparedQueries();
        $this->assertCount(2, $prepared);
        $this->assertSame(
            ['2030-04-10', '2030-04-10', ...SqlPlaceholders::padPidList(range(65, 70)), '2030-04-10', '2030-04-10'],
            $db->getLastBoundParams()
        );
    }
}
