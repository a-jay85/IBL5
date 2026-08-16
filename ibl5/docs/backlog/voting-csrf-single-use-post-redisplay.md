---
description: Single-use CSRF token consumed before ballot validation in VotingController leaves any validation failure in a dead end — Back returns a ballot with the consumed token; POST-redisplay is the proper fix.
last_verified: 2026-08-16
---

# Voting: POST-Redisplay on Validation Failure

## Problem

`ibl5/classes/Voting/VotingController.php` validates the CSRF token at line 38, before ballot
validation runs at line 88. `ibl5/classes/Security/CsrfGuard.php`'s `validateToken()` removes the
token on success (single-use). So when a GM submits an incomplete ASG or EOY ballot and hits a
validation error, their token is already consumed.

The error page's previous instruction ("Please go back and try again") led them to use the browser Back
button, which returns a ballot page carrying the now-dead token. The GM re-picks their selections and
resubmits, but `VotingController` rejects the second POST as an invalid or expired form submission —
a dead end with no path to a valid submission.

For ASG ballots this is especially painful: htmx history restore does not preserve JS-set checkbox
state, so the Back page shows all 310 player checkboxes unchecked, forcing the GM to re-select all 16
players before hitting the wall.

## Evidence

Reproduced over raw HTTP (discovered 2026-08-16 during htmx-history-disabled-submit):

- POST #1 — incomplete ballot → `"Sorry, you selected less than FOUR Western Backcourt players. Use the link below to return to the ballot and select FOUR players."` (validation error; token consumed)
- POST #2 — same `csrf_token` value, corrected ballot → `"Invalid or expired form submission. Please go back and try again."` (CSRF guard rejects the reused token)

## Proposed Fix (needs a `/plan`, security surface)

POST-redisplay: on validation failure, re-render the ballot with the submitted selections preserved and
a freshly generated CSRF token, instead of rendering a bare error page. This is the standard
Post/Redirect/Get-aware pattern for form validation.

Design questions to resolve in the plan:

- Should ballot validation run **before** `validateToken()` so the token is not consumed on a
  validation-only failure? What does that mean for replay protection — an attacker who sends a valid
  token with an invalid ballot would not burn the token.
- Alternatively, should `VotingController` generate a new token after validation fails and inject it
  into the re-rendered ballot response?
- How should preserved checkbox state be threaded back into `VotingBallotView`? The ballot view
  currently reads state from the database (not from POST), so a re-render path needs a POST-state
  overlay.

This is a security surface (POST endpoint, CSRF handling, auth-gated route) and has a genuine design
fork — it requires a `/plan`, not an ad-hoc change.

## Interim Mitigation (shipped in this PR)

`VotingSubmissionView::renderErrors()` now appends a "Return to the ballot" link
(`modules.php?name=Voting`) after the error paragraphs. A GM who hits a validation error can follow
that link to get a fresh ballot page with a fresh CSRF token. The cost is re-picking their selections,
but it is not a dead end.

`VotingController`'s two CSRF-rejection responses (lines 39 and 107) no longer say "please go back and
try again" — following that advice provably lands on the same wall. They now link to
`modules.php?name=Voting` for a fresh ballot with a fresh token. This is copy + a recovery link only;
the CSRF ordering itself is untouched and still needs the plan above.

## Relevant Files

- `ibl5/classes/Voting/VotingController.php` (line 38 — CSRF check; line 88 — ballot validation)
- `ibl5/classes/Voting/VotingSubmissionView.php` (interim mitigation: recovery link)
- `ibl5/classes/Security/CsrfGuard.php` (single-use `validateToken()` / `removeToken()`)

## Status

⬜ Open — interim mitigation shipped (htmx-history-disabled-submit); proper POST-redisplay fix
deferred pending a `/plan` (security surface + design fork). (discovered 2026-08-16 during htmx-history-disabled-submit)
