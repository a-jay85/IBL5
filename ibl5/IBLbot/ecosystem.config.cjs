module.exports = {
  apps: [
    {
      name: 'iblbot',
      script: 'dist/index.js',
      cwd: '/home/iblhoops/public_html/ibl5/IBLbot',
      max_memory_restart: '150M',
      min_uptime: '10s',
      max_restarts: 10,
      log_date_format: 'YYYY-MM-DD HH:mm:ss'
    }
  ]
};
