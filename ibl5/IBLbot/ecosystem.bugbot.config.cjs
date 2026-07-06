module.exports = {
  apps: [
    {
      name: 'ibl-bug-bot',                 // distinct from prod 'iblbot'
      script: 'dist/bug-bot/index.js',     // the Phase 5 entrypoint's compiled output
      cwd: '/Users/ajaynicolas/GitHub/IBL5/ibl5/IBLbot',  // Mac main checkout (NOT the prod path)
      max_memory_restart: '150M',
      min_uptime: '10s',
      max_restarts: 10,
      log_date_format: 'YYYY-MM-DD HH:mm:ss'
    }
  ]
};
