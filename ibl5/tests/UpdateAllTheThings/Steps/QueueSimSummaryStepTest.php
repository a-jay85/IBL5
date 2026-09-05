<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use SimRecap\SimSummaryRepository;
use Season\SeasonQueryRepository;
use Updater\Steps\QueueSimSummaryStep;

class QueueSimSummaryStepTest extends TestCase
{
    private SimSummaryRepository $stubSummaries;
    private SeasonQueryRepository $stubSeasonQuery;

    protected function setUp(): void
    {
        $this->stubSummaries = self::createStub(SimSummaryRepository::class);
        $this->stubSeasonQuery = self::createStub(SeasonQueryRepository::class);
        $this->stubSeasonQuery->method('getSeasonPhase')->willReturn('Regular Season');
        $this->stubSeasonQuery->method('getLastSimDatesArray')->willReturn(['sim' => 5]);
    }

    private function buildStep(?SimSummaryRepository $summaries = null, ?SeasonQueryRepository $seasonQuery = null): QueueSimSummaryStep
    {
        return new QueueSimSummaryStep(
            $summaries ?? $this->stubSummaries,
            $seasonQuery ?? $this->stubSeasonQuery,
        );
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $this->assertSame('Sim recap queued', $this->buildStep()->getLabel());
    }

    public function testSkipsWhenPhaseIsDisabledForRecaps(): void
    {
        $stubQuery = self::createStub(SeasonQueryRepository::class);
        $stubQuery->method('getSeasonPhase')->willReturn('HEAT');

        $result = $this->buildStep(seasonQuery: $stubQuery)->execute();

        $this->assertStringContainsString('HEAT', $result->detail);
    }

    public function testSkipsWhenNoSimDatesRecorded(): void
    {
        $stubQuery = self::createStub(SeasonQueryRepository::class);
        $stubQuery->method('getSeasonPhase')->willReturn('Regular Season');
        $stubQuery->method('getLastSimDatesArray')->willReturn(['sim' => 0]);

        $result = $this->buildStep(seasonQuery: $stubQuery)->execute();

        $this->assertSame('No sim dates recorded — nothing to queue.', $result->detail);
    }

    public function testQueuesSimWhenAbsentFromSummaryTable(): void
    {
        $stubSummaries = self::createStub(SimSummaryRepository::class);
        $stubSummaries->method('queuePendingIfAbsent')->willReturn(true);

        $result = $this->buildStep(summaries: $stubSummaries)->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Queued sim 5 for recap generation.', $result->detail);
        $this->assertStringContainsString('href=', $result->inlineHtml);
    }

    public function testReturnsSuccessWhenSimAlreadyHasSummaryRow(): void
    {
        $stubSummaries = self::createStub(SimSummaryRepository::class);
        $stubSummaries->method('queuePendingIfAbsent')->willReturn(false);
        $stubSummaries->method('listAll')->willReturn([['status' => 'done', 'sim' => 3]]);

        $result = $this->buildStep(summaries: $stubSummaries)->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Sim 5 already has a summary row.', $result->detail);
        $this->assertStringContainsString('href=', $result->inlineHtml);
    }
}
