<?php

declare(strict_types=1);

namespace Tests\Injuries;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;
use Injuries\InjuriesService;
use Injuries\InjuriesView;

/**
 * InjuriesIntegrationTest - Integration tests for Injuries module
 *
 * Tests the complete workflow of retrieving and displaying injured players.
 *
 * @covers \Injuries\InjuriesService
 * @covers \Injuries\InjuriesView
 */
#[AllowMockObjectsWithoutExpectations]
class InjuriesIntegrationTest extends IntegrationTestCase
{
    private InjuriesView $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new InjuriesView();
    }

    // ============================================
    // VIEW RENDERING - XSS PROTECTION TESTS
    // ============================================

    /**
     * Test render sanitizes player name with script tag
     */
    public function testRenderSanitizesPlayerNameScriptTag(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => '<script>alert("xss")</script>John',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Boston',
                'teamName' => 'Celtics',
                'teamColor1' => '007A33',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    /**
     * Test render sanitizes position with HTML injection
     */
    public function testRenderSanitizesPositionHtmlInjection(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => '<img src=x onerror=alert(1)>',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Boston',
                'teamName' => 'Celtics',
                'teamColor1' => '007A33',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        // The raw <img tag should not appear - should be escaped
        $this->assertStringNotContainsString('<img src=x', $result);
        // Escaped version should appear
        $this->assertStringContainsString('&lt;img', $result);
    }

    /**
     * Test render sanitizes team city with script
     */
    public function testRenderSanitizesTeamCityScript(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => 'C',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => '<script>steal(cookie)</script>Boston',
                'teamName' => 'Celtics',
                'teamColor1' => '007A33',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringNotContainsString('<script>steal', $result);
    }

    /**
     * Test render sanitizes team name with malicious content
     */
    public function testRenderSanitizesTeamNameMalicious(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => 'SF',
                'daysRemaining' => 3,
                'teamID' => 1,
                'teamCity' => 'Los Angeles',
                'teamName' => 'Lakers" onclick="alert(1)',
                'teamColor1' => '552583',
                'teamColor2' => 'FDB927',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        // The raw unescaped onclick=" should not appear - should be escaped to onclick=&quot;
        $this->assertStringNotContainsString('onclick="alert', $result);
        // The escaped version should appear
        $this->assertStringContainsString('onclick=&quot;', $result);
    }

    /**
     * Test render sanitizes team color for attribute injection
     */
    public function testRenderSanitizesTeamColorAttributeInjection(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => 'PF',
                'daysRemaining' => 7,
                'teamID' => 1,
                'teamCity' => 'Chicago',
                'teamName' => 'Bulls',
                'teamColor1' => 'FF0000;"><script>alert(1)</script><span style="color:',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        // Script tags should be escaped
        $this->assertStringNotContainsString('<script>alert(1)</script>', $result);
    }

    // ============================================
    // VIEW RENDERING - DATA DISPLAY TESTS
    // ============================================

    /**
     * Test render displays player ID in link
     */
    public function testRenderDisplaysPlayerIdInLink(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 12345,
                'name' => 'Test Player',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Miami',
                'teamName' => 'Heat',
                'teamColor1' => '98002E',
                'teamColor2' => 'F9A01B',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('pid=12345', $result);
    }

    /**
     * Test render displays team ID in link
     */
    public function testRenderDisplaysTeamIdInLink(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => 'SG',
                'daysRemaining' => 5,
                'teamID' => 99,
                'teamCity' => 'Phoenix',
                'teamName' => 'Suns',
                'teamColor1' => '1D1160',
                'teamColor2' => 'E56020',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('teamID=99', $result);
    }

    /**
     * Test render displays days remaining correctly
     */
    public function testRenderDisplaysDaysRemainingCorrectly(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Long Term Injury',
                'position' => 'C',
                'daysRemaining' => 42,
                'teamID' => 1,
                'teamCity' => 'Portland',
                'teamName' => 'Trail Blazers',
                'teamColor1' => 'E03A3E',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('>42<', $result);
    }

    /**
     * Test render with single day injury
     */
    public function testRenderWithSingleDayInjury(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Day To Day',
                'position' => 'PG',
                'daysRemaining' => 1,
                'teamID' => 1,
                'teamCity' => 'Denver',
                'teamName' => 'Nuggets',
                'teamColor1' => '0E2240',
                'teamColor2' => 'FEC524',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        // Check days cell content
        $this->assertStringContainsString('ibl-stat-highlight', $result);
        $this->assertMatchesRegularExpression('/ibl-stat-highlight[^>]*>1</', $result);
    }

    /**
     * Test render with zero days remaining
     */
    public function testRenderWithZeroDaysRemaining(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Ready To Return',
                'position' => 'SF',
                'daysRemaining' => 0,
                'teamID' => 1,
                'teamCity' => 'Sacramento',
                'teamName' => 'Kings',
                'teamColor1' => '5A2D81',
                'teamColor2' => '63727A',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertMatchesRegularExpression('/ibl-stat-highlight[^>]*>0</', $result);
    }

    // ============================================
    // VIEW RENDERING - STYLING TESTS
    // ============================================

    /**
     * Test render includes sortable class for JS sorting
     */
    public function testRenderIncludesSortableClass(): void
    {
        $result = $this->view->render([]);

        $this->assertStringContainsString('class="sortable', $result);
    }

    /**
     * Test render includes team color styling
     */
    public function testRenderIncludesTeamColorStyling(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => 'C',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Golden State',
                'teamName' => 'Warriors',
                'teamColor1' => '006BB6',
                'teamColor2' => 'FDB927',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('background-color: #006BB6', $result);
        $this->assertStringContainsString('color: #FDB927', $result);
    }

    /**
     * Test render includes team cell class
     */
    public function testRenderIncludesTeamCellClass(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => 'PF',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Oklahoma City',
                'teamName' => 'Thunder',
                'teamColor1' => '007AC1',
                'teamColor2' => 'EF3B24',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('class="ibl-team-cell--colored"', $result);
    }

    /**
     * Test render includes days cell class
     */
    public function testRenderIncludesDaysCellClass(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => 'SG',
                'daysRemaining' => 10,
                'teamID' => 1,
                'teamCity' => 'San Antonio',
                'teamName' => 'Spurs',
                'teamColor1' => 'C4CED4',
                'teamColor2' => '000000',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('class="ibl-stat-highlight"', $result);
    }

    // ============================================
    // VIEW RENDERING - CSS DESIGN SYSTEM TESTS
    // ============================================

    /**
     * Test render uses design system CSS classes for styling
     *
     * Styling is now handled by the design system (design/components/existing-components.css)
     * rather than inline CSS. Row alternation, hover effects, fonts, and colors
     * are all inherited from the ibl-data-table class.
     */
    public function testRenderUsesDesignSystemCssClasses(): void
    {
        $result = $this->view->render([]);

        // Design system provides hover effects, fonts, and colors via this class
        $this->assertStringContainsString('ibl-data-table', $result);
        // Module-specific styling via injuries-table class (defined in design system)
        $this->assertStringContainsString('injuries-table', $result);
    }

    // ============================================
    // VIEW RENDERING - HTML STRUCTURE TESTS
    // ============================================

    /**
     * Test render has proper table structure
     */
    public function testRenderHasProperTableStructure(): void
    {
        $result = $this->view->render([]);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('<thead>', $result);
        $this->assertStringContainsString('</thead>', $result);
        $this->assertStringContainsString('<tbody>', $result);
        $this->assertStringContainsString('</tbody>', $result);
        $this->assertStringContainsString('</table>', $result);
    }

    /**
     * Test render has four column headers
     */
    public function testRenderHasFourColumnHeaders(): void
    {
        $result = $this->view->render([]);

        // Count th tags
        $thCount = substr_count($result, '<th>');
        $this->assertEquals(4, $thCount);
    }

    /**
     * Test render uses proper link encoding
     */
    public function testRenderUsesProperLinkEncoding(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Atlanta',
                'teamName' => 'Hawks',
                'teamColor1' => 'E03A3E',
                'teamColor2' => 'C1D32F',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        // Links should use proper HTML encoding for ampersands
        $this->assertStringContainsString('&amp;', $result);
    }

    // ============================================
    // VIEW RENDERING - EDGE CASES
    // ============================================

    /**
     * Test render with empty player name
     */
    public function testRenderWithEmptyPlayerName(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => '',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Charlotte',
                'teamName' => 'Hornets',
                'teamColor1' => '00788C',
                'teamColor2' => '1D1160',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        // Should still render a row
        $this->assertStringContainsString('pid=1', $result);
    }

    /**
     * Test render with long player name
     */
    public function testRenderWithLongPlayerName(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Giannis Antetokounmpo-Smith-Johnson III',
                'position' => 'PF',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'Milwaukee',
                'teamName' => 'Bucks',
                'teamColor1' => '00471B',
                'teamColor2' => 'EEE1C6',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('Antetokounmpo-Smith-Johnson', $result);
    }

    /**
     * Test render with unicode characters in name
     */
    public function testRenderWithUnicodeCharactersInName(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Jose Calderon',
                'position' => 'PG',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'New York',
                'teamName' => 'Knicks',
                'teamColor1' => '006BB6',
                'teamColor2' => 'F58426',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        $this->assertStringContainsString('Calderon', $result);
    }

    /**
     * Test render with apostrophe in team name
     */
    public function testRenderWithApostropheInTeamName(): void
    {
        $injuredPlayers = [
            [
                'playerID' => 1,
                'name' => 'Test Player',
                'position' => 'C',
                'daysRemaining' => 5,
                'teamID' => 1,
                'teamCity' => 'City',
                'teamName' => "O'Brien",
                'teamColor1' => '000000',
                'teamColor2' => 'FFFFFF',
            ],
        ];

        $result = $this->view->render($injuredPlayers);

        // Apostrophe should be properly escaped or preserved
        $this->assertMatchesRegularExpression('/O[\'&#].*Brien/', $result);
    }

    /**
     * Test render with many injured players
     */
    public function testRenderWithManyInjuredPlayers(): void
    {
        $injuredPlayers = [];
        for ($i = 1; $i <= 20; $i++) {
            $injuredPlayers[] = [
                'playerID' => $i,
                'name' => "Player {$i}",
                'position' => 'G',
                'daysRemaining' => $i,
                'teamID' => $i,
                'teamCity' => 'City',
                'teamName' => "Team{$i}",
                'teamColor1' => '000000',
                'teamColor2' => 'FFFFFF',
            ];
        }

        $result = $this->view->render($injuredPlayers);

        // Count table rows (tr tags in tbody)
        $trCount = substr_count($result, '<tr>');
        // One header row + 20 data rows
        $this->assertEquals(21, $trCount);
    }
}
