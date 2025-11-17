# Discord Integration Configuration Guide

## Overview
The IBL5 system integrates with Discord to send automated notifications about trades, free agency signings, draft picks, and other league activities. This guide explains how to configure Discord webhooks for production use.

## How It Works

The `Discord` class (`ibl5/classes/Discord.php`) sends notifications to Discord channels via webhooks. It uses **environment variables** for webhook URLs, which means:
- ✅ No secrets hardcoded in the codebase
- ✅ Safe to commit to public repositories
- ✅ Different URLs for dev/staging/production environments

## Configuration

### Step 1: Create Discord Webhooks

For each channel you want to receive notifications:

1. Go to your Discord server
2. Right-click the channel (e.g., #trades)
3. Select **Edit Channel** → **Integrations** → **Webhooks**
4. Click **New Webhook**
5. Name it (e.g., "IBL5 Trade Notifications")
6. Copy the **Webhook URL** (e.g., `https://discord.com/api/webhooks/123456789/abcdefg...`)

### Step 2: Set Environment Variables

Set these environment variables on your production server:

```bash
# Trading notifications
export DISCORD_WEBHOOK_TRADES="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN"

# General league chat
export DISCORD_WEBHOOK_GENERAL_CHAT="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN"

# Draft pick trades
export DISCORD_WEBHOOK_DRAFT_PICKS="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN"

# Free agency signings
export DISCORD_WEBHOOK_FREE_AGENCY="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN"

# Contract extensions
export DISCORD_WEBHOOK_EXTENSIONS="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN"

# Rookie option decisions
export DISCORD_WEBHOOK_ROOKIE_OPTIONS="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN"

# Waiver wire transactions
export DISCORD_WEBHOOK_WAIVER_WIRE="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN"

# 1v1 game results
export DISCORD_WEBHOOK_1V1_GAMES="https://discord.com/api/webhooks/YOUR_WEBHOOK_ID/YOUR_WEBHOOK_TOKEN"
```

### Step 3: Verify Configuration

You can verify your webhooks are configured by running:

```bash
php -r "echo 'DISCORD_WEBHOOK_TRADES: ' . (getenv('DISCORD_WEBHOOK_TRADES') ? 'CONFIGURED' : 'NOT SET') . PHP_EOL;"
```

## Supported Channels

| Channel Name | Environment Variable | Used For |
|--------------|---------------------|----------|
| `#trades` | `DISCORD_WEBHOOK_TRADES` | Trade announcements |
| `#general-chat` | `DISCORD_WEBHOOK_GENERAL_CHAT` | General league announcements |
| `#draft-picks` | `DISCORD_WEBHOOK_DRAFT_PICKS` | Draft pick trades |
| `#free-agency` | `DISCORD_WEBHOOK_FREE_AGENCY` | Free agent signings |
| `#extensions` | `DISCORD_WEBHOOK_EXTENSIONS` | Contract extensions |
| `#rookie-options` | `DISCORD_WEBHOOK_ROOKIE_OPTIONS` | Rookie option decisions |
| `#waiver-wire` | `DISCORD_WEBHOOK_WAIVER_WIRE` | Waiver wire transactions |
| `#1v1-games` | `DISCORD_WEBHOOK_1V1_GAMES` | 1v1 game results |

## Graceful Degradation

The system is designed to work even if webhooks are not configured:

- **Missing webhook URLs**: Notifications are skipped silently (no errors)
- **Localhost environment**: All Discord posting is disabled automatically
- **Webhook errors**: Logged to error log, but don't break the application

This means you can:
- ✅ Run the application locally without Discord
- ✅ Deploy to staging without webhooks
- ✅ Configure webhooks only in production

## Usage Examples

### From Trading Module
```php
// Announce a trade
Discord::postToChannel('#trades', 'Lakers trade LeBron James to Heat!');

// Get team owner's Discord ID
$discordId = Discord::getDiscordIDFromTeamname($db, 'Lakers');
```

### From Free Agency Module
```php
// Announce free agent signing
Discord::postToChannel('#free-agency', 'Lakers sign Giannis Antetokounmpo!');
```

## Troubleshooting

### Notifications not appearing in Discord?

1. **Check environment variables are set**:
   ```bash
   printenv | grep DISCORD_WEBHOOK
   ```

2. **Verify webhook URLs are valid**:
   - Should start with `https://discord.com/api/webhooks/`
   - Should not be empty or contain `INSERTWEBHOOKURLHERE`

3. **Check error logs**:
   ```bash
   tail -f /path/to/error_log | grep Discord
   ```

4. **Verify you're not on localhost**:
   - Discord posting is disabled when `$_SERVER['SERVER_NAME'] == 'localhost'`

### Webhooks were deleted/regenerated?

Just update the environment variables with the new webhook URLs and restart your web server.

## Security Best Practices

1. **Never commit webhook URLs** to version control
2. **Use environment variables** for all secrets
3. **Restrict webhook permissions** in Discord to only what's needed
4. **Monitor webhook usage** in Discord's audit log
5. **Rotate webhooks** periodically if exposed

## Alternative: Configuration File

If you prefer a configuration file instead of environment variables, you can modify `Discord::getWebhookUrlForChannel()`:

```php
protected static function getWebhookUrlForChannel(string $channelName): ?string
{
    // Load from config file (DO NOT COMMIT THIS FILE!)
    $config = require __DIR__ . '/../config/discord_webhooks.php';
    return $config[$channelName] ?? null;
}
```

Then create `config/discord_webhooks.php`:
```php
<?php
return [
    '#trades' => 'https://discord.com/api/webhooks/...',
    '#general-chat' => 'https://discord.com/api/webhooks/...',
    // etc.
];
```

**Important:** Add `config/discord_webhooks.php` to `.gitignore`!

## Support

For questions or issues, please open an issue on GitHub or contact the development team.
