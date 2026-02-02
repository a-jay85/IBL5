<?php

use PHPUnit\Framework\TestCase;
use UI\Components\StartersLineupComponent;

/**
 * Tests for StartersLineupComponent
 * 
 * Validates the standalone UI component for rendering starting lineups
 */
class StartersLineupComponentTest extends TestCase
{
    private $component;

    protected function setUp(): void
    {
        $this->component = new StartersLineupComponent();
    }

    public function testRenderWithCompleteLineup()
    {
        $starters = [
            'PG' => ['name' => 'John Doe', 'pid' => 1],
            'SG' => ['name' => 'Jane Smith', 'pid' => 2],
            'SF' => ['name' => 'Bob Johnson', 'pid' => 3],
            'PF' => ['name' => 'Mike Williams', 'pid' => 4],
            'C' => ['name' => 'Tom Brown', 'pid' => 5]
        ];

        $html = $this->component->render($starters, 'FF0000', '0000FF');

        // Check structure - uses team-card with starters-lineup
        $this->assertStringContainsString('team-card', $html);
        $this->assertStringContainsString('starters-lineup', $html);
        $this->assertStringContainsString('Last Sim\'s Starters', $html);

        // Check team colors via CSS custom properties
        $this->assertStringContainsString('--team-color-primary: #FF0000', $html);
        $this->assertStringContainsString('--team-color-secondary: #0000FF', $html);

        // Check all positions are rendered
        foreach (['PG', 'SG', 'SF', 'PF', 'C'] as $pos) {
            $this->assertStringContainsString(">$pos</span>", $html);
        }

        // Check all player names
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('Jane Smith', $html);
        $this->assertStringContainsString('Bob Johnson', $html);
        $this->assertStringContainsString('Mike Williams', $html);
        $this->assertStringContainsString('Tom Brown', $html);

        // Check player images and links
        $this->assertStringContainsString('./images/player/1.jpg', $html);
        $this->assertStringContainsString('modules.php?name=Player&amp;pa=showpage&amp;pid=1', $html);
        $this->assertStringContainsString('./images/player/5.jpg', $html);
        $this->assertStringContainsString('modules.php?name=Player&amp;pa=showpage&amp;pid=5', $html);
    }

    public function testRenderWithPartialLineup()
    {
        $starters = [
            'PG' => ['name' => 'John Doe', 'pid' => 1],
            'SG' => ['name' => '', 'pid' => ''],
            'SF' => ['name' => 'Bob Johnson', 'pid' => 3],
            'PF' => ['name' => '', 'pid' => ''],
            'C' => ['name' => 'Tom Brown', 'pid' => 5]
        ];

        $html = $this->component->render($starters, 'FF0000', '0000FF');

        // Should still render structure even with missing players
        foreach (['PG', 'SG', 'SF', 'PF', 'C'] as $pos) {
            $this->assertStringContainsString(">$pos</span>", $html);
        }

        // Check existing players
        $this->assertStringContainsString('John Doe', $html);
        $this->assertStringContainsString('Bob Johnson', $html);
        $this->assertStringContainsString('Tom Brown', $html);
    }

    public function testRenderWithEmptyLineup()
    {
        $starters = [
            'PG' => ['name' => '', 'pid' => ''],
            'SG' => ['name' => '', 'pid' => ''],
            'SF' => ['name' => '', 'pid' => ''],
            'PF' => ['name' => '', 'pid' => ''],
            'C' => ['name' => '', 'pid' => '']
        ];

        $html = $this->component->render($starters, 'FF0000', '0000FF');

        // Should still render complete structure
        $this->assertStringContainsString('Last Sim\'s Starters', $html);
        foreach (['PG', 'SG', 'SF', 'PF', 'C'] as $pos) {
            $this->assertStringContainsString(">$pos</span>", $html);
        }
    }

    public function testRenderPreservesTeamColors()
    {
        $starters = [
            'PG' => ['name' => 'Player One', 'pid' => 1],
            'SG' => ['name' => 'Player Two', 'pid' => 2],
            'SF' => ['name' => 'Player Three', 'pid' => 3],
            'PF' => ['name' => 'Player Four', 'pid' => 4],
            'C' => ['name' => 'Player Five', 'pid' => 5]
        ];

        // Test with different color scheme
        $html = $this->component->render($starters, '00FF00', 'FFFFFF');

        $this->assertStringContainsString('--team-color-primary: #00FF00', $html);
        $this->assertStringContainsString('--team-color-secondary: #FFFFFF', $html);
    }

    public function testRenderSanitizesPlayerNames()
    {
        $starters = [
            'PG' => ['name' => 'John <script>alert("XSS")</script> Doe', 'pid' => 1],
            'SG' => ['name' => 'Jane & Smith', 'pid' => 2],
            'SF' => ['name' => 'Bob "The Builder" Johnson', 'pid' => 3],
            'PF' => ['name' => 'Mike Williams', 'pid' => 4],
            'C' => ['name' => 'Tom Brown', 'pid' => 5]
        ];
        
        $html = $this->component->render($starters, 'FF0000', '0000FF');
        
        // Check that dangerous characters are escaped
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    public function testRenderSanitizesInvalidColors()
    {
        $starters = [
            'PG' => ['name' => 'Player One', 'pid' => 1],
            'SG' => ['name' => 'Player Two', 'pid' => 2],
            'SF' => ['name' => 'Player Three', 'pid' => 3],
            'PF' => ['name' => 'Player Four', 'pid' => 4],
            'C' => ['name' => 'Player Five', 'pid' => 5]
        ];

        // Test with invalid color values
        $html = $this->component->render($starters, 'invalid<script>', 'XYZ');

        // Should use default color for invalid values
        $this->assertStringContainsString('--team-color-primary: #000000', $html);
        $this->assertStringContainsString('--team-color-secondary: #000000', $html);
    }

    public function testRenderAcceptsThreeCharacterHexColors()
    {
        $starters = [
            'PG' => ['name' => 'Player One', 'pid' => 1],
            'SG' => ['name' => 'Player Two', 'pid' => 2],
            'SF' => ['name' => 'Player Three', 'pid' => 3],
            'PF' => ['name' => 'Player Four', 'pid' => 4],
            'C' => ['name' => 'Player Five', 'pid' => 5]
        ];

        // Test with 3-character hex colors
        $html = $this->component->render($starters, 'F00', 'FFF');

        $this->assertStringContainsString('--team-color-primary: #F00', $html);
        $this->assertStringContainsString('--team-color-secondary: #FFF', $html);
    }

    public function testRenderHandlesColorWithHashPrefix()
    {
        $starters = [
            'PG' => ['name' => 'Player One', 'pid' => 1],
            'SG' => ['name' => 'Player Two', 'pid' => 2],
            'SF' => ['name' => 'Player Three', 'pid' => 3],
            'PF' => ['name' => 'Player Four', 'pid' => 4],
            'C' => ['name' => 'Player Five', 'pid' => 5]
        ];

        // Test with # prefix (should be stripped)
        $html = $this->component->render($starters, '#00FF00', '#FFFFFF');

        $this->assertStringContainsString('--team-color-primary: #00FF00', $html);
        $this->assertStringContainsString('--team-color-secondary: #FFFFFF', $html);
    }
}
