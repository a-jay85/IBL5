<?php

declare(strict_types=1);

namespace Tests\LastSimRecap;

use LastSimRecap\RecapHeaderRenderer;

final class RecapHeaderRendererTest extends RecapTestCase
{
    public function testEmptyGameListOmitsWLAndMeta(): void
    {
        $html = (new RecapHeaderRenderer())->render($this->makeSlate(games: []));
        $this->assertStringNotContainsString('last-sim-recap__record-w', $html);
        $this->assertStringNotContainsString('last-sim-recap__meta', $html);
    }

    public function testNonEmptyGameListShowsWLAndMeta(): void
    {
        $html = (new RecapHeaderRenderer())->render($this->makeSlate(games: [$this->makeGame()]));
        $this->assertStringContainsString('last-sim-recap__record-w', $html);
        $this->assertStringContainsString('last-sim-recap__meta', $html);
    }

    public function testOneGameUsesSingularWord(): void
    {
        $html = (new RecapHeaderRenderer())->render($this->makeSlate(games: [$this->makeGame()]));
        $this->assertStringContainsString('1 game)', $html);
        $this->assertStringNotContainsString('1 games)', $html);
    }

    public function testTwoGamesUsesPluralWord(): void
    {
        $html = (new RecapHeaderRenderer())->render(
            $this->makeSlate(games: [$this->makeGame(), $this->makeGame(schedId: 2)])
        );
        $this->assertStringContainsString('2 games)', $html);
        $this->assertStringNotContainsString('2 game)', $html);
    }

    public function testPositiveNetMarginShowsPlusSign(): void
    {
        $html = (new RecapHeaderRenderer())->render(
            $this->makeSlate(games: [$this->makeGame()], netMargin: 7)
        );
        $this->assertStringContainsString('+7', $html);
        $this->assertStringNotContainsString('−7', $html);
    }

    public function testNegativeNetMarginShowsMinusSign(): void
    {
        $html = (new RecapHeaderRenderer())->render(
            $this->makeSlate(games: [$this->makeGame()], netMargin: -5)
        );
        $this->assertStringContainsString('−5', $html);
        $this->assertStringNotContainsString('+5', $html);
    }

    public function testZeroNetMarginShowsPlusSign(): void
    {
        // kills mutant that changes ">= 0" to "> 0" for the plus/minus branch
        $html = (new RecapHeaderRenderer())->render(
            $this->makeSlate(games: [$this->makeGame()], netMargin: 0)
        );
        $this->assertStringContainsString('+0', $html);
        $this->assertStringNotContainsString('−0', $html);
    }
}
