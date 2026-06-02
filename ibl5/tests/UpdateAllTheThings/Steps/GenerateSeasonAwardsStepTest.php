<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Steps\GenerateSeasonAwardsStep;

class GenerateSeasonAwardsStepTest extends TestCase
{
    private function createStep(
        string $seasonPhase = 'Playoffs',
        int $seasonEndingYear = 2026,
        int $eoyVotesCast = 28,
        int $totalRealTeams = 28,
        bool $awardsAlreadyGenerated = false,
        bool $leadersHtmExists = true,
    ): GenerateSeasonAwardsStep {
        return new GenerateSeasonAwardsStep(
            $seasonPhase,
            $seasonEndingYear,
            $eoyVotesCast,
            $totalRealTeams,
            $awardsAlreadyGenerated,
            $leadersHtmExists,
        );
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $this->assertSame('Season awards', $this->createStep()->getLabel());
    }

    public function testShowsSuccessWhenAwardsAlreadyGenerated(): void
    {
        $step = $this->createStep(awardsAlreadyGenerated: true);

        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('already generated', $result->detail);
        $this->assertStringContainsString('2026', $result->detail);
        $this->assertSame('', $result->inlineHtml);
    }

    public function testSkipsWhenPhaseIsNotPlayoffs(): void
    {
        $step = $this->createStep(seasonPhase: 'Regular Season');

        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Only available during Playoffs', $result->detail);
    }

    public function testSkipsWhenVotingBelowThreshold(): void
    {
        $step = $this->createStep(eoyVotesCast: 20, totalRealTeams: 28);

        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Voting not yet complete', $result->detail);
        $this->assertStringContainsString('20/28', $result->detail);
    }

    public function testSkipsWhenLeadersHtmMissing(): void
    {
        $step = $this->createStep(leadersHtmExists: false);

        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Leaders.htm not found', $result->detail);
    }

    public function testRendersFormWhenAllPrerequisitesMet(): void
    {
        $step = $this->createStep();

        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('generate_awards', $result->inlineHtml);
        $this->assertStringContainsString('Generate Season Awards', $result->inlineHtml);
        $this->assertStringContainsString('ibl-card', $result->inlineHtml);
        $this->assertStringContainsString('28/28 EOY votes', $result->inlineHtml);
    }

    public function testThresholdBoundary21Of28Passes(): void
    {
        $step = $this->createStep(eoyVotesCast: 21, totalRealTeams: 28);

        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('generate_awards', $result->inlineHtml);
    }

    public function testThresholdBoundary20Of28Fails(): void
    {
        $step = $this->createStep(eoyVotesCast: 20, totalRealTeams: 28);

        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Voting not yet complete', $result->detail);
        $this->assertSame('', $result->inlineHtml);
    }
}
