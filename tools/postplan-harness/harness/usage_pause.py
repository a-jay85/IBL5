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
import sys
import tempfile
import threading
import time
from dataclasses import dataclass

_SID_RE = re.compile(
    r"^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$", re.IGNORECASE)
_PAUSE_JSON = {"continue": False, "stopReason": "usage-pause"}

# Closed allowlist: a tooled call limited to these cannot write the worktree. Any tool
# not listed (Bash, Edit, an MCP tool, a name added later) counts as write-capable.
READ_ONLY_TOOLS = frozenset({"Read", "Grep", "Glob"})


def _tool_names(tools) -> set:
    if tools is None:
        return set()
    items = tools.split(",") if isinstance(tools, str) else list(tools)
    names = set()
    for t in items:
        name = str(t).split("(", 1)[0].strip()
        if name:
            names.add(name)
    return names


def tools_can_write(allowed_tools, denied_tools=None) -> bool:
    """False only when the effective tool set is a non-empty subset of READ_ONLY_TOOLS."""
    effective = _tool_names(allowed_tools) - _tool_names(denied_tools)
    return not (effective and effective <= READ_ONLY_TOOLS)


class UsagePause(BaseException):
    """The usage gate paused this run. Raised instead of returning a model result.

    Derives from BaseException on purpose: every existing `except Exception` and
    `except HarnessError` swallow site in the harness, and any one added later, lets a
    pause through to `runner.run()`, which maps it to exit 75.

    Cost: cleanups that live on an `except` path (e.g. `adr_draft._discard`) do not
    run on a pause. A write-capable tooled call captures the worktree before the
    spawn, so a pause that interrupts an edit is reported `dirty` and carries that
    capture. The runner records it and the resumed run restores it (ADR-0143
    addendum 2026-10-10).
    """

    def __init__(self, purpose: str, dirty: bool = False,
                 prespawn: "PreSpawn | None" = None, cwd: str | None = None):
        super().__init__(purpose)
        self.purpose = purpose
        self.dirty = dirty
        self.prespawn = prespawn
        self.cwd = cwd


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
        ops = _op_heads(cwd)
    except (OSError, subprocess.CalledProcessError):
        return None
    return hashlib.sha256("\0".join([head, tree, pairs, ",".join(ops)]).encode()).hexdigest()


def _op_heads(cwd: str) -> tuple[str, ...]:
    """In-progress operation heads (rebase, merge, cherry-pick) present in cwd."""
    ops = []
    for ref in ("REBASE_HEAD", "MERGE_HEAD", "CHERRY_PICK_HEAD"):
        r = subprocess.run(["git", "rev-parse", "-q", "--verify", ref], cwd=cwd,
                           capture_output=True, text=True)
        if r.returncode == 0:
            ops.append(ref)
    return tuple(ops)


@dataclass(frozen=True)
class PreSpawn:
    head: str
    tree: str
    ops: tuple[str, ...]


def capture_prespawn(cwd: str) -> PreSpawn | None:
    """HEAD, the full worktree tree, and op heads before a spawn. None on any failure.

    The tree comes from `gatefix._tree_of` (tracked plus untracked non-ignored), so it
    is both a comparison key and a `git restore --source` for the resumed run.
    """
    from . import gatefix   # local: gatefix imports the llm adapter, which imports us
    try:
        head = _git(cwd, "rev-parse", "HEAD").strip()
        tree = gatefix._tree_of(cwd, run=subprocess.run)
        return PreSpawn(head=head, tree=tree, ops=_op_heads(cwd))
    except Exception:  # noqa: BLE001 -- a failed capture reads as an edit (fail closed)
        return None


def edit_since(cwd: str, pre: PreSpawn | None) -> bool:
    """True (dirty) unless HEAD, tree, and op heads all still match the capture."""
    if pre is None:
        return True
    now = capture_prespawn(cwd)
    return now is None or now != pre


# ---------------------------------------------------------------- resume dedupe
# A resumed run re-enters run() under the same S with a fresh out_dir. Every other
# side effect on that path is idempotent at GitHub or git; these two create a new
# PR comment per call, so a resume would double-post them.
DEDUPED_METHODS = ("post_review_findings", "post_review_summary")
_TITLE_ARG = {"post_review_findings": 2, "post_review_summary": 1}


