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
    // Subsumes /watch/classes/**/*.php — listing that separately made chokidar
    // watch the same ~1k files twice.
    "/watch/**/*.php",
  ],
  watchOptions: {
    // Docker Desktop on macOS does not reliably propagate host fsevents into
    // the container, so the watcher has to poll. Polling cost scales with the
    // number of traversed files, and the bare "/watch/**/*.php" glob walks the
    // whole worktree — ~44.8k files, of which ~35.7k are dependencies, build
    // output, or test scratch that can never drive a browser reload.
    // node_modules alone is 21.8k and is a symlink into the main checkout
    // (bin/wt-up), so every worktree stack re-walked that same tree twice a
    // second. With ~14 stacks up, the browsersync containers idled at ~12% CPU
    // each — ~100% of one host core spent stat()ing files nobody edits.
    //
    // Anchored at /watch/ so only top-level directories match, and each
    // alternative also matches the directory itself, not just its children:
    // chokidar v3 skips descending only when the directory path itself is
    // ignored, so a bare "**/vendor/**" would still walk into vendor.
    ignored:
      /^\/watch\/(node_modules|vendor|tmp|tmp-tests|tests|coverage|playwright-report|test-results|logs|cache|backups|spreadsheets)(\/|$)/,
    usePolling: true,
    // Halving the poll frequency roughly halves the remaining stat() work:
    // warm spot samples read ~3% per container at 500ms vs ~1.3% at 1000ms.
    // 1s is the worst-case reload latency and is not perceptible next to
    // reloadDelay (300ms) plus the page render itself.
    interval: 1000,
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
