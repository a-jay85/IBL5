"""Conflict inventory, class gate, per-file resolver, abort/restore contract, and verdict."""
from __future__ import annotations

import glob
import os
import shutil
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Callable, Mapping, Optional, NoReturn

from .adaptations import RESOLVER_ADAPTATION_INSTRUCTIONS, parse_justifications

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
DECLINE_RETRY_MODEL = "opus"
MAX_DECLINE_RETRIES = 2        # per harness run, shared by the plain and stacked paths
DECLINE_REASON_CAP = 300       # chars of model text carried into the decline reason
DECLINE_CONTEXT_CAP = 20_000   # chars per side of diff context in the retry prompt
STAGE_NAMES = {1: "base", 2: "ours", 3: "theirs"}
HUNK_CONTEXT_LINES = 3            # unchanged lines shown before and after each hunk
HUNK_MAX_COUNT = 20               # more hunks than this -> whole-file fallback
HUNK_ARTIFACT_MAX_BYTES = 16_000  # rendered artifact larger than this -> whole-file fallback
HUNK_STAGE_MAX_BYTES = 5_000_000  # any stage larger than this -> skip merge-file entirely
MERGE_FILE_TIMEOUT_S = 30
_MF_OPEN, _MF_BASE, _MF_SEP, _MF_CLOSE = (
    "<<<<<<< ours", "||||||| base", "=======", ">>>>>>> theirs")


@dataclass(frozen=True)
class ConflictInventory:
    files: tuple[str, ...] = ()
    unresolvable_reason: Optional[str] = None
    stage_sets: dict[str, frozenset[int]] = field(default_factory=dict)
    lockfiles: tuple[str, ...] = ()  # registered lockfiles set aside for regen (lockfile_regen.py)


@dataclass(frozen=True)
class ConflictResolutionResult:
    success: bool
    reason: str = ""
    resolved_files: tuple[str, ...] = ()
    adaptations: tuple = ()  # tuple of `Justification` records from the resolver
    decline_retries: int = 0  # Opus decline retries this call spent


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


def _regen_lockfile_ok(path: str, stages: set[int]) -> bool:
    """A registered lockfile both sides modified. Lazy import: lockfile_regen imports
    this module at load time."""
    from .lockfile_regen import regenerable
    return stages == {1, 2, 3} and regenerable(path) is not None


def classify(path: str, stages: set[int], *, regen_lockfiles: bool = False) -> Optional[str]:
    """Return None when resolvable; reason string when not. Checked in fixed order."""
    if path.startswith("ibl5/migrations/") and path.endswith(".sql"):
        return f"{UNRESOLVABLE_MIGRATION}: {path}"
    if regen_lockfiles and _regen_lockfile_ok(path, stages):
        return None
    if (os.path.basename(path) in {"composer.lock", "package-lock.json"}
            or path.endswith(".lock")):
        return f"{UNRESOLVABLE_LOCKFILE}: {path}"
    if stages != {1, 2, 3} and frozenset(stages) not in MODIFY_DELETE_STAGES:
        return f"{UNRESOLVABLE_STAGES}: {path} (stages {sorted(stages)})"
    return None


def inventory_conflicts(
    run: Callable[..., str], *, regen_lockfiles: bool = False,
) -> ConflictInventory:
    """Build a ConflictInventory from `git ls-files --unmerged`. With `regen_lockfiles`,
    a registered lockfile both sides modified goes to `lockfiles` instead of bailing."""
    text = run("ls-files", "--unmerged")
    stages = parse_unmerged(text)
    if not stages:
        return ConflictInventory(unresolvable_reason=UNRESOLVABLE_EMPTY)
    lockfiles: list[str] = []
    for path in sorted(stages):
        if regen_lockfiles and _regen_lockfile_ok(path, stages[path]):
            lockfiles.append(path)
            continue
        reason = classify(path, stages[path])
        if reason:
            return ConflictInventory(files=(), unresolvable_reason=reason)
    files = tuple(p for p in sorted(stages) if p not in lockfiles)
    return ConflictInventory(
        files=files,
        unresolvable_reason=None,
        stage_sets={p: frozenset(stages[p]) for p in files},
        lockfiles=tuple(lockfiles),
    )


