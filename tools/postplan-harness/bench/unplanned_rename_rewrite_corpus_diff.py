"""Diff unplanned-scope verdicts: the pre-backlog#1789 subject rule vs the live rule.

Usage:
  python3 unplanned_rename_rewrite_corpus_diff.py --harness-root <dir containing harness/>

"old" restates the pre-change lookup, a bare `git log --follow` on the cited path. "new" is
the real unplanned_scope.commit_subjects_for, which also folds in the history of paths that a
commit adding the cited path deleted. The corpus is built here, one throwaway repo per cell,
so a rename with a full rewrite reads as add plus delete.
EXPECTED is the set of cells the change must move, each from remove to keep.
Prints a CELL line per cell, then CHANGED, UNEXPECTED and MISSING lines plus a SUMMARY line.
Exits 1 when unexpected>0 or missing>0, and 2 when git is absent. Stdlib only.
"""
import argparse
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

FEAT = "feat: confine quick offer presets to three in a row"
EXPECTED = {
    "rename-rewrite": ("remove", "keep"),
    "two-hop-chain": ("remove", "keep"),
    "machine-add-unrelated-delete": ("remove", "keep"),
}


def _git(repo, *args):
    return subprocess.run(["git", *args], cwd=str(repo), check=True,
                          capture_output=True, text=True).stdout.strip()


def _lines(tag, n=30):
    return "".join(f"{tag} line {i}\n" for i in range(n))


def _init(repo):
    repo.mkdir(parents=True)
    _git(repo, "init", "-q", "-b", "master")
    _git(repo, "config", "user.email", "t@example.com")
    _git(repo, "config", "user.name", "T")
    (repo / "README").write_text("base\n", encoding="utf-8")
    _git(repo, "add", "README")
    _git(repo, "commit", "-q", "-m", "base")
    return _git(repo, "rev-parse", "HEAD")


def _add(repo, rel, subject, text=None):
    (repo / rel).write_text(text or _lines(f"add {rel}"), encoding="utf-8")
    _git(repo, "add", rel)
    _git(repo, "commit", "-q", "-m", subject)


def _move(repo, old, new, subject, rewrite=True, also=None):
    _git(repo, "mv", old, new)
    if rewrite:
        (repo / new).write_text(_lines(f"rewrite {new}"), encoding="utf-8")
        _git(repo, "add", new)
    if also:
        (repo / also).write_text(_lines(f"edit {also}"), encoding="utf-8")
        _git(repo, "add", also)
    _git(repo, "commit", "-q", "-m", subject)


def _cells(machine):
    """name -> (builder, cited path). A builder commits on top of the base commit."""
    def pure_rename(r):
        _add(r, "old.css", FEAT)
        _move(r, "old.css", "new.css", machine, rewrite=False)

    def rename_rewrite(r):
        _add(r, "old.css", FEAT)
        _move(r, "old.css", "new.css", machine)

    def two_hop(r):
        _add(r, "a.css", FEAT)
        _move(r, "a.css", "b.css", machine)
        _move(r, "b.css", "c.css", machine)

    def add_unrelated_delete(r):
        _add(r, "x.txt", FEAT)
        (r / "new.css").write_text(_lines("new.css"), encoding="utf-8")
        _git(r, "rm", "-q", "x.txt")
        _git(r, "add", "new.css")
        _git(r, "commit", "-q", "-m", machine)

    def machine_only_add(r):
        _add(r, "new.css", machine)

    def machine_rewrite_of_machine_file(r):
        _add(r, "user.txt", FEAT)
        _add(r, "a.css", machine)
        _move(r, "a.css", "b.css", machine, also="user.txt")

    def user_rename_rewrite(r):
        _add(r, "old.css", FEAT)
        _move(r, "old.css", "new.css", "feat: restyle")

    def no_history(r):
        _add(r, "other.txt", FEAT)

    return {
        "pure-rename": (pure_rename, "new.css"),
        "rename-rewrite": (rename_rewrite, "new.css"),
        "two-hop-chain": (two_hop, "c.css"),
        "machine-add-unrelated-delete": (add_unrelated_delete, "new.css"),
        "machine-only-add": (machine_only_add, "new.css"),
        "machine-rewrite-of-machine-file": (machine_rewrite_of_machine_file, "b.css"),
        "user-rename-rewrite": (user_rename_rewrite, "new.css"),
        "no-history": (no_history, "ghost.css"),
    }


def _old_subjects(worktree, base, path):
    """The pre-change command, verbatim. [] on any failure."""
    cmd = ["git", "-C", worktree, "log", "--follow", "--no-merges", "--format=%s",
           f"{base}..HEAD", "--", path]
    try:
        proc = subprocess.run(cmd, capture_output=True, text=True, timeout=60)
    except (OSError, ValueError, subprocess.SubprocessError):
        return []
    return proc.stdout.splitlines() if proc.returncode == 0 else []


def _verdict(us, path, subjects):
    return us.classify(f"- [BLOCKING] [unplanned-scope] Remove or plan {path}.",
                       changed_paths=[path], subjects_by_path={path: subjects},
                       must_preserve=set(), removed_guard_paths=set())


def main(argv=None):
    ap = argparse.ArgumentParser()
    ap.add_argument("--harness-root", required=True)
    args = ap.parse_args(argv)
    if shutil.which("git") is None:
        print("SKIP: needs git")
        return 2
    sys.path.insert(0, os.path.abspath(args.harness_root))
    from harness import fidelity, unplanned_scope as us
    cells = _cells(fidelity.REMEDIATION_COMMIT_MSG)
    results = {}
    with tempfile.TemporaryDirectory() as tmp:
        for name, (build, path) in cells.items():
            repo = Path(tmp) / name
            base = _init(repo)
            build(repo)
            old = _verdict(us, path, _old_subjects(str(repo), base, path))
            new = _verdict(us, path, us.commit_subjects_for(str(repo), base, [path])[path])
            results[name] = (old, new)
            print(f"CELL {name} old={old} new={new}")
    changed = {n: m for n, m in results.items() if m[0] != m[1]}
    unexpected = 0
    for name, move in sorted(changed.items()):
        tag = "CHANGED" if EXPECTED.get(name) == move else "UNEXPECTED"
        unexpected += tag == "UNEXPECTED"
        print(f"{tag} cell={name} {move[0]} -> {move[1]}")
    missing = [n for n in EXPECTED if changed.get(n) != EXPECTED[n]]
    for name in missing:
        print(f"MISSING cell={name}")
    print(f"SUMMARY cells={len(cells)} changed={len(changed)} "
          f"unexpected={unexpected} missing={len(missing)}")
    return 1 if unexpected or missing else 0


if __name__ == "__main__":
    sys.exit(main())
