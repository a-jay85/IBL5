---
description: Tailwind CSS is auto-rebuilt by the ibl5-tailwind container; manual builds are recovery-only.
paths: "**/*.css"
last_verified: 2026-05-31
---

# CSS Auto-Rebuild (Tailwind)

The `ibl5-tailwind` Docker container runs `@tailwindcss/cli --watch=always` continuously. Any saved change to a CSS source file (`.css` in `design/`, or any file Tailwind scans for classes) triggers an automatic rebuild of `themes/IBL/style/style.css`.

## Rules

- **Do not run manual Tailwind build commands (`bunx @tailwindcss/cli`, `bun run css:build`) as a routine step** — the `ibl5-tailwind` watcher rebuilds `themes/IBL/style/style.css` automatically on save. Never pass `--minify` locally (CI handles minification).
- **Recovery (the one sanctioned exception):** run a single one-off `bunx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css` when the watcher misses a change — notably after a `git checkout`/branch switch — or when compiled output is verified stale. Confirm staleness first: `grep -c "your-class" themes/IBL/style/style.css`.
- After editing CSS source files, the compiled output is available within ~1 second — just reload the page.
- If the compiled CSS appears stale, check the container is running: `docker compose ps tailwind`. Restart it if needed: `docker compose restart tailwind`.
- In CI, `css:build` runs as part of the Docker image build — no manual step needed there either.
