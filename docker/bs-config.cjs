module.exports = {
  proxy: {
    target: "php:80",
    proxyRes: [
      function (proxyRes) {
        // Relax CSP connect-src so the browser-sync WebSocket can connect.
        // Only affects the dev proxy — the original PHP response is untouched.
        var csp = proxyRes.headers["content-security-policy"];
        if (csp) {
          proxyRes.headers["content-security-policy"] = csp.replace(
            /connect-src\s+'self'/,
            "connect-src 'self' ws: wss:"
          );
        }
      },
    ],
  },
  files: [
    "/watch/themes/**/*.css",
    "/watch/**/*.php",
    "/watch/classes/**/*.php",
  ],
  watchOptions: {
    usePolling: true,
    interval: 500,
  },
  // Give CSS watcher time to rebuild before reload
  reloadDelay: 300,
  // CSS changes inject without reload; PHP changes trigger full reload
  injectChanges: true,
  open: false,
  ui: false,
  port: 3000,
  notify: true,
  ghostMode: false,
};
