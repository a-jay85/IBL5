"""Real-git replay of a conflicted lockfile through `LiveGit._rebase_onto`.

Every scenario runs the real `LiveGit._rebase_onto` and the real, unchanged
`.claude/review-shared/scripts/lostwork.sh`. Only the model is faked (`_ScriptedLlm`), and
the regen command is a bash script that derives the lock from the manifest, so CI needs
neither bun nor network.
"""
from __future__ import annotations

import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conflict_ladder, lockfile_regen
from harness.armable import conflict_flag_path
from test_conflict_ladder_live import (  # noqa: F401  (make_repo is a pytest fixture)
    _drive_runner,
    _git,
    _parents,
    _result_json,
    _ScriptedLlm,
    _show,
    _writer,
    make_repo,
)

_LOCK = "ibl5/bun.lock"
_MANIFEST = "ibl5/package.json"

_FAKE_LOCK_SCRIPT = (
    "#!/usr/bin/env bash\n"
    "set -euo pipefail\n"
    'manifest="$1"; lock="$2"\n'
    '{ echo "# fake lockfile $(git hash-object "$manifest")";'
    " grep -E '^[[:space:]]*\"[a-z-]+\": \"' \"$manifest\""
    " | sed -E 's/^[[:space:]]*//' | LC_ALL=C sort; } > \"$lock\"\n"
)


def _manifest(deps):
    lines = [f'    "{name}": "{ver}"' for name, ver in deps]
    body = ",\n".join(lines)
    return '{\n  "dependencies": {\n' + body + "\n  }\n}\n"


def _hash_object(text):
    return subprocess.run(
        ["git", "hash-object", "--stdin"], input=text, check=True,
        capture_output=True, text=True).stdout.strip()


def _fake_lock(manifest_text):
    """The same rule `gen/fake-lock.sh` applies, built in Python."""
    deps = sorted(
        line.strip() for line in manifest_text.splitlines()
        if line.strip().startswith('"') and ': "' in line)
    return "\n".join([f"# fake lockfile {_hash_object(manifest_text)}", *deps]) + "\n"


def _lockfile_repo(make_repo, *, manifest_conflict=True):
    """Master and the branch both change the manifest and the lock.

    manifest_conflict=True puts both new deps on the line after `alpha`, a real three-way
    content conflict. False keeps the manifest auto-mergeable so only the lock conflicts."""
    if manifest_conflict:
        base = [("alpha", "1.0.0"), ("zeta", "1.0.0")]
        branch = [base[0], ("branch-dep", "1.0.0"), base[1]]
        master = [base[0], ("master-dep", "2.0.0"), base[1]]
        merged = [base[0], ("branch-dep", "1.0.0"), ("master-dep", "2.0.0"), base[1]]
    else:
        base = [("alpha", "1.0.0"), ("kilo", "1.0.0"), ("lima", "1.0.0"),
                ("mike", "1.0.0"), ("zeta", "1.0.0")]
        branch = [base[0], ("beta-dep", "1.0.0"), *base[1:]]
        master = [*base[:4], ("yankee-dep", "2.0.0"), base[4]]
        merged = [base[0], ("beta-dep", "1.0.0"), *base[1:4], ("yankee-dep", "2.0.0"), base[4]]
    texts = {side: _manifest(deps) for side, deps in
             (("base", base), ("branch", branch), ("master", master))}
    fx = make_repo(
        {_MANIFEST: texts["base"], _LOCK: _fake_lock(texts["base"]),
         "gen/fake-lock.sh": _FAKE_LOCK_SCRIPT},
        {_MANIFEST: texts["branch"], _LOCK: _fake_lock(texts["branch"])},
        {_MANIFEST: texts["master"], _LOCK: _fake_lock(texts["master"])})
    fx.merged_manifest = _manifest(merged)
    fx.expected_lock = _fake_lock(fx.merged_manifest)
    return fx


def _other_lock_repo(make_repo, path):
    """Only `path` conflicts: all three sides carry a different copy of it."""
    return make_repo({path: "base\n"}, {path: "branch\n"}, {path: "master\n"})


def _fake_regen(monkeypatch, argv=("bash", "../gen/fake-lock.sh", "package.json", "bun.lock")):
    monkeypatch.setattr(lockfile_regen, "REGENERABLE_LOCKFILES", (
        lockfile_regen.RegenLockfile(_LOCK, _MANIFEST, argv, "ibl5"),))


def _audit(fx):
    with open(os.path.join(fx.out, "audit.log")) as fh:
        return fh.read()


# --- characterization: lockfiles outside the registry keep bailing ----------------------

