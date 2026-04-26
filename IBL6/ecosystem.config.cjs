module.exports = {
  apps: [
    {
      name: 'ibl6',
      script: 'build/index.js',
      cwd: '/home/iblhoops/public_html/IBL6',
      node_args: '--max-old-space-size=128',
      env: {
        PORT: 3001,
        UV_THREADPOOL_SIZE: 2
      },
      log_date_format: 'YYYY-MM-DD HH:mm:ss',
      out_file: 'logs/ibl6-out.log',
      error_file: 'logs/ibl6-error.log',
      merge_logs: true
    }
  ]
};
