"""Local-gate auto-fixer: one headless fixer, one retry, never editing gate paths.

A pre-commit / pre-push hook denial used to end the run (exit 3 + DM). This module lets
the runner spend ONE headless Opus attempt on the dirty tree and retry the denied git
call once. The prompt is advisory. The safety layer is mechanical: a content-hash
snapshot before the spawn, a guard over what changed, and a revert in a `finally`.

This half holds the snapshot, guard, and revert. The spawn half sits below it.
"""
from __future__ import annotations

import os
import subprocess
import tempfile
from dataclasses import dataclass, field
from pathlib import Path

from . import fidelity, rules_budget_carveout
from .state import HarnessError

_IGNORED_ROOTS = (".claude", ".githooks", "bin", "tools/postplan-harness")


@dataclass(frozen=True)
class Snapshot:
    tree: str
    ignored_gate: dict = field(default_factory=dict)       # path -> bytes (ignored gate files)
    outside: dict = field(default_factory=dict)            # abs path -> bytes | None
    carveout: rules_budget_carveout.Carveout = rules_budget_carveout.INACTIVE


@dataclass(frozen=True)
class GuardVerdict:
    ok: bool
    changed: list
    denied: list


def _git(worktree, *args, env=None, run=subprocess.run, text=True) -> str:
    res = run(["git", "-C", str(worktree), *args], capture_output=True, text=text,
              env=env)
    if res.returncode != 0:
        err = res.stderr if text else res.stderr.decode("utf-8", "replace")
        raise HarnessError("gatefix-git", f"git {' '.join(args)} failed: {err.strip()}")
    return res.stdout


def _read(path: Path):
    """Bytes of a regular file, `symlink:<target>` for a link, None when absent."""
    try:
        if path.is_symlink():
            return b"symlink:" + os.readlink(path).encode()
        if path.is_file():
            return path.read_bytes()
    except OSError:
        return None
    return None


def _walk_files(root: Path):
    if not root.is_dir():
        return
    for dirpath, _dirs, files in os.walk(root):
        for name in files:
            yield Path(dirpath) / name


def _tree_of(worktree, *, run) -> str:
    """Git tree SHA of the working tree (tracked plus untracked non-ignored).

    Uses a temp copy of the real index, so the real index and the tree are untouched.
    """
    index = _git(worktree, "rev-parse", "--git-path", "index", run=run).strip()
    index_path = Path(index)
    if not index_path.is_absolute():
        index_path = Path(worktree) / index_path
    fd, tmp = tempfile.mkstemp(prefix="gatefix-index-")
    os.close(fd)
    try:
        if index_path.is_file():
            Path(tmp).write_bytes(index_path.read_bytes())
        else:
            os.unlink(tmp)
        env = {**os.environ, "GIT_INDEX_FILE": tmp}
        _git(worktree, "add", "-A", env=env, run=run)
        return _git(worktree, "write-tree", env=env, run=run).strip()
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)


def _outside_files(worktree, home: Path, *, run) -> dict:
    out: dict = {}
    claude = home / ".claude"
    paths: set[Path] = set(_walk_files(claude / "hooks"))
    if claude.is_dir():
        paths.update(p for p in claude.glob("settings*.json"))
    common = _git(worktree, "rev-parse", "--git-common-dir", run=run).strip()
    common_dir = Path(common)
    if not common_dir.is_absolute():
        common_dir = Path(worktree) / common_dir
    git_dir = _git(worktree, "rev-parse", "--absolute-git-dir", run=run).strip()
    paths.update(_walk_files(common_dir / "hooks"))
    paths.add(common_dir / "config")
    paths.add(Path(git_dir) / "config.worktree")
    for p in paths:
        out[str(p)] = _read(p)
    return out


def _ignored_gate_files(worktree, *, run) -> dict:
    out = _git(worktree, "ls-files", "-z", "--others", "--ignored", "--exclude-standard",
               "--", *_IGNORED_ROOTS, run=run)
    files: dict = {}
    for p in filter(None, out.split("\0")):
        if "__pycache__/" in p or p.endswith(".pyc"):
            continue
        if not fidelity.denied_local_gate_edits([p]):
            continue
        files[p] = _read(Path(worktree) / p)
    return files


def _carveout_of(worktree, tree: str, *, run) -> rules_budget_carveout.Carveout:
    try:
        rc = run([rules_budget_carveout.BUDGET_SCRIPT], cwd=worktree,
                 capture_output=True, text=True).returncode
        if rc == 0:
            return rules_budget_carveout.Carveout(False, "local byte budget passes",
                                                  frozenset())
        names = _git(worktree, "diff-tree", "-r", "--no-renames", "--name-only",
                     "origin/master", tree, run=run).splitlines()
        in_diff = frozenset(n for n in names if rules_budget_carveout._is_rules_md(n))
        if not in_diff:
            return rules_budget_carveout.Carveout(False, "no rules file in PR diff",
                                                  frozenset())
        return rules_budget_carveout.Carveout(True, "active", in_diff)
    except Exception as exc:  # noqa: BLE001 - any failure leaves the carve-out off
        return rules_budget_carveout.Carveout(False, f"carve-out probe failed: {exc}",
                                              frozenset())


def snapshot(worktree, *, home=None, run=subprocess.run) -> Snapshot:
    """Content-hash snapshot of everything the fixer could touch. Raises HarnessError."""
    home_dir = Path(home) if home is not None else Path.home()
    try:
        tree = _tree_of(worktree, run=run)
        ignored = _ignored_gate_files(worktree, run=run)
        outside = _outside_files(worktree, home_dir, run=run)
    except HarnessError:
        raise
    except Exception as exc:  # noqa: BLE001 - any snapshot failure means no fixer
        raise HarnessError("gatefix-snapshot", f"snapshot failed: {exc}") from exc
    return Snapshot(tree, ignored, outside, _carveout_of(worktree, tree, run=run))


