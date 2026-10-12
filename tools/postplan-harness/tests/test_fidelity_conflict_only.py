"""Conflict-only delta proof: reuse of the cached review and fidelity arms after a rebase.

Pure rows cover `reviewcache.conflict_only_delta`. Runner rows drive the real `run()` wiring
through the replay runner (review arm) and `_run_fidelity` through the live-mode driver from
`test_fidelity_carryforward` (fidelity arm), because replay never reads a prior sticky and so
can never carry a fidelity verdict forward.

The proof reads two helper paths (`gitad.lostwork_pre_path`, `conflict.verdict_ok_path`),
redirected to `tmp_path` here. The conflict manifest path is an f-string in
`runner._conflict_only_proof`, not a helper, so each run uses a unique slug and the manifest
file under /tmp is removed afterwards.
"""
import copy
import dataclasses
import os
import sys
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity, reviewcache
from harness.adapters import gitad
from harness import conflict
from harness.adapters.llm import FixtureLlm
from harness.classify import classify, files_from_diff
from harness.review import ReviewPhase
from harness.state import Finding, TerminalState, UsageLedger
from test_fidelity_carryforward import (CANNED_READY, VERSION, _drive_cf, _record,  # noqa: F401
                                        _sticky, git_shim)
from test_runner_replay import CANNED

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

REVIEW_PURPOSES = ("review-agent-a", "review-agent-b", "review-agent-d",
                   "security-audit", "score-findings")

# ---- pure rows ---------------------------------------------------------------------------

PRE_ID = "a" * 40
NEW_ID = "b" * 40
PLAN = "p" * 64
VER = "v" * 64
TITLE = "fix: conflict only"
PRIOR_IDS = {"a.py": "1" * 40, "b.py": "2" * 40, "c.py": "3" * 40}


def _pure_record():
    key = reviewcache.make_key(diff_id=PRE_ID, plan_hash=PLAN, version=VER, pr_title=TITLE,
                               file_list=sorted(PRIOR_IDS))
    return reviewcache.build_record(
        slug="s", pr_number=7, run_id="live-prior", key=key, per_file_ids=PRIOR_IDS,
        review={"run_id": "live-prior", "gates": {}, "findings": [], "scored_findings": [],
                "degraded_agents": []},
        fidelity={"run_id": "live-prior", "verdict": "READY", "diff_id": PRE_ID,
                  "plan_hash": PLAN, "remediated": False})


def _pure_kwargs(**over):
    key = reviewcache.make_key(diff_id=NEW_ID, plan_hash=PLAN, version=VER, pr_title=TITLE,
                               file_list=sorted(PRIOR_IDS))
    kw = dict(record=_pure_record(), key=key, current_files=dict(PRIOR_IDS, **{"b.py": "9" * 40}),
              pre_patch_id=PRE_ID, manifest_paths=["b.py"], verdict_ok=True)
    kw.update(over)
    return kw


def _key_with(**over):
    return dict(_pure_kwargs()["key"], **over)


def test_conflict_only_true_when_all_clauses_hold():
    kw = _pure_kwargs()
    assert reviewcache.conflict_only_delta(**kw) is True
    assert reviewcache.conflict_only_failure(**kw) == ""


@pytest.mark.parametrize("over,reason", [
    ({"pre_patch_id": "f" * 40}, "pre-patch-id-mismatch"),
    ({"pre_patch_id": ""}, "no-pre-patch-id"),
    ({"current_files": dict(PRIOR_IDS, **{"b.py": "9" * 40, "c.py": "8" * 40})},
     "delta-outside-manifest"),
    ({"manifest_paths": []}, "empty-manifest"),
    ({"verdict_ok": False}, "conflict-review-not-clean"),
    ({"key": _key_with(pr_title=TITLE + "x")}, "pr_title-changed"),
    ({"key": _key_with(plan_hash="q" * 64)}, "plan_hash-changed"),
    ({"key": _key_with(version="w" * 64)}, "version-changed"),
    ({"current_files": dict(PRIOR_IDS)}, "empty-delta"),
], ids=["pre-id-mismatch", "pre-id-empty", "delta-outside-manifest", "empty-manifest",
        "verdict-not-ok", "title-changed", "plan-changed", "version-changed", "empty-delta"])
def test_conflict_only_false_per_missing_clause(over, reason):
    kw = _pure_kwargs(**over)
    assert reviewcache.conflict_only_delta(**kw) is False
    assert reviewcache.conflict_only_failure(**kw) == reason


# ---- runner rows -------------------------------------------------------------------------

def _php_section(path, n_lines, tag=""):
    body = "".join(f"+<?php // line {i}{tag}\n" for i in range(n_lines))
    return (f"diff --git a/{path} b/{path}\nnew file mode 100644\nindex 0000000..1111111\n"
            f"--- /dev/null\n+++ b/{path}\n@@ -0,0 +1,{n_lines} @@\n{body}")


def _ts_section(path, tag=""):
    return (f"diff --git a/{path} b/{path}\nnew file mode 100644\nindex 0000000..2222222\n"
            f"--- /dev/null\n+++ b/{path}\n@@ -0,0 +1,2 @@\n+test('x', () => {{}});\n+// spec{tag}\n")


