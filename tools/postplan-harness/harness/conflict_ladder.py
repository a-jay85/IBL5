"""Conflict escalation ladder for `LiveGit._rebase_onto`.

Runs only after the first-pass resolution produced a merge commit that fails the
lost-work proof. Each rung edits files in place on top of that merge commit; the ladder
folds the edits in with `git commit --amend --no-edit` (parents unchanged), then
`post_rung_guard` checks parents, markers, text-only and finally the harness's own proof.
A rung's own claim (RESOLVED, a citation, a self-run proof) never counts. Only
`ctx.prove()` on the amended HEAD can make a rung succeed.
"""
from __future__ import annotations

import dataclasses
import os
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Optional

from .state import HarnessError

RUNG_REGEN = "regen"          # Phase 3
RUNG_OPUS = "opus-retry"      # Phase 4
RUNG_SESSION = "session"      # Phase 5

_STRUCTURAL_PREFIXES = (
    "LOST: file missing at HEAD: ",
    "LOST: deletion lost, still at HEAD: ",
)


@dataclass(frozen=True)
class LadderContext:
    llm: object                                # ClaudeCli or FixtureLlm
    run: Callable[..., str]                    # LiveGit._run
    run_out: Callable[..., tuple[int, str]]    # LiveGit._run_out
    prove: Callable[[], tuple[bool, str]]      # wraps LiveGit._run_proof(lostwork_path, key)
    worktree: str
    key: str
    master_sha: str
    pre_rebase_sha: str
    conflicted_files: tuple[str, ...]
    trail: tuple[str, ...] = ()


@dataclass(frozen=True)
class RungAttempt:
    applied: bool                     # False = rung had nothing to do; ladder skips the guard
    touched: tuple[str, ...]          # repo-relative paths the rung wrote
    note: str = ""


@dataclass(frozen=True)
class LadderOutcome:
    rung: str                         # "" when no rung passed
    resolved_files: tuple[str, ...]
    proof_out: str                    # full proof stdout of the final state
    trail: tuple[str, ...]            # one "<rung>: <reason>" per rung tried


@dataclass(frozen=True)
class GuardVerdict:
    ok: bool
    structural_ok: bool
    reason: str
    proof_out: str


Rung = tuple[str, Callable[[LadderContext, str], RungAttempt]]


def parse_lost_lines(proof_out: str) -> tuple[dict[str, list[str]], list[str]]:
    """Split the proof's LOST lines into {path: [+line/-line, ...]} and the raw
    structural lines (file missing, deletion lost), which carry no line content."""
    lost: dict[str, list[str]] = {}
    structural: list[str] = []
    for raw in proof_out.splitlines():
        if not raw.startswith("LOST: "):
            continue
        if raw.startswith(_STRUCTURAL_PREFIXES):
            structural.append(raw)
            continue
        rest = raw[len("LOST: "):]
        if ": " not in rest:
            structural.append(raw)
            continue
        path, line = rest.split(": ", 1)
        lost.setdefault(path, []).append(line)
    return lost, structural


def lost_count(proof_out: str) -> int:
    return sum(1 for l in proof_out.splitlines() if l.startswith("LOST: "))


def _parents(ctx: LadderContext) -> list[str]:
    return ctx.run("rev-list", "--parents", "-n", "1", "HEAD").split()[1:]


def _parents_reason(ctx: LadderContext) -> str:
    parents = _parents(ctx)
    if parents != [ctx.pre_rebase_sha, ctx.master_sha]:
        return f"merge parents changed: {parents!r}"
    return ""


def _untracked(ctx: LadderContext) -> set[str]:
    return set(ctx.run("ls-files", "--others", "--exclude-standard").split())


def _reset(ctx: LadderContext, sha: str, untracked_before: set[str]) -> None:
    """Hard-reset to `sha` and delete untracked files the rung created. Files that
    were untracked before the rung are left alone."""
    ctx.run("reset", "--hard", sha)
    for rel in _untracked(ctx) - untracked_before:
        try:
            os.remove(os.path.join(ctx.worktree, rel))
        except OSError:
            pass


