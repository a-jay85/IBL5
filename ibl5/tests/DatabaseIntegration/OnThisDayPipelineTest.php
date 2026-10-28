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

    private function execSql(string $sql): void
    {
        self::assertTrue($this->db->query($sql), 'Fixture query failed: ' . $this->db->error);
    }
}
