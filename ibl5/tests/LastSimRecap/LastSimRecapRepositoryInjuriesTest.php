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

    public function testMergedGroupsKeepTransactionIdDescendingTiebreak(): void
    {
        $db = new MockDatabase();
        $repo = new LastSimRecapRepository($db);

        $row = static fn (int $id, int $pid): array => [
            'id' => $id,
            'pid' => $pid,
            'name' => 'Player ' . $pid,
            'pos' => 'PG',
            'injury_date' => '2030-04-10',
            'injury_description' => 'Sprained ankle',
            'injury_games_missed' => 5,
            'days_remaining' => 5,
            'return_date' => '2030-04-15',
            'is_new' => 1,
        ];

        // Equal isNew and date across both groups; only the transaction id differs.
        $db->onQuery('IN \(\s*\'?1\'?,', [$row(10, 1), $row(30, 2)]);
        $db->onQuery('IN \(\s*\'?65\'?,', [$row(20, 65), $row(40, 66)]);

        $result = $repo->getActiveInjuriesForPlayers(range(1, SqlPlaceholders::PID_LIST_WIDTH + 6), '2030-04-10');

        $this->assertCount(2, $db->getPreparedQueries());
        $this->assertSame([66, 2, 65, 1], array_column($result, 'pid'));
        $this->assertArrayNotHasKey('id', $result[0]);
    }
}