def amend_merge(ctx: LadderContext, touched) -> str:
    """Fold the rung's edits into the merge commit. Returns "" on success or a reason."""
    reason = _parents_reason(ctx)
    if reason:
        return reason
    ctx.run("add", "-u")
    if touched:
        ctx.run("add", "-A", "--", *touched)
    rc, _ = ctx.run_out("diff", "--cached", "--quiet")
    if rc == 0:
        return "rung made no change"
    env = {**os.environ, "GIT_EDITOR": "true"}
    proc = subprocess.run(
        ["git", "-C", ctx.worktree, "-c", "core.editor=true",
         "commit", "--amend", "--no-edit"],
        capture_output=True, text=True, errors="replace", env=env,
    )
    if proc.returncode != 0:
        return f"amend failed: {(proc.stderr or proc.stdout).strip()[:400]}"
    return ""


def post_rung_guard(ctx: LadderContext, touched) -> GuardVerdict:
    """Parents, markers, text-only, then the proof. The first failure is structural."""
    from .adapters.gitad import _CONFLICT_MARKER_RE
    from .conflict import assert_text_only

    reason = _parents_reason(ctx)
    if reason:
        return GuardVerdict(False, False, reason, "")
    sweep_rc, sweep_out = ctx.run_out(
        "grep", "-n", "-E", _CONFLICT_MARKER_RE, "HEAD", "--", ".")
    if sweep_rc == 0:
        return GuardVerdict(False, False,
                            f"conflict markers survive: {sweep_out.strip()[:200]}", "")
    if sweep_rc != 1:
        return GuardVerdict(False, False,
                            f"marker sweep failed rc={sweep_rc}: {sweep_out.strip()[:200]}", "")
    text_reason = assert_text_only(ctx.worktree, tuple(touched) + tuple(ctx.conflicted_files))
    if text_reason:
        return GuardVerdict(False, False, text_reason, "")
    ok, out = ctx.prove()
    return GuardVerdict(ok, True, "" if ok else "proof still failed", out)


def run_ladder(ctx: LadderContext, proof_out: str, rungs) -> LadderOutcome:
    """Try each rung in order. Returns at the first rung whose amended HEAD passes the
    guard. A rung that passes the structural checks and lowers the LOST count becomes
    the next rung's base. `UsagePause` is a BaseException and escapes untouched."""
    if rungs is None:
        raise ValueError("run_ladder needs an explicit rungs tuple (default_rungs(branch))")
    base_sha = ctx.run("rev-parse", "HEAD").strip()
    current_proof = proof_out
    carried: list[str] = []
    trail: list[str] = []

    for name, fn in rungs:
        rung_ctx = dataclasses.replace(ctx, trail=tuple(trail))
        untracked_before = _untracked(ctx)
        try:
            attempt = fn(rung_ctx, current_proof)
        except HarnessError as exc:
            if exc.kind == "llm-usage-limit":
                trail.append(f"{name}: llm-usage-limit: {exc.detail}")
                _reset(ctx, base_sha, untracked_before)
                break
            trail.append(f"{name}: {exc.kind}: {exc.detail}")
            _reset(ctx, base_sha, untracked_before)
            continue
        except Exception as exc:
            trail.append(f"{name}: {exc}")
            _reset(ctx, base_sha, untracked_before)
            continue

        if ctx.run("rev-parse", "HEAD").strip() != base_sha:
            # The rung committed, reset or rebased on its own.
            trail.append(f"{name}: merge parents changed: rung moved HEAD")
            _reset(ctx, base_sha, untracked_before)
            continue

        if not attempt.applied:
            trail.append(f"{name}: skipped ({attempt.note})")
            _reset(ctx, base_sha, untracked_before)
            continue

        reason = amend_merge(ctx, attempt.touched)
        if reason:
            trail.append(f"{name}: {reason}")
            _reset(ctx, base_sha, untracked_before)
            continue

        verdict = post_rung_guard(ctx, attempt.touched)
        if verdict.ok:
            trail.append(f"{name}: passed")
            return LadderOutcome(
                name,
                tuple(dict.fromkeys([*ctx.conflicted_files, *carried, *attempt.touched])),
                verdict.proof_out, tuple(trail))
        if verdict.structural_ok and lost_count(verdict.proof_out) < lost_count(current_proof):
            base_sha = ctx.run("rev-parse", "HEAD").strip()
            current_proof = verdict.proof_out
            carried.extend(attempt.touched)
            trail.append(f"{name}: partial ({lost_count(current_proof)} LOST left)")
            continue
        trail.append(f"{name}: {verdict.reason or 'proof still failed'}")
        _reset(ctx, base_sha, untracked_before)

    return LadderOutcome("", (), current_proof, tuple(trail))


