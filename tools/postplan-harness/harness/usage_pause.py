"""Usage-gate bridge for the post-plan harness (ADR-0143 addendum).

`bin/post-plan-now` hands the harness three context vars. This module is the single
Python-side owner of the gate: it validates that context, and reaches the bash library
`bin/lib/usage-gate.sh` by sourcing it in a subprocess, exactly as the machine-local
hook does. No marker path or JSON shape is re-implemented here.

Every failure to establish a context or to reach the library fails OPEN (no pause).
"""
from __future__ import annotations

import hashlib
import json
import os
import re
import subprocess
from dataclasses import dataclass

_SID_RE = re.compile(
    r"^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$", re.IGNORECASE)
_PAUSE_JSON = {"continue": False, "stopReason": "usage-pause"}


class UsagePause(BaseException):
    """The usage gate paused this run. Raised instead of returning a model result.

    Derives from BaseException on purpose: every existing `except Exception` and
    `except HarnessError` swallow site in the harness, and any one added later, lets a
    pause through to `runner.run()`, which maps it to exit 75.

    Cost: cleanups that live on an `except` path (e.g. `adr_draft._discard`) do not
    run on a pause. A tooled call compares a worktree fingerprint taken before the
    spawn, so a pause that interrupts an edit is reported `dirty` and fails closed.
    """

    def __init__(self, purpose: str, dirty: bool = False):
        super().__init__(purpose)
        self.purpose = purpose
        self.dirty = dirty


@dataclass(frozen=True)
class GateContext:
    runner: str
    resume_bin: str
    session_id: str
    lib: str


def context_from_env(environ=None) -> tuple[GateContext | None, str]:
    """Return (ctx, "ok") for a complete, valid gate context, else (None, reason)."""
    env = os.environ if environ is None else environ
    if "IBL5_USAGE_GATE_SESSION_ID" not in env:
        return None, "no-session-id"
    sid = env["IBL5_USAGE_GATE_SESSION_ID"]
    if sid == "":
        return None, "empty-session-id"
    if not _SID_RE.match(sid):
        return None, "bad-session-id"
    runner = env.get("IBL5_USAGE_GATE_RUNNER", "")
    if runner != "post-plan-now":
        return None, "bad-runner"
    rbin = env.get("IBL5_USAGE_GATE_RESUME_BIN", "")
    if not (os.path.isabs(rbin) and os.path.isfile(rbin) and os.access(rbin, os.X_OK)):
        return None, "bad-resume-bin"
    lib = os.path.join(os.path.dirname(rbin), "lib", "usage-gate.sh")
    if not (os.path.isfile(lib) and os.access(lib, os.R_OK)):
        return None, "no-lib"
    return GateContext(runner=runner, resume_bin=rbin, session_id=sid, lib=lib), "ok"


def _lib_call(ctx: GateContext, fn: str, *args, stdin=None, timeout=90):
    """Run one usage-gate.sh function. None on OSError or timeout (callers fail open)."""
    try:
        return subprocess.run(
            ["bash", "-c", '. "$1" >/dev/null 2>&1 || exit 97; shift; "$@"',
             "_", ctx.lib, fn, *args],
            input=stdin, capture_output=True, text=True, timeout=timeout,
            env={**os.environ, "IBL5_USAGE_GATE_RUNNER": ctx.runner,
                 "IBL5_USAGE_GATE_RESUME_BIN": ctx.resume_bin,
                 "IBL5_USAGE_GATE_SESSION_ID": ctx.session_id})
    except (OSError, subprocess.TimeoutExpired):
        return None


def prespawn_decide(ctx: GateContext, cwd: str, *, timeout=90) -> bool:
    """True only when usage_gate_decide printed the pause JSON (marker already on disk)."""
    proc = _lib_call(ctx, "usage_gate_decide",
                     stdin=json.dumps({"session_id": ctx.session_id, "cwd": cwd}),
                     timeout=timeout)
    if proc is None or proc.returncode != 0 or not proc.stdout.strip():
        return False
    try:
        return json.loads(proc.stdout) == _PAUSE_JSON
    except ValueError:
        return False


def _ok(proc) -> bool:
    return proc is not None and proc.returncode == 0


def marker_exists(ctx: GateContext) -> bool:
    return _ok(_lib_call(ctx, "usage_marker_exists", ctx.session_id))


def marker_clear(ctx: GateContext) -> bool:
    return _ok(_lib_call(ctx, "usage_marker_clear", ctx.session_id))


def limit_hit_marker(ctx: GateContext) -> bool:
    return _ok(_lib_call(ctx, "usage_limit_hit_marker", "post-plan-now",
                         ctx.session_id, ctx.resume_bin))


def state_dir(ctx: GateContext) -> str | None:
    proc = _lib_call(ctx, "usage_state_dir")
    if not _ok(proc) or not proc.stdout.strip():
        return None
    return proc.stdout.strip()


def _git(cwd: str, *args, stdin=None) -> str:
    return subprocess.run(["git", *args], cwd=cwd, input=stdin, capture_output=True,
                          text=True, check=True).stdout


def worktree_fingerprint(cwd: str) -> str | None:
    """Hash of HEAD, tracked content, untracked content and in-progress op heads.

    None on any git failure; callers treat None as "changed" (fail closed).
    """
    try:
        head = _git(cwd, "rev-parse", "HEAD").strip()
        stash = _git(cwd, "stash", "create").strip()   # writes objects, moves no ref
        tree = _git(cwd, "rev-parse", f"{stash}^{{tree}}" if stash else "HEAD^{tree}").strip()
        untracked = sorted(p for p in _git(cwd, "ls-files", "-o", "--exclude-standard",
                                           "-z").split("\0") if p)
        blobs = (_git(cwd, "hash-object", "--stdin-paths", stdin="\n".join(untracked) + "\n")
                 .split() if untracked else [])
        pairs = ",".join(f"{p}:{b}" for p, b in zip(untracked, blobs))
        ops = []
        for ref in ("REBASE_HEAD", "MERGE_HEAD", "CHERRY_PICK_HEAD"):
            r = subprocess.run(["git", "rev-parse", "-q", "--verify", ref], cwd=cwd,
                               capture_output=True, text=True)
            if r.returncode == 0:
                ops.append(ref)
    except (OSError, subprocess.CalledProcessError):
        return None
    return hashlib.sha256("\0".join([head, tree, pairs, ",".join(ops)]).encode()).hexdigest()
