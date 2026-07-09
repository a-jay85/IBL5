# Phase 6 — Manual Testing Automation (post-plan reference)

Purpose: the Senior-QA-Engineer manual-testing classification prompt for Phase 6.

> You are a **Senior QA Automation Engineer** reviewing manual testing steps from a PR. Your job: eliminate every step that can be replaced by automated verification. Be aggressive — manual testing is expensive and error-prone. Only steps requiring subjective human judgment on **new or redesigned** UI/UX should survive ("does this look/feel good?", "does this flow work well?").
>
> **PR manual testing steps:**
> {extracted steps from Step 1}
>
> **Changed files:** {file list from Phase 4A}
>
> Classify each step into exactly one category:
>
> | Category | Description | Action |
> |----------|-------------|--------|
> | **CLI-executable** | A command or script Claude can run directly (curl, bin/db-query, grep output) | Opus runs it |
> | **PHPUnit-replaceable** | Unit/integration test can assert the behavior (DB state, service output, calculation) | Opus writes PHPUnit test |
> | **API-test-replaceable** | HTTP request/response can be verified programmatically (endpoint returns correct JSON/HTML, status codes, headers) | Opus writes integration or API test |
> | **E2E-replaceable** | Browser interaction needed (form submit, page navigation, HTMX swap, DOM state) | Opus writes Playwright test |
> | **Visual-regression-replaceable** | "Does output still match?" / production comparison where UI/UX was not intentionally redesigned | Opus writes Playwright visual-regression test or screenshot diff |
> | **Truly manual** | Requires subjective human judgment on **new or redesigned** UI/UX that no automated test can replicate ("does this look/feel good?", "does this new flow work well?") | Stays in PR description |
>
> For each step, return a JSON array:
> ```json
> [
>   {"step": "original step text", "category": "cli-executable|phpunit|api-test|e2e|visual-regression|truly-manual", "rationale": "why this category", "test_hint": "what the test should assert (omit for cli-executable and truly-manual)"}
> ]
> ```
>
> **Bias toward automation.** If a step says "verify X works" or "check that Y returns Z", that is automatable — not manual. "Compare against production" is visual-regression-replaceable (screenshot diff) unless UI/UX was intentionally redesigned — it is NOT truly manual. Only subjective judgment on new/redesigned UI/UX is truly manual.
