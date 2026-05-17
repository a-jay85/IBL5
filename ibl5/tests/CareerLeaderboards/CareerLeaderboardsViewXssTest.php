<?php

declare(strict_types=1);

namespace Tests\CareerLeaderboards;

use CareerLeaderboards\CareerLeaderboardsService;
use CareerLeaderboards\CareerLeaderboardsView;
use PHPUnit\Framework\TestCase;

final class CareerLeaderboardsViewXssTest extends TestCase
{
    private CareerLeaderboardsView $view;

    protected function setUp(): void
    {
        $service = new CareerLeaderboardsService();
        $this->view = new CareerLeaderboardsView($service);
    }

    /**
     * @return array{pid: int, name: string, games: int, minutes: string, fgm: string, fga: string, fgp: string, ftm: string, fta: string, ftp: string, tgm: string, tga: string, tgp: string, orb: string, drb: string, reb: string, ast: string, stl: string, tvr: string, blk: string, pf: string, pts: string}
     */
    private function makeStats(array $overrides = []): array
    {
        return array_merge([
            'pid' => 1,
            'name' => 'Safe Player',
            'games' => 82,
            'minutes' => '30.0',
            'fgm' => '8.0',
            'fga' => '16.0',
            'fgp' => '0.500',
            'ftm' => '4.0',
            'fta' => '5.0',
            'ftp' => '0.800',
            'tgm' => '2.0',
            'tga' => '5.0',
            'tgp' => '0.400',
            'orb' => '2.0',
            'drb' => '4.0',
            'reb' => '6.0',
            'ast' => '5.0',
            'stl' => '1.0',
            'tvr' => '2.0',
            'blk' => '0.5',
            'pf' => '2.0',
            'pts' => '22.0',
        ], $overrides);
    }

    public function testPlayerNameWithScriptPayloadIsEscaped(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $stats = $this->makeStats(['name' => $xss]);
        $html = $this->view->renderPlayerRow($stats, 1);

        $this->assertStringContainsString($escaped, $html);
        $this->assertStringNotContainsString($xss, $html);
    }
}
