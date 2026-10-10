<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use RecordHolders\RecordAnnouncementRepository;

/**
 * Direct tests for RecordAnnouncementRepository against the real schema.
 *
 * Isolation: the seed already holds a `cache` row and `ibl_sim_dates` rows, so
 * each test plants its own latest sim (sim 4000000001) and box scores in the
 * year-2098 window, and deletes the announcement cache key when it needs "absent".
 */
#[Group('database')]
final class RecordAnnouncementRepositoryTest extends DatabaseTestCase
{
    private const CACHE_KEY = 'record_announcements_last_date';

    private RecordAnnouncementRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new RecordAnnouncementRepository($this->db);
    }

    // --- getLastAnnouncedDate ---

    public function testGetLastAnnouncedDateReturnsNullWhenKeyAbsent(): void
    {
        $this->deleteAnnouncementKey();

        self::assertNull($this->repo->getLastAnnouncedDate());
    }

    public function testGetLastAnnouncedDateReturnsStoredValue(): void
    {
        $this->deleteAnnouncementKey();
        $this->insertRow('cache', ['cache_key' => self::CACHE_KEY, 'value' => '2098-03-05', 'expiration' => 0]);

        self::assertSame('2098-03-05', $this->repo->getLastAnnouncedDate());
    }

    public function testGetLastAnnouncedDateIgnoresOtherCacheKeys(): void
    {
        $this->deleteAnnouncementKey();
        $this->insertRow('cache', ['cache_key' => self::CACHE_KEY, 'value' => '2098-03-05', 'expiration' => 0]);
        $this->insertRow('cache', ['cache_key' => 'rh_ann_other', 'value' => '2098-09-09', 'expiration' => 0]);

        self::assertSame('2098-03-05', $this->repo->getLastAnnouncedDate());
    }

    // --- markAnnouncementsProcessed ---

    public function testMarkAnnouncementsProcessedInsertsRowWithZeroExpiration(): void
    {
        $this->deleteAnnouncementKey();

        $this->repo->markAnnouncementsProcessed('2098-03-05');

        self::assertSame(
            ['value' => '2098-03-05', 'expiration' => 0],
            $this->fetchAnnouncementRow(),
        );
    }

    public function testMarkAnnouncementsProcessedReplacesExistingValue(): void
    {
        $this->deleteAnnouncementKey();

        $this->repo->markAnnouncementsProcessed('2098-03-05');
        $this->repo->markAnnouncementsProcessed('2098-03-18');

        self::assertSame('2098-03-18', $this->repo->getLastAnnouncedDate());
        $result = $this->db->query("SELECT COUNT(*) AS c FROM cache WHERE cache_key = '" . self::CACHE_KEY . "'");
        self::assertInstanceOf(\mysqli_result::class, $result);
        self::assertSame(['c' => 1], $result->fetch_assoc());
    }

    public function testMarkAnnouncementsProcessedLeavesOtherKeysUntouched(): void
    {
        $this->deleteAnnouncementKey();
        $this->insertRow('cache', ['cache_key' => 'rh_ann_other', 'value' => '2098-09-09', 'expiration' => 0]);

        $this->repo->markAnnouncementsProcessed('2098-03-05');

        $result = $this->db->query("SELECT value, expiration FROM cache WHERE cache_key = 'rh_ann_other'");
        self::assertInstanceOf(\mysqli_result::class, $result);
        self::assertSame(['value' => '2098-09-09', 'expiration' => 0], $result->fetch_assoc());
    }

    // --- getUnannouncedGameDates ---

    public function testGetUnannouncedGameDatesWithNullLastDateReturnsSimWindowDistinctAscending(): void
    {
        $this->seedWindow();

        self::assertSame(
            ['2098-03-12', '2098-03-15', '2098-03-20'],
            $this->repo->getUnannouncedGameDates(null),
        );
    }

    public function testGetUnannouncedGameDatesExcludesSimStartDay(): void
    {
        $this->seedWindow();

        $dates = $this->repo->getUnannouncedGameDates(null);

        // Pins current behavior; see PR body.
        self::assertNotContains('2098-03-10', $dates);
        self::assertNotContains('2098-03-09', $dates);
    }

    public function testGetUnannouncedGameDatesWithLastDateInsideSimStartsAfterIt(): void
    {
        $this->seedWindow();

        self::assertSame(
            ['2098-03-15', '2098-03-20'],
            $this->repo->getUnannouncedGameDates('2098-03-12'),
        );
    }

    public function testGetUnannouncedGameDatesWithLastDateBeforeSimStartMatchesNull(): void
    {
        $this->seedWindow();

        self::assertSame(
            ['2098-03-12', '2098-03-15', '2098-03-20'],
            $this->repo->getUnannouncedGameDates('2098-03-05'),
        );
    }

    public function testGetUnannouncedGameDatesLastDateOneBeforeEndReturnsEndOnly(): void
    {
        $this->seedWindow();

        self::assertSame(['2098-03-20'], $this->repo->getUnannouncedGameDates('2098-03-19'));
    }

    public function testGetUnannouncedGameDatesLastDateAtSimEndReturnsEmpty(): void
    {
        $this->seedWindow();

        self::assertSame([], $this->repo->getUnannouncedGameDates('2098-03-20'));
    }

    public function testGetUnannouncedGameDatesLastDateAfterSimEndReturnsEmpty(): void
    {
        $this->seedWindow();

        self::assertSame([], $this->repo->getUnannouncedGameDates('2098-03-25'));
    }

    public function testGetUnannouncedGameDatesUsesLatestSimOnly(): void
    {
        $this->insertRow('ibl_sim_dates', ['sim' => 4000000000, 'start_date' => '2098-04-01', 'end_date' => '2098-04-10']);
        $this->seedLatestSim();
        $this->seedGameOn('2098-04-05', 200091001);
        $this->seedGameOn('2098-03-15', 200091002);

        self::assertSame(['2098-03-15'], $this->repo->getUnannouncedGameDates(null));
    }

    public function testGetUnannouncedGameDatesReturnsEmptyWhenNoSimDates(): void
    {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        $this->db->query('DELETE FROM ibl_sim_dates');
        $this->db->query('SET FOREIGN_KEY_CHECKS=1');

        self::assertSame([], $this->repo->getUnannouncedGameDates(null));
    }

    // --- helpers ---

    private function deleteAnnouncementKey(): void
    {
        $this->db->query("DELETE FROM cache WHERE cache_key = '" . self::CACHE_KEY . "'");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchAnnouncementRow(): ?array
    {
        $result = $this->db->query("SELECT value, expiration FROM cache WHERE cache_key = '" . self::CACHE_KEY . "'");
        self::assertInstanceOf(\mysqli_result::class, $result);

        return $result->fetch_assoc();
    }

    private function seedLatestSim(): void
    {
        $this->insertRow('ibl_sim_dates', ['sim' => 4000000001, 'start_date' => '2098-03-10', 'end_date' => '2098-03-20']);
    }

    private function seedGameOn(string $date, int $pid): void
    {
        $this->insertTestPlayer($pid, 'RhAnn' . $pid);
        $this->insertPlayerBoxscoreRow(
            $date,
            $pid,
            'RhAnn' . $pid,
            'PG',
            2,
            1,
            1,
            overrides: ['uuid' => sprintf('rh-ann-0000-0000-%012d', $pid)],
        );
    }

    /**
     * Latest sim 2098-03-10..2098-03-20 with games before, at, inside and after it.
     */
    private function seedWindow(): void
    {
        $this->seedLatestSim();
        $this->seedGameOn('2098-03-09', 200091001);
        $this->seedGameOn('2098-03-10', 200091002);
        $this->seedGameOn('2098-03-12', 200091003);
        $this->seedGameOn('2098-03-12', 200091004);
        $this->seedGameOn('2098-03-15', 200091005);
        $this->seedGameOn('2098-03-20', 200091006);
        $this->seedGameOn('2098-03-21', 200091007);
    }
}