def test_live_iblbot_package_lock_conflict_still_bails(make_repo, monkeypatch, tmp_path):
    path = "ibl5/IBLbot/package-lock.json"
    fx = _other_lock_repo(make_repo, path)
    llm = _ScriptedLlm({})

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.error_kind == "rebase-conflict", res.error
    assert f"lockfile: {path}" in res.error
    assert _git(fx.d, "rev-parse", "HEAD").stdout.strip() == fx.pre
    assert _git(fx.d, "ls-files", "--unmerged").stdout.strip() == ""
    assert llm.first_pass_calls == []


def test_live_unregistered_lock_conflict_still_bails(make_repo, monkeypatch, tmp_path):
    path = "ibl5/other.lock"
    fx = _other_lock_repo(make_repo, path)
    llm = _ScriptedLlm({})

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.error_kind == "rebase-conflict", res.error
    assert f"lockfile: {path}" in res.error
    assert _git(fx.d, "rev-parse", "HEAD").stdout.strip() == fx.pre
    assert _git(fx.d, "ls-files", "--unmerged").stdout.strip() == ""
    assert llm.first_pass_calls == []


# --- the fix: a registered lockfile is rebuilt from the resolved manifest ----------------

def test_live_bun_lock_conflict_regenerated_and_proof_passes(make_repo, monkeypatch, tmp_path):
    fx = _lockfile_repo(make_repo)
    _fake_regen(monkeypatch)
    llm = _ScriptedLlm({
        f"conflict-resolve:{_MANIFEST}": _writer(_MANIFEST, fx.merged_manifest),
    })

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.error_kind == "test-stop", res.error
    assert [p for p, _m, _pr in llm.first_pass_calls] == [f"conflict-resolve:{_MANIFEST}"]
    assert llm.ladder_calls == []
    assert _parents(fx.d) == [fx.pre, fx.master]
    assert _show(fx.d, f"HEAD:{_LOCK}") == fx.expected_lock
    assert ("phase2: lockfile regenerated: ibl5/bun.lock via "
            "bash ../gen/fake-lock.sh package.json bun.lock") in _audit(fx)
    assert os.path.exists(conflict_flag_path(fx.key))


def test_live_lockfile_only_conflict_sets_conflict_flag(make_repo, monkeypatch, tmp_path):
    fx = _lockfile_repo(make_repo, manifest_conflict=False)
    _fake_regen(monkeypatch)
    llm = _ScriptedLlm({})

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.error_kind == "test-stop", res.error
    assert llm.first_pass_calls == []
    assert _result_json(fx)["conflict_rung"] == "regen"
    assert os.path.exists(conflict_flag_path(fx.key))
    assert [p for p, _m, _pr in llm.calls] == ["conflict-review"]
    assert _show(fx.d, f"HEAD:{_LOCK}") == fx.expected_lock


def _assert_restored(fx):
    assert _git(fx.d, "rev-parse", "HEAD").stdout.strip() == fx.pre
    assert _git(fx.d, "ls-files", "--unmerged").stdout.strip() == ""


def test_live_bun_lock_regen_failure_names_command(make_repo, monkeypatch, tmp_path):
    fx = _lockfile_repo(make_repo)
    _fake_regen(monkeypatch, ("bash", "-c", "exit 7"))
    llm = _ScriptedLlm({
        f"conflict-resolve:{_MANIFEST}": _writer(_MANIFEST, fx.merged_manifest),
    })

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.error_kind == "rebase-conflict", res.error
    assert "lockfile: ibl5/bun.lock (regen failed: bash -c 'exit 7' exit 7" in res.error
    _assert_restored(fx)
    assert "lockfile regenerated" not in _audit(fx)


def test_live_bun_lock_regen_timeout_names_command(make_repo, monkeypatch, tmp_path):
    fx = _lockfile_repo(make_repo)
    _fake_regen(monkeypatch, ("bash", "-c", "sleep 5"))
    monkeypatch.setattr(lockfile_regen, "LOCKFILE_REGEN_TIMEOUT", 1)
    llm = _ScriptedLlm({
        f"conflict-resolve:{_MANIFEST}": _writer(_MANIFEST, fx.merged_manifest),
    })

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.error_kind == "rebase-conflict", res.error
    assert "lockfile: ibl5/bun.lock (regen timed out after 1s: bash -c 'sleep 5')" in res.error
    _assert_restored(fx)


def test_live_bun_lock_with_declined_manifest_still_fails(make_repo, monkeypatch, tmp_path):
    fx = _lockfile_repo(make_repo)
    _fake_regen(monkeypatch)
    monkeypatch.setattr(conflict_ladder, "default_rungs", lambda branch: ())
    llm = _ScriptedLlm({f"conflict-resolve:{_MANIFEST}": lambda cwd, prompt: "FAILED"})

    res = _drive_runner(fx, llm, monkeypatch, tmp_path)

    assert res.error_kind == "rebase-conflict", res.error
    assert "regen failed" not in res.error
    _assert_restored(fx)
    assert "lockfile regenerated" not in _audit(fx)
