---
description: Updater pipeline web entry point (scripts/updateAllTheThings.php, run from the League Control Panel's "Update All The Things" button) and notes on its report-only steps.
last_verified: 2026-09-23
---

# Updater

The `classes/Updater/` pipeline (Controller → Service → View + Steps) has exactly one entry point: the root-level web POST endpoint `scripts/updateAllTheThings.php`.

## Invocation path

Triggered by the **"Update All The Things"** button in the League Control Panel (`classes/LeagueControlPanel/LeagueControlPanelView.php`):

```html
<button type="submit" formaction="/ibl5/scripts/updateAllTheThings.php" formmethod="post">
    Update All The Things
</button>
```

`scripts/updateAllTheThings.php` enforces:

- **Admin-only** — non-admins receive HTTP 403.
- **POST-only** — GET requests are redirected to `leagueControlPanel.php`.
- **CSRF-guarded** — token name `lcp_update_all`; invalid tokens receive HTTP 403.
- **Progressive HTML output** — streams progress via `flush()` as each Step completes.

See the security comment block at the top of `scripts/updateAllTheThings.php` for the full rationale.

## Steps

The step list and its order live in `scripts/updateAllTheThings.php`. This section records steps whose output needs explaining.

- `PlrRatingsCongruenceCheckStep` compares each player's 2GP, FTP, and 3GP ratings in the .plr file with the percentages from the player's real-life stat line. A player with at least 20 real-life attempts and a rating 3 or more points off gets a red `ERROR:` line in the update log. The pipeline still finishes, and the step never edits the .plr file.
  - In Preseason and HEAT the line warns that the rating will be overwritten by the start of the Regular Season unless the real-life line is updated. Snapshot history shows the engine resets these ratings from the real-life line at that point.
  - In Regular Season and Playoffs the line reports a standing disagreement between rating and real-life line.
  - The step skips Draft and Free Agency, where ratings are mid-rollover and hundreds of players mismatch.
  - The log lists at most 25 mismatches, then one line with the remaining count.

## No `modules/Updater/` and no CLI entry point

There is **no** `modules/Updater/index.php` route and **no** CLI entry point. The pipeline is web-only, reached outside the PHP-Nuke `modules/` system as a root-level `scripts/` endpoint.

The maintenance-backlog audit incorrectly described this pipeline as having no web-accessible route; it is web-only.
