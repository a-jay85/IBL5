---
description: Tailwind CSS is auto-rebuilt by the ibl5-tailwind container (on save) and by git hooks (after rebase/checkout/merge); manual builds are a last resort.
paths: "**/*.css"
last_verified: 2026-07-02
---

# CSS Auto-Rebuild (Tailwind)

The `ibl5-tailwind` Docker container runs `@tailwindcss/cli --watch=always` continuously. Any saved change to a CSS source file (`.css` in `design/`, or any file Tailwind scans for classes) triggers an automatic rebuild of `themes/IBL/style/style.css`.

## Two auto-rebuild paths (you should never need a manual build)

1. **Editor save → watcher.** The container's `--watch` reacts to the fsevent; compiled output is ready within ~1s. Just reload.
2. **Git op → hook.** Editor saves propagate, but git BULK file replacement (rebase / checkout / merge / pull rewriting a `design/**` source via rename) is **not reliably delivered** to `@parcel/watcher` through the macOS→Docker bind mount — so the compiled CSS silently goes stale. `bin/install-git-hooks` injects `bin/rebuild-css-if-source-changed` into **post-checkout, post-merge, post-rewrite** to heal that: it is mtime-gated (no-op when the output is already fresh) and backgrounded (git returns at once), rebuilding on the host (`bunx`) with a `docker exec` fallback. `bin/wt-new`/`bin/wt-up` build once at create/up; the hooks cover every git op after that.

## Rules

- **Do not run manual Tailwind build commands (`bunx @tailwindcss/cli`, `bun run css:build`) as a routine step.** The watcher covers saves; the git hooks cover rebase/checkout/merge. Never pass `--minify` locally (CI handles minification).
- **Manual build is a last resort** — reach for it only if both auto-paths are unavailable (git hooks not installed — run `bin/install-git-hooks`; or the container is down). Confirm staleness first (`grep -c "your-class" themes/IBL/style/style.css`), then: `bunx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css`.
- If the compiled CSS appears stale, check the container is running: `docker compose ps tailwind`. Restart it if needed: `docker compose restart tailwind`. Check the hook log at `~/.cache/ibl5-hooks/css-rebuild.log`.
- In CI, `css:build` runs as part of the Docker image build — no manual step needed there either.
