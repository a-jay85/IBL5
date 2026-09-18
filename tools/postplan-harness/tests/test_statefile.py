"""Tests for harness/statefile.py and runner.py's per-slug state file checkpoints.

All replay-based tests pass state_dir=str(tmp_path / "state") so that they write
into a tmp directory and never touch the harness's own out/state.  PR numbers
7201-7299 are reserved for this module; /tmp/post-plan-fidelity-*-<pr>* is
cleaned up by the _st_sticky fixture.
"""
from __future__ import annotations

import glob
import json
import os
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import statefile
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, RunResult, TerminalState, UsageLedger

# Replay runs reach fidelity's procedure lookup; see tests/conftest.py.
pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# ---------------------------------------------------------------------------
# Shared helpers (re-created here; do not import from test_runner_replay)
# ---------------------------------------------------------------------------

CANNED = {
    "pr-copy": {
        "type": "chore",
        "title": "chore: replay",
        "commit_subject": "chore: replay commit",
        "summary_md": "## Summary\n- x\n",
    },
    "review-agent-a": [],
    "review-agent-b": [],
    "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}


def _fixture(**over):
    fx = {
        "slug": "synthetic-degrade",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "pr_number": 9999,
        "pr_meta": {
            "number": 9999,
            "title": "fix: synthetic",
            "body": "## Manual Testing\n\nNo manual testing needed\n",
            "headRefOid": "deadbeef",
        },
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": "# Synthetic plan\n\nBody with no matrix and no frontmatter.\n",
    }
    fx.update(over)
    return fx


class ScriptedToolLlm(FixtureLlm):
    """FixtureLlm whose tooled calls follow a script: one reply per purpose, in order."""

    def __init__(self, ledger, canned, scripts):
        super().__init__(ledger, dict(canned))
        self.scripts = {k: list(v) for k, v in scripts.items()}

    def call_tooled(self, purpose, model, prompt, **kw):
        queue = self.scripts.get(purpose)
        if queue:
            self.canned[purpose] = queue.pop(0)
        return super().call_tooled(purpose, model, prompt, **kw)


def _verdict_doc(word, tree_line=None, notes="Check 3: the diff matches the plan."):
    doc = f"## 6d checks\n\n{notes}\n\n{word}\n"
    if tree_line:
        doc += tree_line + "\n"
    return doc + "\n## DIGEST\n\n**What changed:** a synthetic replay change\n"


@pytest.fixture
def _st_sticky():
    """Yield and clean up /tmp/post-plan-fidelity-*-<pr>* files for each PR used."""
    used: list[int] = []

    def _next(n: int) -> int:
        used.append(n)
        return n

    yield _next
    for n in used:
        for path in glob.glob(f"/tmp/post-plan-fidelity-*-{n}*"):
            try:
                os.unlink(path)
            except OSError:
                pass


def _state_run(tmp_path, pr, scripts, state_dir, **over):
    """Like _sticky_run but passes state_dir directly to runner.run()."""
    out = str(tmp_path / f"out{pr}")
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""  # opt into real Phase 5.5 path
    llm = ScriptedToolLlm(UsageLedger(), canned, scripts)
    fx = _fixture(pr_number=pr, **over)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    res = runner.run(fx, out, llm, mode="replay", state_dir=state_dir)
    return res, out


def _read_state(state_dir, slug):
    path = os.path.join(state_dir, statefile.safe_slug(slug) + ".json")
    with open(path) as fh:
        return json.load(fh)


# ---------------------------------------------------------------------------
# test_state_fields_after_clean_arm
# ---------------------------------------------------------------------------

def test_state_fields_after_clean_arm(tmp_path, _st_sticky):
    """field completeness — replay (a) inputs produce a file with exactly STATE_KEYS."""
    import re
    pr = _st_sticky(7201)
    state_dir = str(tmp_path / "state")
    head = "a" * 40
    res, out = _state_run(
        tmp_path, pr,
        {"plan-fidelity-review": [_verdict_doc("READY")]},
        state_dir,
        head_sha=head,
        checks_outcome={"exit": 0},
        sticky_comment_id="sc-7201",
    )
    assert res.terminal == TerminalState.SHIPPED_ARMED

    doc = _read_state(state_dir, "synthetic-degrade")

    # key set
    assert set(doc.keys()) == set(statefile.STATE_KEYS)

    # schema and pr_number
    assert doc["schema_version"] == 1
    assert doc["pr_number"] == pr
    assert doc["terminal"] == "shipped-armed"

    # phases list
    assert [p["phase"] for p in doc["phases"]] == [
        "pr-open", "review", "fidelity", "arm", "terminal"
    ]

    # every review_agents entry has a valid sha256
    assert doc["review_agents"], "review_agents should be non-empty after the review phase"
    sha256_re = re.compile(r"^[0-9a-f]{64}$")
    for k, entry in doc["review_agents"].items():
        assert sha256_re.match(entry["findings_sha256"]), (
            f"review_agents[{k}].findings_sha256 is not a sha256 hex string"
        )

    # fidelity
    assert doc["fidelity"]["verdict_1"] == "READY"
    assert re.match(r"^[0-9a-f]{40}$", doc["fidelity"]["reviewed_tree"]), (
        "fidelity.reviewed_tree must be a bare 40-hex sha"
    )

    # arm
    assert doc["arm"] == {"armed": True, "holds": []}

    # ci — a sha-keyed entry because head_sha was set
    assert head in doc["ci"], "expected ci entry for the replay head sha"
    assert doc["ci"][head]["outcome"] == "green"
    assert "recorded_at" in doc["ci"][head]

    # sticky_comment_id
    assert doc["sticky_comment_id"] == "sc-7201"

    # timestamps
    ts_re = re.compile(r"^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$")
    for key in ("first_seen_at", "run_started_at", "updated_at"):
        assert ts_re.match(doc[key]), f"{key} is not an ISO timestamp: {doc[key]!r}"


# ---------------------------------------------------------------------------
# test_state_held_run_still_records
# ---------------------------------------------------------------------------

def test_state_held_run_still_records(tmp_path, _st_sticky):
    """Held run: terminal == 'shipped-held' and arm holds condition 12."""
    pr = _st_sticky(7202)
    state_dir = str(tmp_path / "state")
    res, out = _state_run(
        tmp_path, pr,
        {
            "plan-fidelity-review": [_verdict_doc("NOT READY")],
            "fidelity-remediation": ["edits made"],
            "plan-fidelity-re-review-2": [_verdict_doc("NOT READY")],
        },
        state_dir,
    )
    assert res.terminal == TerminalState.SHIPPED_HELD

    doc = _read_state(state_dir, "synthetic-degrade")
    assert doc["terminal"] == "shipped-held"
    assert 12 in doc["arm"]["holds"]

    # Each verdict keeps its own tree. compose_sticky prints reviewed_tree as
    # **Reviewed tree:** and reviewed_tree_2 as **Re-reviewed tree:**; collapsing
    # the first onto the second drops verdict 1's tree from the merge digest.
    # Condition (12) reads reviewed_tree_2 only (runner.py fidelity_tree_2), so
    # nothing downstream wants them equal.
    assert doc["fidelity"]["reviewed_tree"] != doc["fidelity"]["reviewed_tree_2"]

    # Mutation: placing arm inside `if decision.armed:` would omit it for held runs.
    assert "arm" in [p["phase"] for p in doc["phases"]]


# ---------------------------------------------------------------------------
# test_state_records_mid_fidelity_failure
# ---------------------------------------------------------------------------

def test_state_records_mid_fidelity_failure(monkeypatch, tmp_path, _st_sticky):
    """Failure after review is recorded via the finally-block terminal checkpoint.

    The run is forced to fail by patching _run_fidelity to raise HarnessError
    ("push-failed", ...) — this mirrors the scenario in the packet description
    (a push failure during Phase 5.5 remediation) while ensuring the error
    propagates past all inner catches to the outer except.  The key assertion is
    that phases == ["pr-open", "review", "terminal"], proving the terminal
    checkpoint in finally fires even when fidelity/arm checkpoints are never reached.

    Mutation: move terminal checkpoint from finally to end of try → file ends at
    review with terminal null.
    """
    pr = _st_sticky(7203)
    state_dir = str(tmp_path / "state")

    def _failing_fidelity(*args, **kwargs):
        raise HarnessError("push-failed", "remote rejected")

    monkeypatch.setattr(runner, "_run_fidelity", _failing_fidelity)

    out = str(tmp_path / f"out{pr}")
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""
    llm = ScriptedToolLlm(UsageLedger(), canned, {})
    fx = _fixture(pr_number=pr)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    res = runner.run(fx, out, llm, mode="replay", state_dir=state_dir)

    assert res.terminal == TerminalState.FAILED
    assert res.error_kind == "push-failed"

    doc = _read_state(state_dir, "synthetic-degrade")
    assert doc["terminal"] == "failed"
    assert doc["error_kind"] == "push-failed"
    assert [p["phase"] for p in doc["phases"]] == ["pr-open", "review", "terminal"]


# ---------------------------------------------------------------------------
# test_state_ci_history_survives_rerun
# ---------------------------------------------------------------------------

def test_state_ci_history_survives_rerun(tmp_path, _st_sticky):
    """CI history from a previous run is preserved across re-runs."""
    pr = _st_sticky(7204)
    state_dir = str(tmp_path / "state")
    os.makedirs(state_dir, exist_ok=True)

    slug = "synthetic-degrade"
    state_path = os.path.join(state_dir, statefile.safe_slug(slug) + ".json")
    old_sha = "b" * 40
    seed = {
        "ci": {old_sha: {"outcome": "failed", "recorded_at": "2026-01-01T00:00:00Z"}},
        "first_seen_at": "2026-01-01T00:00:00Z",
    }
    with open(state_path, "w") as fh:
        json.dump(seed, fh)

    head = "c" * 40
    res, out = _state_run(
        tmp_path, pr,
        {"plan-fidelity-review": [_verdict_doc("READY")]},
        state_dir,
        head_sha=head,
        checks_outcome={"exit": 0},
    )
    assert res.terminal == TerminalState.SHIPPED_ARMED

    doc = _read_state(state_dir, slug)
    # old SHA preserved
    assert old_sha in doc["ci"], "old CI SHA should be preserved from the previous run"
    # new SHA added
    assert head in doc["ci"], "new CI SHA should be recorded in this run"
    # first_seen_at unchanged
    assert doc["first_seen_at"] == "2026-01-01T00:00:00Z"


# ---------------------------------------------------------------------------
# test_state_no_sha_no_ci_entry
# ---------------------------------------------------------------------------

def test_state_no_sha_no_ci_entry(tmp_path, _st_sticky):
    """When head_sha is absent, ci stays empty and head_sha is None in phases."""
    pr = _st_sticky(7205)
    state_dir = str(tmp_path / "state")

    # (a) inputs but no head_sha in fixture
    res, out = _state_run(
        tmp_path, pr,
        {"plan-fidelity-review": [_verdict_doc("READY")]},
        state_dir,
        checks_outcome={"exit": 0},
    )
    assert res.terminal == TerminalState.SHIPPED_ARMED

    doc = _read_state(state_dir, "synthetic-degrade")
    assert doc["ci"] == {}, "no CI entry should be recorded without a head_sha"
    # terminal phase should have head_sha = None
    terminal_phase = next(p for p in doc["phases"] if p["phase"] == "terminal")
    assert terminal_phase["head_sha"] is None


# ---------------------------------------------------------------------------
# test_state_corrupt_previous_file
# ---------------------------------------------------------------------------

def test_state_corrupt_previous_file(tmp_path, _st_sticky):
    """Corrupt previous file: run succeeds and the log records the unreadable state."""
    pr = _st_sticky(7206)
    state_dir = str(tmp_path / "state")
    os.makedirs(state_dir, exist_ok=True)

    slug = "synthetic-degrade"
    state_path = os.path.join(state_dir, statefile.safe_slug(slug) + ".json")
    with open(state_path, "w") as fh:
        fh.write("{not json")

    res, out = _state_run(
        tmp_path, pr,
        {"plan-fidelity-review": [_verdict_doc("READY")]},
        state_dir,
        head_sha="e" * 40,
        checks_outcome={"exit": 0},
    )
    assert res.terminal == TerminalState.SHIPPED_ARMED

    # state file now parses
    doc = _read_state(state_dir, slug)
    assert doc["schema_version"] == 1

    # audit log contains the warning
    with open(os.path.join(out, "audit.log")) as fh:
        audit = fh.read()
    assert "state: previous file unreadable" in audit


# ---------------------------------------------------------------------------
# test_state_write_failure_never_fails_run
# ---------------------------------------------------------------------------

def test_state_write_failure_never_fails_run(tmp_path, _st_sticky):
    """state_dir under a regular file: writes fail silently, run succeeds."""
    pr = _st_sticky(7207)

    # Create a regular FILE where state_dir would be, making writes impossible
    regular_file = str(tmp_path / "not_a_dir")
    with open(regular_file, "w") as fh:
        fh.write("x")
    state_dir = regular_file + "/sub"  # path UNDER the regular file

    head = "f" * 40
    res, out = _state_run(
        tmp_path, pr,
        {"plan-fidelity-review": [_verdict_doc("READY")]},
        state_dir,
        head_sha=head,
        checks_outcome={"exit": 0},
    )
    assert res.terminal == TerminalState.SHIPPED_ARMED

    # exactly one pr_merge_auto (armed)
    import json as _json
    acts_path = os.path.join(out, "actions.jsonl")
    acts = []
    if os.path.exists(acts_path):
        with open(acts_path) as fh:
            acts = [_json.loads(l) for l in fh if l.strip()]
    assert sum(1 for a in acts if a.get("action") == "pr_merge_auto") == 1

    # audit log contains the write-failure notice
    with open(os.path.join(out, "audit.log")) as fh:
        audit = fh.read()
    assert "state: write failed" in audit


# ---------------------------------------------------------------------------
# test_state_atomic_replace
# ---------------------------------------------------------------------------

def test_state_atomic_replace(tmp_path, monkeypatch):
    """os.replace failure leaves the original file byte-unchanged and no tmp remains."""
    state_dir = str(tmp_path / "state")
    os.makedirs(state_dir, exist_ok=True)

    slug = "atomic-test"
    path = os.path.join(state_dir, statefile.safe_slug(slug) + ".json")
    original = json.dumps({"schema_version": 0, "note": "original"})
    with open(path, "w") as fh:
        fh.write(original)

    # Simple stub git
    class _StubGit:
        def head(self):
            return "0" * 40

    log_lines: list[str] = []
    sf = statefile.StateFile(path, slug, _StubGit(), "/tmp/fake-out", log_lines.append)

    import harness.statefile as _sf_mod

    def _raise_replace(src, dst):
        raise OSError("disk full")

    monkeypatch.setattr(_sf_mod.os, "replace", _raise_replace)

    # Trigger a checkpoint; should fail silently
    res = RunResult(terminal=TerminalState.FAILED)
    sf.checkpoint("pr-open", res)

    # File bytes unchanged
    with open(path) as fh:
        assert fh.read() == original, "file should be unchanged after replace failure"

    # No .tmp file left behind
    leftover = glob.glob(state_dir + "/*.tmp")
    assert leftover == [], f"tmp files leaked: {leftover}"


# ---------------------------------------------------------------------------
# test_safe_slug_no_escape
# ---------------------------------------------------------------------------

def test_safe_slug_no_escape():
    """safe_slug prevents path traversal and returns 'unknown' for degenerate inputs."""
    result = statefile.safe_slug("feat/../../x")
    assert "/" not in result, "safe_slug must not produce a slash"
    assert os.path.dirname(os.path.join("/some/state", result + ".json")) == "/some/state"

    for bad in ("", "..", "/"):
        assert statefile.safe_slug(bad) == "unknown", (
            f"safe_slug({bad!r}) should return 'unknown'"
        )


# ---------------------------------------------------------------------------
# test_state_dir_location_by_mode
# ---------------------------------------------------------------------------

def test_state_dir_location_by_mode():
    """_state_dir returns the correct directory depending on mode and override."""
    assert runner._state_dir("/o", False, None) == "/o/state"

    live_result = runner._state_dir("/o", True, None)
    assert live_result.endswith("tools/postplan-harness/out/state"), (
        f"live state_dir should end with tools/postplan-harness/out/state, got {live_result!r}"
    )

    assert runner._state_dir("/o", False, "/override") == "/override"
    assert runner._state_dir("/o", True, "/override") == "/override"


# ---------------------------------------------------------------------------
# test_out_state_is_gitignored
# ---------------------------------------------------------------------------

def test_out_state_is_gitignored():
    """tools/postplan-harness/out/state/ must be gitignored so live runs don't appear
    in git status."""
    repo_root = os.path.dirname(os.path.dirname(os.path.dirname(
        os.path.dirname(os.path.abspath(__file__)))))

    check = subprocess.run(
        ["git", "check-ignore", "-q",
         "tools/postplan-harness/out/state/some-slug.json"],
        cwd=repo_root,
        capture_output=True,
    )
    assert check.returncode == 0, (
        "tools/postplan-harness/out/state/some-slug.json is NOT gitignored; "
        "add tools/postplan-harness/out/ to .gitignore"
    )

    status = subprocess.run(
        ["git", "status", "--porcelain", "tools/postplan-harness/out"],
        cwd=repo_root,
        capture_output=True,
        text=True,
    )
    assert status.stdout.strip() == "", (
        "git status shows untracked/dirty files under tools/postplan-harness/out: "
        + status.stdout
    )
