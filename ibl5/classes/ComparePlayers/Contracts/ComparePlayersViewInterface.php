<?php

declare(strict_types=1);

namespace ComparePlayers\Contracts;

/**
 * ComparePlayersViewInterface - View rendering for player comparison
 * 
 * Defines HTML rendering methods for the player comparison feature.
 * All output is properly escaped with htmlspecialchars() to prevent XSS.
 * Uses output buffering pattern for clean HTML generation.
 */
interface ComparePlayersViewInterface
{
    /**
     * Render the player comparison search form
     * 
     * Displays a form with two autocomplete text inputs for selecting players.
     * Uses jQuery UI autocomplete widget for player name suggestions.
     * Submits via POST to modules.php?name=Compare_Players.
     * 
     * The form includes:
     * - jQuery UI CSS/JS dependencies
     * - JavaScript initialization for autocomplete on both inputs
     * - Player name data embedded in JavaScript array
     * - Two text inputs (Player1, Player2)
     * - Submit button
     * 
     * @param array<int, string> $playerNames Array of all active player names
     *     Used to populate JavaScript autocomplete suggestions
     *     Each name is JSON-encoded and stripped of SQL escaping
     * 
     * @return string Complete HTML form with embedded JavaScript
     *     Ready to be displayed in page output
     *     Uses output buffering to capture HTML
     * 
     * JAVASCRIPT BEHAVIOR:
     *  - Loads jQuery 1.12.4 and jQuery UI 1.12.1
     *  - Initializes autocomplete on #Player1 and #Player2 inputs
     *  - Autocomplete source is array of player names
     *  - Names are JSON-encoded with HEX flags (XSS safe)
     *  - Uses stripslashes() to handle escaped quotes
     * 
     * FORM BEHAVIOR:
     *  - Method: POST
     *  - Action: modules.php?name=Compare_Players
     *  - Fields: Player1 (text), Player2 (text)
     *  - Submit button label: "Compare"
     *  - No validation (server-side handles it)
     * 
     * SECURITY:
     *  - Player names JSON-encoded with HEX flags
     *  - Prevents XSS via JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
     *  - stripslashes() removes SQL escaping before JSON encoding
     * 
     * Examples:
     *  $html = $view->renderSearchForm(['Michael Jordan', 'Kobe Bryant', ...]);
     *  echo $html; // Displays form with autocomplete
     */
    public function renderSearchForm(array $playerNames): string;

    /**
     * Render the player comparison results
     * 
     * Displays three side-by-side comparison tables:
     * 1. Current Ratings (24 columns) - r_* fields and skill ratings
     * 2. Current Season Stats (19 columns) - stats_* fields
     * 3. Career Stats (19 columns) - car_* fields
     * 
     * Each table shows:
     * - First row: Player 1 data
     * - Second row: Player 2 data
     * - Alternating background colors for visual grouping
     * - Sortable class for JavaScript sorting
     * 
     * @param array{player1: array<string, mixed>, player2: array<string, mixed>} $comparisonData
     *     Both players' complete data from service
     * 
     * @return string Complete HTML with three comparison tables
     *     Ready to be displayed in page output
     *     Uses output buffering to capture HTML
     * 
     * TABLE 1 - Current Ratings:
     *  Columns: Pos, Player, Age, 2ga, 2g%, fta, ft%, 3ga, 3g%, orb, drb, ast, stl, tvr, blk, foul,
     *           oo, do, po, to, od, dd, pd, td
     *  - Uses <colgroup> for alternating column backgrounds
     *  - Statistical ratings (r_*) and skill ratings (oo/do/po/to/od/dd/pd/td)
     * 
     * TABLE 2 - Current Season Stats:
     *  Columns: Pos, Player, g, gs, min, fgm, fga, ftm, fta, 3gm, 3ga, orb, reb, ast, stl, to, blk, pf, pts
     *  - Points calculated: 2*fgm + ftm + 3gm
     *  - Uses stats_* fields from current season
     * 
     * TABLE 3 - Career Stats:
     *  Columns: Pos, Player, g, min, fgm, fga, ftm, fta, 3gm, 3ga, orb, drb, reb, ast, stl, to, blk, pf, pts
     *  - Uses car_* fields for career totals
     *  - All-time statistics across entire career
     * 
     * VISUAL STYLING:
     *  - Table border=1, cellspacing=0, align=center, class="sortable"
     *  - Caption with centered bold title
     *  - <colgroup> defines alternating column backgrounds (#ddd)
     *  - <thead> with column headers
     *  - <tbody> with two player rows
     *  - <p> spacing between tables
     * 
     * SECURITY:
     *  - All string values escaped with htmlspecialchars()
     *  - Numeric values output directly (no escaping needed)
     *  - Array access uses proper syntax: $player['key']
     * 
     * IMPORTANT BEHAVIORS:
     *  - Assumes valid comparison data (service validates)
     *  - Uses array access syntax in HTML: $player1Array[key]
     *  - Points calculated inline: (2 * $fgm + $ftm + $tgm)
     *  - No null checks (service ensures data exists)
     *  - Three separate tables with consistent structure
     * 
     * Examples:
     *  $html = $view->renderComparisonResults([
     *    'player1' => ['name' => 'Jordan', 'pos' => 'SG', ...],
     *    'player2' => ['name' => 'Bryant', 'pos' => 'SG', ...]
     *  ]);
     *  echo $html; // Displays three comparison tables
     */
    public function renderComparisonResults(array $comparisonData): string;
}
