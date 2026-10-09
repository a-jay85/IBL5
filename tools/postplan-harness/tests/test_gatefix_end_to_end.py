"""Gate fixer against a real git repo with a real denying pre-commit hook (Phase 7b)."""
from __future__ import annotations

import os
import stat
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import rules_budget_carveout
from harness.adapters.gitad import LiveGit, classify_local_gate_denial
from harness.state import HarnessError, RunResult, TerminalState

from test_gatefix import WritingLlm

DENIAL = ("Restore the stub pointer in the always-on file. Trim the rule(s) above "
          "(or move detail into a path-scoped *-detail.md companion) before committing.")

BUDGET_STUB = """#!/bin/sh
[ "$(wc -c < .claude/rules/big.md)" -gt 200 ] && exit 1
exit 0
"""
OK_STUB = "#!/bin/sh\nexit 0\n"
HOOK = f"""#!/bin/sh
root="$(pwd)"
echo run >> "$(dirname "$root")/hook-calls.log"
limit=200
[ -f "$(dirname "$root")/threshold" ] && limit="$(cat "$(dirname "$root")/threshold")"
if [ "$(wc -c < .claude/rules/big.md)" -gt "$limit" ]; then
  echo "{DENIAL}" >&2
  exit 1
fi
exit 0
"""
BIG_BASE = "b" * 150
BIG_GROWN = "g" * 400
PREFIX_DIRTY = "<?php // dirty before the fix\n"


def _sh(wt, *args):
    subprocess.run(["git", "-C", str(wt), *args], check=True, capture_output=True)


def _write_exec(path, text):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text)
    path.chmod(path.stat().st_mode | stat.S_IEXEC)


@pytest.fixture
def e2e(tmp_path, monkeypatch):
    wt = tmp_path / "wt"
    wt.mkdir()
    home = tmp_path / "home"
    (home / ".claude" / "hooks").mkdir(parents=True)
    hook_file = home / ".claude" / "hooks" / "plan-gate-edit.sh"
    hook_file.write_text("#!/bin/sh\n# original out-of-repo hook\n")
    monkeypatch.setenv("HOME", str(home))
    _sh(wt, "init", "-q")
    _sh(wt, "symbolic-ref", "HEAD", "refs/heads/master")
    _sh(wt, "config", "user.email", "t@example.com")
    _sh(wt, "config", "user.name", "T")
    (wt / ".claude/rules").mkdir(parents=True)
    (wt / ".claude/rules/big.md").write_text(BIG_BASE)
    (wt / "ibl5").mkdir()
    (wt / "ibl5/x.php").write_text("<?php // base\n")
    _write_exec(wt / "bin/check-rules-byte-budget", BUDGET_STUB)
    _write_exec(wt / "bin/check-prose", OK_STUB)
    _write_exec(wt / "bin/check-docs", OK_STUB)
    _sh(wt, "add", "-A")
    _sh(wt, "commit", "-q", "-m", "base")
    _sh(wt, "update-ref", "refs/remotes/origin/master", "HEAD")
    hook = wt / ".git/hooks/pre-commit"
    _write_exec(hook, HOOK)
    # Working tree before the fix: big.md grown past the budget, x.php dirty.
    (wt / ".claude/rules/big.md").write_text(BIG_GROWN)
    (wt / "ibl5/x.php").write_text(PREFIX_DIRTY)
    return {"wt": wt, "home": home, "hook": hook, "hook_file": hook_file,
            "git": LiveGit(str(wt), push_remote="origin"),
            "calls": tmp_path / "hook-calls.log", "tmp": tmp_path}


def _calls(e2e):
    p = e2e["calls"]
    return len(p.read_text().splitlines()) if p.exists() else 0


