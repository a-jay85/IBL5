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

## No `modules/Updater/` and no CLI entry point

There is **no** `modules/Updater/index.php` route and **no** CLI entry point. The pipeline is web-only, reached outside the PHP-Nuke `modules/` system as a root-level `scripts/` endpoint.

The maintenance-backlog audit incorrectly described this pipeline as having no web-accessible route; it is web-only.
