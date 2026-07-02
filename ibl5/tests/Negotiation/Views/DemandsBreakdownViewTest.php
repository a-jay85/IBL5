<?php

declare(strict_types=1);

namespace Tests\Negotiation\Views;

use Negotiation\Views\DemandsBreakdownView;
use PHPUnit\Framework\TestCase;

/**
 * Characterization test for DemandsBreakdownView (moved from
 * Negotiation\NegotiationDemandsBreakdownView during the Tier-1 module
 * architecture scaffold). Locks the rendered HTML structure so the
 * namespace/class move stays behavior-preserving.
 */
final class DemandsBreakdownViewTest extends TestCase
{
    /**
     * @return array{
     *     ratings: list<array{name: string, playerValue: int, marketMax: int, rawScore: int}>,
     *     totalRawScore: int,
     *     baseline: int,
     *     adjustedScore: int,
     *     avgDemands: float|int,
     *     totalDemands: float|int,
     *     baseDemands: float|int,
     *     maxRaise: float|int,
     *     faPreferences: array{playForWinner: int, tradition: int, loyalty: int, playingTime: int},
     *     teamFactors: array{wins: int, losses: int, tradition_wins: int, tradition_losses: int, money_committed_at_position: int},
     *     modifiers: list<array{name: string, formula: string, inputs: string, result: float}>,
     *     totalModifier: float,
     *     demands: array{year1: float|int, year2: float|int, year3: float|int, year4: float|int, year5: float|int, year6: int, years: int, total: float|int, modifier: float}
     * }
     */
    private function sampleBreakdown(): array
    {
        return [
            'ratings' => [
                ['name' => 'Scoring', 'playerValue' => 80, 'marketMax' => 100, 'rawScore' => 60],
            ],
            'totalRawScore' => 60,
            'baseline' => 10,
            'adjustedScore' => 50,
            'avgDemands' => 16.6667,
            'totalDemands' => 83.3335,
            'baseDemands' => 13.8889,
            'maxRaise' => 1.3889,
            'faPreferences' => [
                'playForWinner' => 5,
                'tradition' => 5,
                'loyalty' => 5,
                'playingTime' => 5,
            ],
            'teamFactors' => [
                'wins' => 41,
                'losses' => 41,
                'tradition_wins' => 0,
                'tradition_losses' => 0,
                'money_committed_at_position' => 0,
            ],
            'modifiers' => [
                ['name' => 'Play for Winner', 'formula' => '(PFW - 5) * 0.02', 'inputs' => 'PFW=5', 'result' => 0.0],
            ],
            'totalModifier' => 1.0,
            'demands' => [
                'year1' => 14,
                'year2' => 15,
                'year3' => 17,
                'year4' => 19,
                'year5' => 21,
                'year6' => 0,
                'years' => 5,
                'total' => 86,
                'modifier' => 1.0,
            ],
        ];
    }

    public function testRenderProducesStableBreakdownStructure(): void
    {
        $html = DemandsBreakdownView::render($this->sampleBreakdown());

        $this->assertStringContainsString('<details class="debug-breakdown">', $html);
        $this->assertStringContainsString('<summary class="debug-breakdown__summary">Demands Formula Breakdown</summary>', $html);
        $this->assertStringContainsString('Ratings vs Market', $html);
        $this->assertStringContainsString('Score Pipeline', $html);
        $this->assertStringContainsString('Player FA Preferences', $html);
        $this->assertStringContainsString('Team Factors', $html);
        $this->assertStringContainsString('Modifier Components', $html);
        $this->assertStringContainsString('Combined Modifier', $html);
        $this->assertStringContainsString('Final Demands', $html);
        $this->assertStringContainsString('<strong>86</strong>', $html);
        $this->assertStringContainsString('</details>', $html);
    }
}
