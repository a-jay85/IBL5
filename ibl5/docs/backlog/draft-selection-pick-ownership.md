---
description: Draft submission path has no pick-slot ownership check — a GM can POST their own team name with an arbitrary round/pick owned by another team; two supporting data-quality findings (year-filterless queries, 12-hour clock timestamp).
last_verified: 2026-08-18
---

# Draft Selection: Pick-Slot Ownership and Supporting Data-Quality Findings

**Effort scale (this file):** S = ≤1 day, M = 1–3 days, L = > 3 days.

---

## Finding 1 — No pick-slot ownership check on draft submission (security/authz)

### Problem

`DraftController::submitSelection()` (`ibl5/classes/Draft/DraftController.php:88`) has one
ownership check: it confirms the POSTed `teamname` matches the session user's own team (lines
108–112). It does not verify that the session team *owns the submitted pick slot*.

```php
$teamName = is_string($post['teamname'] ?? null) ? $post['teamname'] : '';
$sessionTeam = $this->commonRepository->getTeamnameFromUsername($username);
if ($sessionTeam === null
    || $sessionTeam === \League\League::FREE_AGENTS_TEAM_NAME
    || $sessionTeam !== $teamName) {
    return $this->view->renderValidationError('You can only make selections for your own team.');
}
```

After that check passes, control flows to `handleDraftSelection()` (line 125), which calls
`DraftValidator::validateDraftSelection()` (`ibl5/classes/Draft/DraftValidator.php:18`). That
method checks only:

1. Player name is non-empty (line 20).
2. The `(round, pick)` slot is not already filled (line 23).
3. The player is not already drafted by another team (line 26).

It has no constructor parameters and no repository dependency — there is no ownership check.

A logged-in GM can therefore POST their own team name alongside an arbitrary `round`/`pick` that
belongs to a different team. If the slot is unfilled and the player name is valid, validation
passes. `processDraftSelection()` then calls
`DraftRepository::createPlayerFromDraftClass()` (line 150), which resolves the team id from the
session team name and inserts the rookie into `ibl_plr` with that tid — landing the player on the
attacker's roster.

### On `getCurrentOwnerOfDraftPick()`

`DraftRepository::getCurrentOwnerOfDraftPick()` (`ibl5/classes/Draft/DraftRepository.php:217`)
runs:

```sql
SELECT ownerofpick FROM ibl_draft_picks WHERE year = ? AND round = ? AND teampick_teamid = ? LIMIT 1
```

This method has two callers; neither gates the write:

- `DraftService.php:42` — called in `getDraftBoardData()`, the *display* path, to determine
  which team to show on the draft clock. Not invoked during submission.
- `DraftController.php:192` — called inside `sendNotifications()`, which is reached only after
  `commit()` succeeds (line 157). It resolves the *next unfilled* pick's origin team
  (`$nextPick['teamid']`) to choose a Discord mention target. It receives a different
  `(round, teamid)` than the one just submitted and its return value never gates the write.

### Suggested fix

Add an ownership check in `submitSelection()` before line 119's `handleDraftSelection()` call.
The check requires a two-step lookup:

1. Resolve the slot's *origin* team id: `ibl_draft.teamid` for the submitted `(round, pick)` —
   visible in the query at `DraftRepository.php:197–209`.
2. Call `getCurrentOwnerOfDraftPick($currentYear, $round, $originTeamId)` to get the team name
   of the current owner (which reflects any trade).
3. Reject if the returned owner name ≠ `$sessionTeam`.

Ownership must key on `ibl_draft_picks.ownerofpick`, never on `ibl_draft.team` (the original
slot holder). Prod confirms the distinction: the 2008 round-1, pick-3 slot sits in the
Timberwolves row in `ibl_draft` but was legitimately exercised by the Bulls, who had acquired
that pick via trade (verified 2026-08-18).

This is a security surface (authenticated POST endpoint, authz-gated write) with a genuine
design decision about where the session team's tid is resolved — it requires a `/plan`, not an
ad-hoc change. (See `.claude/rules/work-triage.md` § Safety mirror for the full gate surface.)

**Effort:** M | **Risk:** authz surface — human-merge required.

---

## Finding 2 — `getCurrentDraftSelection()` and `updateDraftTable()` have no year filter

### Problem

`ibl5/classes/Draft/DraftRepository.php`:

- Line 38 (inside `getCurrentDraftSelection()`):
  `SELECT \`player\` FROM \`ibl_draft\` WHERE \`round\` = ? AND \`pick\` = ?`
- Line 52 (inside `updateDraftTable()`):
  `UPDATE \`ibl_draft\` SET \`player\` = ?, \`date\` = ? WHERE \`round\` = ? AND \`pick\` = ?`

Neither query filters by year. Both match on `(round, pick)` only, so they will touch every row
with that coordinate across all years.

### Impact today

Harmless: `ibl_draft` currently holds only the current draft year (56 rows, all `year = 2008`,
verified 2026-08-18). `(round, pick)` is effectively unique in practice.

### Latent risk

When a second draft year's rows coexist in `ibl_draft`, a single submission will read and write
across both years — reading a stale player name for the slot check and clobbering both rows'
`player` and `date`.

### Suggested fix

Add a `year = ?` predicate to both queries, bound from the current season (already available in
the controller as `$this->season->endingYear`).

**Effort:** S | **Risk:** low — additive predicate, behavior unchanged until a second year is present.

---

## Finding 3 — 12-hour clock in draft timestamp

### Problem

`ibl5/classes/Draft/DraftController.php:144`:

```php
$date = date('Y-m-d h:i:s');
```

PHP's `h` is the 12-hour hour with no AM/PM marker. A pick made at 20:06 (8:06 PM) is stored as
`08:06:ss`, identical to a pick made at 08:06 AM. Evening picks and morning picks are therefore
ambiguous and sort incorrectly when the stored time is used for ordering.

### Suggested fix

Change `h` to `H` (24-hour). This is a one-character fix with no schema change.

**Effort:** S | **Risk:** cosmetic/data-quality.

---

## Relevant Files

- `ibl5/classes/Draft/DraftController.php` (lines 88–120 `submitSelection()`; line 144 timestamp; line 192 `getCurrentOwnerOfDraftPick()` post-commit call)
- `ibl5/classes/Draft/DraftValidator.php` (lines 18–31 `validateDraftSelection()` — no ownership check)
- `ibl5/classes/Draft/DraftRepository.php` (line 38 `getCurrentDraftSelection()` SELECT; line 52 `updateDraftTable()` UPDATE; line 217 `getCurrentOwnerOfDraftPick()`)
- `ibl5/classes/Draft/DraftService.php` (line 42 `getCurrentOwnerOfDraftPick()` display-path call)

## Status

⬜ Open — no plan yet. Finding 1 is a security/authz surface and requires a `/plan`. Findings 2 and 3 are latent/cosmetic and can follow. (discovered 2026-08-18 during sco-trailing-partial-record)
