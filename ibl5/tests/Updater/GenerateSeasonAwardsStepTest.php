<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Updater\Steps\GenerateSeasonAwardsStep;

class GenerateSeasonAwardsStepTest extends TestCase
{
    public function testGetLabelReturnsSeasonAwards(): void
    {
        $step = $this->buildStep();
        self::assertSame('Season awards', $step->getLabel());
    }

    public function testAlreadyGeneratedReturnsSuccessWithDetail(): void
    {
        $step = $this->buildStep(awardsAlreadyGenerated: true);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('already generated', $result->detail);
        self::assertStringContainsString('2025', $result->detail);
    }

    public function testNotPlayoffsPhaseReturnsSkipped(): void
    {
        $step = $this->buildStep(seasonPhase: 'Regular Season');
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('Playoffs', $result->detail);
    }

    public function testPreseasonPhaseReturnsSkipped(): void
    {
        $step = $this->buildStep(seasonPhase: 'Preseason');
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('Playoffs', $result->detail);
    }

    public function testVotesBelowThresholdReturnsSkipped(): void
    {
        // 32 teams, threshold = ceil(32 * 0.75) = 24; 23 votes is below threshold
        $step = $this->buildStep(
            seasonPhase: 'Playoffs',
            eoyVotesCast: 23,
            totalRealTeams: 32,
        );
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('23', $result->detail);
        self::assertStringContainsString('32', $result->detail);
    }

    public function testVotesAtThresholdProceedsPastVotingGate(): void
    {
        // 32 teams, threshold = ceil(32 * 0.75) = 24; 24 votes is exactly at threshold
        // With leadersHtmExists = false it still skips, but for the *voting* reason only
        $step = $this->buildStep(
            seasonPhase: 'Playoffs',
            eoyVotesCast: 24,
            totalRealTeams: 32,
            leadersHtmExists: false,
        );
        $result = $step->execute();

        // Skipped due to missing Leaders.htm, NOT due to insufficient votes
        self::assertTrue($result->success);
        self::assertStringContainsString('Leaders.htm', $result->detail);
        self::assertStringNotContainsStringIgnoringCase('votes', $result->detail);
    }

    public function testNoLeadersHtmReturnsSkipped(): void
    {
        $step = $this->buildStep(
            seasonPhase: 'Playoffs',
            eoyVotesCast: 30,
            totalRealTeams: 32,
            leadersHtmExists: false,
        );
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('Leaders.htm', $result->detail);
    }

    public function testAllConditionsMetReturnsSuccessWithInlineHtml(): void
    {
        $step = $this->buildStep(
            seasonPhase: 'Playoffs',
            eoyVotesCast: 30,
            totalRealTeams: 32,
            leadersHtmExists: true,
        );
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('generate_awards', $result->inlineHtml);
    }

    public function testAwardsAlreadyGeneratedShortCircuitsEvenInWrongPhase(): void
    {
        $step = $this->buildStep(
            seasonPhase: 'Preseason',
            awardsAlreadyGenerated: true,
        );
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('already generated', $result->detail);
    }

    /**
     * @param non-empty-string $seasonPhase
     */
    private function buildStep(
        string $seasonPhase = 'Playoffs',
        int $seasonEndingYear = 2025,
        int $eoyVotesCast = 30,
        int $totalRealTeams = 32,
        bool $awardsAlreadyGenerated = false,
        bool $leadersHtmExists = true,
    ): GenerateSeasonAwardsStep {
        return new GenerateSeasonAwardsStep(
            seasonPhase: $seasonPhase,
            seasonEndingYear: $seasonEndingYear,
            eoyVotesCast: $eoyVotesCast,
            totalRealTeams: $totalRealTeams,
            awardsAlreadyGenerated: $awardsAlreadyGenerated,
            leadersHtmExists: $leadersHtmExists,
        );
    }
}
