<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\RecapPanelRenderer;

final class RecapPanelRendererTest extends RecapTestCase
{
    public function testFirstPanelIsNotHidden(): void
    {
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(), 0);
        $this->assertStringNotContainsString(' hidden', $html);
    }

    public function testNonFirstPanelIsHidden(): void
    {
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(), 1);
        $this->assertStringContainsString(' hidden>', $html);
    }

    public function testWonGameHasWinStripModifier(): void
    {
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(won: true), 0);
        $this->assertStringContainsString('last-sim-recap__strip--win', $html);
        $this->assertStringNotContainsString('last-sim-recap__strip--loss', $html);
    }

    public function testLostGameHasLossStripModifier(): void
    {
        $html = (new RecapPanelRenderer())->render(
            $this->makeSlate(), $this->makeGame(won: false, margin: -3), 0
        );
        $this->assertStringContainsString('last-sim-recap__strip--loss', $html);
        $this->assertStringNotContainsString('last-sim-recap__strip--win', $html);
    }

    public function testPositiveMarginShowsPlusInVerdict(): void
    {
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(margin: 8), 0);
        $this->assertStringContainsString('>+8<', $html);
    }

    public function testNegativeMarginShowsMinusInVerdict(): void
    {
        $html = (new RecapPanelRenderer())->render(
            $this->makeSlate(), $this->makeGame(won: false, margin: -6), 0
        );
        $this->assertStringContainsString('>−6<', $html);
    }

    public function testOtGameHasOtSuffix(): void
    {
        $html = (new RecapPanelRenderer())->render(
            $this->makeSlate(), $this->makeGame(ot: true, margin: 3), 0
        );
        $this->assertStringContainsString('3 OT', $html);
    }

    public function testNonOtGameHasNoOtSuffixInVerdict(): void
    {
        // "OT" won't appear in default fixture names/cities — safe negative assertion
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(ot: false), 0);
        $this->assertStringNotContainsString(' OT<', $html);
    }

    public function testEmptyMarginsProducesBareQuarterCell(): void
    {
        $game = $this->makeGame(margins: [], qLabels: []);
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $game, 0);
        $this->assertStringContainsString(
            '<h4 class="last-sim-recap__cell-head">Quarter margin</h4></div>',
            $html
        );
        $this->assertStringNotContainsString('last-sim-recap__mom', $html);
    }

    public function testPositiveQuarterMarginHasPosClass(): void
    {
        $game = $this->makeGame(margins: [5], qLabels: ['Q1']);
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $game, 0);
        $this->assertStringContainsString('last-sim-recap__mom-bar-shape--pos', $html);
        $this->assertStringNotContainsString('last-sim-recap__mom-bar-shape--neg', $html);
    }

    public function testNegativeQuarterMarginHasNegClass(): void
    {
        $game = $this->makeGame(margins: [-4], qLabels: ['Q1']);
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $game, 0);
        $this->assertStringContainsString('last-sim-recap__mom-bar-shape--neg', $html);
        $this->assertStringNotContainsString('last-sim-recap__mom-bar-shape--pos', $html);
    }

    public function testBoxUrlPresentShowsBoxScoreLink(): void
    {
        // gameOfThatDay=1 + valid date → buildUrl returns non-empty → anchor rendered
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(gameOfThatDay: 1), 0);
        $this->assertStringContainsString('last-sim-recap__box-link', $html);
    }

    public function testBoxUrlAbsentShowsNoBoxScoreLink(): void
    {
        // gameOfThatDay=0 + boxId=0 → buildUrl returns '' → no anchor
        $game = $this->makeGame(gameOfThatDay: 0, boxId: 0);
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $game, 0);
        $this->assertStringNotContainsString('last-sim-recap__box-link', $html);
    }

    public function testWinnerRowHasWinModifier(): void
    {
        // home=true, yourScore(110) > oppScore(106): home row gets --win
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(home: true, margin: 4), 0);
        $this->assertStringContainsString('last-sim-recap__final-row--win', $html);
    }

    public function testExactlyOneRowHasWinModifier(): void
    {
        // kills a mutant that applies --win to both rows
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(home: true, margin: 4), 0);
        $this->assertSame(1, substr_count($html, 'last-sim-recap__final-row--win'));
    }

    public function testTeamLogoLinksCarryAwayThenHomeAriaLabels(): void
    {
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(home: true), 0);

        self::assertStringContainsString('class="last-sim-recap__team-link" aria-label="Pistons"><img', $html);
        self::assertStringContainsString('class="last-sim-recap__team-link" aria-label="Cavaliers"><img', $html);
        self::assertLessThan(
            strpos($html, 'aria-label="Cavaliers"><img'),
            strpos($html, 'aria-label="Pistons"><img'),
        );
        self::assertSame(2, substr_count($html, 'last-sim-recap__team-link" aria-label='));
        self::assertSame(2, substr_count($html, 'alt="" class="last-sim-recap__team-mark"'));
    }

    public function testTeamLogoLinkAriaLabelsFlipWhenSlateTeamIsAway(): void
    {
        $html = (new RecapPanelRenderer())->render($this->makeSlate(), $this->makeGame(home: false), 0);

        self::assertLessThan(
            strpos($html, 'aria-label="Pistons"><img'),
            strpos($html, 'aria-label="Cavaliers"><img'),
        );
    }

    public function testTeamLogoLinkAriaLabelEscapesTeamName(): void
    {
        $html = (new RecapPanelRenderer())->render(
            $this->makeSlate(teamName: 'Bulls & "Bears" <x>'),
            $this->makeGame(home: true),
            0,
        );

        self::assertStringContainsString('aria-label="Bulls &amp; &quot;Bears&quot; &lt;x&gt;"><img', $html);
        self::assertStringNotContainsString('aria-label="Bulls & "', $html);
    }
}
