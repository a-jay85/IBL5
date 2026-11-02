<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use OnThisDay\OnThisDayFactory;
use OnThisDay\OnThisDayRepository;
use OnThisDay\OnThisDayView;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises "on this day" through the production composition root: the same
 * OnThisDayFactory graph the homepage and the updater build. Discord itself is
 * a PHPUnit no-op, so the post path runs up to that guard.
 *
 * Fixture: a 60-point regular-season game on 2029-03-17, two seasons before the
 * sim 900002 end date (2031-03-17).
 */
#[Group('database')]
class OnThisDayPipelineTest extends DatabaseTestCase
{
    private const PID = 200_920_001;
    private const SIM_PREVIOUS = 900001;
    private const SIM_LATEST = 900002;
    private const SIM_NEXT_SEASON = 900003;

    protected function setUp(): void
    {
        parent::setUp();

        $this->execSql(
            "DELETE FROM `ibl_settings` WHERE setting_key = '" . OnThisDayRepository::LAST_POSTED_SIM_KEY . "' AND league = 'ibl'"
        );
        $this->execSql(
            "INSERT INTO `ibl_settings` (setting_key, setting_value, league)
             VALUES ('Current Season Phase', 'Regular Season', 'ibl')
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );

        $this->insertRow('ibl_sim_dates', [
            'sim' => self::SIM_PREVIOUS,
            'start_date' => '2031-03-04',
            'end_date' => '2031-03-10',
        ]);
        $this->insertRow('ibl_sim_dates', [
            'sim' => self::SIM_LATEST,
            'start_date' => '2031-03-11',
            'end_date' => '2031-03-17',
        ]);

        $this->insertTestPlayer(self::PID, 'Pipeline Scorer');
        // 21 two-pointers, 9 free throws, 3 threes = 60 points.
        $this->insertPlayerBoxscoreRow(
            '2029-03-17',
            self::PID,
            'Box Name',
            'PG',
            2,
            1,
            1,
            points2m: 21,
            points2a: 30,
            ftm: 9,
            fta: 12,
            points3m: 3,
            points3a: 8,
        );

        OnThisDayFactory::createRebuilder($this->db)->rebuild();
    }

    public function testFactoryStepPostsOnceAndRerunSkips(): void
    {
        $first = OnThisDayFactory::createPostStep($this->db)->execute();

        self::assertTrue($first->success, $first->detail);
        self::assertFalse($first->skipped, $first->detail);
        self::assertStringContainsString('Posted sim 900002', $first->detail);
        self::assertStringContainsString('2 seasons ago', $first->detail);

        $second = OnThisDayFactory::createPostStep($this->db)->execute();

        self::assertTrue($second->skipped, $second->detail);
        self::assertStringContainsString('Already posted for sim 900002', $second->detail);
    }

    public function testFactoryServiceFeedsHomepageView(): void
    {
        $selection = OnThisDayFactory::createService($this->db)->getMomentsForSim();

        $html = (new OnThisDayView())->renderHomepageBlock($selection);

        self::assertStringContainsString('data-component="on-this-day"', $html);
        self::assertStringContainsString('2 seasons ago', $html);
    }

    public function testFactoryStepSkipsWhenNoMomentInWindow(): void
    {
        $this->execSql("UPDATE `ibl_sim_dates` SET end_date = '2031-08-15' WHERE sim = " . self::SIM_LATEST);

        $result = OnThisDayFactory::createPostStep($this->db)->execute();

        self::assertTrue($result->skipped, $result->detail);

        $claimed = $this->db->query(
            "SELECT setting_value FROM `ibl_settings` WHERE setting_key = '" . OnThisDayRepository::LAST_POSTED_SIM_KEY . "' AND league = 'ibl'"
        );
        self::assertInstanceOf(\mysqli_result::class, $claimed);
        self::assertSame(0, $claimed->num_rows);
    }

    public function testPostStepSkipsAndLeavesSimUnclaimedWhenStoreIsEmpty(): void
    {
        $this->execSql('DELETE FROM `ibl_on_this_day_moments`');

        $result = OnThisDayFactory::createPostStep($this->db)->execute();

        self::assertTrue($result->skipped, $result->detail);

        $claimed = $this->db->query(
            "SELECT setting_value FROM `ibl_settings` WHERE setting_key = '" . OnThisDayRepository::LAST_POSTED_SIM_KEY . "' AND league = 'ibl'"
        );
        self::assertInstanceOf(\mysqli_result::class, $claimed);
        self::assertSame(0, $claimed->num_rows);
    }

