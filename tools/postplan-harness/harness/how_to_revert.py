"""The PR body's advisory `**How to revert:**` field: parse, mechanical default, resolve, inject.

Pure functions only. The line sits directly after the `**Merge danger:**` line. It is advisory:
no arming condition, shell twin, digest, or body checker reads it, so an old body without it is
never flagged. `merge_danger.py` is deliberately untouched; this module only borrows its
`FIELD_PREFIX` to find the line to insert after.
"""
from __future__ import annotations

import fnmatch
import re
from collections.abc import Iterable

from harness import merge_danger
from harness.gate_backtest import changes_from_unified_diff

FIELD_PREFIX = "**How to revert:**"
_LINE_RE = re.compile(r"^\*\*How to revert:\*\*")
MIN_TEXT = 12          # cleaned LLM text shorter than this falls back to the default
MAX_TEXT = 300         # cap, like merge_danger.WHY_MAX
_MIGRATION_RE = re.compile(r"^ibl5/migrations/.*\.sql$")
_PROD_SYNC_PATHS = ("bin/db-sync-prod", ".github/workflows/promote-to-production.yml")
_CONFIG_PATHS = ("ibl5/.htaccess", "ibl5/composer.json")
_WORKFLOW_GLOB = ".github/workflows/*.yml"

PLAIN = "Open a PR with `git revert <merge-sha>`"
STEP_MIGRATION = ("then add a new migration that undoes the schema change, "
                  "since git revert does not roll back an applied migration")
STEP_PROD_SYNC = "then rerun the previous prod sync or promotion so production matches the reverted code"
STEP_CONFIG = ("then confirm the deploy picked up the restored config "
               "(rerun `composer install` if `ibl5/composer.json` changed)")


def parse_how_to_revert(body: str | None) -> str | None:
    """The text after the prefix on the first field line, or None. Never raises."""
    if not body:
        return None
    for ln in body.split("\n"):
        if _LINE_RE.match(ln):
            return ln[len(FIELD_PREFIX):].strip()
    return None


def count_field_lines(body: str | None) -> int:
    return sum(1 for ln in (body or "").split("\n") if _LINE_RE.match(ln))


def default_how_to_revert(paths: Iterable[str]) -> str:
    """One mechanical line from the changed paths: plain git revert plus fixed-order extra steps."""
    plist = list(paths)
    steps = [PLAIN]
    if any(_MIGRATION_RE.match(p) for p in plist):
        steps.append(STEP_MIGRATION)
    if any(p in _PROD_SYNC_PATHS for p in plist):
        steps.append(STEP_PROD_SYNC)
    if any(p in _CONFIG_PATHS or fnmatch.fnmatch(p, _WORKFLOW_GLOB) for p in plist):
        steps.append(STEP_CONFIG)
    return "; ".join(steps) + "."


def default_from_diff(diff_text: str) -> str:
    return default_how_to_revert(p for _, p in changes_from_unified_diff(diff_text or ""))


def _clean(text: str | None) -> str:
    return re.sub(r"\s+", " ", (text or "").replace("|", " ")).strip()[:MAX_TEXT]


def resolve_how_to_revert(llm_text: str | None, mechanical: str) -> str:
    """The mechanical text wins when the diff touches a surface that needs an extra step.
    Otherwise keep the cleaned LLM text when it is long enough."""
    if mechanical != PLAIN + ".":
        return mechanical
    cleaned = _clean(llm_text)
    return cleaned if len(cleaned) >= MIN_TEXT else mechanical


def render_how_to_revert(text: str) -> str:
    return f"{FIELD_PREFIX} {text}"


def inject_how_to_revert(body: str | None, text: str) -> str:
    """Drop every existing field line, then place one directly after the Merge danger line.
    Append after a blank line when there is none. Idempotent."""
    rendered = render_how_to_revert(text)
    kept = [ln for ln in (body or "").split("\n") if not _LINE_RE.match(ln)]
    for i, ln in enumerate(kept):
        if ln.startswith(merge_danger.FIELD_PREFIX):
            return "\n".join(kept[: i + 1] + [rendered] + kept[i + 1:])
    base = "\n".join(kept).rstrip("\n")
    return f"{base}\n\n{rendered}" if base else rendered


def apply_to_body(body: str, diff_text: str) -> tuple[str, str]:
    """Return (body with exactly one How to revert line after Merge danger, source)."""
    llm_text = parse_how_to_revert(body)
    mechanical = default_from_diff(diff_text)
    text = resolve_how_to_revert(llm_text, mechanical)
    source = "mechanical" if text == mechanical else "llm"
    return inject_how_to_revert(body, text), source