ALPHA, BETA, SPEC = "ibl5/classes/Alpha.php", "ibl5/classes/Beta.php", "ibl5/tests/e2e/co.spec.ts"
PR = 5151
PLAN_BODY = b"# plan\n"


def _diff(beta_tag="", spec_tag=""):
    return _php_section(ALPHA, 60) + _php_section(BETA, 3, beta_tag) + _ts_section(SPEC, spec_tag)


PRE_DIFF = _diff()
CONFLICT_DIFF = _diff(beta_tag=" resolved")                       # delta = {BETA}
UNRELATED_DIFF = _diff(beta_tag=" resolved", spec_tag=" edited")   # delta = {BETA, SPEC}
PRE_FILES = reviewcache.per_file_patch_ids(PRE_DIFF)
GATES = ReviewPhase(None, None).gates(classify(files_from_diff(CONFLICT_DIFF), CONFLICT_DIFF))


def _finding(path, score, body):
    return dataclasses.asdict(Finding(source="code-review", agent="A", path=path, line=3,
                                      body=body, score=score))


def _review_arm():
    findings = [_finding(ALPHA, 85, "alpha cached"), _finding(BETA, 90, "beta cached")]
    return {"run_id": "live-prior", "gates": dict(GATES), "findings": findings,
            "scored_findings": [{"source": f["source"], "agent": f["agent"], "path": f["path"],
                                 "line": f["line"], "score": f["score"],
                                 "body_head": f["body"][:160]} for f in findings],
            "degraded_agents": []}


@pytest.fixture
def conflict_env(tmp_path, monkeypatch):
    """Unique slug plus tmp_path-redirected pre-patch and verdict-ok paths; manifest in /tmp."""
    slug = "co-" + uuid.uuid4().hex[:10]
    key = runner._conflict_key(slug)
    pre = tmp_path / "pre.patch"
    verdict = tmp_path / "verdict.ok"
    manifest = f"/tmp/postplan-conflict-files-{key}.txt"
    monkeypatch.setattr(gitad, "lostwork_pre_path", lambda k: str(pre))
    monkeypatch.setattr(conflict, "verdict_ok_path", lambda k, sha: str(verdict))
    env = type("Env", (), {})()
    env.slug, env.pre, env.verdict, env.manifest = slug, pre, verdict, manifest

    def arm_proof(*, manifest_paths, verdict_ok=True, pre_diff=PRE_DIFF):
        pre.write_text(pre_diff)
        with open(manifest, "w") as fh:
            fh.write("".join(p + "\n" for p in manifest_paths))
        if verdict_ok:
            verdict.write_text("CONFLICT-REVIEW=CLEAN\n")

    env.arm_proof = arm_proof
    try:
        yield env
    finally:
        try:
            os.unlink(manifest)
        except OSError:
            pass


def _plan_hash():
    import hashlib
    return hashlib.sha256(PLAN_BODY).hexdigest()


def _cache_record(pre_id):
    key = reviewcache.make_key(diff_id=pre_id, plan_hash=_plan_hash(), version=VER, pr_title=TITLE,
                               file_list=sorted(PRE_FILES))
    return reviewcache.build_record(
        slug="s", pr_number=PR, run_id="live-prior", key=key, per_file_ids=PRE_FILES,
        review=_review_arm(),
        fidelity={"run_id": "live-prior", "verdict": "READY", "diff_id": pre_id,
                  "plan_hash": _plan_hash(), "remediated": False})


def _replay(monkeypatch, tmp_path, env, cur_diff, canned):
    """Replay run over `cur_diff` with the pre-rebase record seeded. Returns (res, out, seen)
    where `seen` captures the arguments the runner passed to `_run_fidelity`."""
    cur_files = reviewcache.per_file_patch_ids(cur_diff)
    cur_key = reviewcache.make_key(diff_id=fidelity.diff_patch_id(cur_diff), plan_hash=_plan_hash(),
                                   version=VER, pr_title=TITLE, file_list=sorted(cur_files))
    monkeypatch.setattr(runner, "_review_cache_context", lambda *a, **k: (cur_key, dict(cur_files)))
    seen = {}
    real_run_fidelity = runner._run_fidelity

    def spy(*a, **k):
        seen.update(conflict_only=k.get("conflict_only"), cache_rec=k.get("cache_rec"),
                    cache_version=k.get("cache_version"))
        return real_run_fidelity(*a, **k)

    monkeypatch.setattr(runner, "_run_fidelity", spy)
    state = str(tmp_path / "state")
    reviewcache.save_record(state, env.slug, _cache_record(fidelity.diff_patch_id(PRE_DIFF)))
    fixture = {
        "slug": env.slug, "diff": cur_diff, "pr_number": PR, "head_sha": "c" * 40,
        "pr_meta": {"number": PR, "title": TITLE,
                    "body": "## Manual Testing\n\nNo manual testing needed\n",
                    "headRefOid": "c" * 40},
        "labels": [], "final_state": "OPEN", "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": "# Synthetic plan\n\nBody with no matrix and no frontmatter.\n",
    }
    out = str(tmp_path / "out")
    llm = FixtureLlm(UsageLedger(), canned)
    res = runner.run(fixture, out, llm, mode="replay", headless=True, state_dir=state)
    return res, out, seen


