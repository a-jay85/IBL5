"""The PR body's `## Removed guards` block: render, upsert, and the Phase 6.5 condition (19) read.

Pure functions only, apart from two IO shims: `run_detector`, which runs `bin/lib/deleted-guards`
on a diff, and `read_reasons`, which reads the impl session's reasons file under the git dir. The
Phase 6.5 shell twin is `pr_removed_guards_hold` in `bin/lib/pr-armable.sh`; both are held to the
shared case table `bin/fixtures/removed-guards/cases.json`, so a one-sided edit goes red.

Fail-closed: a detector that cannot run returns `(False, [])`, and the caller holds the PR.
"""
from __future__ import annotations

import re
import subprocess
from typing import Optional, Sequence

BEGIN = "<!-- removed-guards:begin -->"
END = "<!-- removed-guards:end -->"
PLACEHOLDER = "<one-line reason required>"
REASONS_FILE = "removed-guards-reasons.tsv"
SCOPE_NOTES_END = "<!-- scope-notes:end -->"
INTRO = "The deleted-guard check flagged these removals. Each needs a one-line reason, or auto-merge holds."
REASON_MIN = 15

_BULLET_RE = re.compile(r"^- `([a-z-]+@[^`#\s]+#[0-9a-f]{8})` — Reason: (.*)$")
_FILLER_RE = re.compile(r"^(none|n/?a|tbd|todo|unknown|no reason|-+|\.+)\.?$")
_PLACEHOLDER_RE = re.compile(r"^<.*>$")

Row = tuple  # (key, path, kind, snippet)


def is_substantive(reason: str) -> bool:
    """Design decision 9: 15+ chars after trimming, not a filler word, not a `<...>` placeholder."""
    r = reason.strip()
    if len(r) < REASON_MIN:
        return False
    if _FILLER_RE.match(r.lower()) or _PLACEHOLDER_RE.match(r):
        return False
    return True


def classify_body(body: str, flagged_keys: Optional[Sequence[str]] = None) -> list[str]:
    """Line-for-line port of `pr_removed_guards_hold`. Returns the hold lines; empty means armable."""
    keys = [k for k in (flagged_keys or []) if k]
    nb = ne = nbul = 0
    order_bad = inside = bad = False
    bullet_keys: list[str] = []
    out: list[str] = []
    for raw in (body or "").split("\n"):
        line = raw[:-1] if raw.endswith("\r") else raw
        if line == BEGIN:
            nb += 1
            inside = True
            continue
        if line == END:
            ne += 1
            if nb == 0:
                order_bad = True
            inside = False
            continue
        if not inside or not line.startswith("- "):
            continue
        nbul += 1
        m = _BULLET_RE.match(line)
        if not m:
            bad = True
            continue
        key, reason = m.group(1), m.group(2)
        bullet_keys.append(key)
        if not is_substantive(reason):
            out.append(f"removed-guards:UNJUSTIFIED:{key}")
    if nb == 0 and ne == 0:
        return [f"removed-guards:UNLISTED:{k}" for k in keys]
    if nb != 1 or ne != 1 or order_bad or nbul == 0 or bad:
        return ["removed-guards:MALFORMED"]
    listed = set(bullet_keys)
    out.extend(f"removed-guards:UNLISTED:{k}" for k in keys if k not in listed)
    return out


def run_detector(repo_root: str, diff_text: str,
                 plan_path: Optional[str]) -> tuple[bool, list[tuple[str, str, str, str]]]:
    """Run `bin/lib/deleted-guards` on `diff_text`. `(True, rows)` on exit 0/1, else `(False, [])`."""
    cmd = [f"{repo_root}/bin/lib/deleted-guards", "--diff", "-"]
    if plan_path:
        cmd += ["--plan", plan_path]
    try:
        proc = subprocess.run(cmd, input=diff_text, capture_output=True, text=True, timeout=60)
    except (OSError, subprocess.SubprocessError):
        return False, []
    if proc.returncode not in (0, 1):
        return False, []
    rows: list[tuple[str, str, str, str]] = []
    for line in proc.stdout.splitlines():
        if not line.startswith("REMOVED-GUARD\t"):
            continue
        parts = line.split("\t")
        if len(parts) < 5:
            return False, []
        rows.append((parts[1], parts[2], parts[3], "\t".join(parts[4:])))
    if proc.returncode == 1 and not rows:
        return False, []
    return True, rows


def _git_dir(worktree: str) -> Optional[str]:
    try:
        proc = subprocess.run(["git", "-C", worktree, "rev-parse", "--absolute-git-dir"],
                              capture_output=True, text=True, timeout=30)
    except (OSError, subprocess.SubprocessError):
        return None
    return proc.stdout.strip() if proc.returncode == 0 and proc.stdout.strip() else None


def parse_reasons(text: str) -> dict[str, str]:
    """`<key>\\t<reason>` per line; the last line for a key wins. Tabs and CR in a reason are dropped."""
    reasons: dict[str, str] = {}
    for line in text.split("\n"):
        line = line.rstrip("\r")
        if "\t" not in line:
            continue
        key, reason = line.split("\t", 1)
        key = key.strip()
        if not key:
            continue
        reasons[key] = reason.replace("\t", " ").replace("\r", "").strip()
    return reasons


def read_reasons(worktree: str) -> dict[str, str]:
    """Read `<git-dir>/removed-guards-reasons.tsv`. A missing file (or git dir) gives `{}`."""
    gd = _git_dir(worktree)
    if not gd:
        return {}
    try:
        with open(f"{gd}/{REASONS_FILE}", encoding="utf-8") as f:
            return parse_reasons(f.read())
    except OSError:
        return {}


def render_block(rows: Sequence[Sequence[str]], reasons: dict[str, str]) -> str:
    """The marker-delimited block, or "" when nothing is flagged. A missing reason renders the
    placeholder, which `classify_body` rejects, so the PR holds until a human writes one."""
    if not rows:
        return ""
    lines = [BEGIN, "## Removed guards", INTRO]
    seen: set[str] = set()
    for row in rows:
        key, snippet = row[0], row[3]
        if key in seen:
            continue
        seen.add(key)
        reason = (reasons.get(key) or "").replace("\n", " ").strip() or PLACEHOLDER
        lines.append(f"- `{key}` — Reason: {reason}")
        lines.append(f"  removed: `{snippet}`")
    lines.append(END)
    return "\n".join(lines)


def upsert_block(body: str, block: str) -> str:
    """Replace a well-formed block in place, else insert after the scope-notes block, else append.
    An empty `block` removes a stale well-formed span. Mirrors `classify.upsert_scope_notes`."""
    body = body or ""
    begin_idx = body.find(BEGIN)
    end_idx = body.find(END)
    if begin_idx != -1 and end_idx != -1 and begin_idx < end_idx:
        after_end = end_idx + len(END)
        if not block:
            head = body[:begin_idx].rstrip("\n")
            tail = body[after_end:].lstrip("\n")
            return head + ("\n\n" + tail if tail else "\n") if head else tail
        return body[:begin_idx] + block + body[after_end:]
    if not block:
        return body
    if not body.strip():
        return block
    sn = body.find(SCOPE_NOTES_END)
    if sn != -1:
        cut = sn + len(SCOPE_NOTES_END)
        tail = body[cut:].lstrip("\n")
        return body[:cut] + "\n\n" + block + ("\n\n" + tail if tail else "\n")
    return body.rstrip() + "\n\n" + block + "\n"
