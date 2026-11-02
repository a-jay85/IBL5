<?php

declare(strict_types=1);

namespace ApiKeys;

use ApiKeys\Contracts\ApiKeysViewInterface;
use Security\HtmlSanitizer;

/**
 * ApiKeysView - Renders the self-service API key management page
 *
 * Three states: no key, key just generated (flash), active key exists.
 *
 * @see ApiKeysViewInterface For method contracts
 */
class ApiKeysView implements ApiKeysViewInterface
{
    /**
     * A ?key= URL lands in the web server's access log. Google Sheets IMPORTDATA
     * cannot send headers, so the URL form stays supported; this tells the user.
     */
    private const URL_KEY_NOTICE = '<div class="ibl-alert ibl-alert--info mb-6">'
        . '<strong>Your key is part of this URL.</strong> '
        . 'The web server logs every URL it serves, so it records the key too. Only paste the formula into a sheet you trust. '
        . 'Scripts and other tools should send the key in an <code>X-API-Key</code> header instead. '
        . 'If the URL gets shared, revoke the key and generate a new one.'
        . '</div>';

    /**
     * @see ApiKeysViewInterface::renderNoKeyState()
     */
    public function renderNoKeyState(): string
    {
        ob_start();
        ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h1 class="ibl-card__title">API Key</h1>
    </div>
    <div class="ibl-card__body">
        <p class="mb-4">You don't have an API key yet. Generate one to use the Player Export feature with Google Sheets.</p>
        <p class="mb-6 text-sm text-gray-600">An API key lets you pull live player data directly into your spreadsheet using Google Sheets' <code>IMPORTDATA</code> function.</p>
        <div id="api-key-actions" class="flex flex-wrap justify-center gap-4 items-center">
            <form method="post" action="modules.php?name=ApiKeys&amp;op=generate">
                <?= \Security\CsrfGuard::generateToken('api_keys_generate') ?>
                <button type="submit" class="ibl-btn ibl-btn--primary">Generate API Key</button>
            </form>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see ApiKeysViewInterface::renderNewKeyState()
     */
    public function renderNewKeyState(string $rawKey): string
    {
        $exportUrl = 'https://iblhoops.net/ibl5/api/v1/players/export?key=' . $rawKey;

        ob_start();
        ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h1 class="ibl-card__title">API Key Generated</h1>
    </div>
    <div class="ibl-card__body">
        <div class="ibl-alert ibl-alert--warning mb-4">
            <strong>Copy this key now — it won't be shown again.</strong>
        </div>

        <div class="ibl-form-group">
            <label class="ibl-label">Your API Key</label>
            <input type="text" class="ibl-input" value="<?= HtmlSanitizer::e($rawKey) ?>" readonly onclick="this.select()">
        </div>

        <div class="ibl-form-group">
            <label class="ibl-label">Google Sheets Formula</label>
            <p class="text-sm text-gray-600 mb-2">Paste this into any cell in Google Sheets to import the full player database:</p>
            <input type="text" class="ibl-input" value="=IMPORTDATA(&quot;<?= HtmlSanitizer::e($exportUrl) ?>&quot;)" readonly onclick="this.select()">
        </div>

        <?= self::URL_KEY_NOTICE ?>

        <div id="api-key-actions" class="flex flex-wrap justify-center gap-4 items-center">
            <a href="modules.php?name=ApiKeys" class="ibl-btn ibl-btn--primary">Done</a>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Human text for each GoogleSheetConnectionRepositoryInterface::REASON_* value.
     */
    private const REASON_TEXT = [
        'invalid_grant' => 'Google access was revoked or expired.',
        'sheet_missing' => 'The spreadsheet was deleted from Drive.',
        'key_unavailable' => 'The server encryption key changed.',
    ];

    private const GOOGLE_SHEET_URL_PREFIX = 'https://docs.google.com/';

    /**
     * @see ApiKeysViewInterface::renderFlash()
     */
    public function renderFlash(?array $flash): string
    {
        if ($flash === null) {
            return '';
        }
        $modifier = $flash['type'] === 'success' ? 'success' : 'error';

        ob_start();
        ?>
<div class="ibl-alert ibl-alert--<?= HtmlSanitizer::e($modifier) ?> mb-4" id="apikeys-flash"><?= HtmlSanitizer::e($flash['text']) ?></div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see ApiKeysViewInterface::renderGoogleSheetCard()
     */
    public function renderGoogleSheetCard(?array $summary, bool $configured): string
    {
        $url = $summary['spreadsheet_url'] ?? '';
        $safeUrl = str_starts_with($url, self::GOOGLE_SHEET_URL_PREFIX) ? $url : null;
        $isBroken = $summary !== null && $summary['status'] === 'broken';
        $reason = $summary['broken_reason'] ?? '';
        $showOpen = $safeUrl !== null && !($isBroken && $reason === 'sheet_missing');

        ob_start();
        ?>
<div class="ibl-card" id="google-sheet-card">
    <div class="ibl-card__header">
        <h1 class="ibl-card__title">Google Sheets Sync</h1>
    </div>
    <div class="ibl-card__body">
        <?php if (!$configured): ?>
        <p class="text-sm text-gray-600">Google Sheets sync is not configured on this server.</p>
        <?php elseif ($summary === null): ?>
        <p class="mb-4">Sign in with Google and the site creates a spreadsheet in your Drive, then rewrites its Players tab with the full player export after every sim. Other tabs you add are never touched.</p>
        <div id="google-sheet-actions" class="flex flex-wrap justify-center gap-4 items-center">
            <form method="post" action="modules.php?name=ApiKeys&amp;op=google_start" hx-boost="false">
                <?= \Security\CsrfGuard::generateToken('google_start') ?>
                <button type="submit" id="google-sheet-connect" class="ibl-btn ibl-btn--primary">Sign in with Google</button>
            </form>
        </div>
        <?php else: ?>
            <?php if ($isBroken): ?>
        <div class="ibl-alert ibl-alert--warning mb-4">Your Google connection needs attention: <?= HtmlSanitizer::e(self::REASON_TEXT[$reason] ?? 'Please reconnect.') ?></div>
            <?php else: ?>
        <table class="ibl-data-table api-keys-table mb-4">
            <tbody>
                <tr>
                    <th class="text-left">Status</th>
                    <td>Connected</td>
                </tr>
                <tr>
                    <th class="text-left">Last refreshed</th>
                    <td><?= $summary['last_refresh_at'] !== null ? HtmlSanitizer::e($summary['last_refresh_at']) : 'Never' ?></td>
                </tr>
                <tr>
                    <th class="text-left">Last result</th>
                    <td><?= HtmlSanitizer::e($summary['last_refresh_status'] ?? 'None yet') ?></td>
                </tr>
            </tbody>
        </table>
                <?php if ($summary['last_refresh_status'] === 'error'): ?>
        <div class="ibl-alert ibl-alert--warning mb-4">Last refresh failed: <?= HtmlSanitizer::e($summary['last_error'] ?? 'unknown error') ?></div>
                <?php endif; ?>
            <?php endif; ?>
        <div id="google-sheet-actions" class="flex flex-wrap justify-center gap-4 items-center">
            <?php if ($showOpen && $safeUrl !== null): ?>
            <a id="google-sheet-open" class="ibl-btn ibl-btn--primary" href="<?= HtmlSanitizer::e($safeUrl) ?>" target="_blank" rel="noopener">Open sheet</a>
            <?php endif; ?>
            <?php if ($isBroken): ?>
            <form method="post" action="modules.php?name=ApiKeys&amp;op=google_start" hx-boost="false">
                <?= \Security\CsrfGuard::generateToken('google_start') ?>
                <button type="submit" id="google-sheet-reconnect" class="ibl-btn ibl-btn--primary">Reconnect Google</button>
            </form>
            <?php else: ?>
            <form method="post" action="modules.php?name=ApiKeys&amp;op=google_refresh">
                <?= \Security\CsrfGuard::generateToken('google_refresh') ?>
                <button type="submit" id="google-sheet-refresh" class="ibl-btn ibl-btn--primary">Refresh</button>
            </form>
            <?php endif; ?>
            <form method="post" action="modules.php?name=ApiKeys&amp;op=google_disconnect" onsubmit="return confirm('Disconnect Google? The spreadsheet stays in your Drive but will stop updating.');">
                <?= \Security\CsrfGuard::generateToken('google_disconnect') ?>
                <button type="submit" id="google-sheet-disconnect" class="ibl-btn ibl-btn--danger">Disconnect</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @see ApiKeysViewInterface::renderActiveKeyState()
     */
    public function renderActiveKeyState(array $keyStatus): string
    {
        ob_start();
        ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h1 class="ibl-card__title">API Key</h1>
    </div>
    <div class="ibl-card__body">
        <table class="ibl-data-table api-keys-table mb-6">
            <tbody>
                <tr>
                    <th class="text-left">Key</th>
                    <td><code><?= HtmlSanitizer::e($keyStatus['key_prefix']) ?>...</code></td>
                </tr>
                <tr>
                    <th class="text-left">Permission</th>
                    <td><?= HtmlSanitizer::e($keyStatus['permission_level']) ?></td>
                </tr>
                <tr>
                    <th class="text-left">Rate Limit</th>
                    <td><?= HtmlSanitizer::e($keyStatus['rate_limit_tier']) ?> (60 requests/min)</td>
                </tr>
                <tr>
                    <th class="text-left">Created</th>
                    <td><?= HtmlSanitizer::e($keyStatus['created_at']) ?></td>
                </tr>
                <tr>
                    <th class="text-left">Last Used</th>
                    <td><?= $keyStatus['last_used_at'] !== null ? HtmlSanitizer::e($keyStatus['last_used_at']) : 'Never' ?></td>
                </tr>
            </tbody>
        </table>

        <div id="api-key-actions" class="flex flex-wrap justify-center gap-4 items-center">
            <form method="post" action="modules.php?name=ApiKeys&amp;op=revoke" onsubmit="return confirm('Are you sure? You will need to generate a new key and update any spreadsheets that use the current one.');">
                <?= \Security\CsrfGuard::generateToken('api_keys_revoke') ?>
                <button type="submit" class="ibl-btn ibl-btn--danger">Revoke Key</button>
            </form>

        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Static Player Export guide (moved from the retired PlayerExportGuide module).
     * Rendered below the Google Sheets card by ApiKeysController::handle().
     */
    public function renderExportGuide(): string
    {
        ob_start();
        ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">Player Export Guide</h2>
    </div>
    <div class="ibl-card__body">
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
                <tr><td>Age</td><td>Player age</td></tr>
                <tr><td>Position</td><td>PG, SG, SF, PF, or C</td></tr>
                <tr><td>Height (ft) / Height (in)</td><td>Height in feet and inches</td></tr>
                <tr><td>Weight</td><td>Weight in pounds</td></tr>
                <tr><td>Active</td><td>1 = on a depth chart, 0 = not</td></tr>
                <tr><td>Injury Days</td><td>Days left on the current injury (0 = healthy)</td></tr>
                <tr><td>Retired</td><td>1 = retired, 0 = active</td></tr>
                <tr><td>Experience</td><td>Years of experience</td></tr>
                <tr><td>Draft Year / Draft Round / Draft Pick</td><td>Draft details</td></tr>
                <tr><td>College</td><td>College or last school</td></tr>
                <tr><td>Bird Rights</td><td>Bird rights status</td></tr>
                <tr><td>Team ID / Team City / Team Name</td><td>Team information (empty for free agents)</td></tr>
                <tr><td>Owner</td><td>Team owner username</td></tr>
                <tr><td>Contract Year</td><td>Current year of contract (1-6, 0 = unsigned)</td></tr>
                <tr><td>Current Salary</td><td>Salary for the current contract year (thousands)</td></tr>
                <tr><td>Year 1-6 Salary</td><td>Full contract breakdown by year (thousands)</td></tr>
                <tr><td>GP / GS / MIN</td><td>Games played / games started / minutes played (season totals)</td></tr>
                <tr><td>FGM / FGA</td><td>Field goals made / attempted (season totals)</td></tr>
                <tr><td>FTM / FTA</td><td>Free throws made / attempted (season totals)</td></tr>
                <tr><td>3GM / 3GA</td><td>Three-pointers made / attempted (season totals)</td></tr>
                <tr><td>ORB / DRB</td><td>Offensive / defensive rebounds (season totals)</td></tr>
                <tr><td>AST / STL / TO / BLK / PF</td><td>Assists, steals, turnovers, blocks, personal fouls (season totals)</td></tr>
                <tr><td>PPG</td><td>Points per game</td></tr>
                <tr><td>FG% / FT% / 3G%</td><td>Shooting percentages</td></tr>
                <tr><td>r2GA / r2G% / rFTA / rFT% / r3GA / r3G%</td><td>Shooting ratings</td></tr>
                <tr><td>rORB / rDRB / rAST / rSTL / rTVR / rBLK / rFOUL</td><td>Rebounding, passing, defense, turnover, and foul ratings</td></tr>
                <tr><td>rOO / rDO / rPO / rTO</td><td>Outside, drive, post, and transition offense ratings</td></tr>
                <tr><td>rOD / rDD / rPD / rTD</td><td>Outside, drive, post, and transition defense ratings</td></tr>
                <tr><td>Clutch / Consistency</td><td>Clutch and consistency ratings</td></tr>
                <tr><td>Talent / Skill / Intangibles</td><td>TSI ratings</td></tr>
                <tr><td>FA Loyalty / FA Playing Time / FA Play for Winner / FA Tradition / FA Security</td><td>Free agency factors</td></tr>
            </tbody>
        </table>

        <h3 class="mb-2">Tips</h3>
        <ul class="mb-6">
            <li class="mb-1">The site rewrites the Players tab after every sim. Press Refresh above to update it sooner.</li>
            <li class="mb-1">Don't edit the Players tab, since the next refresh overwrites it. Build your own tabs that pull from it; the sync never touches them.</li>
            <li class="mb-1">Use Google Sheets' built-in FILTER, SORT, and QUERY functions to slice the data.</li>
            <li class="mb-1">Salary values are in thousands (e.g., 1500 = $1,500K).</li>
            <li class="mb-1">Stats columns (GP, MIN, FGM, etc.) are season totals. Divide by GP for per-game averages.</li>
        </ul>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