def ladder_failure_reason(first_proof: str, trail) -> str:
    """Same `tree proof failed: ` prefix and 400-char cut as `_prove_tree_equivalent`,
    so rebase_cause.py classifies the block unchanged."""
    return (f"tree proof failed: {first_proof.strip()[:400]}"
            f" | ladder: {'; '.join(trail)[:800]}")


# --- Rung 1: generated-file regeneration (no model call) -------------------------


@dataclass(frozen=True)
class GeneratedFile:
    path: str                                # repo-relative
    command: Optional[tuple[str, ...]]       # run from the worktree root; None = master-copy-only
    requires: tuple[str, ...] = ()           # repo-relative paths that must exist to run `command`
    why_copy_only: str = ""


_BASELINES = ("bin/regen-baselines",)
_VENDOR = ("ibl5/vendor/autoload.php",)
GENERATED_FILES: tuple[GeneratedFile, ...] = (
    GeneratedFile("ibl5/phpstan-baseline.neon", _BASELINES, _VENDOR),
    GeneratedFile("ibl5/phpstan-tests-baseline.neon", _BASELINES, _VENDOR),
    GeneratedFile("ibl5/phpstan-baseline-counts.json", _BASELINES, _VENDOR),
    GeneratedFile(".claude/rules/codebase-map.md", ("bin/generate-codebase-map",)),
    GeneratedFile("ibl5/docs/schema/current-schema.sql", None,
                  why_copy_only="needs a MariaDB with every migration applied"),
    GeneratedFile("ibl5/coverage-baseline.json", None,
                  why_copy_only="needs the CI clover artifact"),
)
REGEN_TIMEOUT = 900   # bin/regen-baselines runs two PHPStan passes, about 2-3 min


def make_regen_rung(registry=GENERATED_FILES, timeout: int = REGEN_TIMEOUT) -> Rung:
    def fn(ctx: LadderContext, proof_out: str) -> RungAttempt:
        lost, structural = parse_lost_lines(proof_out)
        involved = (set(ctx.conflicted_files) | set(lost)
                    | {line.rsplit(": ", 1)[1] for line in structural if ": " in line})
        candidates = [g for g in registry if g.path in involved]
        if not candidates:
            return RungAttempt(False, (), "no generated file involved")
        for g in candidates:
            rc, _ = ctx.run_out("cat-file", "-e", f"{ctx.master_sha}:{g.path}")
            if rc == 0:
                ctx.run("checkout", ctx.master_sha, "--", g.path)
        notes: list[str] = []
        commands: list[tuple[str, ...]] = []
        for g in candidates:
            if g.command is None:
                notes.append(f"{g.path}: master copy only ({g.why_copy_only})")
                continue
            missing = [r for r in g.requires
                       if not os.path.exists(os.path.join(ctx.worktree, r))]
            if missing:
                notes.append(f"{g.path}: master copy only (missing {missing[0]})")
                continue
            if g.command not in commands:
                commands.append(g.command)
        for command in commands:
            proc = subprocess.run(list(command), cwd=ctx.worktree, capture_output=True,
                                  text=True, errors="replace", timeout=timeout)
            if proc.returncode != 0:
                tail = (proc.stderr or proc.stdout).strip()[-300:]
                raise RuntimeError(f"regen {command[0]} exit {proc.returncode}: {tail}")
        touched = tuple(ctx.run("diff", "--name-only", "HEAD").split())
        return RungAttempt(True, touched, "; ".join(notes))
    return (RUNG_REGEN, fn)


# --- Rung 2: one Opus resolver retry carrying the exact LOST lines ---------------

