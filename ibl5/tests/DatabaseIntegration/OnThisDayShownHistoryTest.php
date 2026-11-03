<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use OnThisDay\OnThisDayRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * DB-integration tests for the shown-moment history in ibl_on_this_day_shown:
 * OnThisDayRepository::recordShown() writes it and findShownBefore() reads it.
 */
#[Group('database')]
class OnThisDayShownHistoryTest extends DatabaseTestCase
{
    private OnThisDayRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new OnThisDayRepository($this->db);
    }

    public function testRecordShownIsIdempotentPerSim(): void
    {
        self::assertSame(2, $this->repo->recordShown(10, 2007, ['k:a', 'k:b']));
        self::assertSame(0, $this->repo->recordShown(10, 2007, ['k:a', 'k:b']));
        self::assertSame(2, $this->rowCount(10));
    }

    public function testRecordShownWithNoKeysWritesNothing(): void
    {
        self::assertSame(0, $this->repo->recordShown(10, 2007, []));
        self::assertSame(0, $this->rowCount(10));
    }

    public function testFindShownBeforeReturnsLatestEarlierSimOnly(): void
    {
        $this->repo->recordShown(5, 2005, ['k:a']);
        $this->repo->recordShown(8, 2006, ['k:a']);
        $this->repo->recordShown(10, 2007, ['k:b']);

        self::assertSame(['k:a' => 8], $this->repo->findShownBefore(10));
    }

    public function testFindShownBeforeDegradesWhenTableMissing(): void
    {
        $recording = new RecordingMysqli($this->db, 1146, 'ibl_on_this_day_shown');

        $shown = (new OnThisDayRepository($recording))->findShownBefore(10);

        self::assertSame([], $shown);
        self::assertNotSame([], $recording->statements);
    }

    public function testShownHistoryQueriesBindEveryValue(): void
    {
        $recording = new RecordingMysqli($this->db);
        $repo = new OnThisDayRepository($recording);
        $key = "k:o'neil";

        self::assertSame(1, $repo->recordShown(7340, 2947, [$key]));
        self::assertSame([$key => 7340], $repo->findShownBefore(7341));

        $shownStatements = array_values(array_filter(
            $recording->statements,
            static fn (string $sql): bool => str_contains($sql, 'ibl_on_this_day_shown'),
        ));
        self::assertCount(2, $shownStatements);
        foreach ($shownStatements as $sql) {
            self::assertStringContainsString('?', $sql);
            self::assertStringNotContainsString('neil', $sql);
            self::assertStringNotContainsString('7340', $sql);
            self::assertStringNotContainsString('7341', $sql);
            self::assertStringNotContainsString('2947', $sql);
        }
    }

    private function rowCount(int $sim): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM ibl_on_this_day_shown WHERE sim = ?');
        self::assertNotFalse($stmt, 'Failed to prepare count: ' . $this->db->error);
        $stmt->bind_param('i', $sim);
        $stmt->execute();
        $count = null;
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        return is_int($count) ? $count : 0;
    }
}