def _ledger_path(ctx: GateContext) -> str | None:
    sd = state_dir(ctx)
    return None if sd is None else os.path.join(sd, "runs", f"{ctx.session_id}.effects.json")


class EffectLedger:
    """runs/<S>.effects.json: the deduped posts made by any launch under S."""

    def __init__(self, path: str, run_id: str):
        self.path = path
        self.run_id = run_id
        self._lock = threading.Lock()

    def _load(self) -> list:
        try:
            with open(self.path) as fh:
                data = json.load(fh)
            return data if isinstance(data, list) else []
        except (OSError, ValueError):
            return []

    def earlier(self, key: str) -> tuple[bool, object]:
        """(True, result) only for an entry under this key from a DIFFERENT run."""
        with self._lock:
            for e in self._load():
                if e.get("key") == key and e.get("run_id") != self.run_id:
                    return True, e.get("result")
        return False, None

    def record(self, key: str, result) -> None:
        try:
            json.dumps(result)
        except (TypeError, ValueError):
            result = None
        with self._lock:
            entries = self._load()
            entries.append({"key": key, "run_id": self.run_id, "result": result,
                            "ts": time.time()})
            try:
                os.makedirs(os.path.dirname(self.path), exist_ok=True)
                fd, tmp = tempfile.mkstemp(dir=os.path.dirname(self.path), suffix=".tmp")
                with os.fdopen(fd, "w") as fh:
                    json.dump(entries, fh)
                os.replace(tmp, self.path)
            except OSError:
                pass


class _DedupingGh:
    """gh proxy: the two comment posts skip a key an earlier launch under S already
    posted at this HEAD; every other attribute passes straight through."""

    def __init__(self, inner, ledger: EffectLedger, worktree: str):
        self._inner = inner
        self._ledger = ledger
        self._worktree = worktree

    def __getattr__(self, name):
        target = getattr(self._inner, name)
        if name not in DEDUPED_METHODS:
            return target

        def wrapper(*args, **kwargs):
            try:
                head = _git(self._worktree, "rev-parse", "HEAD").strip()
            except (OSError, subprocess.CalledProcessError):
                return target(*args, **kwargs)   # fail toward a duplicate, never a gap
            i = _TITLE_ARG[name]
            title = args[i] if len(args) > i and isinstance(args[i], str) else kwargs.get("title", "")
            key = hashlib.sha256("\0".join(
                [name, str(args[0]) if args else str(kwargs.get("pr", "")), head, str(title)]
            ).encode()).hexdigest()
            seen, stored = self._ledger.earlier(key)
            if seen:
                print(f"resume: skipped duplicate {name}", file=sys.stderr)
                return stored
            result = target(*args, **kwargs)
            self._ledger.record(key, result)
            return result

        return wrapper


def dedupe_on_resume(gh, worktree: str, out_dir: str):
    ctx, _ = context_from_env()
    if ctx is None:
        return gh
    path = _ledger_path(ctx)
    if path is None:
        return gh
    return _DedupingGh(gh, EffectLedger(path, os.path.abspath(out_dir)), worktree)


def ledger_clear() -> None:
    ctx, _ = context_from_env()
    if ctx is None:
        return
    path = _ledger_path(ctx)
    if path is not None:
        try:
            os.remove(path)
        except OSError:
            pass
    pause_record_clear(ctx)


# ---------------------------------------------------------------- dirty pause record
# A pause that interrupted a write-capable call leaves a partial edit. runs/<S>.pause.json
# proves which edit it was, so the resumed run under S can discard exactly that edit
# and continue. Every refusal leaves the worktree untouched (ADR-0143 addendum
# 2026-10-10).
PAUSE_RECORD_VERSION = 1


def _pause_record_path_for(ctx: GateContext, sid: str) -> str | None:
    sd = state_dir(ctx)
    return None if sd is None else os.path.join(sd, "runs", f"{sid}.pause.json")


def _pause_record_path(ctx: GateContext) -> str | None:
    return _pause_record_path_for(ctx, ctx.session_id)


def _changed_between(cwd: str, pre_tree: str, post_tree: str) -> list:
    """[[status, path], ...] between two trees, status in A/M/D/T."""
    raw = _git(cwd, "diff-tree", "-r", "-z", "--no-renames", "--name-status",
               pre_tree, post_tree)
    toks = raw.split("\0")
    pairs = []
    i = 0
    while i + 1 < len(toks):
        status, path = toks[i], toks[i + 1]
        if not status:
            i += 1
            continue
        pairs.append([status[0], path])
        i += 2
    return pairs


