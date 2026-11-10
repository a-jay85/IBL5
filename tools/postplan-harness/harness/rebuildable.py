"""Tables a refresh step rebuilds from source data, so deleting their rows is a two-way door.

The Merge danger raise bar reads `REBUILDABLE_TABLES`: a one-way Why whose only cited loss is
rows of a table listed here is refused, because the table's refresh step rebuilds the rows. A
schema change on one of these tables stays one-way. `registry_drift` keeps the registry in step
with `ibl5/classes/Updater/Steps/Refresh*Step.php`, and `render_rule_block` produces the list the
door rule in `.claude/skills/post-plan/_pr-body-claims.md` carries between its markers.
"""
from __future__ import annotations

import re
from pathlib import Path

REBUILDABLE_TABLES: dict[str, str] = {
    "ibl_hist": "RefreshIblHistStep",
    "ibl_playoff_series_results": "RefreshPlayoffSeriesResultsStep",
    "ibl_team_season_records": "RefreshTeamSeasonRecordsStep",
}
# Steps that rebuild only keyed rows of the shared `cache` table through DatabaseCache.
# `cache` also holds keys no step rebuilds (RecordAnnouncementRepository REPLACE INTO
# `cache`), so the table itself is never registered.
CACHE_ONLY_REFRESH_STEPS: tuple[str, ...] = (
    "RefreshHeadToHeadRecordsStep",
    "RefreshRecordHoldersStep",
)
STEPS_GLOB = "ibl5/classes/Updater/Steps/Refresh*Step.php"

_DELETE_RE = re.compile(r"(?i)\bDELETE\s+FROM\s+`?(\w+)`?")
_INSERT_RE = re.compile(r"(?i)\bINSERT\s+INTO\s+`?(\w+)`?")


def _step_files(repo_root: str | Path) -> list[Path]:
    root = Path(repo_root)
    steps_glob = STEPS_GLOB
    steps_dir = root / Path(steps_glob).parent
    files = sorted(root.glob(steps_glob)) if steps_dir.is_dir() else []
    if not files:
        raise FileNotFoundError(steps_glob)
    return files


def scan_refresh_steps(repo_root: str | Path) -> dict[str, str]:
    """{table: step stem} for every table a Refresh step both deletes from and inserts into.
    A missing steps directory or an empty glob raises FileNotFoundError, so a drift check never
    passes on zero files."""
    found: dict[str, str] = {}
    for path in _step_files(repo_root):
        text = path.read_text(encoding="utf-8", errors="replace")
        deletes = set(_DELETE_RE.findall(text))
        inserts = set(_INSERT_RE.findall(text))
        for table in deletes & inserts:
            found[table] = path.stem
    return found


def registry_drift(repo_root: str | Path) -> list[str]:
    """One message per mismatch between the registry and the Refresh steps. [] means in step."""
    drift: list[str] = []
    scanned = scan_refresh_steps(repo_root)
    for table, step in sorted(scanned.items()):
        if table not in REBUILDABLE_TABLES:
            drift.append(f"unregistered rebuild target {table} in {step}")
    for table, step in sorted(REBUILDABLE_TABLES.items()):
        if table not in scanned:
            drift.append(f"registered table {table} is no longer rebuilt by {step}")
        elif scanned[table] != step:
            drift.append(f"registered table {table} maps to {step} but {scanned[table]} rebuilds it")
    classified = set(REBUILDABLE_TABLES.values()) | set(CACHE_ONLY_REFRESH_STEPS)
    for stem in sorted(p.stem for p in _step_files(repo_root)):
        if stem not in classified:
            drift.append(f"unclassified refresh step {stem}")
    return drift


def render_rule_block() -> str:
    """The Markdown list the door rule carries between its rebuildable-tables markers."""
    lines = []
    for table in sorted(REBUILDABLE_TABLES):
        lines.append(f"- `{table}` (rebuilt by `{REBUILDABLE_TABLES[table]}`)")
    return "\n".join(lines)
