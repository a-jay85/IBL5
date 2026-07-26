<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\RecapInjuryCellRenderer;

final class RecapInjuryCellRendererTest extends RecapTestCase
{
    public function testBothSidesHealthyShowsTwoHealthyLabels(): void
    {
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [], oppInjuries: [])
        );
        $this->assertSame(2, substr_count($html, 'Healthy'));
    }

    public function testYourInjuriesHideYourHealthyLabel(): void
    {
        // kills mutant removing the "$healthy = $injuries === []" check
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [$this->makeInjury()], oppInjuries: [])
        );
        $this->assertSame(1, substr_count($html, 'Healthy'));
    }

    public function testNewInjuryHasNewRowModifier(): void
    {
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [$this->makeInjury(isNew: true)])
        );
        $this->assertStringContainsString('last-sim-recap__inj-row--new', $html);
    }

    public function testOldInjuryHasNoNewRowModifier(): void
    {
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [$this->makeInjury(isNew: false)])
        );
        $this->assertStringNotContainsString('last-sim-recap__inj-row--new', $html);
    }

    public function testNewInjuryHasExclamationSpan(): void
    {
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [$this->makeInjury(isNew: true)])
        );
        $this->assertStringContainsString('last-sim-recap__inj-new', $html);
    }

    public function testOldInjuryHasNoExclamationSpan(): void
    {
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [$this->makeInjury(isNew: false)])
        );
        $this->assertStringNotContainsString('last-sim-recap__inj-new', $html);
    }

    public function testNonEmptyDescriptionShowsDescriptionSpan(): void
    {
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [$this->makeInjury(description: 'Hamstring')])
        );
        $this->assertStringContainsString('last-sim-recap__inj-why', $html);
    }

    public function testEmptyDescriptionHidesDescriptionSpan(): void
    {
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [$this->makeInjury(description: '')])
        );
        $this->assertStringNotContainsString('last-sim-recap__inj-why', $html);
    }

    public function testPositiveDaysWithReturnDateShowsTooltip(): void
    {
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [$this->makeInjury(daysRemaining: 5, returnDate: '2030-06-01')])
        );
        $this->assertStringContainsString('tooltip', $html);
    }

    public function testPositiveDaysWithNoReturnDateShowsPlainSpan(): void
    {
        // kills mutant removing "returnDate !== ''" from the && condition
        $html = (new RecapInjuryCellRenderer())->render(
            $this->makeSlate(),
            $this->makeGame(yourInjuries: [$this->makeInjury(daysRemaining: 5, returnDate: '')])
        );
        $this->assertStringNotContainsString('tooltip', $html);
        $this->assertStringContainsString('last-sim-recap__eta-num', $html);
    }
}
