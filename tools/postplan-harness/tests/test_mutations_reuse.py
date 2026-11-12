"""Post-plan reuses pre-fire's executable-mutations pass (ADR-0199).

Pre-fire writes a reuse sidecar keyed on the parsed rows; post-plan rebuilds its
MutationReport from it only when no dependency path changed since pre-fire.
"""
import os
import re
import stat
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

REPO = Path(__file__).resolve().parents[3]
PREFIRE = REPO / "bin" / "lib" / "prefire-verify"

RECORD = b"1\x00target.txt\x00grep -q ALPHA target.txt && grep -q P checks/probe.txt\x00ALPHA\x00BETA\n"
TRIPPED = "TRIPPED row 1 — check exited 1 under mutation"
HELD = "HELD row 1 — check still exited 0 under mutation"


def _git(repo, *args, input=None):
    return subprocess.run(["git", "-C", str(repo), *args], capture_output=True,
                          input=input, check=True)


def _commit(repo, msg="c"):
    _git(repo, "add", "-A")
    _git(repo, "commit", "-qm", msg)


def _repo(tmp_path):
    repo = tmp_path / "repo"
    repo.mkdir()
    _git(repo, "init", "-q", "-b", "master")
    _git(repo, "config", "user.name", "t")
    _git(repo, "config", "user.email", "t@t")
    (repo / "target.txt").write_text("ALPHA\n")
    (repo / "checks").mkdir()
    (repo / "checks" / "probe.txt").write_text("P\n")
    _commit(repo, "fixture")
    plan = tmp_path / "plan.md"
    plan.write_text("# Fixture plan\n")
    return repo, plan


def _stub(tmp_path, records, run_body, pending_body="exit 1"):
    """A fake lib. `pending` exits 1 on an empty journal, like the real one."""
    (tmp_path / "records").write_bytes(records)
    lib = tmp_path / "stub-lib"
    lib.write_text(
        "#!/bin/bash\n"
        f"S={tmp_path}\n"
        "case \"$1\" in\n"
        f"parse) cat \"$S/records\" ;;\n"
        f"run) touch \"$S/run-called\"; {run_body} ;;\n"
        f"pending) {pending_body} ;;\n"
        "recover) exit 0 ;;\n"
        "esac\n")
    lib.chmod(lib.stat().st_mode | stat.S_IXUSR)
    return lib


def _prefire(repo, plan, lib):
    env = {**os.environ, "PREFIRE_EXEC_MUTATIONS_LIB": str(lib)}
    env.pop("CLAUDE_HEADLESS", None)
    return subprocess.run([str(PREFIRE), "--base", "master", "--plan", str(plan),
                           "--stage", "exec-mutations"],
                          cwd=str(repo), env=env, capture_output=True, text=True, timeout=120)


def _state_dir(repo):
    gp = Path(_git(repo, "rev-parse", "--git-path", "prefire-verify").stdout.decode().strip())
    return gp if gp.is_absolute() else Path(repo) / gp


def _key(data):
    return _git(".", "hash-object", "--stdin", input=data).stdout.decode().strip()


def _sidecars(repo):
    return sorted(_state_dir(repo).glob("exec-mutations.rows-*.reuse"))


# --- Phase 1: pre-fire writes the sidecar -------------------------------------

def test_prefire_pass_writes_sidecar(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, RECORD, f'echo "{TRIPPED}"; exit 0')
    proc = _prefire(repo, plan, lib)
    assert proc.returncode == 0, proc.stdout + proc.stderr
    sidecar = _state_dir(repo) / f"exec-mutations.rows-{_key(RECORD)}.reuse"
    assert sidecar.is_file()
    lines = sidecar.read_text(encoding="utf-8").splitlines()
    assert lines[0] == "prefire-xm-reuse v1"
    assert lines[1] == f"rows={_key(RECORD)}"
    assert re.fullmatch(r"tree=[0-9a-f]{40}", lines[2])
    assert lines[3].startswith("at=")
    assert lines[4:] == [TRIPPED]
    gitdir = Path(_git(repo, "rev-parse", "--absolute-git-dir").stdout.decode().strip())
    assert sidecar.resolve().is_relative_to(gitdir.resolve())
    assert _git(repo, "status", "--porcelain", "--ignored").stdout == b""


def test_prefire_held_writes_no_sidecar(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, RECORD, f'echo "{HELD}"; exit 1')
    proc = _prefire(repo, plan, lib)
    assert proc.returncode != 0
    assert _sidecars(repo) == []


def test_prefire_tree_change_drops_sidecar(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, RECORD, f'echo "{TRIPPED}"; touch {repo}/stray.txt; exit 0')
    proc = _prefire(repo, plan, lib)
    assert proc.returncode == 4, proc.stdout + proc.stderr
    assert _sidecars(repo) == []
