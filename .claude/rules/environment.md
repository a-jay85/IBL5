# Environment Commands

## CSS Development (Tailwind 4)

```bash
# DEVELOPMENT: Auto-rebuilds on save
bun run css:watch

# LOCAL BUILDS: Rebuild CSS without minification (for commits)
bunx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css
```

- **NEVER use `--minify` locally.** Minification is handled by GitHub Actions on merge/push.
- **NEVER commit `themes/IBL/style/style.css`.** It is gitignored and built on production. Only commit the source CSS files in `design/`.

## IBLbot (Discord Bot)

```bash
# Build the TypeScript bot — must run from IBLbot directory, NOT from ibl5/
cd /Users/ajaynicolas/Documents/GitHub/IBL5/ibl5/IBLbot && npm run build
```
- CWD is usually `ibl5/` so bare `npm run build` will fail ("Missing script: build"). Always `cd` to the IBLbot directory first.
