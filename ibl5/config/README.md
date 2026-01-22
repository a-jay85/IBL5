# Configuration Directory

This directory contains configuration files for the IBL5 application.

## Discord Webhook Configuration

### Setup Instructions

1. **Copy the example config file:**
   ```bash
   cp discord.config.example.php discord.config.php
   ```

2. **Edit `discord.config.php`** and replace the placeholder webhook URLs with your actual Discord webhook URLs

3. **Never commit `discord.config.php`** - This file is gitignored and contains sensitive webhook URLs

### File Descriptions

- **`discord.config.example.php`** - Template file with placeholder webhooks (tracked in git)
- **`discord.config.php`** - Actual webhooks configuration (gitignored, contains secrets)

### Security

The `discord.config.php` file is excluded from version control via `.gitignore` to protect sensitive webhook URLs. Only the example template is tracked in git.

### Production Deployment

When deploying to production:

1. Copy `discord.config.example.php` to `discord.config.php` on the server
2. Update `discord.config.php` with production webhook URLs
3. Ensure file permissions are restrictive (e.g., `chmod 600 discord.config.php`)

### Testing

For local development, you can use test webhook URLs in your `discord.config.php`. The Discord class automatically uses the `testing` webhook when `SERVER_NAME` is `localhost` or `127.0.0.1`.