def _purposes(res):
    return [c.purpose for c in res.ledger.calls]


def _fidelity_half(tmp_path, seen, plan_hash_pre):
    """Drive `_run_fidelity` live with the flag the replay run computed. The sticky and the
    record's fidelity arm both name the pre-rebase diff."""
    pre_id = fidelity.diff_patch_id(PRE_DIFF)
    sticky = _sticky(verdict="READY", diff_id=pre_id, plan_hash=plan_hash_pre)
    rec = _record(pre_id, plan_hash_pre, version=VERSION)
    purposes = []
    res, spawns, logs = _drive_cf(tmp_path / "fid", prior_sticky=sticky, plan_body=PLAN_BODY,
                                  canned=CANNED_READY, cache_rec=rec, cache_version=VERSION,
                                  conflict_only=seen["conflict_only"], purposes_out=purposes)
    return res, spawns, logs, purposes


@pytest.mark.usefixtures("git_shim")
def test_conflict_only_run_reuses_fidelity_and_review_without_llm_calls(
        monkeypatch, tmp_path, conflict_env):
    conflict_env.arm_proof(manifest_paths=[BETA])
    res, out, seen = _replay(monkeypatch, tmp_path, conflict_env, CONFLICT_DIFF, CANNED)
    assert res.terminal != TerminalState.FAILED, res.error
    audit = "\n".join(res.audit)
    assert "reviewcache: conflict-only delta proven (1 file(s): " + BETA + ")" in audit
    assert seen["conflict_only"] is True

    # Review arm: restored from the pre-rebase record, no review LLM call.
    assert not set(REVIEW_PURPOSES) & set(_purposes(res))
    assert res.reused_from == {"review": "live-prior"}
    assert res.review_delta == []
    assert "review reused from live-prior (conflict-only delta)" in audit
    assert [f.score for f in res.findings] == [85, 90]

    # Fidelity arm: the runner's own conflict_only flag carries the verdict, no reviewer spawn.
    fres, spawns, logs, fpurposes = _fidelity_half(tmp_path, seen, _plan_hash())
    assert spawns == 0
    assert "plan-fidelity-review" not in fpurposes
    assert fres.fidelity["carried_forward"] is True
    assert fres.fidelity["carry_reason"] == "conflict-only-delta"
    assert any("carried forward" in ln and "(conflict-only delta)" in ln for ln in logs)
    assert fres.reused_from == {"fidelity": "live-prior"}
    assert {**res.reused_from, **fres.reused_from} == {"fidelity": "live-prior", "review": "live-prior"}

    # The sticky audit line names the conflict-only reason.
    body = fidelity.compose_sticky(
        "rebased", "ci", fres.fidelity, None, ["a", "b", "c", "d", "e"], "findings",
        fidelity.terminal_line("READY", None, None, None, None, 0),
        diff_id=fidelity.diff_patch_id(CONFLICT_DIFF), plan_hash=_plan_hash(),
        reused_from={**res.reused_from, **fres.reused_from})
    carried = [ln for ln in body.splitlines() if ln.startswith("**Carried forward:**")]
    assert len(carried) == 1 and "conflict-only delta" in carried[0]


@pytest.mark.usefixtures("git_shim")
def test_conflict_delta_plus_unrelated_edit_runs_full_fidelity(monkeypatch, tmp_path, conflict_env):
    # The manifest lists only BETA, but the delta also touches SPEC.
    conflict_env.arm_proof(manifest_paths=[BETA])
    canned = dict(CANNED, **{"review-agent-a": [{"path": BETA, "line": 1, "body": "beta again"}],
                             "score-findings": [{"n": 1, "score": 85}]})
    res, out, seen = _replay(monkeypatch, tmp_path, conflict_env, UNRELATED_DIFF, canned)
    assert res.terminal != TerminalState.FAILED, res.error
    audit = "\n".join(res.audit)
    assert "conflict-only delta not proven (delta-outside-manifest)" in audit
    assert seen["conflict_only"] is False

    # Review: interdiff mode over both delta files; the unchanged ALPHA finding is reused.
    assert res.review_delta == sorted([BETA, SPEC])
    assert res.reused_from.get("review") == "live-prior"
    assert "review-agent-a" in _purposes(res)
    assert any(f.path == ALPHA and f.body == "alpha cached" for f in res.findings)
    assert not any(f.path == BETA and f.body == "beta cached" for f in res.findings)

    # Fidelity: not a proven conflict-only delta, so the diff-changed decline runs the reviewer.
    fres, spawns, logs, fpurposes = _fidelity_half(tmp_path, seen, _plan_hash())
    assert spawns == 1
    assert "plan-fidelity-review" in fpurposes
    assert not fres.fidelity.get("carried_forward")
    assert "fidelity" not in fres.reused_from
    assert any("carry-forward declined: diff-changed" in ln for ln in logs)
