"""bench/replay_body_only_recheck.py: offline classification and live mode over tmp run dirs."""
import importlib.util
import json
import os
import re
import subprocess
import sys
from pathlib import Path

HARNESS = Path(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, str(HARNESS))

from harness.state import HarnessError  # noqa: E402
from test_fidelity_body_recheck import VERDICT1_CODE, VERDICT1_PURE_BODY  # noqa: E402

SCRIPT = HARNESS / "bench" / "replay_body_only_recheck.py"
_spec = importlib.util.spec_from_file_location("replay_body_only_recheck", SCRIPT)
rbr = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(rbr)

TREE_A = "a" * 40
TREE_B = "b" * 40
FOO_DIFF = ("diff --git a/ibl5/tests/FooTest.php b/ibl5/tests/FooTest.php\n"
            "--- a/ibl5/tests/FooTest.php\n+++ b/ibl5/tests/FooTest.php\n"
            "@@ -1 +1 @@\n-x\n+y\n")


def _run(out: Path, name: str, text: str, *, verdict: str = "READY",
         tree_prior: str = TREE_A, tree_now: str = TREE_A, marker: str = "") -> Path:
    """One synthetic run dir with a single body-only round 1."""
    d = out / name
    (d / "fidelity-packet").mkdir(parents=True)
    (d / "fidelity-packet-2").mkdir(parents=True)
    result = {"fidelity": {"plan_hash": "h" * 64,
                           "rounds": [{"round": 1, "outcome": "body-only", "verdict": verdict}]}}
    (d / "result.json").write_text(json.dumps(result))
    (d / "raw-plan-fidelity-review-attempt0.txt").write_text(text)
    (d / "fidelity-packet" / "context.md").write_text(f"# Context\n\nREVIEWED_TREE: {tree_prior}\n")
    (d / "fidelity-packet" / "pr-body.md").write_text("Summary\nThe test file has 23-case coverage.\n")
    (d / "fidelity-packet-2" / "context.md").write_text(f"# Context\n\nREVIEWED_TREE: {tree_now}\n")
    (d / "fidelity-packet-2" / "pr-body.md").write_text(
        f"Summary\nThe test file has 26-case coverage. {marker}\n")
    (d / "fidelity-packet-2" / "diff.patch").write_text(FOO_DIFF)
    return d


def _offline(tmp_path: Path) -> dict:
    return rbr.replay(str(HARNESS), str(tmp_path / "out"))


def test_replay_offline_classifies_eligible_and_ineligible_rounds(tmp_path):
    out = tmp_path / "out"
    _run(out, "live-synthetic-1", VERDICT1_PURE_BODY)
    _run(out, "live-synthetic-2", VERDICT1_CODE)
    _run(out, "live-synthetic-3", VERDICT1_PURE_BODY, tree_prior=TREE_A, tree_now=TREE_B)
    res = _offline(tmp_path)
    assert res["rounds_total"] == 3
    assert res["eligible"] == 1
    assert res["ineligible_by_reason"]["not-pure-body"] == 1
    assert res["ineligible_by_reason"]["tree-changed"] == 1


def test_replay_offline_lists_every_eligible_not_ready_round(tmp_path):
    out = tmp_path / "out"
    _run(out, "live-synthetic-held", VERDICT1_PURE_BODY, verdict="NOT READY")
    _run(out, "live-synthetic-clean", VERDICT1_PURE_BODY, verdict="READY")
    res = _offline(tmp_path)
    assert res["eligible"] == 2
    assert res["eligible_verdicts"] == {"NOT READY": 1, "READY": 1}
    assert [(r["run"], r["round"]) for r in res["not_ready_eligible"]] == [
        ("live-synthetic-held", 1)]
    assert "live-synthetic-held" in rbr.render(res)