def extract_stages(
    run: Callable[..., str],
    key: str,
    path: str,
    present: frozenset[int] = frozenset({1, 2, 3}),
    revs: Optional[Mapping[int, str]] = None,
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
        spec = f"{revs[stage]}:{path}" if revs else f":{stage}:{path}"
        content = run("show", spec, check=False)
        if not content:
            raise ValueError(
                f"stage {stage} empty for {path!r} — class gate should have prevented this"
            )
        dest.write_text(content)
        result[stage] = dest
    return result


@dataclass(frozen=True)
class HunkContext:
    path: Path        # the artifact file the resolver Reads
    hunk_count: int
    size_bytes: int


def _parse_diff3_hunks(lines: list[str]) -> Optional[list[dict]]:
    """Parse `git merge-file --diff3` output into hunks. None on any out-of-order marker
    or unclosed hunk (a literal marker line in unchanged text lands here)."""
    hunks: list[dict] = []
    state = "text"
    cur: dict = {}
    for idx, line in enumerate(lines):
        if line == _MF_OPEN:
            if state != "text":
                return None
            cur = {"open": idx, "base": [], "ours": [], "theirs": []}
            state = "ours"
        elif line == _MF_BASE:
            if state != "ours":
                return None
            state = "base"
        elif line == _MF_SEP:
            if state != "base":
                return None
            state = "theirs"
        elif line == _MF_CLOSE:
            if state != "theirs":
                return None
            cur["close"] = idx
            hunks.append(cur)
            state = "text"
        elif state in ("ours", "base", "theirs"):
            cur[state].append(line)
    if state != "text":
        return None
    return hunks


def build_hunk_context(
    path: str,
    stage_paths: Mapping[int, Path],
    *,
    context_lines: int = HUNK_CONTEXT_LINES,
    max_hunks: int = HUNK_MAX_COUNT,
    max_bytes: int = HUNK_ARTIFACT_MAX_BYTES,
) -> Optional[HunkContext]:
    """Write a small per-hunk artifact into the stage dir, or return None.

    None means "keep the whole-file prompt": modify/delete, no detected hunks, too many
    hunks, an oversized artifact, a binary-like or oversized stage, or any merge-file
    failure. Never raises. A stale artifact from an earlier build is deleted on decline."""
    artifact = (
        stage_paths[1].parent.parent / "hunks" / (path.replace("/", "__") + ".md")
        if 1 in stage_paths else None
    )

    def decline() -> None:
        if artifact is not None:
            try:
                artifact.unlink(missing_ok=True)
            except OSError:
                pass
        return None

    try:
        if set(stage_paths) != {1, 2, 3}:
            return decline()
        for stage in (1, 2, 3):
            data = stage_paths[stage].read_bytes()
            if len(data) > HUNK_STAGE_MAX_BYTES or b"\x00" in data:
                return decline()
        proc = subprocess.run(
            ["git", "merge-file", "-p", "--diff3",
             "-L", "ours", "-L", "base", "-L", "theirs",
             str(stage_paths[2]), str(stage_paths[1]), str(stage_paths[3])],
            capture_output=True, text=True, encoding="utf-8", errors="replace",
            stdin=subprocess.DEVNULL, timeout=MERGE_FILE_TIMEOUT_S, check=False,
        )
        if proc.returncode == 0 or proc.returncode > 127:
            return decline()
        lines = proc.stdout.splitlines()
        hunks = _parse_diff3_hunks(lines)
        if hunks is None:
            return decline()
        count = len(hunks)
        if count == 0 or count > max_hunks:
            return decline()
        if proc.returncode < 127 and count != proc.returncode:
            return decline()

        parts = [
            f"# Hunk context for {path}",
            f"{count} conflict hunk(s). Each hunk lists up to {context_lines} unchanged "
            "lines before and after it,",
            "then the base, ours, and theirs text of the conflicting region.",
            "Text outside these hunks merged cleanly and needs no decision.",
            "Line ranges are the approximate position of each conflict block in the "
            "worktree file",
            "when that file still holds conflict markers.",
            "",
        ]
        removed = 0
        for i, hunk in enumerate(hunks, start=1):
            prev_close = hunks[i - 2]["close"] if i > 1 else -1
            next_open = hunks[i]["open"] if i < count else len(lines)
            before = lines[prev_close + 1:hunk["open"]]
            after = lines[hunk["close"] + 1:next_open]
            before = before[-context_lines:] if context_lines > 0 else []
            after = after[:context_lines]
            start = hunk["open"] + 1 - removed
            end = start + len(hunk["ours"]) + len(hunk["theirs"]) + 2
            removed += 1 + len(hunk["base"])
            parts.append(f"## Hunk {i} of {count} (worktree lines ~{start}-{end})")
            parts.append("--- context before")
            parts.extend(before)
            parts.append("--- base (merge base)")
            parts.extend(hunk["base"])
            parts.append("--- ours (stage 2)")
            parts.extend(hunk["ours"])
            parts.append("--- theirs (stage 3)")
            parts.extend(hunk["theirs"])
            parts.append("--- context after")
            parts.extend(after)
            parts.append("")
        encoded = "\n".join(parts).encode("utf-8")
        if len(encoded) > max_bytes:
            return decline()
        artifact.parent.mkdir(parents=True, exist_ok=True)
        artifact.write_bytes(encoded)
        return HunkContext(artifact, count, len(encoded))
    except (OSError, subprocess.SubprocessError, UnicodeError, ValueError):
        return decline()


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


def _decline_reason(path: str, reply: str) -> str:
    """`resolver declined: <path>` plus the model's own words before FAILED, capped.
    Lines are joined with a single space and the last DECLINE_REASON_CAP chars are kept;
    runs of spaces inside a line are not collapsed. A literal `tree proof failed` in the
    model text is rewritten to `tree-proof failed`, so the reason never contains the key
    rebase_cause.py classifies on."""
    lines = [l.strip() for l in reply.splitlines() if l.strip()]
    said = " ".join(lines[:-1]).replace("tree proof failed", "tree-proof failed")
    said = said[-DECLINE_REASON_CAP:]
    return f"resolver declined: {path}" + (f" (model: {said})" if said else "")


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
    adaptations: Optional[list] = None,
    model: str = "sonnet",
    extra_context: str = "",
    max_rounds: int = MAX_RESOLVE_ROUNDS,
    stage_revs: Optional[Mapping[int, str]] = None,
) -> tuple[bool, str]:
    """Resolve a single conflicted path. Returns (success, reason).

    `adaptations` is a sink the caller passes to collect `Justification` records from the
    RESOLVED round.

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

    stage_paths = extract_stages(run, key, path, present=frozenset(stages),
                                 revs=stage_revs)
    base_path = stage_paths[1]
    ours_path = stage_paths.get(2, _ABSENT)
    theirs_path = stage_paths.get(3, _ABSENT)
    stage_dir = str(stage_paths[1].parent.parent)
    hunk_ctx = build_hunk_context(path, stage_paths)
    ours_is, theirs_is = (
        ("the branch", "master") if branch_stage == 2 else ("master", "the branch")
    )

    pre_snapshot = run("status", "--porcelain")
    last_error = ""

    for _round in range(max_rounds):
        if hunk_ctx is None:
            head = (
                f"You are resolving exactly ONE conflicted file: `{path}`.\n"
                f"The three merge stages are already extracted:\n"
                f"  base:   {base_path}\n"
                f"  ours:   {ours_path}\n"
                f"  theirs: {theirs_path}\n"
                f"Read all three before deciding. Write the merged result to "
                f"`{worktree}/{path}` with Write.\n"
            )
        else:
            head = (
                f"You are resolving exactly ONE conflicted file: `{path}`.\n"
                f"The conflicting regions are summarized in a hunk context file:\n"
                f"  hunk context: {hunk_ctx.path} ({hunk_ctx.hunk_count} hunk(s))\n"
                f"It holds the base, ours, and theirs text of every conflicting hunk "
                f"with a few unchanged lines around each. Everything outside those "
                f"hunks already merged cleanly.\n"
                f"Read the hunk context file once. Then Read `{worktree}/{path}` once: "
                f"it is the current merged copy you start from. Write the complete "
                f"merged file to `{worktree}/{path}` with Write, keeping every line "
                f"outside the hunks as that copy has it.\n"
                f"Never Read a whole stage file under `{stage_dir}`. If one hunk needs "
                f"more surrounding text, Read that one stage file with offset and limit "
                f"(at most 200 lines). Never re-read a file you already read; work from "
                f"what you have.\n"
            )
        prompt = head + (
            f"Stage 2 (ours) is {ours_is}; stage 3 (theirs) is {theirs_is}.\n"
            f"Never wholesale-take one side. Touch no other file.\n"
            f"{RESOLVER_ADAPTATION_INSTRUCTIONS}\n"
            f"End your reply with a line that is exactly `RESOLVED` or exactly `FAILED`."
        )
        if stages != {1, 2, 3}:
            prompt += (
                "\nOne side deleted this file. Either Write the merged result to the path "
                "and end with RESOLVED, or end with DELETED to keep the deletion."
            )
        if extra_context:
            prompt += "\n\n" + extra_context
        if last_error:
            prompt += f"\n\nPrevious round failed: {last_error}"

        try:
            reply = llm.call_tooled(
                f"conflict-resolve:{path}",
                model,
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
            return False, _decline_reason(path, reply)
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

        found = ()
        if last_line == "RESOLVED":
            found, reason = parse_justifications(reply, path)
            if reason:
                last_error = f"malformed ADAPTED-LINE justification: {reason}"
                continue

        run("add", "--", path)

        if last_line == "RESOLVED":
            if adaptations is not None:
                adaptations.extend(found)
            return True, ""

        last_error = "reply did not end with RESOLVED"

    return False, f"round cap exceeded: {path}"


def decline_retry_context(run, *, path: str, pre_sha: str, master_sha: str,
                          prior_reason: str, replay_base: str = "") -> str:
    """Both sides' whole change to `path` since the merge base, for one Opus retry.

    `replay_base` scopes the branch diff to the replayed range on the stacked --onto
    path, where `pre_sha` also carries the parent branch's commits."""
    mb = run("merge-base", pre_sha, master_sha, check=False).strip()
    branch_base = replay_base or mb
    branch = run("diff", f"{branch_base}..{pre_sha}", "--", path, check=False)[:DECLINE_CONTEXT_CAP]
    master = run("diff", f"{mb}..{master_sha}", "--", path, check=False)[:DECLINE_CONTEXT_CAP]
    return (
        f"A first resolver attempt declined this file. Its words: {prior_reason}\n"
        "The branch's whole change to this file since the merge base. Keep every added "
        f"line verbatim:\n```diff\n{branch}\n```\n"
        f"Master's whole change to this file. Keep it too:\n```diff\n{master}\n```\n"
        "Combine both changes. End with FAILED only if the two changes cannot both hold, "
        "and say why in one line first."
    )


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
    model: str = "sonnet",
    extra_context: str = "",
    max_rounds: int = MAX_RESOLVE_ROUNDS,
    decline_retry_model: Optional[str] = None,
    max_decline_retries: int = 0,
    replay_base: str = "",
) -> ConflictResolutionResult:
    """Attempt to resolve all conflicted files. Returns ConflictResolutionResult.

    With `decline_retry_model` set, a file the resolver declined is retried once on that
    model with both sides' whole diffs, at most `max_decline_retries` times per call."""
    sink: list = []
    retries = 0
    for path in inventory.files:
        success, reason = resolve_one(
            llm, run, worktree=worktree, key=key, path=path,
            stages=inventory.stage_sets.get(path, frozenset({1, 2, 3})),
            branch_stage=branch_stage, master_sha=master_sha, pre_sha=pre_sha,
            adaptations=sink,
            model=model, extra_context=extra_context, max_rounds=max_rounds,
        )
        if (not success and decline_retry_model and retries < max_decline_retries
                and reason.startswith("resolver declined: ")):
            retries += 1
            ctx = decline_retry_context(run, path=path, pre_sha=pre_sha,
                                        master_sha=master_sha, prior_reason=reason,
                                        replay_base=replay_base)
            success, reason = resolve_one(
                llm, run, worktree=worktree, key=key, path=path,
                stages=inventory.stage_sets.get(path, frozenset({1, 2, 3})),
                branch_stage=branch_stage, master_sha=master_sha, pre_sha=pre_sha,
                adaptations=sink, model=decline_retry_model, max_rounds=1,
                extra_context=(extra_context + "\n\n" + ctx) if extra_context else ctx,
            )
        if not success:
            return ConflictResolutionResult(False, reason, decline_retries=retries)
    return ConflictResolutionResult(True, "", tuple(inventory.files), tuple(sink),
                                    decline_retries=retries)


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