def _unsafe(path: str) -> bool:
    return os.path.isabs(path) or ".." in path.split("/")


def pause_record_write(ctx: GateContext, purpose: str, pre: PreSpawn,
                       cwd: str) -> str | None:
    """Record the interrupted edit. None on success, else the refusal reason."""
    post = capture_prespawn(cwd)
    if post is None:
        return "capture-failed"
    if post.head != pre.head:
        return "head-moved"
    if pre.ops or post.ops:
        return "op-in-progress"
    if post.tree == pre.tree:
        return "no-edit"
    try:
        changed = _changed_between(cwd, pre.tree, post.tree)
    except (OSError, subprocess.CalledProcessError):
        return "capture-failed"
    if any(_unsafe(p) for _, p in changed):
        return "unsafe-path"
    path = _pause_record_path(ctx)
    if path is None:
        return "write-failed"
    rec = {"version": PAUSE_RECORD_VERSION, "session_id": ctx.session_id,
           "purpose": purpose, "head": pre.head, "pre_tree": pre.tree,
           "post_tree": post.tree, "changed": changed,
           "written_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())}
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        tmp = path + ".tmp"
        with open(tmp, "w") as fh:
            json.dump(rec, fh)
        os.replace(tmp, path)
    except OSError:
        return "write-failed"
    return None


def _load_record_at(path: str | None) -> tuple[str, dict | None]:
    if path is None or not os.path.exists(path):
        return "missing", None
    try:
        with open(path) as fh:
            rec = json.load(fh)
    except (OSError, ValueError):
        return "malformed", None
    if not isinstance(rec, dict) or rec.get("version") != PAUSE_RECORD_VERSION:
        return "malformed", None
    for key in ("session_id", "head", "pre_tree", "post_tree"):
        if not isinstance(rec.get(key), str) or not rec[key]:
            return "malformed", None
    changed = rec.get("changed")
    if not isinstance(changed, list) or not all(
            isinstance(c, list) and len(c) == 2 and all(isinstance(x, str) for x in c)
            for c in changed):
        return "malformed", None
    return "ok", rec


def pause_record_load(ctx: GateContext) -> tuple[str, dict | None]:
    """("missing", None), ("malformed", None) or ("ok", rec) for this session's record."""
    return _load_record_at(_pause_record_path(ctx))


def pause_record_exists_for(ctx: GateContext, sid: str) -> bool:
    path = _pause_record_path_for(ctx, sid)
    return path is not None and os.path.exists(path)


def pause_record_clear(ctx: GateContext) -> None:
    path = _pause_record_path(ctx)
    if path is not None:
        try:
            os.remove(path)
        except OSError:
            pass


def restore_from_record(cwd: str, rec: dict, session_id: str) -> str | None:
    """Discard exactly the recorded edit. None after a verified discard, else a reason.

    Every check runs before any write, so a refusal leaves the worktree untouched.
    """
    if rec.get("session_id") != session_id:
        return "session-mismatch"
    cur = capture_prespawn(cwd)
    if cur is None:
        return "capture-failed"
    if cur.head != rec["head"]:
        return "head-moved"
    if cur.ops:
        return "op-in-progress"
    if cur.tree != rec["post_tree"]:
        return "tree-mismatch"
    # Same shape as gatefix.revert's tree half: restore M/D/T from the pre-spawn tree
    # (keeps staged pre-spawn work; never `reset --hard`), unlink what the edit added.
    try:
        pairs = _changed_between(cwd, rec["pre_tree"], rec["post_tree"])
        if any(_unsafe(p) for _, p in pairs):
            return "restore-failed"
        restore = [p for s, p in pairs if s in ("M", "D", "T")]
        remove = [p for s, p in pairs if s == "A"]
        if restore:
            _git(cwd, "restore", f"--source={rec['pre_tree']}", "--worktree", "--", *restore)
        for p in remove:
            target = os.path.join(cwd, p)
            if os.path.islink(target) or os.path.exists(target):
                os.unlink(target)
    except Exception:  # noqa: BLE001 -- any failure fails closed
        return "restore-failed"
    after = capture_prespawn(cwd)
    if after is None or after.tree != rec["pre_tree"]:
        return "restore-unverified"
    return None
