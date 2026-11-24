"""Phase 7 ci-fix carve-out for the .claude/rules byte budget (ADR-0184; see tools/postplan-harness/README.md).

When the only failing CI check is `Static guards` and `bin/check-rules-byte-budget`
fails locally, the ci-fix fixer may shrink rules files the PR already changes and may
add one `*-detail.md` companion that carries a `paths:` list. Every decision comes from
git or a `bin/check-*` exit code; the fixer's own claims never enter the verdict.

Phase 3 wires `snapshot` (before the fixer spawns), `carveout_prompt_text` (into the
ci-fix prompt) and `permit` (at the `denied_gate_edits` site).
"""
from __future__ import annotations

import re
import subprocess
from dataclasses import dataclass

BUDGET_CHECK_NAME = "Static guards"            # tests.yml job name; its step
                                               # "Check path-unscoped rules byte budget"
BUDGET_SCRIPT = "bin/check-rules-byte-budget"
SPLIT_CAPS_SCRIPT = "bin/test-architect-contract-split"  # caps _architect-contract.md, _plan-verification.md
POST_CHECKS = (
    ("bin/check-rules-byte-budget",),
    ("bin/check-prose", "--since=origin/master"),
    ("bin/check-docs", "--since=origin/master", "--no-staleness"),
)
RULES_PREFIX = ".claude/rules/"
DETAIL_SUFFIX = "-detail.md"


@dataclass(frozen=True)
class Carveout:
    active: bool
    reason: str                 # "active" or why not
    in_diff: frozenset[str]     # .claude/rules/*.md already in the PR diff


INACTIVE = Carveout(False, "not evaluated", frozenset())

_TRIM_HINT = (
    "Fix hint (budget-forced trim): {script} fails on {path} "
    "with this trim reverted. Shrink your own added text or move it to a -detail "
    "companion. Keep the trim in place."
)
BUDGET_TRIM_HINT = _TRIM_HINT.replace("{script}", BUDGET_SCRIPT)
_UNPLANNED_TAG_RE = re.compile(r"\[unplanned-scope\]")
_CAPPED_PATH_RE = re.compile(r"\.claude/[\w./-]+\.md\b")

_SCALAR_RE = re.compile(r"^paths:[ \t]*(\S.*)$")
_BARE_RE = re.compile(r"^paths:[ \t]*$")
_ITEM_RE = re.compile(r"^[ \t]*-[ \t]+(.*)$")


def _is_rules_md(path: str) -> bool:
    return path.startswith(RULES_PREFIX) and path.endswith(".md")


def _is_cap_candidate(path: str) -> bool:
    """A .claude markdown file that either caps script may list."""
    return path.startswith(".claude/") and path.endswith(".md")


def _hint_for(path: str) -> str:
    script = BUDGET_SCRIPT if _is_rules_md(path) else SPLIT_CAPS_SCRIPT
    return _TRIM_HINT.format(script=script, path=path)


def _git(worktree, *args: str) -> str:
    return subprocess.run(["git", "-C", str(worktree), *args], capture_output=True,
                          text=True, check=True).stdout


def frontmatter_paths(text: str) -> list[str]:
    """Globs from the `paths:` key of a rules file's frontmatter, as the byte-budget gate reads them."""
    lines = text.splitlines()
    if not lines or lines[0].rstrip() != "---":
        return []
    block: list[str] = []
    for line in lines[1:]:
        if line.rstrip() == "---":
            break
        block.append(line)
    else:
        return []
    globs: list[str] = []
    in_list = False
    for line in block:
        if _BARE_RE.match(line):
            in_list = True
            continue
        scalar = _SCALAR_RE.match(line)
        if scalar:
            globs.append(scalar.group(1))
            continue
        item = _ITEM_RE.match(line)
        if in_list and item:
            globs.append(item.group(1))
            continue
        if in_list and line and not line[0].isspace():
            in_list = False
    cleaned = (g.replace('"', "").replace("'", "").strip() for g in globs)
    return [g for g in cleaned if g]


def snapshot(names, git, worktree, *, run=subprocess.run) -> Carveout:
    """Decide on the pre-fix tree whether the carve-out applies to this attempt."""
    if worktree is None:
        return Carveout(False, "no worktree", frozenset())
    if set(names) != {BUDGET_CHECK_NAME}:
        return Carveout(False, "failing checks are not exactly Static guards: "
                        f"{sorted(names)}", frozenset())
    try:
        budget_rc = run([BUDGET_SCRIPT], cwd=worktree, capture_output=True,
                        text=True).returncode
    except Exception as exc:  # noqa: BLE001 - any failure to run leaves it inactive
        return Carveout(False, f"byte budget run failed: {exc}", frozenset())
    if budget_rc == 0:
        return Carveout(False, "local byte budget passes", frozenset())
    try:
        in_diff = frozenset(p for p in git.changed_files("origin/master")
                            if _is_rules_md(p))
    except Exception as exc:  # noqa: BLE001 - an unreadable PR diff leaves it inactive
        return Carveout(False, f"pr diff read failed: {exc}", frozenset())
    if not in_diff:
        return Carveout(False, "no rules file in PR diff", frozenset())
    return Carveout(True, "active", in_diff)