def verdict_ok_path(key: str, sha: str) -> str:
    """The conflict-review verdict file review_resolution writes for `sha`."""
    return f"/tmp/postplan-conflict-verdict-{key}-{sha}.ok"


# Terminal LLM failures: both sit in runner._FAIL_CLOSED_KINDS, and a second ask after
# either one only repeats the failure. Everything else (tooled-cli, tooled-empty, a
# subprocess error, a tokenless reply) gets the one re-ask.
_NO_RETRY_KINDS = ("llm-usage-limit", "llm-auth-expired")


def review_resolution(
    llm,
    run: Callable[..., str],
    *,
    worktree: str,
    key: str,
    resolved_files: tuple[str, ...],
    proof_out: str,
    adaptations: tuple = (),
    retries_out: dict[str, int] | None = None,
) -> str:
    """Run a read-only conflict review. Writes verdict and sidecar files. Returns verdict line.
    A reply with no verdict token (or a non-terminal exception) is re-asked once; when that
    happens and retries_out is given, retries_out["conflict-review"] is set to 1.
    `adaptations` holds the AcceptedAdaptation records the tree proof let through; when
    non-empty they are written to adaptations.txt and named in the prompt."""
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
    adaptations_path = review_dir / "adaptations.txt"
    if adaptations:
        adaptations_path.write_text("".join(
            f"- {a.path}: +{a.original} => +{a.adapted} [{a.old} -> {a.new}]\n"
            for a in adaptations
        ))
    else:
        adaptations_path.unlink(missing_ok=True)

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
    if adaptations:
        prompt += (
            "\nadaptations.txt lists branch-added lines the resolver rewrote to follow a "
            "rename master made in the same file; a separate reviewer confirmed each one. "
            "Treat any you judge wrong as FOUND-PROBLEM."
        )

    reply = ""
    for attempt in range(2):   # one retry, never a loop: attempt 0, then exactly one re-ask
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
        except Exception as exc:
            reply = ""
            if getattr(exc, "kind", "") in _NO_RETRY_KINDS:
                break   # a usage limit or expired login does not improve on a second ask
        if parse_verdict(reply) != VERDICT_ABSENT:
            break
        if attempt == 0 and retries_out is not None:
            retries_out["conflict-review"] = 1

    verdict_line = parse_verdict(reply)

    post_resolution_sha = run("rev-parse", "HEAD").strip()
    verdict_path = Path(verdict_ok_path(key, post_resolution_sha))
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
