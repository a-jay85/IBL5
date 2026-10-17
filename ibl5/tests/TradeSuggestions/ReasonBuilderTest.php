<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\HorizonScores;
use TradeSuggestions\ReasonBuilder;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamMode;

class ReasonBuilderTest extends TestCase
{
    private ReasonBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ReasonBuilder();
    }

    public function testReasonNamesFilledPositionHole(): void
    {
        $assets = new TeamAssets(1, [], []);
        $before = new HorizonScores(100.0, 50.0, 30.0, ['big' => 1]);
        $after = new HorizonScores(110.0, 50.0, 28.0, []);

        $this->assertSame('Fills a hole at big.', $this->builder->build($assets, $assets, $before, $after, TeamMode::WinNow));
    }

    public function testReasonNamesIncomingPick(): void
    {
        $before = new TeamAssets(1, [], []);
        $after = new TeamAssets(1, [], [new SuggestionPick(55, 3, 2027, 1, 9.0)]);
        $beforeScores = new HorizonScores(100.0, 50.0, 30.0, []);
        $afterScores = new HorizonScores(95.0, 48.0, 39.0, []);

        $this->assertSame('Adds a 2027 round 1 draft pick.', $this->builder->build($before, $after, $beforeScores, $afterScores, TeamMode::Rebuild));
    }

    public function testReasonNamesClearedSalary(): void
    {
        $before = new TeamAssets(1, [self::player(1, 4000), self::player(2, 1000)], []);
        $after = new TeamAssets(1, [self::player(2, 1000), self::player(3, 1000)], []);
        $beforeScores = new HorizonScores(100.0, 50.0, 30.0, []);
        $afterScores = new HorizonScores(98.0, 49.0, 33.0, []);

        $this->assertSame('Clears 3000K of future salary.', $this->builder->build($before, $after, $beforeScores, $afterScores, TeamMode::Rebuild));
    }

    private static function player(int $pid, int $futureSalary): SuggestionPlayer
    {
        return new SuggestionPlayer($pid, 'Player ' . $pid, 'SF', 28, 15, false, 1000, 3, $futureSalary);
    }
}
