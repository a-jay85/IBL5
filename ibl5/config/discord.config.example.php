<?php
/**
 * Discord Webhook Configuration Template
 *
 * Copy this file to discord.config.php and replace the placeholder URLs
 * with your actual Discord webhook URLs.
 *
 * IMPORTANT: Never commit discord.config.php to version control!
 * It is excluded via .gitignore to protect your webhook URLs.
 *
 * DELIBERATELY no 'testing' key here. In non-production environments
 * (CI E2E, a fresh dev checkout running on THIS example because no real
 * discord.config.php exists) Discord::postToChannel() resolves the webhook
 * via `$webhooks['testing'] ?? null` and only POSTs when it is non-null. The
 * placeholder URLs below are real discord.com URLs that return HTTP 400, which
 * sendCurlPOST() throws on — so the first code path that COMPLETES a
 * Discord-posting action (a played 1v1 game, a finalized trade, etc.) would
 * fail there. Omitting 'testing' makes non-prod posting a deterministic no-op
 * instead. To get non-prod posts, add your own 'testing' webhook in
 * discord.config.php (the gitignored real config).
 */

return [
    'webhooks' => [
        '1v1-games' => 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN',
        'draft-picks' => 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN',
        'extensions' => 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN',
        'free-agency' => 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN',
        'general-chat' => 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN',
        'rookie-options' => 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN',
        'trades' => 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN',
        'waiver-wire' => 'https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN',
    ],
    'iblbot_url' => 'http://localhost:50000',
    'bug_pipeline_approver_discord_id' => 'YOUR_APPROVER_DISCORD_ID',
];