    public function testRecordStepIsIdempotentPerSim(): void
    {
        $expected = count(OnThisDayFactory::createService($this->db)->getMomentsForSim()->moments);
        self::assertGreaterThan(0, $expected);

        $first = OnThisDayFactory::createRecordShownStep($this->db)->execute();
        self::assertTrue($first->success, $first->detail);
        self::assertFalse($first->skipped, $first->detail);
        $afterFirst = $this->countShownRows(self::SIM_LATEST);

        $second = OnThisDayFactory::createRecordShownStep($this->db)->execute();
        self::assertTrue($second->success, $second->detail);
        $afterSecond = $this->countShownRows(self::SIM_LATEST);

        self::assertGreaterThan(0, $afterFirst);
        self::assertSame($afterFirst, $afterSecond);
        self::assertSame($expected, $afterSecond);
    }

    public function testSecondSeasonWithSameSimDatesShowsDifferentMoments(): void
    {
        // Three more fifty-point games inside the sim's month-days. With the
        // 60-point game that is four candidates, above both the limit of 3 and
        // the per-kind cap of 2, so some always stay unshown.
        $extraGames = [
            [self::PID + 1, '2028-03-12', 24, 6, 1],
            [self::PID + 2, '2027-03-14', 22, 7, 1],
            [self::PID + 3, '2026-03-16', 20, 8, 1],
        ];
        foreach ($extraGames as [$pid, $date, $twoPointers, $freeThrows, $threes]) {
            $this->insertTestPlayer($pid, 'Extra Scorer ' . ($pid - self::PID));
            $this->insertPlayerBoxscoreRow(
                $date,
                $pid,
                'Box Name',
                'PG',
                2,
                1,
                1,
                points2m: $twoPointers,
                points2a: $twoPointers + 8,
                ftm: $freeThrows,
                fta: $freeThrows + 2,
                points3m: $threes,
                points3a: $threes + 4,
            );
        }
        OnThisDayFactory::createRebuilder($this->db)->rebuild();

        $service = OnThisDayFactory::createService($this->db);
        $recorded = OnThisDayFactory::createRecordShownStep($this->db)->execute();
        self::assertFalse($recorded->skipped, $recorded->detail);
        $seasonOneKeys = $this->momentKeys($service->getMomentsForSim()->moments);
        self::assertGreaterThan(0, count($seasonOneKeys));

        $this->insertRow('ibl_sim_dates', [
            'sim' => self::SIM_NEXT_SEASON,
            'start_date' => '2032-03-11',
            'end_date' => '2032-03-17',
        ]);

        $seasonTwo = $service->getMomentsForSim();
        $seasonTwoKeys = $this->momentKeys($seasonTwo->moments);

        self::assertSame(self::SIM_NEXT_SEASON, $seasonTwo->sim);
        self::assertGreaterThan(0, count($seasonTwoKeys));
        self::assertNotSame($seasonOneKeys[0], $seasonTwoKeys[0]);
        self::assertSame([], array_intersect($seasonOneKeys, $seasonTwoKeys));
    }

    public function testMissingHistoryTableKeepsCardWorking(): void
    {
        $db = new RecordingMysqli($this->db, 1146, 'ibl_on_this_day_shown');

        $selection = OnThisDayFactory::createService($db)->getMomentsForSim();

        self::assertGreaterThan(0, count($selection->moments));
        self::assertSame(60, $selection->moments[0]->facts['points']);

        $result = OnThisDayFactory::createRecordShownStep($db)->execute();

        self::assertTrue($result->skipped, $result->detail);
    }

    /**
     * @param list<\OnThisDay\Moment> $moments
     * @return list<string>
     */
    private function momentKeys(array $moments): array
    {
        return array_map(static fn (\OnThisDay\Moment $m): string => $m->key, $moments);
    }

    private function countShownRows(int $sim): int
    {
        $result = $this->db->query('SELECT COUNT(*) AS c FROM `ibl_on_this_day_shown` WHERE sim = ' . $sim);
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);

        return (int) $row['c'];
    }

    private function execSql(string $sql): void
    {
        self::assertTrue($this->db->query($sql), 'Fixture query failed: ' . $this->db->error);
    }
}