def _run(e2e, edit):
    res = RunResult(terminal=TerminalState.FAILED)
    logs: list[str] = []
    llm = WritingLlm(edit=edit)
    try:
        sha = runner._commit_with_gate_fix(e2e["git"], str(e2e["wt"]), "feat: x", logs.append,
                                           "phase2", llm=llm, res=res)
        return sha, None, res, logs
    except HarnessError as err:
        res.error_kind = err.kind
        return None, err, res, logs


def _show(wt, *spec):
    return subprocess.run(["git", "-C", str(wt), "show", *spec], check=True,
                          capture_output=True, text=True).stdout


def test_e2e_commit_denial_fixed_and_retried(e2e):
    sha, err, res, _ = _run(
        e2e, lambda cwd: (cwd / ".claude/rules/big.md").write_text("t" * 180))
    assert err is None and sha
    assert _calls(e2e) == 2
    wt = e2e["wt"]
    assert len(_show(wt, "HEAD:.claude/rules/big.md")) == 180
    assert _show(wt, "HEAD:ibl5/x.php") == PREFIX_DIRTY
    assert _show(wt, "-s", "--format=%B", "HEAD").rstrip().endswith(runner._GATE_FIX_NOTE)
    g = res.gate_fix
    assert g["status"] == "fixed" and g["retry"] == "passed"
    assert g["files"] == [".claude/rules/big.md"]
    assert g["gate_class"] == classify_local_gate_denial(DENIAL)
    assert rules_budget_carveout.BUDGET_SCRIPT == "bin/check-rules-byte-budget"


def test_e2e_fixer_disables_hook_is_reverted(e2e):
    original = e2e["hook"].read_bytes()
    mode = e2e["hook"].stat().st_mode

    def edit(cwd):
        (cwd / ".git/hooks/pre-commit").write_text("#!/bin/sh\nexit 0\n")
        (cwd / ".claude/rules/big.md").write_text("t" * 180)

    sha, err, res, _ = _run(e2e, edit)
    assert sha is None and err is not None
    assert DENIAL in err.detail
    assert runner.exit_code_for(res) == 3
    assert e2e["hook"].read_bytes() == original
    assert e2e["hook"].stat().st_mode == mode
    assert _calls(e2e) == 1
    assert (e2e["wt"] / ".claude/rules/big.md").read_text() == BIG_GROWN
    assert res.gate_fix["status"] == "guard-rejected"
    assert "out-of-repo gate path" in res.gate_fix["reason"]


def test_e2e_fixer_edits_gate_script_is_reverted(e2e):
    stub = e2e["wt"] / "bin/check-rules-byte-budget"
    original = stub.read_bytes()
    sha, err, res, _ = _run(e2e, lambda cwd: (cwd / "bin/check-rules-byte-budget")
                            .write_text("#!/bin/sh\nexit 0\n"))
    assert sha is None and err is not None
    assert "bin/check-rules-byte-budget: gate path" in res.gate_fix["reason"]
    assert stub.read_bytes() == original
    assert _calls(e2e) == 1


def test_e2e_retry_still_denied_exits_3(e2e):
    (e2e["tmp"] / "threshold").write_text("100")
    sha, err, res, _ = _run(
        e2e, lambda cwd: (cwd / ".claude/rules/big.md").write_text("t" * 190))
    assert sha is None and err is not None
    assert _calls(e2e) == 2
    assert DENIAL in err.detail
    assert runner.exit_code_for(res) == 3
    assert res.gate_fix["retry"] == "denied"
    assert len((e2e["wt"] / ".claude/rules/big.md").read_text()) == 190


def test_e2e_out_of_repo_claude_hook_edit_is_reverted(e2e):
    original = e2e["hook_file"].read_bytes()
    sha, err, res, _ = _run(
        e2e, lambda cwd: e2e["hook_file"].write_text("#!/bin/sh\nexit 0\n"))
    assert sha is None and err is not None
    assert e2e["hook_file"].read_bytes() == original
    assert res.gate_fix["status"] == "guard-rejected"
    assert _calls(e2e) == 1
