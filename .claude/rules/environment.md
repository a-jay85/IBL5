---
description: Environment setup: CSS build, IBLbot, and environment-specific gotchas.
paths:
  - "**/design/**/*.css"
  - "**/IBLbot/**/*"
last_verified: 2026-05-31
---

# Environment Commands

## CSS Development (Tailwind 4)

```bash
# DEVELOPMENT: Auto-rebuilds on save
bun run css:watch

# RECOVERY: one-off rebuild when the watcher misses a change (e.g. after a branch switch)
bunx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css
```

- See `.claude/rules/css-auto-rebuild.md` — this is the sanctioned recovery exception to the no-manual-build rule, not a routine step.
- **NEVER use `--minify` locally.** Minification is handled by GitHub Actions on merge/push.
- The compiled `themes/IBL/style/style.css` is `.gitignore`-enforced (built on production) — only commit the source CSS files in `design/`.

## IBLbot (Discord Bot)

```bash
# Build the TypeScript bot — must run from IBLbot directory, NOT from ibl5/
cd /Users/ajaynicolas/GitHub/IBL5/ibl5/IBLbot && npm run build
```
- CWD is usually `ibl5/` so bare `npm run build` will fail ("Missing script: build"). Always `cd` to the IBLbot directory first.
