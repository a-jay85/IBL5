"""Conflict inventory, class gate, per-file resolver, abort/restore contract, and verdict."""
from __future__ import annotations

import glob
import os
import shutil
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Callable, Optional, NoReturn

# ── Conflict marker pattern (exact: 7-char leader + space or EOL, not >=7) ───
import re as _re
_CONFLICT_MARKER_PAT = _re.compile(
    r"^(<{7}( |$)|={7}$|>{7}( |$)|\|{7}( |$))", _re.MULTILINE
)

# ── Unresolvable class labels ─────────────────────────────────────────────────
UNRESOLVABLE_MIGRATION = "migration file"
UNRESOLVABLE_LOCKFILE  = "lockfile"
UNRESOLVABLE_STAGES    = "incomplete merge stages (add/add, no base)"
UNRESOLVABLE_EMPTY     = "no unmerged paths reported"

# A base stage plus exactly one side: one side modified, the other deleted.
MODIFY_DELETE_STAGES = (frozenset({1, 2}), frozenset({1, 3}))

# ── Resolution cap ────────────────────────────────────────────────────────────
MAX_RESOLVE_ROUNDS = 3
STAGE_NAMES = {1: "base", 2: "ours", 3: "theirs"}


@dataclass(frozen=True)
class ConflictInventory:
    files: tuple[str, ...] = ()
    unresolvable_reason: Optional[str] = None
    stage_sets: dict[str, frozenset[int]] = field(default_factory=dict)


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
    if stages != {1, 2, 3} and frozenset(stages) not in MODIFY_DELETE_STAGES:
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
    return ConflictInventory(
        files=tuple(sorted(stages)),
        unresolvable_reason=None,
        stage_sets={p: frozenset(s) for p, s in stages.items()},
    )


def extract_stages(
    run: Callable[..., str],
    key: str,
    path: str,
    present: frozenset[int] = frozenset({1, 2, 3}),
) -> dict[int, Path]:
    """Extract the present merge stages into /tmp files. Returns {stage_int: Path}.
    A modify/delete conflict passes the two stages it has; an absent stage is skipped."""
    stage_dir = Path(f"/tmp/postplan-conflict-stages-{key}")
    result: dict[int, Path] = {}
    for stage, name in STAGE_NAMES.items():
        if stage not in present:
            continue
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


def master_references(run, *, path, master_sha, merge_base) -> tuple[str, ...]:
    """Files master changed since the fork that newly mention `path` verbatim."""
    changed = [p for p in run("diff", "--name-only", merge_base, master_sha).splitlines()
               if p and p != path]
    if not changed:
        return ()

    def refs(rev):
        out = run("grep", "-l", "-F", "-e", path, rev, "--", *changed, check=False)
        return {line.split(":", 1)[1] for line in out.splitlines() if ":" in line}
    return tuple(sorted(refs(master_sha) - refs(merge_base)))


_ABSENT = "(absent: this side deleted the file)"


