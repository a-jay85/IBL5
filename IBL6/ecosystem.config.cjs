module.exports = {
  apps: [
    {
      name: 'ibl6',
      script: 'server.js',
      cwd: '/home/iblhoops/public_html/IBL6',
      node_args: '--max-old-space-size=128',
      env: {
        PORT: 3001,
        UV_THREADPOOL_SIZE: 2
      },
      // server.js emits process.send('ready') once the HTTP server is listening,
      // so `pm2 start` blocks until IBL6 is actually serving (or listen_timeout).
      wait_ready: true,
      listen_timeout: 10000,
      max_memory_restart: '200M',
      min_uptime: '10s',
      max_restarts: 10,
      log_date_format: 'YYYY-MM-DD HH:mm:ss',
      out_file: 'logs/ibl6-out.log',
      error_file: 'logs/ibl6-error.log',
      merge_logs: true
    }
  ]
};
