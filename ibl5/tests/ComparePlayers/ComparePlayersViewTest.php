<?php

declare(strict_types=1);

namespace Tests\ComparePlayers;

use PHPUnit\Framework\TestCase;
use ComparePlayers\ComparePlayersView;

class ComparePlayersViewTest extends TestCase
{
    private ComparePlayersView $view;

    protected function setUp(): void
    {
        $this->view = new ComparePlayersView();
    }

    public function testRenderSearchFormReturnsString(): void
    {
        $playerNames = ['Michael Jordan', 'Kobe Bryant'];
        $result = $this->view->renderSearchForm($playerNames);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testRenderSearchFormIncludesJQueryUI(): void
    {
        $playerNames = ['Player 1', 'Player 2'];
        $result = $this->view->renderSearchForm($playerNames);

        $this->assertStringContainsString('jquery-ui', $result);
        $this->assertStringContainsString('jquery-1.12.4.js', $result);
    }

    public function testRenderSearchFormIncludesFormElements(): void
    {
        $playerNames = ['Player 1'];
        $result = $this->view->renderSearchForm($playerNames);

        $this->assertStringContainsString('<form', $result);
        $this->assertStringContainsString('method="POST"', $result);
        $this->assertStringContainsString('modules.php?name=Compare_Players', $result);
        $this->assertStringContainsString('Player1', $result);
        $this->assertStringContainsString('Player2', $result);
        $this->assertStringContainsString('type="submit"', $result);
    }

    public function testRenderSearchFormIncludesPlayerNamesInJavaScript(): void
    {
        $playerNames = ['Michael Jordan', 'Kobe Bryant', 'Tim Duncan'];
        $result = $this->view->renderSearchForm($playerNames);

        $this->assertStringContainsString('Michael Jordan', $result);
        $this->assertStringContainsString('Kobe Bryant', $result);
        $this->assertStringContainsString('Tim Duncan', $result);
        $this->assertStringContainsString('availableTags', $result);
    }

    public function testRenderSearchFormEscapesPlayerNamesForJavaScript(): void
    {
        $playerNames = ["O'Neal", 'Player with "quotes"'];
        $result = $this->view->renderSearchForm($playerNames);

        // JSON encoding with JSON_HEX_APOS escapes apostrophes as \u0027
        $this->assertStringContainsString('O\\u0027Neal', $result);
        // JSON encoding with JSON_HEX_QUOT escapes quotes as \u0022
        $this->assertStringContainsString('\\u0022', $result);
    }

    public function testRenderSearchFormHandlesEmptyPlayerArray(): void
    {
        $result = $this->view->renderSearchForm([]);

        $this->assertIsString($result);
        $this->assertStringContainsString('<form', $result);
    }

    public function testRenderComparisonResultsReturnsString(): void
    {
        $comparisonData = $this->getValidComparisonData();
        $result = $this->view->renderComparisonResults($comparisonData);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testRenderComparisonResultsIncludesThreeTables(): void
    {
        $comparisonData = $this->getValidComparisonData();
        $result = $this->view->renderComparisonResults($comparisonData);

        // Count table occurrences
        $tableCount = substr_count($result, '<table');
        $this->assertEquals(3, $tableCount);

        // Verify table captions
        $this->assertStringContainsString('Current Ratings', $result);
        $this->assertStringContainsString('Current Season Stats', $result);
        $this->assertStringContainsString('Career Stats', $result);
    }

    public function testRenderComparisonResultsEscapesPlayerNames(): void
    {
        $comparisonData = [
            'player1' => array_merge($this->getPlayerTemplate(), [
                'name' => '<script>alert("XSS")</script>',
            ]),
            'player2' => array_merge($this->getPlayerTemplate(), [
                'name' => 'Normal Name',
            ]),
        ];

        $result = $this->view->renderComparisonResults($comparisonData);

        // Should not contain unescaped script tags
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $result);
        // Should contain escaped version
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderComparisonResultsIncludesAllRatingColumns(): void
    {
        $comparisonData = $this->getValidComparisonData();
        $result = $this->view->renderComparisonResults($comparisonData);

        // Verify rating column headers
        $expectedHeaders = ['2ga', '2g%', 'fta', 'ft%', '3ga', '3g%', 'orb', 'drb', 'ast', 'stl', 'tvr', 'blk', 'foul'];
        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString($header, $result);
        }

        // Verify skill headers
        $skillHeaders = ['oo', 'do', 'po', 'to', 'od', 'dd', 'pd', 'td'];
        foreach ($skillHeaders as $header) {
            $this->assertStringContainsString($header, $result);
        }
    }

    public function testRenderComparisonResultsIncludesCurrentStatsColumns(): void
    {
        $comparisonData = $this->getValidComparisonData();
        $result = $this->view->renderComparisonResults($comparisonData);

        // Verify current stats column headers
        $expectedHeaders = ['g', 'gs', 'min', 'fgm', 'fga', 'ftm', 'fta', '3gm', '3ga', 'orb', 'reb', 'ast', 'stl', 'to', 'blk', 'pf', 'pts'];
        foreach ($expectedHeaders as $header) {
            $this->assertStringContainsString('>' . $header . '<', $result);
        }
    }

    public function testRenderComparisonResultsIncludesCareerStatsColumns(): void
    {
        $comparisonData = $this->getValidComparisonData();
        $result = $this->view->renderComparisonResults($comparisonData);

        // Career stats table should include these columns
        $expectedColumns = ['car_gm', 'car_min', 'car_fgm', 'car_pts'];
        foreach ($expectedColumns as $column) {
            // Values should be in the output
            $this->assertStringContainsString((string)$comparisonData['player1'][$column], $result);
        }
    }

    public function testRenderComparisonResultsCalculatesPointsCorrectly(): void
    {
        $comparisonData = [
            'player1' => array_merge($this->getPlayerTemplate(), [
                'stats_fgm' => 400,
                'stats_ftm' => 200,
                'stats_3gm' => 100,
            ]),
            'player2' => array_merge($this->getPlayerTemplate(), [
                'stats_fgm' => 300,
                'stats_ftm' => 150,
                'stats_3gm' => 50,
            ]),
        ];

        $result = $this->view->renderComparisonResults($comparisonData);

        // Player 1 points: 2*400 + 200 + 100 = 1100
        $this->assertStringContainsString('1100', $result);
        // Player 2 points: 2*300 + 150 + 50 = 800
        $this->assertStringContainsString('800', $result);
    }

    public function testRenderComparisonResultsIncludesTableStyling(): void
    {
        $comparisonData = $this->getValidComparisonData();
        $result = $this->view->renderComparisonResults($comparisonData);

        $this->assertStringContainsString('class="sortable"', $result);
        $this->assertStringContainsString('border="1"', $result);
        $this->assertStringContainsString('cellspacing="0"', $result);
        $this->assertStringContainsString('<colgroup>', $result);
        $this->assertStringContainsString('background-color: #ddd', $result);
    }

    public function testRenderComparisonResultsDisplaysBothPlayers(): void
    {
        $comparisonData = [
            'player1' => array_merge($this->getPlayerTemplate(), [
                'name' => 'Michael Jordan',
                'pos' => 'SG',
            ]),
            'player2' => array_merge($this->getPlayerTemplate(), [
                'name' => 'Kobe Bryant',
                'pos' => 'SG',
            ]),
        ];

        $result = $this->view->renderComparisonResults($comparisonData);

        $this->assertStringContainsString('Michael Jordan', $result);
        $this->assertStringContainsString('Kobe Bryant', $result);
    }

    public function testRenderComparisonResultsHandlesSpecialCharactersInPosition(): void
    {
        $comparisonData = [
            'player1' => array_merge($this->getPlayerTemplate(), ['pos' => 'PG']),
            'player2' => array_merge($this->getPlayerTemplate(), ['pos' => 'C']),
        ];

        $result = $this->view->renderComparisonResults($comparisonData);

        $this->assertStringContainsString('PG', $result);
        $this->assertStringContainsString('C', $result);
    }

    public function testRenderComparisonResultsEscapesNumericValues(): void
    {
        $comparisonData = $this->getValidComparisonData();
        $result = $this->view->renderComparisonResults($comparisonData);

        // Numeric values should be converted to strings and escaped
        $this->assertStringContainsString('28', $result); // age
        $this->assertStringContainsString('85', $result); // r_fga
    }

    private function getValidComparisonData(): array
    {
        return [
            'player1' => array_merge($this->getPlayerTemplate(), [
                'name' => 'Michael Jordan',
                'pos' => 'SG',
                'age' => 28,
            ]),
            'player2' => array_merge($this->getPlayerTemplate(), [
                'name' => 'Kobe Bryant',
                'pos' => 'SG',
                'age' => 26,
            ]),
        ];
    }

    private function getPlayerTemplate(): array
    {
        return [
            'pid' => 1,
            'name' => 'Test Player',
            'pos' => 'PG',
            'age' => 25,
            'r_fga' => 85,
            'r_fgp' => 90,
            'r_fta' => 80,
            'r_ftp' => 88,
            'r_tga' => 75,
            'r_tgp' => 82,
            'r_orb' => 70,
            'r_drb' => 85,
            'r_ast' => 90,
            'r_stl' => 78,
            'r_to' => 72,
            'r_blk' => 65,
            'r_foul' => 68,
            'oo' => 88,
            'do' => 82,
            'po' => 75,
            'to' => 90,
            'od' => 80,
            'dd' => 85,
            'pd' => 78,
            'td' => 88,
            'stats_gm' => 70,
            'stats_gs' => 68,
            'stats_min' => 2500,
            'stats_fgm' => 400,
            'stats_fga' => 850,
            'stats_ftm' => 200,
            'stats_fta' => 250,
            'stats_3gm' => 100,
            'stats_3ga' => 280,
            'stats_orb' => 80,
            'stats_drb' => 350,
            'stats_ast' => 450,
            'stats_stl' => 120,
            'stats_to' => 180,
            'stats_blk' => 40,
            'stats_pf' => 160,
            'car_gm' => 500,
            'car_min' => 18000,
            'car_fgm' => 3000,
            'car_fga' => 6500,
            'car_ftm' => 1500,
            'car_fta' => 1900,
            'car_tgm' => 800,
            'car_tga' => 2200,
            'car_orb' => 600,
            'car_drb' => 2500,
            'car_reb' => 3100,
            'car_ast' => 3500,
            'car_stl' => 900,
            'car_to' => 1200,
            'car_blk' => 300,
            'car_pf' => 1400,
            'car_pts' => 8300,
        ];
    }
}
