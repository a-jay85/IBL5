<?php

namespace UI\Components;

/**
 * StartersLineupComponent - Renders the starting lineup UI
 * 
 * This is a standalone UI component that can be called independently
 * to render a team's starting lineup display.
 */
class StartersLineupComponent
{
    /**
     * Render the starting lineup HTML
     * 
     * @param array $starters Array of starter data with structure:
     *   [
     *     'PG' => ['name' => string, 'pid' => int],
     *     'SG' => ['name' => string, 'pid' => int],
     *     'SF' => ['name' => string, 'pid' => int],
     *     'PF' => ['name' => string, 'pid' => int],
     *     'C' => ['name' => string, 'pid' => int]
     *   ]
     * @param string $color1 Primary team color (hex without #)
     * @param string $color2 Secondary team color (hex without #)
     * @return string HTML table representation of starters
     */
    public function render(array $starters, string $color1, string $color2): string
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        
        // Sanitize color values to prevent HTML injection
        $color1 = $this->sanitizeColor($color1);
        $color2 = $this->sanitizeColor($color2);
        
        $headerRow = "<tr bgcolor=\"$color1\">
            <td colspan=\"5\"><font color=\"$color2\"><center><b>Last Sim's Starters</b></center></font></td>
        </tr>";
        
        $starterRow = "<tr>";
        foreach ($positions as $position) {
            $name = $starters[$position]['name'] ?? '';
            $pid = $starters[$position]['pid'] ?? '';
            
            $starterRow .= $this->renderPlayerCell($position, $name, $pid);
        }
        $starterRow .= "</tr>";
        
        return "<table align=\"center\" border=\"1\" cellpadding=\"1\" cellspacing=\"1\">
            $headerRow
            $starterRow
        </table>";
    }
    
    /**
     * Render a single player cell
     * 
     * @param string $position Position abbreviation (PG, SG, SF, PF, C)
     * @param string $name Player name
     * @param int|string $pid Player ID
     * @return string HTML for the player cell
     */
    private function renderPlayerCell(string $position, string $name, $pid): string
    {
        // Sanitize all output to prevent XSS
        $position = htmlspecialchars($position, ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        
        // Use PlayerImageHelper to safely get the image URL
        $playerImageUrl = \Player\PlayerImageHelper::getImageUrl($pid);
        
        $pidEscaped = htmlspecialchars((string)$pid, ENT_QUOTES, 'UTF-8');
        if ($name) {
            $playerLink = "<a href=\"./modules.php?name=Player&pa=showpage&pid=$pidEscaped\">$name</a>";
        } else {
            $playerLink = '&nbsp;';
        }
        
        return "<td><center><b>$position</b><br>" .
               "<img src=\"{$playerImageUrl}\" height=\"90\" width=\"65\"><br>" .
               $playerLink .
               "</td>";
    }
    
    /**
     * Sanitize color value to prevent HTML injection
     * 
     * @param string $color Hex color value (with or without #)
     * @return string Sanitized hex color value without #
     */
    private function sanitizeColor(string $color): string
    {
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Validate hex color format (3 or 6 characters)
        if (preg_match('/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $color)) {
            return $color;
        }
        
        // Return safe default if invalid
        return '000000';
    }
}
