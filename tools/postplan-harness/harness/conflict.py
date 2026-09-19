"""Conflict inventory, class gate, per-file resolver, abort/restore contract, and verdict."""
from __future__ import annotations

import glob
import os
import shutil
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Optional, NoReturn

# ── Unresolvable class labels ─────────────────────────────────────────────────
UNRESOLVABLE_MIGRATION = "migration file"
UNRESOLVABLE_LOCKFILE  = "lockfile"
UNRESOLVABLE_STAGES    = "incomplete merge stages (delete/add vs modify)"
UNRESOLVABLE_EMPTY     = "no unmerged paths reported"

# ── Resolution cap ────────────────────────────────────────────────────────────
MAX_RESOLVE_ROUNDS = 3
STAGE_NAMES = {1: "base", 2: "ours", 3: "theirs"}


@dataclass(frozen=True)
class ConflictInventory:
    files: tuple[str, ...] = ()
    unresolvable_reason: Optional[str] = None


@dataclass(frozen=True)
class ConflictResolutionResult:
    success: bool
    reason: str = ""
    resolved_files: tuple[str, ...] = ()


def parse_unmerged(text: str) -> dict[str, set[int]]:
    """Parse `git ls-files --unmerged` output. Returns {path: {stages}}."""
    result: dict[str, set[int]] = {}
    for line in text.splitlines():
        if not line.strip():
            continue
        if "\t" not in line:
            raise ValueError(f"malformed ls-files --unmerged line: {line!r}")
        left, path = line.split("\t", 1)
        parts = left.split()
        if len(parts) < 3:
            raise ValueError(f"malformed ls-files --unmerged line: {line!r}")
        stage = int(parts[2])
        result.setdefault(path, set()).add(stage)
    return result


def classify(path: str, stages: set[int]) -> Optional[str]:
    """Return None when resolvable; reason string when not. Checked in fixed order."""
    if path.startswith("ibl5/migrations/") and path.endswith(".sql"):
        return f"{UNRESOLVABLE_MIGRATION}: {path}"
    if (os.path.basename(path) in {"composer.lock", "package-lock.json"}
            or path.endswith(".lock")):
        return f"{UNRESOLVABLE_LOCKFILE}: {path}"
    if stages != {1, 2, 3}:
        return f"{UNRESOLVABLE_STAGES}: {path} (stages {sorted(stages)})"
    return None


def inventory_conflicts(run: Callable[..., str]) -> ConflictInventory:
    """Build a ConflictInventory from `git ls-files --unmerged`."""
    text = run("ls-files", "--unmerged")
    stages = parse_unmerged(text)
    if not stages:
        return ConflictInventory(unresolvable_reason=UNRESOLVABLE_EMPTY)
    for path in sorted(stages):
        reason = classify(path, stages[path])
        if reason:
            return ConflictInventory(files=(), unresolvable_reason=reason)
    return ConflictInventory(files=tuple(sorted(stages)), unresolvable_reason=None)


def extract_stages(run: Callable[..., str], key: str, path: str) -> dict[int, Path]:
    """Extract the three merge stages into /tmp files. Returns {stage_int: Path}."""
    stage_dir = Path(f"/tmp/postplan-conflict-stages-{key}")
    result: dict[int, Path] = {}
    for stage, name in STAGE_NAMES.items():
        subdir = stage_dir / name
        subdir.mkdir(parents=True, exist_ok=True)
        flat = path.replace("/", "__")
        dest = subdir / flat
        content = run("show", f":{stage}:{path}", check=False)
        if not content:
            raise ValueError(
                f"stage {stage} empty for {path!r} — class gate should have prevented this"
            )
        dest.write_text(content)
        result[stage] = dest
    return result


def resolve_one(
    llm, run: Callable[..., str], *, worktree: str, key: str, path: str
) -> tuple[bool, str]:
    """Resolve a single conflicted path. Returns (success, reason)."""
    from .adapters.llm import TOOLED_MAX_TURNS

    stage_paths = extract_stages(run, key, path)
    base_path = stage_paths[1]
    ours_path = stage_paths[2]
    theirs_path = stage_paths[3]
    stage_dir = str(stage_paths[1].parent.parent)

    pre_snapshot = run("status", "--porcelain")
    last_error = ""

    for _round in range(MAX_RESOLVE_ROUNDS):
        prompt = (
            f"You are resolving exactly ONE conflicted file: `{path}`.\n"
            f"The three merge stages are already extracted:\n"
            f"  base:   {base_path}\n"
            f"  ours:   {ours_path}\n"
            f"  theirs: {theirs_path}\n"
            f"Read all three before deciding. Write the merged result to "
            f"`{worktree}/{path}` with Write.\n"
            f"Never wholesale-take one side. Touch no other file.\n"
            f"End your reply with a line that is exactly `RESOLVED` or exactly `FAILED`."
        )
        if last_error:
            prompt += f"\n\nPrevious round failed: {last_error}"

        try:
            reply = llm.call_tooled(
                f"conflict-resolve:{path}",
                "sonnet",
                prompt,
                cwd=worktree,
                allowed_tools=("Read", "Write"),
                denied_tools=("Bash", "Agent"),
                add_dirs=(stage_dir,),
                max_turns=TOOLED_MAX_TURNS,
            )
        except Exception as exc:
            last_error = f"call_tooled raised: {exc}"
            continue

        last_line = next(
            (l.strip() for l in reversed(reply.splitlines()) if l.strip()), ""
        )
        if last_line == "FAILED":
            return False, f"resolver declined: {path}"

        full_path = os.path.join(worktree, path)
        if not os.path.exists(full_path) or os.path.getsize(full_path) == 0:
            last_error = "file missing or empty after resolution"
            continue

        with open(full_path, encoding="utf-8", errors="replace") as fh:
            content = fh.read()
        marker_lines = [
            l for l in content.splitlines()
            if l.startswith("<<<<<<<") or l.startswith("=======") or l.startswith(">>>>>>>")
        ]
        if marker_lines:
            last_error = f"conflict markers remain: {marker_lines[0]!r}"
            continue

        post_snapshot = run("status", "--porcelain")
        pre_set = set(pre_snapshot.splitlines())
        changed = set()
        for sline in post_snapshot.splitlines():
            if sline not in pre_set:
                sline_path = sline[3:].split(" -> ")[-1].strip().strip('"')
                changed.add(sline_path)
        if changed - {path}:
            last_error = f"blast-radius violation: touched {changed - {path}}"
            continue

        run("add", "--", path)

        if last_line == "RESOLVED":
            return True, ""

        last_error = "reply did not end with RESOLVED"

    return False, f"round cap exceeded: {path}"


