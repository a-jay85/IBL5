<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Updater\Steps\QueueSimSummaryStep;

class QueueSimSummaryStepTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testQueuesTheLatestSim(): void
    {
        $this->mockDb->onQuery('ibl_sim_dates', [['sim' => 412, 'start_date' => '2026-01-01', 'end_date' => '2026-01-07']]);
        $this->mockDb->setAffectedRows(1);

        $step = $this->buildStep();
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('412', $result->detail);

        $queries = $this->mockDb->getExecutedQueries();
        $found = false;
        foreach ($queries as $q) {
            if (stripos($q, 'INSERT IGNORE') !== false && stripos($q, 'ibl_sim_summaries') !== false) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected an INSERT IGNORE into ibl_sim_summaries');
    }

    public function testSkipsWhenARowAlreadyExists(): void
    {
        $this->mockDb->onQuery('ibl_sim_dates', [['sim' => 412, 'start_date' => '2026-01-01', 'end_date' => '2026-01-07']]);
        $this->mockDb->setAffectedRows(0);

        $step = $this->buildStep();
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('already', $result->detail);
    }

    public function testSkipsWhenNoSimDatesExist(): void
    {
        $this->mockDb->onQuery('ibl_sim_dates', []);

        $step = $this->buildStep();
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('No sim dates', $result->detail);

        $queries = $this->mockDb->getExecutedQueries();
        foreach ($queries as $q) {
            self::assertStringNotContainsStringIgnoringCase('ibl_sim_summaries', $q, 'Must not touch ibl_sim_summaries when no sim dates exist');
        }
    }

    public function testGetLabelIsStable(): void
    {
        $step = $this->buildStep();
        self::assertSame('Sim recap queued', $step->getLabel());
    }

    public function testStepIsNotRegisteredInTheUpdaterPipeline(): void
    {
        // unit 3 removes this test as part of its registration phase — the inline comment is intentional
        self::assertStringNotContainsString(
            'QueueSimSummaryStep',
            (string) file_get_contents(__DIR__ . '/../../scripts/updateAllTheThings.php')
        );
    }

    private function buildStep(): QueueSimSummaryStep
    {
        $summaries = new \SimRecap\SimSummaryRepository($this->mockDb);
        $seasonQuery = new \Season\SeasonQueryRepository($this->mockDb);
        return new QueueSimSummaryStep($summaries, $seasonQuery);
    }
}