def file_verdicts(gate_hits, carveout, worktree, sha, *,
                  run=subprocess.run) -> list[tuple[str, str]]:
    """`(path, reason)` for every gate hit the carve-out still denies; empty means all allowed."""
    status: dict[str, str] = {}
    out = _git(worktree, "diff", "--name-status", "--no-renames", f"{sha}^", sha)
    for line in out.splitlines():
        parts = line.split("\t", 1)
        if len(parts) == 2:
            status[parts[1]] = parts[0].strip()
    denied: list[tuple[str, str]] = []
    for hit in gate_hits:
        if not _is_rules_md(hit):
            denied.append((hit, "outside the carve-out (only .claude/rules/*.md may change)"))
            continue
        st = status.get(hit)
        if st is None:
            denied.append((hit, "not present in the fix commit"))
        elif st == "D":
            denied.append((hit, "deleting a rules file is never allowed"))
        elif st == "M":
            if hit not in carveout.in_diff:
                denied.append((hit, "not in the PR diff vs origin/master"))
                continue
            old = int(_git(worktree, "cat-file", "-s", f"{sha}^:{hit}"))
            new = int(_git(worktree, "cat-file", "-s", f"{sha}:{hit}"))
            if new >= old:
                denied.append((hit, f"did not shrink ({old} -> {new} bytes)"))
        elif st == "A":
            if not hit.endswith(DETAIL_SUFFIX):
                denied.append((hit, "a new rules file must be a *-detail.md companion"))
            elif frontmatter_paths(_git(worktree, "show", f"{sha}:{hit}")) == []:
                denied.append((hit, "new companion has no paths: list"))
        else:
            denied.append((hit, f"unsupported change type {st}"))
    return denied


def post_check_failures(worktree, *, run=subprocess.run) -> list[str]:
    """Rerun the three gates the fix must satisfy; one entry per failure, all gates run."""
    failures: list[str] = []
    for argv in POST_CHECKS:
        cmd = " ".join(argv)
        try:
            res = run(list(argv), cwd=worktree, capture_output=True, text=True)
        except Exception as exc:  # noqa: BLE001 - a gate that cannot run is a failure
            failures.append(f"{cmd} raised: {exc}")
            continue
        if res.returncode != 0:
            text = (res.stderr or "").strip() or (res.stdout or "").strip()
            tail = next((ln for ln in reversed(text.splitlines()) if ln.strip()), "")
            failures.append(f"{cmd} exit {res.returncode}: {tail.strip()}")
    return failures


def permit(gate_hits, carveout, worktree, sha, log, *, run=subprocess.run) -> bool:
    """True only when every gate hit is a legal shrink and all post-checks pass."""
    if not carveout.active:
        log(f"phase7 ci-fix: rules-budget carve-out inactive ({carveout.reason})")
        return False
    try:
        denied = file_verdicts(gate_hits, carveout, worktree, sha, run=run)
    except Exception as exc:  # noqa: BLE001 - fail closed on any git error
        log(f"phase7 ci-fix: rules-budget carve-out error: {exc}")
        return False
    if denied:
        for path, reason in denied:
            log(f"phase7 ci-fix: rules-budget carve-out refused {path}: {reason}")
        return False
    failures = post_check_failures(worktree, run=run)
    if failures:
        for entry in failures:
            log(f"phase7 ci-fix: rules-budget carve-out post-check failed: {entry}")
        return False
    log(f"phase7 ci-fix: rules-budget carve-out allowed: {sorted(gate_hits)}")
    return True


def carveout_prompt_text(in_diff) -> str:
    """Paragraph `ci_fix_prompt` appends after the gate-edit deny when the snapshot is active."""
    files = ", ".join(sorted(in_diff))
    return (
        "ONE exception to the gate-owning deny above applies to this attempt, because "
        "the only failing check is Static guards and bin/check-rules-byte-budget fails "
        "locally. You MAY shrink these rules files, which this PR already changes: "
        f"`{files}`. You MAY also create ONE new `.claude/rules/<name>-detail.md` "
        "companion whose frontmatter carries a `paths:` list (see "
        "`.claude/rules/doc-freshness.md`) to hold moved sections. The harness checks "
        "your commit with git: every edited rules file must end with fewer bytes than "
        "before; a rules file outside that list, a deleted rules file, a new file that "
        "is not `*-detail.md` or lacks `paths:`, or any change under `bin/check-*`, "
        "`.github/workflows/**` or `harness/armable.py` discards the whole commit. "
        "After the commit the harness reruns `bin/check-rules-byte-budget`, "
        "`bin/check-prose --since=origin/master` and "
        "`bin/check-docs --since=origin/master --no-staleness`; a failure discards the "
        "commit. Bump `last_verified` on each touched rules file. Never raise a cap or "
        "edit `bin/check-rules-byte-budget`."
    )


