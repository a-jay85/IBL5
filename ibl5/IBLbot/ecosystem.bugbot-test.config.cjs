const fs = require('fs');
const path = require('path');
const dotenv = require('dotenv');

const HERE = '/Users/ajaynicolas/GitHub/IBL5/ibl5/IBLbot';
const TEST_ENV = path.join(HERE, '.env.bugbot.test');
const PROD_ENV = path.join(HERE, '.env.bugbot');

if (!fs.existsSync(TEST_ENV)) {
  throw new Error(`missing ${TEST_ENV} — copy .env.bugbot.test.example and fill it in`);
}
const t = dotenv.parse(fs.readFileSync(TEST_ENV));

// Fail closed: every var config.ts requires must be present AND non-empty here,
// or the unset one silently falls through to the production .env.bugbot.
const REQUIRED = ['BUG_BOT_DISCORD_TOKEN', 'DISCORD_GUILD_ID', 'BUG_CHANNEL_ID',
                  'BUG_PIPELINE_API_BASE_URL', 'API_KEY'];
const missing = REQUIRED.filter((k) => !t[k] || t[k].trim() === '');
if (missing.length > 0) {
  throw new Error(`.env.bugbot.test: unset or empty: ${missing.join(', ')}`);
}

// Fail closed: refuse to boot on the production bot's identity.
if (fs.existsSync(PROD_ENV)) {
  const p = dotenv.parse(fs.readFileSync(PROD_ENV));
  if (p.BUG_BOT_DISCORD_TOKEN && p.BUG_BOT_DISCORD_TOKEN === t.BUG_BOT_DISCORD_TOKEN) {
    throw new Error('refusing to start: test token equals the production bug-bot token');
  }
  if (p.BUG_CHANNEL_ID && p.BUG_CHANNEL_ID === t.BUG_CHANNEL_ID) {
    throw new Error('refusing to start: test channel equals the production bug channel');
  }
}

module.exports = {
  apps: [{
    name: 'ibl-bug-bot-test',            // distinct from prod 'ibl-bug-bot' and 'iblbot'
    script: 'dist/bug-bot/index.js',
    cwd: HERE,
    max_memory_restart: '150M',
    min_uptime: '10s',
    max_restarts: 10,
    log_date_format: 'YYYY-MM-DD HH:mm:ss',
    // EVERY var config.ts reads is masked here. An omission falls through to
    // the production .env.bugbot at the shared cwd — that is the whole risk.
    env: {
      EXPRESS_PORT: '50002',
      BUG_BOT_DISCORD_TOKEN: t.BUG_BOT_DISCORD_TOKEN,
      DISCORD_CLIENT_ID: t.DISCORD_CLIENT_ID || '',
      DISCORD_GUILD_ID: t.DISCORD_GUILD_ID,
      BUG_CHANNEL_ID: t.BUG_CHANNEL_ID,
      BUG_PIPELINE_API_BASE_URL: t.BUG_PIPELINE_API_BASE_URL,
      API_KEY: t.API_KEY,
    },
  }],
};
