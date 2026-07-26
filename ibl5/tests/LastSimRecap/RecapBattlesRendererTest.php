<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\RecapBattlesRenderer;

final class RecapBattlesRendererTest extends RecapTestCase
{
    public function testEmptyStartersProducesEmptyBattlesDiv(): void
    {
        $game = $this->makeGame(starters: []);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringContainsString('last-sim-recap__battles', $html);
        $this->assertStringNotContainsString('last-sim-recap__battle"', $html);
    }

    public function testYouPlayerHasYouModifier(): void
    {
        $game = $this->makeGame(starters: [$this->makeStarter()]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringContainsString('last-sim-recap__player--you', $html);
    }

    public function testOppPlayerHasNoYouModifier(): void
    {
        // 1 starter → exactly 1 player row with --you; opp row must not have it
        $game = $this->makeGame(starters: [$this->makeStarter()]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertSame(1, substr_count($html, 'last-sim-recap__player--you'));
    }

    public function testYouPlayerHurtShowsInjdot(): void
    {
        $game = $this->makeGame(starters: [$this->makeStarter(youHurt: true)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringContainsString('last-sim-recap__injdot', $html);
    }

    public function testYouPlayerNotHurtHidesInjdot(): void
    {
        $game = $this->makeGame(starters: [$this->makeStarter(youHurt: false)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringNotContainsString('last-sim-recap__injdot', $html);
    }

    public function testRebAtBoundaryIncluded(): void
    {
        $game = $this->makeGame(starters: [$this->makeStarter(youReb: 5)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringContainsString('5 reb', $html);
    }

    public function testRebBelowBoundaryExcluded(): void
    {
        // kills mutant changing ">= 5" to ">= 4" for reb threshold
        $game = $this->makeGame(starters: [$this->makeStarter(youReb: 4)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringNotContainsString('4 reb', $html);
    }

    public function testAstAtBoundaryIncluded(): void
    {
        $game = $this->makeGame(starters: [$this->makeStarter(youAst: 5)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringContainsString('5 ast', $html);
    }

    public function testAstBelowBoundaryExcluded(): void
    {
        $game = $this->makeGame(starters: [$this->makeStarter(youAst: 4)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringNotContainsString('4 ast', $html);
    }

    public function testStlAtBoundaryIncluded(): void
    {
        $game = $this->makeGame(starters: [$this->makeStarter(youStl: 2)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringContainsString('2 stl', $html);
    }

    public function testStlBelowBoundaryExcluded(): void
    {
        // kills mutant changing ">= 2" to ">= 1" for stl threshold
        $game = $this->makeGame(starters: [$this->makeStarter(youStl: 1)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringNotContainsString('1 stl', $html);
    }

    public function testBlkAtBoundaryIncluded(): void
    {
        $game = $this->makeGame(starters: [$this->makeStarter(youBlk: 2)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringContainsString('2 blk', $html);
    }

    public function testBlkBelowBoundaryExcluded(): void
    {
        // kills mutant changing ">= 2" to ">= 1" for blk threshold
        $game = $this->makeGame(starters: [$this->makeStarter(youBlk: 1)]);
        $html = (new RecapBattlesRenderer())->render($game);
        $this->assertStringNotContainsString('1 blk', $html);
    }
}
