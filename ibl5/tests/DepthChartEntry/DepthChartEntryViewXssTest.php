<?php

declare(strict_types=1);

namespace Tests\DepthChartEntry;

use DepthChartEntry\DepthChartEntryView;
use League\LeagueContext;
use PHPUnit\Framework\TestCase;

final class DepthChartEntryViewXssTest extends TestCase
{
    private DepthChartEntryView $view;

    protected function setUp(): void
    {
        $leagueContext = $this->createStub(LeagueContext::class);
        $this->view = new DepthChartEntryView($leagueContext, new \DepthChartEntry\DepthChartEntryService());
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makePlayer(array $overrides = []): array
    {
        return array_merge([
            'pid' => 1,
            'name' => 'Safe Player',
            'nickname' => null,
            'age' => null,
            'teamid' => 1,
            'teamname' => null,
            'pos' => 'PG',
            'injured' => 0,
            'stamina' => null,
            'dc_can_play_in_game' => 1,
            'dc_pg_depth' => 1,
            'dc_sg_depth' => 0,
            'dc_sf_depth' => 0,
            'dc_pf_depth' => 0,
            'dc_c_depth' => 0,
            'dc_minutes' => 30,
            'stats_fgm' => 200,
            'stats_3gm' => 50,
            'stats_ftm' => 100,
            'stats_orb' => 80,
            'stats_drb' => 200,
            'stats_ast' => 150,
            'stats_stl' => 60,
            'stats_blk' => 20,
        ], $overrides);
    }

    public function testPlayerNameWithScriptPayloadIsEscaped(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $player = $this->makePlayer(['name' => $xss]);

        ob_start();
        $this->view->renderPlayerRow($player, 1);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString($escaped, $html);
        $this->assertStringNotContainsString($xss, $html);
    }
}