OPUS_MAX_FILES = 5
OPUS_MAX_LOST_LINES_PER_FILE = 400


def _stage_dir(key: str) -> str:
    return f"/tmp/postplan-conflict-stages-{key}"


def _read_citations(key: str) -> str:
    path = Path(_stage_dir(key)) / "citations.txt"
    if path.exists():
        return path.read_text(errors="replace").strip()
    return ""


def _opus_context(ctx: LadderContext, path: str, lines) -> str:
    lost_block = "\n".join(f"LOST: {path}: {line}" for line in lines)
    return (
        "ESCALATION. An earlier resolution of this file failed the lost-work proof.\n"
        f"The file currently at {ctx.worktree}/{path} is that failed resolution. "
        "Read it as well as the three stages.\n"
        "The proof reported these lines from the branch's own change as LOST:\n"
        f"{lost_block}\n"
        "Requirements:\n"
        '- Every LOST line starting with "+" must appear as a whole, unchanged line in your result.\n'
        '- Every LOST line starting with "-" was deleted by the branch. Remove it unless '
        "master's side (theirs) also has it.\n"
        "- Keep master's changes too. Never wholesale-take one side.\n"
        "If you conclude a line must NOT be restored because master already carries it "
        f"elsewhere, write one line per such case to {_stage_dir(ctx.key)}/citations.txt as "
        "`CITED: <LOST line> -> <master path>:<line number>`, and end your reply with FAILED.\n"
        "The harness re-runs the proof after you finish. It is the only judge."
    )


def make_opus_rung() -> Rung:
    def fn(ctx: LadderContext, proof_out: str) -> RungAttempt:
        from .conflict import resolve_one

        # Cleared before any early return, so a stale file never feeds rung 3's prompt.
        citations = Path(_stage_dir(ctx.key)) / "citations.txt"
        if citations.exists():
            citations.unlink()
        lost, _structural = parse_lost_lines(proof_out)
        if not lost:
            return RungAttempt(False, (), "no line-level LOST")
        merge_base = ctx.run("merge-base", ctx.pre_rebase_sha, ctx.master_sha).strip()
        revs = {1: merge_base, 2: ctx.pre_rebase_sha, 3: ctx.master_sha}
        eligible: list[str] = []
        notes: list[str] = []
        for path, lines in lost.items():
            has_all = all(ctx.run("show", f"{rev}:{path}", check=False)
                          for rev in revs.values())
            if has_all and len(lines) <= OPUS_MAX_LOST_LINES_PER_FILE:
                eligible.append(path)
            else:
                notes.append(f"{path}: left for session")
        if not eligible:
            return RungAttempt(False, (), "; ".join(notes) or "no eligible file")
        if len(eligible) > OPUS_MAX_FILES:
            return RungAttempt(False, (), f"{len(eligible)} files exceed the cap "
                                          f"OPUS_MAX_FILES={OPUS_MAX_FILES}")
        for path in eligible:
            ok, reason = resolve_one(
                ctx.llm, ctx.run, worktree=ctx.worktree, key=ctx.key, path=path,
                stages=frozenset({1, 2, 3}), branch_stage=2,
                master_sha=ctx.master_sha, pre_sha=ctx.pre_rebase_sha,
                model="opus", max_rounds=1, stage_revs=revs,
                extra_context=_opus_context(ctx, path, lost[path]),
            )
            if not ok:
                text = _read_citations(ctx.key)
                raise RuntimeError(f"opus-retry {path}: {reason}"
                                   + (f"; citations: {text[:600]}" if text else ""))
        return RungAttempt(True, tuple(eligible), "; ".join(notes))
    return (RUNG_OPUS, fn)


# --- Rung 3: one capped headless claude session ---------------------------------

