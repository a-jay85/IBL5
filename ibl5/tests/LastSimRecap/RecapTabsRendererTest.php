<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\RecapTabsRenderer;

final class RecapTabsRendererTest extends RecapTestCase
{
    public function testFirstTabIsActive(): void
    {
        $html = (new RecapTabsRenderer())->render([$this->makeGame()], 1);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('last-sim-recap__tab--active', $html);
        $this->assertStringNotContainsString('aria-selected="false"', $html);
    }

    public function testSecondTabIsInactive(): void
    {
        $html = (new RecapTabsRenderer())->render(
            [$this->makeGame(), $this->makeGame(schedId: 2)],
            2
        );
        $this->assertStringContainsString('aria-selected="false"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
    }

    public function testWonGameHasWinModifier(): void
    {
        $html = (new RecapTabsRenderer())->render([$this->makeGame(won: true)], 1);
        $this->assertStringContainsString('last-sim-recap__tab--win', $html);
        $this->assertStringNotContainsString('last-sim-recap__tab--loss', $html);
        $this->assertStringContainsString('>W<', $html);
    }

    public function testLostGameHasLossModifier(): void
    {
        $html = (new RecapTabsRenderer())->render([$this->makeGame(won: false, margin: -3)], 1);
        $this->assertStringContainsString('last-sim-recap__tab--loss', $html);
        $this->assertStringNotContainsString('last-sim-recap__tab--win', $html);
        $this->assertStringContainsString('>L<', $html);
    }

    public function testHomeGameShowsVs(): void
    {
        $html = (new RecapTabsRenderer())->render([$this->makeGame(home: true)], 1);
        $this->assertStringContainsString('>vs<', $html);
        $this->assertStringNotContainsString('>@<', $html);
    }

    public function testAwayGameShowsAt(): void
    {
        $html = (new RecapTabsRenderer())->render([$this->makeGame(home: false)], 1);
        $this->assertStringContainsString('>@<', $html);
        $this->assertStringNotContainsString('>vs<', $html);
    }

    public function testOtGameHasOtSpan(): void
    {
        $html = (new RecapTabsRenderer())->render([$this->makeGame(ot: true)], 1);
        $this->assertStringContainsString('last-sim-recap__tab-ot', $html);
    }

    public function testNonOtGameHasNoOtSpan(): void
    {
        $html = (new RecapTabsRenderer())->render([$this->makeGame(ot: false)], 1);
        $this->assertStringNotContainsString('last-sim-recap__tab-ot', $html);
    }

    public function testYourNewInjuryShowsFlag(): void
    {
        // kills mutant replacing "||" with "&&" — only your side has isNew=true
        $html = (new RecapTabsRenderer())->render(
            [$this->makeGame(yourInjuries: [$this->makeInjury(isNew: true)], oppInjuries: [])],
            1
        );
        $this->assertStringContainsString('last-sim-recap__tab-flag', $html);
    }

    public function testOppNewInjuryShowsFlag(): void
    {
        // kills mutant removing the right-hand hasNewOppInjury() branch
        $html = (new RecapTabsRenderer())->render(
            [$this->makeGame(yourInjuries: [], oppInjuries: [$this->makeInjury(isNew: true)])],
            1
        );
        $this->assertStringContainsString('last-sim-recap__tab-flag', $html);
    }

    public function testNoNewInjuryHidesFlag(): void
    {
        $html = (new RecapTabsRenderer())->render([$this->makeGame()], 1);
        $this->assertStringNotContainsString('last-sim-recap__tab-flag', $html);
    }

    public function testTabCountAppearsInCssProperty(): void
    {
        $html = (new RecapTabsRenderer())->render([$this->makeGame()], 3);
        $this->assertStringContainsString('--last-sim-recap-tab-count: 3;', $html);
    }
}
