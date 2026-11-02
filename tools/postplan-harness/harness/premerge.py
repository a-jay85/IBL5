"""SHA-bound manifest for a merge that `bin/postplan-fix --headless` committed locally.

A local merge of origin/master makes the harness's own `git merge --no-edit origin/master`
a no-op, so without this manifest no lost-work proof and no conflict review would run on
the bot's resolution. `LiveGit._rebase_onto` loads the manifest, and applies it only when
its SHA equals HEAD. The proof itself stays in `LiveGit._prove_tree_equivalent`; nothing
here proves anything.

CLI (used by bin/postplan-fix):

    python3 -m harness.premerge record --worktree W --branch B --pre-sha S --files-from F
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
from dataclasses import dataclass
from typing import Optional

MANIFEST_SCHEMA = 1

_SHA = re.compile(r"^[0-9a-f]{40}$")


class PremergeError(Exception):
    pass


@dataclass(frozen=True)
class Premerged:
    sha: str
    pre_sha: str
    files: tuple[str, ...]


def manifest_path(key: str, tmp_dir: str = "/tmp") -> str:
    return f"{tmp_dir}/postplan-premerged-{key}.json"


def load(key: str, tmp_dir: str = "/tmp") -> Optional[Premerged]:
    """The manifest for key, or None when absent or malformed. A malformed manifest never
    applies; the condition-(14) flag, recorded separately, still holds the PR."""
    path = manifest_path(key, tmp_dir)
    try:
        with open(path) as fh:
            raw = fh.read()
    except FileNotFoundError:
        return None
    except OSError:
        sys.stderr.write(f"premerge: ignoring malformed manifest {path}\n")
        return None
    try:
        data = json.loads(raw)
        if not isinstance(data, dict) or data.get("schema_version") != MANIFEST_SCHEMA:
            raise ValueError("schema")
        sha, pre_sha, files = data.get("sha"), data.get("pre_sha"), data.get("files")
        if not (isinstance(sha, str) and _SHA.match(sha)
                and isinstance(pre_sha, str) and _SHA.match(pre_sha)):
            raise ValueError("sha")
        if (not isinstance(files, list) or not files
                or not all(isinstance(f, str) and f for f in files)):
            raise ValueError("files")
    except ValueError:
        sys.stderr.write(f"premerge: ignoring malformed manifest {path}\n")
        return None
    return Premerged(sha=sha, pre_sha=pre_sha, files=tuple(files))


def write(key: str, premerged: Premerged, tmp_dir: str = "/tmp") -> str:
    path = manifest_path(key, tmp_dir)
    tmp = f"{path}.{os.getpid()}.tmp"
    with open(tmp, "w") as fh:
        json.dump({"schema_version": MANIFEST_SCHEMA, "sha": premerged.sha,
                   "pre_sha": premerged.pre_sha, "files": list(premerged.files)}, fh)
    os.replace(tmp, path)
    return path


def _git(worktree: str, *args: str) -> tuple[int, str]:
    proc = subprocess.run(["git", "-C", worktree, *args],
                          capture_output=True, text=True, errors="replace")
    return proc.returncode, proc.stdout


def record(worktree: str, branch: str, pre_sha: str, files: tuple[str, ...],
           tmp_dir: str = "/tmp") -> Premerged:
    """Validate that HEAD is the bot's merge of origin/master onto pre_sha, then write the
    manifest and, last, the condition-(14) flag. The flag goes last so a crash between the
    two leaves a manifest with no flag, which the harness still proves and reviews."""
    from .armable import record_conflict_resolution

    rc, out = _git(worktree, "rev-list", "--parents", "-n", "1", "HEAD")
    parts = out.split()
    if rc != 0 or len(parts) != 3:
        raise PremergeError("HEAD is not a two-parent merge")
    head_sha, parent1, parent2 = parts
    if parent1 != pre_sha:
        raise PremergeError("first parent is not --pre-sha")
    if _git(worktree, "merge-base", "--is-ancestor", parent2, "origin/master")[0] != 0:
        raise PremergeError("second parent is not in origin/master")
    if not files:
        raise PremergeError("no conflicted files")
    if not _SHA.match(head_sha) or not _SHA.match(pre_sha):
        raise PremergeError("not a full SHA")
    premerged = Premerged(sha=head_sha, pre_sha=pre_sha, files=tuple(files))
    key = branch.replace("/", "-")
    write(key, premerged, tmp_dir)
    record_conflict_resolution(branch, head_sha, tmp_dir)
    return premerged


def main(argv: list[str]) -> int:
    parser = argparse.ArgumentParser(prog="harness.premerge")
    sub = parser.add_subparsers(dest="cmd", required=True)
    rec = sub.add_parser("record")
    rec.add_argument("--worktree", required=True)
    rec.add_argument("--branch", required=True)
    rec.add_argument("--pre-sha", required=True)
    rec.add_argument("--files-from", required=True)
    args = parser.parse_args(argv)
    try:
        with open(args.files_from) as fh:
            files = tuple(line.strip() for line in fh if line.strip())
        premerged = record(args.worktree, args.branch, args.pre_sha, files)
    except (PremergeError, OSError) as exc:
        sys.stderr.write(f"premerge: {exc}\n")
        return 3
    print(f"premerge: recorded {premerged.sha}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
