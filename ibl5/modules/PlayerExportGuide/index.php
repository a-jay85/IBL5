<?php

declare(strict_types=1);

/**
 * PlayerExportGuide Module - How to use the Player Export CSV in Google Sheets
 *
 * Static guide page explaining IMPORTDATA usage, column reference, and API key management.
 */

if (stripos($_SERVER['PHP_SELF'], 'modules.php') === false) {
    die("You can't access this file directly...");
}

PageLayout\PageLayout::header();

?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Player Export Guide</h2>
    </div>
    <div class="ibl-card__body">
        <h3 class="mb-2">Quick Start</h3>
        <ol class="mb-6">
            <li class="mb-2">Go to <a href="modules.php?name=ApiKeys" class="ibl-link">API Key Management</a> and generate an API key.</li>
            <li class="mb-2">Copy the Google Sheets formula shown after key generation.</li>
            <li class="mb-2">Paste the formula into any cell in Google Sheets. The full player database will populate automatically.</li>
        </ol>

        <h3 class="mb-2">Manual Formula</h3>
        <p class="mb-4">If you already have your API key, paste this into a Google Sheets cell (replace <code>YOUR_KEY</code> with your actual key):</p>
        <pre class="ibl-code-block mb-6">=IMPORTDATA("https://iblhoops.net/ibl5/api/v1/players/export?key=YOUR_KEY")</pre>

        <h3 class="mb-2">Column Reference</h3>
        <table class="ibl-data-table mb-6">
            <thead>
                <tr>
                    <th>Column</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>PID</td><td>Player ID</td></tr>
                <tr><td>Name</td><td>Player name</td></tr>
                <tr><td>Nickname</td><td>Player nickname</td></tr>
                <tr><td>Age</td><td>Player age</td></tr>
                <tr><td>Position</td><td>PG, SG, SF, PF, or C</td></tr>
                <tr><td>Height (ft) / Height (in)</td><td>Height in feet and inches</td></tr>
                <tr><td>Active</td><td>1 = on a depth chart, 0 = not</td></tr>
                <tr><td>Retired</td><td>1 = retired, 0 = active</td></tr>
                <tr><td>Experience</td><td>Years of experience</td></tr>
                <tr><td>Bird Rights</td><td>Bird rights status</td></tr>
                <tr><td>Team ID / Team City / Team Name</td><td>Team information (empty for free agents)</td></tr>
                <tr><td>Owner</td><td>Team owner username</td></tr>
                <tr><td>Contract Year</td><td>Current year of contract (1-6, 0 = unsigned)</td></tr>
                <tr><td>Current Salary</td><td>Salary for the current contract year (thousands)</td></tr>
                <tr><td>Year 1-6 Salary</td><td>Full contract breakdown by year (thousands)</td></tr>
                <tr><td>GP / MIN</td><td>Games played / minutes played (season totals)</td></tr>
                <tr><td>FGM / FGA</td><td>Field goals made / attempted (season totals)</td></tr>
                <tr><td>FTM / FTA</td><td>Free throws made / attempted (season totals)</td></tr>
                <tr><td>3PM / 3PA</td><td>Three-pointers made / attempted (season totals)</td></tr>
                <tr><td>ORB / DRB</td><td>Offensive / defensive rebounds (season totals)</td></tr>
                <tr><td>AST / STL / TO / BLK / PF</td><td>Assists, steals, turnovers, blocks, personal fouls (season totals)</td></tr>
                <tr><td>PPG</td><td>Points per game</td></tr>
                <tr><td>FG% / FT% / 3P%</td><td>Shooting percentages</td></tr>
            </tbody>
        </table>

        <h3 class="mb-2">Tips</h3>
        <ul class="mb-6">
            <li class="mb-1">Data refreshes each time Google Sheets recalculates (approximately every hour).</li>
            <li class="mb-1">Use Google Sheets' built-in FILTER, SORT, and QUERY functions to slice the data.</li>
            <li class="mb-1">Salary values are in thousands (e.g., 1500 = $1,500K).</li>
            <li class="mb-1">Stats columns (GP, MIN, FGM, etc.) are season totals. Divide by GP for per-game averages.</li>
        </ul>

        <h3 class="mb-2">Key Management</h3>
        <p>Visit <a href="modules.php?name=ApiKeys" class="ibl-link">API Key Management</a> to view, revoke, or regenerate your key. Each user can have one active key at a time.</p>
    </div>
</div>
<?php

PageLayout\PageLayout::footer();