def test_replay_offline_flags_content_heuristic_disagreement_and_coverage(tmp_path):
    out = tmp_path / "out"
    rules_text = VERDICT1_PURE_BODY.replace(
        "`ibl5/tests/FooTest.php` carries 26 `test` methods.",
        "`.claude/rules/foo.md` carries 26 `test` methods.")
    assert rules_text != VERDICT1_PURE_BODY
    _run(out, "live-synthetic-rules", rules_text)
    _run(out, "live-synthetic-classd", VERDICT1_CODE)
    _run(out, "live-synthetic-treechanged", VERDICT1_PURE_BODY, tree_now=TREE_B)
    res = _offline(tmp_path)
    assert res["eligible"] == 1
    assert [r["run"] for r in res["disagreements"]] == ["live-synthetic-rules"]
    # only the tree-changed run is heuristic-pure: the class-d bullet names a code subject
    # and the eligible run's bullet matches too
    assert res["content_heuristic_pure_body"] == 1
    assert res["coverage_given_up"] == 1
    assert [(r["run"], r["reason"]) for r in res["coverage_given_up_rounds"]] == [
        ("live-synthetic-treechanged", "tree-changed")]


def test_replay_rejects_unknown_flag_and_missing_harness_root():
    bogus = subprocess.run([sys.executable, str(SCRIPT), "--bogus"],
                           capture_output=True, text=True)
    assert bogus.returncode == 2
    assert "unrecognized arguments" in bogus.stderr
    missing = subprocess.run([sys.executable, str(SCRIPT)], capture_output=True, text=True)
    assert missing.returncode == 2
    assert "--harness-root" in missing.stderr


class _StubLlm:
    """Stands in for ClaudeCli.call: answers every F-id resolved and every H-id supported."""

    def __init__(self, fail: bool = False):
        self.fail = fail
        self.prompts: list[str] = []
        self.purposes: list[str] = []

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.purposes.append(purpose)
        self.prompts.append(prompt)
        if self.fail:
            raise HarnessError("llm-invalid-output", "")
        data = {
            "findings": [{"id": f"F{i}", "status": "resolved", "evidence": "e"}
                         for i in re.findall(r"^F(\d+):", prompt, re.M)],
            "hunks": [{"id": f"H{i}", "status": "supported", "evidence": "e"}
                      for i in re.findall(r"^H(\d+) \(", prompt, re.M)],
        }
        data = normalizer(data)
        validate(data)
        return data


def _live_main(tmp_path: Path, monkeypatch, stub: _StubLlm, limit: int):
    monkeypatch.setattr(rbr, "make_llm", lambda out_dir: stub)
    json_path = tmp_path / "report.json"
    rc = rbr.main(["--harness-root", str(HARNESS), "--out-dir", str(tmp_path / "out"),
                   "--live", "--limit", str(limit), "--json", str(json_path)])
    return rc, json.loads(json_path.read_text())


def test_replay_live_orders_not_ready_first_and_reports_dangerous(tmp_path, monkeypatch):
    out = tmp_path / "out"
    # name order puts the READY run first, so only the verdict ordering picks the held one
    _run(out, "live-synthetic-a-ready", VERDICT1_PURE_BODY, verdict="READY", marker="MARK-READY")
    _run(out, "live-synthetic-z-held", VERDICT1_PURE_BODY, verdict="NOT READY", marker="MARK-HELD")
    stub = _StubLlm()
    rc, data = _live_main(tmp_path, monkeypatch, stub, limit=1)
    assert len(stub.prompts) == 1
    assert "MARK-HELD" in stub.prompts[0]
    assert stub.purposes == ["plan-fidelity-body-recheck-replay"]
    live = data["live"]
    assert [r["run"] for r in live["dangerous"]] == ["live-synthetic-z-held"]
    assert live["coverage_loss"] == []
    assert rc == 2


def test_replay_live_harness_error_is_would_hold(tmp_path, monkeypatch):
    out = tmp_path / "out"
    _run(out, "live-synthetic-held", VERDICT1_PURE_BODY, verdict="NOT READY")
    _run(out, "live-synthetic-clean", VERDICT1_PURE_BODY, verdict="READY")
    stub = _StubLlm(fail=True)
    rc, data = _live_main(tmp_path, monkeypatch, stub, limit=0)
    live = data["live"]
    assert len(stub.prompts) == 2
    assert {r["decision"] for r in live["rows"]} == {"would_hold"}
    assert live["errors_by_kind"] == {"llm-invalid-output": 2}
    assert live["dangerous"] == []
    assert [r["run"] for r in live["coverage_loss"]] == ["live-synthetic-clean"]
    assert rc == 0