def resolve_one(
    llm,
    run: Callable[..., str],
    *,
    worktree: str,
    key: str,
    path: str,
    stages: frozenset[int] = frozenset({1, 2, 3}),
    branch_stage: int = 2,
    master_sha: str = "origin/master",
    pre_sha: str = "HEAD",
) -> tuple[bool, str]:
    """Resolve a single conflicted path. Returns (success, reason).

    `stages` is the path's unmerged stage set. `branch_stage` names the stage holding the
    branch's copy: 2 under `git merge origin/master`, 3 under a rebase. The missing stage
    of a modify/delete conflict names the side that deleted the file."""
    from .adapters.llm import TOOLED_MAX_TURNS

    if stages != {1, 2, 3}:
        deleted_side = ({1, 2, 3} - set(stages)).pop()
        if deleted_side == branch_stage:
            # The branch deleted it: keep the deletion unless master newly points at it.
            merge_base = run("merge-base", pre_sha, master_sha).strip()
            refs = master_references(
                run, path=path, master_sha=master_sha, merge_base=merge_base
            )
            if refs:
                return False, (f"deleted on branch but newly referenced on master: {path}"
                               f" <- {', '.join(refs)}")
            run("rm", "-q", "--", path)
            return True, ""

    stage_paths = extract_stages(run, key, path, present=frozenset(stages))
    base_path = stage_paths[1]
    ours_path = stage_paths.get(2, _ABSENT)
    theirs_path = stage_paths.get(3, _ABSENT)
    stage_dir = str(stage_paths[1].parent.parent)
    ours_is, theirs_is = (
        ("the branch", "master") if branch_stage == 2 else ("master", "the branch")
    )

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
            f"Stage 2 (ours) is {ours_is}; stage 3 (theirs) is {theirs_is}.\n"
            f"Never wholesale-take one side. Touch no other file.\n"
            f"End your reply with a line that is exactly `RESOLVED` or exactly `FAILED`."
        )
        if stages != {1, 2, 3}:
            prompt += (
                "\nOne side deleted this file. Either Write the merged result to the path "
                "and end with RESOLVED, or end with DELETED to keep the deletion."
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
        if last_line == "DELETED":
            if stages == {1, 2, 3}:
                last_error = "DELETED is only valid for a modify/delete conflict"
                continue
            run("rm", "-q", "--", path)
            return True, ""

        full_path = os.path.join(worktree, path)
        if not os.path.exists(full_path) or os.path.getsize(full_path) == 0:
            last_error = "file missing or empty after resolution"
            continue

        with open(full_path, encoding="utf-8", errors="replace") as fh:
            content = fh.read()
        marker_lines = [
            l for l in content.splitlines()
            if _CONFLICT_MARKER_PAT.match(l)
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
    branch_stage: int = 2,
    master_sha: str = "origin/master",
    pre_sha: str = "HEAD",
) -> ConflictResolutionResult:
    """Attempt to resolve all conflicted files. Returns ConflictResolutionResult."""
    for path in inventory.files:
        success, reason = resolve_one(
            llm, run, worktree=worktree, key=key, path=path,
            stages=inventory.stage_sets.get(path, frozenset({1, 2, 3})),
            branch_stage=branch_stage, master_sha=master_sha, pre_sha=pre_sha,
        )
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
    """Abort any in-progress merge or rebase, hard-reset to pre_rebase_sha, then raise
    HarnessError."""
    from .state import HarnessError

    run("merge", "--abort", check=False)
    run("rebase", "--abort", check=False)

    for subdir in ("rebase-merge", "rebase-apply"):
        path = run("rev-parse", "--git-path", subdir, check=False).strip()
        if os.path.exists(path):
            raise HarnessError(
                "rebase-conflict",
                f"RESTORE-FAILED: {subdir} still present after abort; {reason}",
            )
    if run("rev-parse", "-q", "--verify", "MERGE_HEAD", check=False).strip():
        raise HarnessError(
            "rebase-conflict",
            f"RESTORE-FAILED: MERGE_HEAD still present after abort; {reason}",
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


def assert_text_only(worktree: str, paths) -> Optional[str]:
    """Plan Phase 3d step 2: every resolved path must decode as UTF-8 after
    `rebase --continue`. A binary payload that slipped past the class gate cannot
    have been three-way merged as text. Returns a reason string on the first
    non-decodable path, or None when every path decodes."""
    for rel in paths:
        full = Path(worktree) / rel
        if not full.exists():
            continue
        try:
            full.read_bytes().decode("utf-8")
        except UnicodeDecodeError as exc:
            return f"non-UTF-8 content after resolution in {rel}: {exc}"
    return None


VERDICT_CLEAN = "CONFLICT-REVIEW=CLEAN"
VERDICT_FOUND_PROBLEM = "CONFLICT-REVIEW=FOUND-PROBLEM"
VERDICT_ABSENT = "CONFLICT-REVIEW=ABSENT"
_VERDICT_TOKENS = frozenset((VERDICT_CLEAN, VERDICT_FOUND_PROBLEM))


def parse_verdict(reply: str) -> str:
    """Return the normalized conflict-review verdict for a model reply.

    A line is a verdict token when, after str.strip(), it equals exactly
    CONFLICT-REVIEW=CLEAN or CONFLICT-REVIEW=FOUND-PROBLEM. Any other line is
    ignored, including lines that merely contain a token inside prose.

    - exactly one distinct token anywhere in the reply -> that token
    - both tokens present                               -> FOUND-PROBLEM (fail closed)
    - no token, empty reply                             -> ABSENT
    """
    found = {
        stripped
        for stripped in (line.strip() for line in reply.splitlines())
        if stripped in _VERDICT_TOKENS
    }
    if not found:
        return VERDICT_ABSENT
    if len(found) > 1:
        return VERDICT_FOUND_PROBLEM
    return next(iter(found))


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
        "Your reply MUST begin with the verdict. Line 1 of your reply is exactly "
        "`CONFLICT-REVIEW=CLEAN` or exactly `CONFLICT-REVIEW=FOUND-PROBLEM` and "
        "nothing else: no preamble, no summary sentence, no blank line, no code fence "
        "before it. Put your reasoning on the lines after the verdict. "
        "Write the verdict token exactly once."
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
    except Exception:
        reply = ""

    verdict_line = parse_verdict(reply)

    post_resolution_sha = run("rev-parse", "HEAD").strip()
    verdict_path = Path(
        f"/tmp/postplan-conflict-verdict-{key}-{post_resolution_sha}.ok"
    )
    verdict_path.write_text(verdict_line + "\n" + reply)

    sidecar_path = Path(f"/tmp/postplan-conflict-sha-{key}.txt")
    sidecar_path.write_text(post_resolution_sha)

    return verdict_line


def purge_verdict_artifacts(key: str) -> None:
    """Remove the sidecar and verdict temp files for key (per-rebase). Never removes the
    conflict flag file, and never removes the auto-resolved list: a BEHIND re-rebase in the
    same run must not drop files an earlier rebase recorded (see purge_autoresolved_list)."""
    sidecar = f"/tmp/postplan-conflict-sha-{key}.txt"
    if os.path.exists(sidecar):
        os.unlink(sidecar)
    for p in glob.glob(f"/tmp/postplan-conflict-verdict-{key}-*.ok"):
        os.unlink(p)


def purge_autoresolved_list(key: str) -> None:
    """Remove the auto-resolved files list for key. Call exactly once per harness run,
    before the first rebase, so a stale list from a previous run of the same branch is cleared."""
    autoresolved = f"/tmp/postplan-conflict-files-{key}-autoresolved.txt"
    if os.path.exists(autoresolved):
        os.unlink(autoresolved)
