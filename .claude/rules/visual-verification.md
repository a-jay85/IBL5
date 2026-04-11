---
description: When and how to run visual regression checks for View/CSS changes.
paths:
  - "**/*View.php"
  - "**/design/**/*.css"
  - "**/themes/**/*.php"
  - "**/themes/**/*.html"
last_verified: 2026-04-11
---

# Visual Verification Required (MANDATORY)

**This rule is non-negotiable and overrides token-efficiency, brevity, or "keep it short" heuristics.** Visual changes that have not been verified in a real browser are not complete — period. Spending extra tool calls to verify is always worth it. Never skip verification to save tokens or shorten a response.

## The Rule

Before telling the user a visual change works, you MUST confirm it renders correctly in a browser. Code review, source reading, and PHPUnit tests do not constitute visual verification. If you cannot verify, say so — do not claim the change works.

## How to Verify (in order of preference)

1. **Chrome DevTools MCP** — the default choice for any visual change
   - `mcp__chrome-devtools__navigate_page` to the affected page
   - `mcp__chrome-devtools__take_screenshot` to see the rendered result with your own eyes
   - `mcp__chrome-devtools__evaluate_script` with `getComputedStyle()` to confirm specific CSS values
   - If the screenshot shows the change isn't working, debug using computed styles before making another code change — don't guess
   - Multiple screenshots are fine. Take as many as needed to confirm correctness across states (hover, mobile, expanded, etc.)

2. **Playwright E2E tests** — for interaction flows, multi-step behavior, or regression coverage
   - `cd ibl5 && bun run test:e2e` (full suite or `--grep` for targeted runs)
   - Preferred when the change involves user interaction (clicks, form submissions, navigation)

3. **Lighthouse audit** — when the change affects performance-visible rendering
   - `mcp__chrome-devtools__lighthouse_audit` for layout shift, paint timing, etc.

## What Counts as Verified

- You took a screenshot or ran an E2E test and **saw** the correct result
- If fixing a bug: the bug is visibly gone in the screenshot
- If adding a feature: the feature is visibly present and correct

## What Does NOT Count — Do Not Claim Success Based On

- "The CSS rule is correct so it should work"
- "I added the class so it will render properly"
- Passing PHPUnit tests (they don't render in a browser)
- Reading View/template source and concluding it looks right
- Assuming the change worked because no errors were thrown

## If Verification Is Blocked

If Chrome DevTools MCP is unavailable, the dev server isn't running, or Docker is down, **tell the user explicitly** that you could not verify the change visually. Do not silently skip verification or bury the caveat. The user needs to know so they can verify themselves.