def _caps(worktree: str, script: str = BUDGET_SCRIPT) -> dict[str, tuple[str, int, int, int]]:
    """path -> (class, cap, bytes, headroom), read from script --print-caps at HEAD."""
    proc = subprocess.run([script, "--print-caps"], cwd=worktree,
                          capture_output=True, text=True, timeout=60)
    if proc.returncode != 0:
        raise subprocess.SubprocessError(f"{script} --print-caps exit {proc.returncode}")
    caps: dict[str, tuple[str, int, int, int]] = {}
    for line in proc.stdout.splitlines():
        cols = line.split("\t")
        if len(cols) != 5:
            continue
        caps[cols[0]] = (cols[1], int(cols[2]), int(cols[3]), int(cols[4]))
    return caps


def _deleted_bytes(worktree: str, base_sha: str, path: str) -> int:
    """Bytes the PR trimmed from path, counted per diff hunk as removed bytes minus added
    bytes (floored at 0 per hunk). A line the PR rewrote still has new text in HEAD, so
    restoring the old wording costs only the size difference. Summing per hunk, not over
    the whole file, keeps a trim visible when the same PR also grew the file elsewhere.
    A file absent at either end counts 0."""
    try:
        _git(worktree, "cat-file", "-e", f"{base_sha}:{path}")
        _git(worktree, "cat-file", "-e", f"HEAD:{path}")
        diff = _git(worktree, "diff", "-U0", "--no-color", base_sha, "HEAD", "--", path)
    except subprocess.CalledProcessError:
        return 0
    total = 0
    removed = added = 0
    in_hunk = False
    for line in diff.splitlines():
        if line.startswith("@@"):
            total += max(0, removed - added)
            removed = added = 0
            in_hunk = True
        elif not in_hunk:
            continue
        elif line.startswith("-"):
            removed += len(line[1:].encode()) + 1
        elif line.startswith("+"):
            added += len(line[1:].encode()) + 1
    return total + max(0, removed - added)


def budget_forced_paths(worktree: str, base_sha: str, paths) -> frozenset[str]:
    """Capped `.claude` markdown files whose trim the byte cap forced: putting the PR's
    deleted bytes back would push the file past its cap (headroom - deleted < 0). Caps
    come from BUDGET_SCRIPT for `.claude/rules/*.md` and SPLIT_CAPS_SCRIPT for the rest;
    either script failing returns frozenset(). For a resident rules file the headroom
    already includes the MAX_TOTAL aggregate. Any git failure also returns frozenset()."""
    try:
        candidates = sorted({p for p in paths if _is_cap_candidate(p)})
        if not candidates:
            return frozenset()
        caps: dict[str, tuple[str, int, int, int]] = {}
        if any(_is_rules_md(p) for p in candidates):
            caps.update(_caps(worktree))
        if any(not _is_rules_md(p) for p in candidates):
            caps.update(_caps(worktree, SPLIT_CAPS_SCRIPT))
        forced = set()
        for path in candidates:
            row = caps.get(path)
            if row is None:
                continue
            deleted = _deleted_bytes(worktree, base_sha, path)
            if deleted > 0 and row[3] - deleted < 0:
                forced.add(path)
        return frozenset(forced)
    except (OSError, subprocess.SubprocessError, ValueError):
        return frozenset()


def forced_paths_since(worktree: str, base_sha: str) -> frozenset[str]:
    """budget_forced_paths over every file changed from base_sha to HEAD. Fail closed."""
    try:
        names = _git(worktree, "diff", "--name-only", base_sha, "HEAD").splitlines()
    except (OSError, subprocess.SubprocessError, ValueError):
        return frozenset()
    return budget_forced_paths(worktree, base_sha, names)


def annotate_work_list(work_list, worktree: str, base_sha: str, skip=()) -> list[dict]:
    """Return a new list. An item gains the hint only when all hold: its text carries
    [unplanned-scope], is not in skip, and cites a capped `.claude` path in budget_forced_paths.
    The hint is appended on a new line after the unchanged original text, one hint per
    forced path. Every other item, and every key besides text, passes through unchanged.
    The item count never changes; severity tags are never touched."""
    cited: list[list[str]] = []
    for item in work_list:
        text = item.get("text", "")
        if text in skip or not _UNPLANNED_TAG_RE.search(text):
            cited.append([])
        else:
            cited.append(list(dict.fromkeys(_CAPPED_PATH_RE.findall(text))))
    every = {p for paths in cited for p in paths}
    forced = budget_forced_paths(worktree, base_sha, every) if every else frozenset()
    out: list[dict] = []
    for item, paths in zip(work_list, cited):
        hits = [p for p in paths if p in forced]
        if not hits:
            out.append(item)
            continue
        hints = "\n".join(_hint_for(p) for p in hits)
        out.append({**item, "text": f"{item['text']}\n{hints}"})
    return out