def changed_paths(worktree, before: Snapshot, after: Snapshot, *,
                  run=subprocess.run) -> list:
    """`(status, path)` pairs, status in A/M/D/T, between two snapshots."""
    pairs: list = []
    if before.tree != after.tree:
        raw = _git(worktree, "diff-tree", "-r", "-z", "--no-renames", before.tree,
                   after.tree, run=run)
        toks = raw.split("\0")
        i = 0
        while i + 1 < len(toks):
            meta, path = toks[i], toks[i + 1]
            if not meta.startswith(":"):
                i += 1
                continue
            pairs.append((meta.split()[-1][0], path))
            i += 2
    for p in sorted(set(before.ignored_gate) | set(after.ignored_gate)):
        b, a = before.ignored_gate.get(p), after.ignored_gate.get(p)
        if b == a:
            continue
        pairs.append(("A" if b is None else "D" if a is None else "M", p))
    return pairs


def _blob_size(worktree, tree: str, path: str, *, run) -> int:
    return int(_git(worktree, "cat-file", "-s", f"{tree}:{path}", run=run).strip())


def _carveout_denials(worktree, pairs, before, after, *, run) -> list:
    """Working-tree analog of `rules_budget_carveout.file_verdicts`."""
    denied: list = []
    added = 0
    for st, p in pairs:
        if st == "D":
            denied.append((p, "deleting a rules file is never allowed"))
        elif st == "M":
            if p not in before.carveout.in_diff:
                denied.append((p, "not in the PR diff vs origin/master"))
                continue
            old = _blob_size(worktree, before.tree, p, run=run)
            new = _blob_size(worktree, after.tree, p, run=run)
            if new >= old:
                denied.append((p, f"did not shrink ({old} -> {new} bytes)"))
        elif st == "A":
            added += 1
            if not p.endswith(rules_budget_carveout.DETAIL_SUFFIX):
                denied.append((p, "a new rules file must be a *-detail.md companion"))
            elif rules_budget_carveout.frontmatter_paths(
                    _git(worktree, "show", f"{after.tree}:{p}", run=run)) == []:
                denied.append((p, "new companion has no paths: list"))
        else:
            denied.append((p, f"unsupported change type {st}"))
    if added > 1:
        denied.append(("<new files>", "only one new *-detail.md companion"))
    return denied


def guard(worktree, before: Snapshot, after: Snapshot, *,
          run=subprocess.run) -> GuardVerdict:
    """Verdict on what the fixer changed. Any exception fails closed."""
    try:
        denied: list = []
        for key in sorted(set(before.outside) | set(after.outside)):
            if before.outside.get(key) != after.outside.get(key):
                denied.append((key, "out-of-repo gate path"))
        pairs = changed_paths(worktree, before, after, run=run)
        changed = sorted({p for _s, p in pairs})
        gate_hits = set(fidelity.denied_local_gate_edits(changed))
        if gate_hits:
            rules_only = all(rules_budget_carveout._is_rules_md(p) for p in gate_hits)
            if rules_only and before.carveout.active and not denied:
                hit_pairs = [(s, p) for s, p in pairs if p in gate_hits]
                denied.extend(_carveout_denials(worktree, hit_pairs, before, after,
                                                run=run))
                if not denied:
                    for entry in rules_budget_carveout.post_check_failures(
                            worktree, run=run):
                        denied.append(("<post-check>", f"post-check: {entry}"))
            else:
                denied.extend((p, "gate path") for p in sorted(gate_hits))
        return GuardVerdict(not denied, changed, denied)
    except Exception as exc:  # noqa: BLE001 - a broken guard must reject
        return GuardVerdict(False, [], [("<guard>", f"guard error: {exc}")])


def revert(worktree, before: Snapshot, after: Snapshot, *,
           run=subprocess.run) -> list:
    """Undo every change between the snapshots. Returns error strings; empty is clean."""
    errors: list = []
    try:
        pairs = changed_paths(worktree, before, after, run=run)
    except Exception as exc:  # noqa: BLE001
        return [f"changed_paths failed: {exc}"]
    ignored = set(before.ignored_gate) | set(after.ignored_gate)
    restore = [p for s, p in pairs if s in ("M", "D", "T") and p not in ignored]
    remove = [p for s, p in pairs if s == "A" and p not in ignored]
    if restore:
        try:
            _git(worktree, "restore", f"--source={before.tree}", "--worktree", "--",
                 *restore, run=run)
        except Exception as exc:  # noqa: BLE001
            errors.append(f"restore failed: {exc}")
    for p in remove:
        try:
            target = Path(worktree) / p
            if target.is_symlink() or target.exists():
                target.unlink()
        except OSError as exc:
            errors.append(f"unlink {p} failed: {exc}")
    for p in sorted(ignored):
        want = before.ignored_gate.get(p)
        if want == after.ignored_gate.get(p):
            continue
        _write_back(Path(worktree) / p, want, errors)
    for key in sorted(set(before.outside) | set(after.outside)):
        want = before.outside.get(key)
        if want == after.outside.get(key):
            continue
        _write_back(Path(key), want, errors)
    return errors


def _write_back(path: Path, want, errors: list) -> None:
    try:
        if want is None:
            if path.is_symlink() or path.exists():
                path.unlink()
        elif want.startswith(b"symlink:"):
            if path.is_symlink() or path.exists():
                path.unlink()
            os.symlink(want[len(b"symlink:"):].decode(), path)
        else:
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(want)
    except OSError as exc:
        errors.append(f"write-back {path} failed: {exc}")
