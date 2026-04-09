module.exports = {
  proxy: {
    target: "php:80",
    // Preserve the client's Host header (e.g. localhost:3000) instead of
    // rewriting it to the target (php:80). Apache derives SERVER_NAME from
    // the Host header, and DevAutoLogin only fires when SERVER_NAME matches
    // localhost/127.0.0.1/*.localhost — rewriting to "php" silently breaks
    // auto-login, so the user sees a logged-out view through the proxy.
    proxyOptions: {
      changeOrigin: false,
    },
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
  // Inject in <head> so the script survives HTMX body swaps (hx-boost).
  // Default injection before </body> gets removed when HTMX replaces body content.
  snippetOptions: {
    rule: {
      match: /<head[^>]*>/i,
      fn: function (snippet, match) {
        return match + snippet;
      },
    },
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