def resolve_all(
    llm,
    run: Callable[..., str],
    *,
    worktree: str,
    key: str,
    inventory: ConflictInventory,
) -> ConflictResolutionResult:
    """Attempt to resolve all conflicted files. Returns ConflictResolutionResult."""
    for path in inventory.files:
        success, reason = resolve_one(llm, run, worktree=worktree, key=key, path=path)
        if not success:
            return ConflictResolutionResult(False, reason)
    return ConflictResolutionResult(True, "", tuple(inventory.files))


def abort_and_restore(
    run: Callable[..., str],
    *,
    worktree: str,
    pre_rebase_sha: str,
    reason: str,
) -> NoReturn:
    """Abort any in-progress rebase, hard-reset to pre_rebase_sha, then raise HarnessError."""
    from .state import HarnessError

    run("rebase", "--abort", check=False)

    for subdir in ("rebase-merge", "rebase-apply"):
        path = run("rev-parse", "--git-path", subdir, check=False).strip()
        if os.path.exists(path):
            raise HarnessError(
                "rebase-conflict",
                f"RESTORE-FAILED: {subdir} still present after abort; {reason}",
            )

    run("reset", "--hard", pre_rebase_sha)

    head = run("rev-parse", "HEAD").strip()
    porcelain = run("status", "--porcelain")
    tracked_changes = [
        l for l in porcelain.splitlines() if l.strip() and not l.startswith("?? ")
    ]
    if head != pre_rebase_sha or tracked_changes:
        raise HarnessError(
            "rebase-conflict",
            f"RESTORE-FAILED: HEAD={head!r} status={porcelain!r}; {reason}",
        )

    raise HarnessError("rebase-conflict", reason)


def review_resolution(
    llm,
    run: Callable[..., str],
    *,
    worktree: str,
    key: str,
    resolved_files: tuple[str, ...],
    proof_out: str,
) -> str:
    """Run a read-only conflict review. Writes verdict and sidecar files. Returns verdict line."""
    review_dir = Path(f"/tmp/postplan-conflict-review-{key}")
    review_dir.mkdir(parents=True, exist_ok=True)

    manifest_src = Path(f"/tmp/postplan-conflict-files-{key}.txt")
    pre_patch_src = Path(f"/tmp/pr-ready-diff-pre-{key}.patch")
    if manifest_src.exists():
        shutil.copy(manifest_src, review_dir / "manifest.txt")
    if pre_patch_src.exists():
        shutil.copy(pre_patch_src, review_dir / "pre-rebase.patch")
    (review_dir / "proof-output.txt").write_text(proof_out)
    (review_dir / "resolved-files.txt").write_text("\n".join(resolved_files) + "\n")

    prompt = (
        "You are reviewing an automated three-way conflict resolution.\n"
        "The pre-rebase diff (pre-rebase.patch) shows what the branch contained before the replay.\n"
        "`resolved-files.txt` names every file a model touched.\n"
        "Read each of those files in the worktree and judge whether the resolution "
        "dropped, duplicated, or invented content relative to the pre-rebase diff.\n"
        "The tree proof already passed, so look for semantically wrong merges the proof cannot see.\n"
        "Reply with a FIRST line that is exactly `CONFLICT-REVIEW=CLEAN` or exactly "
        "`CONFLICT-REVIEW=FOUND-PROBLEM`, then your reasoning."
    )

    reply = ""
    try:
        reply = llm.call_tooled(
            "conflict-review",
            "sonnet",
            prompt,
            cwd=worktree,
            allowed_tools=("Read",),
            denied_tools=("Bash", "Agent", "Write", "Edit"),
            add_dirs=(str(review_dir),),
        )
        first_line = reply.splitlines()[0].rstrip() if reply.strip() else ""
    except Exception:
        first_line = ""

    if first_line not in ("CONFLICT-REVIEW=CLEAN", "CONFLICT-REVIEW=FOUND-PROBLEM"):
        verdict_line = "CONFLICT-REVIEW=ABSENT"
    else:
        verdict_line = first_line

    post_resolution_sha = run("rev-parse", "HEAD").strip()
    verdict_path = Path(
        f"/tmp/postplan-conflict-verdict-{key}-{post_resolution_sha}.ok"
    )
    verdict_path.write_text(verdict_line + "\n" + reply)

    sidecar_path = Path(f"/tmp/postplan-conflict-sha-{key}.txt")
    sidecar_path.write_text(post_resolution_sha)

    return verdict_line


def purge_verdict_artifacts(key: str) -> None:
    """Remove the sidecar and verdict files for key. Never removes the conflict flag file."""
    sidecar = f"/tmp/postplan-conflict-sha-{key}.txt"
    if os.path.exists(sidecar):
        os.unlink(sidecar)
    for p in glob.glob(f"/tmp/postplan-conflict-verdict-{key}-*.ok"):
        os.unlink(p)
