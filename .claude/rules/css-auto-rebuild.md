---
description: Tailwind CSS is auto-rebuilt by the ibl5-tailwind Docker container — never run manual build commands.
last_verified: 2026-05-24
---

# CSS Auto-Rebuild (Tailwind)

The `ibl5-tailwind` Docker container runs `@tailwindcss/cli --watch=always` continuously. Any saved change to a CSS source file (`.css` in `design/`, or any file Tailwind scans for classes) triggers an automatic rebuild of `themes/IBL/style/style.css`.

## Rules

- **Never run `bunx @tailwindcss/cli`, `bun run css:build`, or any manual Tailwind build command.** The watcher handles it.
- After editing CSS source files, the compiled output is available within ~1 second — just reload the page.
- If the compiled CSS appears stale, check the container is running: `docker compose ps tailwind`. Restart it if needed: `docker compose restart tailwind`.
- In CI, `css:build` runs as part of the Docker image build — no manual step needed there either.
