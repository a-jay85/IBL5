<?php

declare(strict_types=1);

namespace ApiKeys;

use ApiKeys\Contracts\ApiKeysViewInterface;
use Utilities\HtmlSanitizer;

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
     * @see ApiKeysViewInterface::renderNoKeyState()
     */
    public function renderNoKeyState(): string
    {
        ob_start();
        ?>
<div class="ibl-card">
    <div class="ibl-card__header">
        <h2 class="ibl-card__title">API Key</h2>
    </div>
    <div class="ibl-card__body">
        <p class="mb-4">You don't have an API key yet. Generate one to use the Player Export feature with Google Sheets.</p>
        <p class="mb-6 text-sm text-gray-600">An API key lets you pull live player data directly into your spreadsheet using Google Sheets' <code>IMPORTDATA</code> function.</p>
        <form method="post" action="modules.php?name=ApiKeys&amp;op=generate">
            <?= \Utilities\CsrfGuard::generateToken('api_keys_generate') ?>
            <button type="submit" class="ibl-btn ibl-btn--primary">Generate API Key</button>
        </form>
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
        <h2 class="ibl-card__title">API Key Generated</h2>
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

        <a href="modules.php?name=ApiKeys" class="ibl-btn ibl-btn--primary">Done</a>
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
        <h2 class="ibl-card__title">API Key</h2>
    </div>
    <div class="ibl-card__body">
        <table class="ibl-data-table mb-6">
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

        <div class="flex gap-4 items-center">
            <form method="post" action="modules.php?name=ApiKeys&amp;op=revoke" onsubmit="return confirm('Are you sure? You will need to generate a new key and update any spreadsheets that use the current one.');">
                <?= \Utilities\CsrfGuard::generateToken('api_keys_revoke') ?>
                <button type="submit" class="ibl-btn ibl-btn--danger">Revoke Key</button>
            </form>

            <a href="modules.php?name=PlayerExportGuide" class="ibl-btn ibl-btn--ghost">Player Export Guide</a>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
