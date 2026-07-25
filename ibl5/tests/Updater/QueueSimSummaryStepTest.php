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

    public function testStepIsRegisteredInTheUpdaterPipeline(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../../scripts/updateAllTheThings.php');

        self::assertStringContainsString('QueueSimSummaryStep', $src);
        self::assertSame(1, substr_count($src, 'QueueSimSummaryStep'));

        $posRefresh = strpos($src, 'RefreshIblHistStep');
        $posQueue = strpos($src, 'QueueSimSummaryStep');
        $posController = strpos($src, 'UpdaterController');
        self::assertIsInt($posRefresh, 'RefreshIblHistStep must be present in the pipeline');
        self::assertIsInt($posQueue, 'QueueSimSummaryStep must be present in the pipeline');
        self::assertIsInt($posController, 'UpdaterController must be present in the pipeline');
        self::assertGreaterThan($posRefresh, $posQueue, 'QueueSimSummaryStep must appear after RefreshIblHistStep');
        self::assertLessThan($posController, $posQueue, 'QueueSimSummaryStep must appear before UpdaterController');
    }

    public function testQueuedInlineHtmlContainsSimAndLink(): void
    {
        $this->mockDb->onQuery('ibl_sim_dates', [['sim' => 412, 'start_date' => '2026-01-01', 'end_date' => '2026-01-07']]);
        $this->mockDb->setAffectedRows(1);

        $result = $this->buildStep()->execute();

        self::assertStringContainsString('has been queued for recap generation', $result->inlineHtml);
        self::assertStringContainsString(\SimRecap\SimSummaryLink::path(412), $result->inlineHtml);
    }

    public function testStateBLinksLastDoneSimNotCurrentSim(): void
    {
        $this->mockDb->onQuery('ibl_sim_dates', [['sim' => 412, 'start_date' => '2026-01-01', 'end_date' => '2026-01-07']]);
        $this->mockDb->setAffectedRows(0);
        $this->mockDb->onQuery('ibl_sim_summaries', [
            ['sim' => 400, 'status' => 'done', 'attempts' => 3, 'generated_at' => '2026-01-01', 'created_at' => '2025-12-01', 'recap_length' => 500],
            ['sim' => 412, 'status' => 'pending', 'attempts' => 0, 'generated_at' => null, 'created_at' => '2026-01-07', 'recap_length' => null],
        ]);

        $result = $this->buildStep()->execute();

        self::assertStringContainsString('No new sim to recap this run', $result->inlineHtml);
        self::assertStringContainsString(\SimRecap\SimSummaryLink::path(400), $result->inlineHtml);
        self::assertStringNotContainsString(\SimRecap\SimSummaryLink::path(412), $result->inlineHtml);
    }

    public function testStateBRendersTextOnlyWhenNoDoneRecapExists(): void
    {
        $this->mockDb->onQuery('ibl_sim_dates', [['sim' => 412, 'start_date' => '2026-01-01', 'end_date' => '2026-01-07']]);
        $this->mockDb->setAffectedRows(0);
        $this->mockDb->onQuery('ibl_sim_summaries', []);

        $result = $this->buildStep()->execute();

        self::assertSame(
            'No new sim to recap this run, and no recap has been generated yet.',
            $result->inlineHtml,
        );
    }

    public function testSentinelSimZeroProducesEmptyInlineHtml(): void
    {
        $this->mockDb->onQuery('ibl_sim_dates', []);

        $result = $this->buildStep()->execute();

        self::assertSame('', $result->inlineHtml);
    }

    public function testEscapingPrimitiveNeutralizesHtmlPayload(): void
    {
        // $sim is typed int through the call chain, so injection via execute() is
        // type-blocked under declare(strict_types=1). This test proves the escape
        // primitive itself neutralizes a payload — a regression guard if the type
        // constraint ever changes.
        $dangerous = '1"><script>alert(1)</script>';
        $escaped = \Security\HtmlSanitizer::e($dangerous);

        self::assertStringNotContainsString('<', $escaped);
        self::assertStringNotContainsString('"', $escaped);
    }

    private function buildStep(): QueueSimSummaryStep
    {
        $summaries = new \SimRecap\SimSummaryRepository($this->mockDb);
        $seasonQuery = new \Season\SeasonQueryRepository($this->mockDb);
        return new QueueSimSummaryStep($summaries, $seasonQuery);
    }
}
