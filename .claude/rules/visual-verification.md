---
description: When and how to run visual regression checks for View/CSS changes.
paths:
  - "**/*View.php"
  - "**/design/**/*.css"
  - "**/themes/**/*.php"
  - "**/themes/**/*.html"
last_verified: 2026-07-27
---

# Visual Verification Required

Before telling the user a visual change works, you MUST confirm it renders correctly in a browser. Code review, source reading, and PHPUnit tests do not constitute visual verification. If you cannot verify, say so — do not claim the change works.

## How to Verify (in order of preference)

1. **`curl`** — for HTTP status, headers, and non-rendered content checks
   ```bash
   curl -s http://<slug>.localhost/ibl5/<path> | grep -i 'pattern'
   ```

2. **`google-chrome --headless`** — for rendered DOM, JS-driven content, and visual state
   ```bash
   google-chrome --headless --disable-gpu --dump-dom http://<slug>.localhost/ibl5/<path> 2>/dev/null | grep -i 'pattern'
   ```
   Use this when you need to confirm CSS classes are applied or dynamic content is rendered.

3. **Playwright E2E tests** — for interaction flows, multi-step behavior, or regression coverage
   - `cd ibl5 && bun run test:e2e` (full suite or `--grep` for targeted runs)
   - Preferred when the change involves user interaction (clicks, form submissions, navigation)

**Never use Chrome DevTools MCP** — it is disabled (`disabledMcpjsonServers: ["chrome-devtools"]`). Use the headless CLI equivalents above.

## What Counts as Verified

- You ran curl/headless Chrome and **saw** the correct output
- You ran an E2E test and it passed
- If fixing a bug: the bug is visibly gone in the output
- If adding a feature: the feature is visibly present and correct

## What Does NOT Count — Do Not Claim Success Based On

- "The CSS rule is correct so it should work"
- "I added the class so it will render properly"
- Passing PHPUnit tests (they don't render in a browser)
- Reading View/template source and concluding it looks right
- Assuming the change worked because no errors were thrown

## If Verification Is Blocked

If the dev server isn't running or Docker is down, **tell the user explicitly** that you could not verify the change visually. Do not silently skip verification or bury the caveat. The user needs to know so they can verify themselves.
