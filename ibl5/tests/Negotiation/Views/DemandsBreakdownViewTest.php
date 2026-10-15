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
                ['name' => 'Defense', 'playerValue' => 70, 'marketMax' => 90, 'rawScore' => 50],
            ],
            'totalRawScore' => 110,
            'baseline' => 10,
            'adjustedScore' => 100,
            'avgDemands' => 16.66,
            'totalDemands' => 8333.36,
            'baseDemands' => 13.84,
            'maxRaise' => 1.36,
            'faPreferences' => [
                'playForWinner' => 7,
                'tradition' => 3,
                'loyalty' => 9,
                'playingTime' => 2,
            ],
            'teamFactors' => [
                'wins' => 41,
                'losses' => 31,
                'tradition_wins' => 1200,
                'tradition_losses' => 900,
                'money_committed_at_position' => 2345,
            ],
            'modifiers' => [
                ['name' => 'Play for Winner', 'formula' => '(PFW - 5) * 0.02', 'inputs' => 'PFW=7', 'result' => 0.04],
                ['name' => 'Tradition', 'formula' => '(TRAD - 5) * 0.01', 'inputs' => 'TRAD=3', 'result' => 0.12346],
            ],
            'totalModifier' => 1.23456,
            'demands' => [
                'year1' => 14,
                'year2' => 15,
                'year3' => 17,
                'year4' => 19,
                'year5' => 21,
                'year6' => 23,
                'years' => 5,
                'total' => 86,
                'modifier' => 1.0,
            ],
        ];
    }

    /**
     * Same values as sampleBreakdown() with 0.7 added to every integer field.
     *
     * @return array{
     *     ratings: list<array{name: string, playerValue: float, marketMax: float, rawScore: float}>,
     *     totalRawScore: float,
     *     baseline: float,
     *     adjustedScore: float,
     *     avgDemands: float|int,
     *     totalDemands: float|int,
     *     baseDemands: float|int,
     *     maxRaise: float|int,
     *     faPreferences: array{playForWinner: float, tradition: float, loyalty: float, playingTime: float},
     *     teamFactors: array{wins: float, losses: float, tradition_wins: float, tradition_losses: float, money_committed_at_position: float},
     *     modifiers: list<array{name: string, formula: string, inputs: string, result: float}>,
     *     totalModifier: float,
     *     demands: array{year1: float|int, year2: float|int, year3: float|int, year4: float|int, year5: float|int, year6: float, years: float, total: float|int, modifier: float}
     * }
     */
    private function fractionalBreakdown(): array
    {
        return [
            'ratings' => [
                ['name' => 'Scoring', 'playerValue' => 80.7, 'marketMax' => 100.7, 'rawScore' => 60.7],
                ['name' => 'Defense', 'playerValue' => 70.7, 'marketMax' => 90.7, 'rawScore' => 50.7],
            ],
            'totalRawScore' => 110.7,
            'baseline' => 10.7,
            'adjustedScore' => 100.7,
            'avgDemands' => 16.66,
            'totalDemands' => 8333.36,
            'baseDemands' => 13.84,
            'maxRaise' => 1.36,
            'faPreferences' => [
                'playForWinner' => 7.7,
                'tradition' => 3.7,
                'loyalty' => 9.7,
                'playingTime' => 2.7,
            ],
            'teamFactors' => [
                'wins' => 41.7,
                'losses' => 31.7,
                'tradition_wins' => 1200.7,
                'tradition_losses' => 900.7,
                'money_committed_at_position' => 2345.7,
            ],
            'modifiers' => [
                ['name' => 'Play for Winner', 'formula' => '(PFW - 5) * 0.02', 'inputs' => 'PFW=7', 'result' => 0.04],
                ['name' => 'Tradition', 'formula' => '(TRAD - 5) * 0.01', 'inputs' => 'TRAD=3', 'result' => 0.12346],
            ],
            'totalModifier' => 1.23456,
            'demands' => [
                'year1' => 14.7,
                'year2' => 15.7,
                'year3' => 17.7,
                'year4' => 19.7,
                'year5' => 21.7,
                'year6' => 23.7,
                'years' => 5.7,
                'total' => 86.7,
                'modifier' => 1.0,
            ],
        ];
    }

    private static function spaces(int $count): string
    {
        return str_repeat(' ', $count);
    }

    /** The `<tbody>...</tbody>` of the table under the given section heading. */
    private static function tbodyOf(string $html, string $heading): string
    {
        $headingPos = strpos($html, '<h3 class="debug-breakdown__heading">' . $heading . '</h3>');
        self::assertNotFalse($headingPos, "heading $heading missing");
        $start = strpos($html, '<tbody>', $headingPos);
        self::assertNotFalse($start);
        $end = strpos($html, '</tbody>', $start);
        self::assertNotFalse($end);

        return substr($html, $start, $end + strlen('</tbody>') - $start);
    }

    private static function yearRow(int $yr, int $amount): string
    {
        return self::spaces(60) . "<tr>\n"
            . self::spaces(24) . "<td>Year $yr</td>\n"
            . self::spaces(24) . "<td>$amount</td>\n"
            . self::spaces(20) . "</tr>\n"
            . self::spaces(36);
    }

    private static function skippedYear(): string
    {
        return self::spaces(56);
    }

    private static function demandsTail(int $total, int $years): string
    {
        return self::spaces(16) . '<tr class="debug-breakdown__total-row">' . "\n"
            . self::spaces(20) . "<td><strong>Total</strong></td>\n"
            . self::spaces(20) . "<td><strong>$total</strong></td>\n"
            . self::spaces(16) . "</tr>\n"
            . self::spaces(16) . "<tr>\n"
            . self::spaces(20) . "<td>Years</td>\n"
            . self::spaces(20) . "<td>$years</td>\n"
            . self::spaces(16) . "</tr>\n"
            . self::spaces(12) . '</tbody>';
    }

    private static function demandsPrefix(): string
    {
        return "<tbody>\n" . self::spaces(16);
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

    public function testRenderMatchesGoldenHtml(): void
    {
        $expected = <<<'HTML'
<details class="debug-breakdown">
    <summary class="debug-breakdown__summary">Demands Formula Breakdown</summary>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Ratings vs Market</h3>
        <table class="ibl-data-table">
            <thead>
                <tr>
                    <th>Rating</th>
                    <th>Player</th>
                    <th>Market Max</th>
                    <th>Raw Score</th>
                </tr>
            </thead>
            <tbody>
                                <tr>
                    <td>Scoring</td>
                    <td>80</td>
                    <td>100</td>
                    <td>60</td>
                </tr>
                                <tr>
                    <td>Defense</td>
                    <td>70</td>
                    <td>90</td>
                    <td>50</td>
                </tr>
                            </tbody>
        </table>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Score Pipeline</h3>
        <dl class="debug-breakdown__kv">
            <div><dt>Total Raw Score</dt><dd>110</dd></div>
            <div><dt>Baseline</dt><dd>-10</dd></div>
            <div><dt>Adjusted Score</dt><dd>100</dd></div>
            <div><dt>&times; 3 (demands factor) = Avg Demands</dt><dd>16.7</dd></div>
            <div><dt>&times; 5 = Total Demands</dt><dd>8,333.4</dd></div>
            <div><dt>&divide; 6 = Base Demands (Yr 1)</dt><dd>13.8</dd></div>
            <div><dt>10% Raise per Year</dt><dd>1.4</dd></div>
        </dl>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Player FA Preferences</h3>
        <table class="ibl-data-table">
            <thead>
                <tr><th>Attribute</th><th>Value (1-10)</th></tr>
            </thead>
            <tbody>
                <tr><td>Play for Winner</td><td>7</td></tr>
                <tr><td>Tradition</td><td>3</td></tr>
                <tr><td>Loyalty</td><td>9</td></tr>
                <tr><td>Playing Time</td><td>2</td></tr>
            </tbody>
        </table>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Team Factors</h3>
        <table class="ibl-data-table">
            <thead>
                <tr><th>Factor</th><th>Value</th></tr>
            </thead>
            <tbody>
                <tr><td>Wins</td><td>41</td></tr>
                <tr><td>Losses</td><td>31</td></tr>
                <tr><td>Tradition Wins</td><td>1200</td></tr>
                <tr><td>Tradition Losses</td><td>900</td></tr>
                <tr><td>$ at Position</td><td>2345</td></tr>
            </tbody>
        </table>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Modifier Components</h3>
        <table class="ibl-data-table">
            <thead>
                <tr>
                    <th>Modifier</th>
                    <th>Formula</th>
                    <th>Inputs</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                                <tr>
                    <td>Play for Winner</td>
                    <td class="debug-breakdown__mono">(PFW - 5) * 0.02</td>
                    <td class="debug-breakdown__mono">PFW=7</td>
                    <td>0.0400</td>
                </tr>
                                <tr>
                    <td>Tradition</td>
                    <td class="debug-breakdown__mono">(TRAD - 5) * 0.01</td>
                    <td class="debug-breakdown__mono">TRAD=3</td>
                    <td>0.1235</td>
                </tr>
                            </tbody>
        </table>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Combined Modifier</h3>
        <p class="debug-breakdown__formula">1 + PFW + tradition + loyalty + PT = <strong>1.2346</strong></p>
    </div>

    <div class="debug-breakdown__section">
        <h3 class="debug-breakdown__heading">Final Demands</h3>
        <table class="ibl-data-table">
            <thead>
                <tr><th>Year</th><th>Amount</th></tr>
            </thead>
            <tbody>
                                                                            <tr>
                        <td>Year 1</td>
                        <td>14</td>
                    </tr>
                                                                                                <tr>
                        <td>Year 2</td>
                        <td>15</td>
                    </tr>
                                                                                                <tr>
                        <td>Year 3</td>
                        <td>17</td>
                    </tr>
                                                                                                <tr>
                        <td>Year 4</td>
                        <td>19</td>
                    </tr>
                                                                                                <tr>
                        <td>Year 5</td>
                        <td>21</td>
                    </tr>
                                                    <tr class="debug-breakdown__total-row">
                    <td><strong>Total</strong></td>
                    <td><strong>86</strong></td>
                </tr>
                <tr>
                    <td>Years</td>
                    <td>5</td>
                </tr>
            </tbody>
        </table>
    </div>
</details>
HTML . "\n" . self::spaces(8);

        $html = DemandsBreakdownView::render($this->sampleBreakdown());

        self::assertSame($expected, $html);
    }

    public function testRatingsTableRendersNoRowsForEmptyRatings(): void
    {
        $breakdown = $this->sampleBreakdown();
        $breakdown['ratings'] = [];

        $html = DemandsBreakdownView::render($breakdown);

        self::assertSame("<tbody>\n" . self::spaces(28) . '</tbody>', self::tbodyOf($html, 'Ratings vs Market'));
    }

    public function testRatingsTableRendersOneRowForSingleRating(): void
    {
        $breakdown = $this->sampleBreakdown();
        $breakdown['ratings'] = [
            ['name' => 'Scoring', 'playerValue' => 80, 'marketMax' => 100, 'rawScore' => 60],
        ];

        $html = DemandsBreakdownView::render($breakdown);

        $expected = "<tbody>\n"
            . self::spaces(32) . "<tr>\n"
            . self::spaces(20) . "<td>Scoring</td>\n"
            . self::spaces(20) . "<td>80</td>\n"
            . self::spaces(20) . "<td>100</td>\n"
            . self::spaces(20) . "<td>60</td>\n"
            . self::spaces(16) . "</tr>\n"
            . self::spaces(28) . '</tbody>';
        self::assertSame($expected, self::tbodyOf($html, 'Ratings vs Market'));
    }

    public function testModifiersTableRendersNoRowsForEmptyModifiers(): void
    {
        $breakdown = $this->sampleBreakdown();
        $breakdown['modifiers'] = [];

        $html = DemandsBreakdownView::render($breakdown);

        self::assertSame("<tbody>\n" . self::spaces(28) . '</tbody>', self::tbodyOf($html, 'Modifier Components'));
    }

    public function testModifiersTableRendersOneRowForSingleModifier(): void
    {
        $breakdown = $this->sampleBreakdown();
        $breakdown['modifiers'] = [
            ['name' => 'Play for Winner', 'formula' => '(PFW - 5) * 0.02', 'inputs' => 'PFW=7', 'result' => 0.04],
        ];

        $html = DemandsBreakdownView::render($breakdown);

        $expected = "<tbody>\n"
            . self::spaces(32) . "<tr>\n"
            . self::spaces(20) . "<td>Play for Winner</td>\n"
            . self::spaces(20) . "<td class=\"debug-breakdown__mono\">(PFW - 5) * 0.02</td>\n"
            . self::spaces(20) . "<td class=\"debug-breakdown__mono\">PFW=7</td>\n"
            . self::spaces(20) . "<td>0.0400</td>\n"
            . self::spaces(16) . "</tr>\n"
            . self::spaces(28) . '</tbody>';
        self::assertSame($expected, self::tbodyOf($html, 'Modifier Components'));
    }

    public function testYearRowIsSkippedWhenDemandIsIntZero(): void
    {
        $breakdown = $this->sampleBreakdown();
        $breakdown['demands']['year3'] = 0;

        $html = DemandsBreakdownView::render($breakdown);

        $expected = self::demandsPrefix()
            . self::yearRow(1, 14)
            . self::yearRow(2, 15)
            . self::skippedYear()
            . self::yearRow(4, 19)
            . self::yearRow(5, 21)
            . self::demandsTail(86, 5);
        self::assertSame($expected, self::tbodyOf($html, 'Final Demands'));
    }

    public function testYearRowIsRenderedWhenDemandIsFloatZero(): void
    {
        $breakdown = $this->sampleBreakdown();
        $breakdown['demands']['year3'] = 0.0;

        $html = DemandsBreakdownView::render($breakdown);

        $expected = self::demandsPrefix()
            . self::yearRow(1, 14)
            . self::yearRow(2, 15)
            . self::yearRow(3, 0)
            . self::yearRow(4, 19)
            . self::yearRow(5, 21)
            . self::demandsTail(86, 5);
        self::assertSame($expected, self::tbodyOf($html, 'Final Demands'));
    }

    public function testAllYearRowsAreSkippedWhenEveryDemandIsIntZero(): void
    {
        $breakdown = $this->sampleBreakdown();
        $breakdown['demands']['year1'] = 0;
        $breakdown['demands']['year2'] = 0;
        $breakdown['demands']['year3'] = 0;
        $breakdown['demands']['year4'] = 0;
        $breakdown['demands']['year5'] = 0;
        $breakdown['demands']['total'] = 0;
        $breakdown['demands']['years'] = 0;

        $html = DemandsBreakdownView::render($breakdown);

        $expected = self::demandsPrefix()
            . self::skippedYear()
            . self::skippedYear()
            . self::skippedYear()
            . self::skippedYear()
            . self::skippedYear()
            . self::demandsTail(0, 0);
        self::assertSame($expected, self::tbodyOf($html, 'Final Demands'));
    }

    public function testEscapesHostileStringsAtEveryEscapeSite(): void
    {
        $hostile = '<script>alert(1)</script>&"\'';
        $escaped = '&lt;script&gt;alert(1)&lt;/script&gt;&amp;&quot;&apos;';

        $breakdown = $this->sampleBreakdown();
        $breakdown['ratings'][0]['name'] = $hostile;
        $breakdown['modifiers'][0]['name'] = $hostile;
        $breakdown['modifiers'][0]['formula'] = $hostile;
        $breakdown['modifiers'][0]['inputs'] = $hostile;

        $html = DemandsBreakdownView::render($breakdown);

        self::assertStringNotContainsString('<script>', $html);
        self::assertSame(4, substr_count($html, $escaped));
        self::assertStringContainsString('<td>' . $escaped . '</td>', $html);
        self::assertStringContainsString('<td class="debug-breakdown__mono">' . $escaped . '</td>', $html);
    }

    public function testIntCastsTruncateFractionalValues(): void
    {
        self::assertSame(DemandsBreakdownView::render($this->sampleBreakdown()), DemandsBreakdownView::render($this->fractionalBreakdown())); // @phpstan-ignore argument.type (fractional ints are deliberately outside the DemandsBreakdown contract: proves every (int) cast is live)
    }

    public function testFormatsDecimalsAndThousandsSeparators(): void
    {
        $breakdown = $this->sampleBreakdown();
        $breakdown['avgDemands'] = 2499.96;
        $breakdown['totalDemands'] = 1000000.04;
        $breakdown['baseDemands'] = 0.04;
        $breakdown['maxRaise'] = 0.96;
        $breakdown['modifiers'][0]['result'] = 0.00006;
        $breakdown['totalModifier'] = 1.99996;

        $html = DemandsBreakdownView::render($breakdown);

        $cells = ['<dd>2,500.0</dd>', '<dd>1,000,000.0</dd>', '<dd>0.0</dd>', '<dd>1.0</dd>'];
        $previousPos = -1;
        foreach ($cells as $cell) {
            self::assertStringContainsString($cell, $html);
            $pos = strpos($html, $cell);
            self::assertNotFalse($pos);
            self::assertGreaterThan($previousPos, $pos, "$cell out of order");
            $previousPos = $pos;
        }
        self::assertSame(1, substr_count($html, '<dd>2,500.0</dd>'));
        self::assertStringContainsString('<td>0.0001</td>', $html);
        self::assertStringContainsString('<strong>2.0000</strong>', $html);
    }

    public function testViewIsStatelessWithSingleStaticEntryPoint(): void
    {
        $ref = new \ReflectionClass(DemandsBreakdownView::class);
        self::assertSame([], $ref->getProperties());
        self::assertNull($ref->getConstructor());
        $publicNames = array_map(static fn (\ReflectionMethod $m): string => $m->getName(), $ref->getMethods(\ReflectionMethod::IS_PUBLIC));
        self::assertSame(['render'], $publicNames);
        foreach ($ref->getMethods() as $method) {
            self::assertTrue($method->isStatic(), $method->getName() . ' must be static');
        }
    }
}