SESSION_MODEL = "opus"
SESSION_BUDGET_USD = 5.0
SESSION_TIMEOUT = 1200
SESSION_MAX_TURNS = 80
SESSION_PROOF_CAP = 60_000
SESSION_ALLOWED = ("Read", "Write", "Edit", "Glob", "Grep", "Bash")
SESSION_DENIED = (
    "Agent", "WebFetch", "WebSearch",
    "Bash(git push:*)", "Bash(git commit:*)", "Bash(git reset:*)",
    "Bash(git rebase:*)", "Bash(git merge:*)", "Bash(git cherry-pick:*)",
    "Bash(git fetch:*)", "Bash(git pull:*)", "Bash(git branch:*)",
    "Bash(git switch:*)", "Bash(git tag:*)", "Bash(git remote:*)",
    "Bash(gh:*)", "Bash(curl:*)", "Bash(wget:*)", "Bash(ssh:*)", "Bash(scp:*)",
    "Bash(bin/post-plan-now:*)", "Bash(bin/discord-dm:*)",
)


def _session_prompt(ctx: LadderContext, proof_out: str, citations: str) -> str:
    proof = proof_out
    if len(proof.encode()) > SESSION_PROOF_CAP:
        proof = (proof.encode()[:SESSION_PROOF_CAP].decode(errors="ignore")
                 + "\n[proof output truncated]")
    citations_block = (f"Citations from the Opus retry:\n{citations}\n" if citations else "")
    return (
        "You are finishing a git merge of origin/master into this branch, in the worktree "
        "that is your cwd.\n"
        f"HEAD is already the merge commit (parents: branch {ctx.pre_rebase_sha}, master "
        f"{ctx.master_sha}). Earlier automated\n"
        "attempts left the tree failing the lost-work proof. Conflicted files: "
        f"{', '.join(ctx.conflicted_files)}.\n"
        "Proof output (every LOST line is branch work missing from HEAD):\n"
        f"{proof}\n"
        f"Earlier attempts: {'; '.join(ctx.trail) or 'none'}\n"
        f"{citations_block}"
        "Edit files in place so every LOST line is satisfied while keeping master's changes. "
        "Rerun generators where a\n"
        "file is generated (bin/regen-baselines, bin/generate-codebase-map). You may check "
        "yourself with\n"
        f"`bash /tmp/postplan-lostwork-{ctx.key}.sh {ctx.key}`; the harness re-runs it after "
        "you exit and only that run counts.\n"
        "Do NOT commit, reset, rebase, merge, push, fetch, switch branches, run gh, or use "
        "the network.\n"
        "End your reply with a line that is exactly RESOLVED or exactly FAILED."
    )


def make_session_rung(branch: str) -> Rung:
    def fn(ctx: LadderContext, proof_out: str) -> RungAttempt:
        remote_ref = f"refs/remotes/origin/{branch}"
        remote_before = ctx.run("rev-parse", "--verify", "-q", remote_ref, check=False).strip()
        untracked_before = _untracked(ctx)
        citations = _read_citations(ctx.key)
        Path(_stage_dir(ctx.key)).mkdir(parents=True, exist_ok=True)
        reply = ctx.llm.call_tooled(
            "conflict-ladder-session", SESSION_MODEL,
            _session_prompt(ctx, proof_out, citations), cwd=ctx.worktree,
            allowed_tools=SESSION_ALLOWED, denied_tools=SESSION_DENIED,
            add_dirs=(_stage_dir(ctx.key),),
            timeout=SESSION_TIMEOUT, max_turns=SESSION_MAX_TURNS,
            max_budget_usd=SESSION_BUDGET_USD, max_retries=0,
        )
        remote_after = ctx.run("rev-parse", "--verify", "-q", remote_ref, check=False).strip()
        if remote_after != remote_before:
            raise RuntimeError(f"session moved {remote_ref}")
        last_line = next(
            (l.strip() for l in reversed((reply or "").splitlines()) if l.strip()), "")
        if last_line != "RESOLVED":
            raise RuntimeError(f"session did not end with RESOLVED: {(reply or '')[-300:]}")
        touched = sorted(set(ctx.run("diff", "--name-only", "HEAD").split())
                         | (_untracked(ctx) - untracked_before))
        if not touched:
            return RungAttempt(False, (), "session made no change")
        return RungAttempt(True, tuple(touched), "")
    return (RUNG_SESSION, fn)


def default_rungs(branch: str) -> tuple[Rung, ...]:
    return (make_regen_rung(), make_opus_rung(), make_session_rung(branch))
