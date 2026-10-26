"""Shadow-mode plan-fidelity verdict: the typed `## SHADOW-ARMING` block that stands in for
arming conditions (3) and (18) and the body-number checks. Shadow mode only: nothing here may
change the verdict word, the findings parsers, or the arm decision."""
import dataclasses
import hashlib
import inspect
import json
import os
import shutil
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import armable, fidelity, reviewcache, schemas
from harness.state import ArmDecision, ConditionResult, HarnessError, RunResult, TerminalState
from tests.test_runner_replay import CANNED, _pin_verifier_worktree, run_slug  # noqa: F401 (autouse fixture)

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FIXTURES = os.path.join(ROOT, "tests", "fixtures", "fidelity-shadow")
BASELINE = os.path.join(FIXTURES, "verdict-baseline.md")

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")


def _baseline_text() -> str:
    with open(BASELINE) as fh:
        return fh.read()


# Pinned from one run of unmodified master code (Phase 1). Never re-pin to make a test pass.
BASELINE_WORD = "NOT READY"
BASELINE_FINDINGS = [
    '- [BLOCKING] Check 4: the PR body claims a "23-case" test file; `ibl5/tests/FooTest.php` carries 26 `test` methods.',
    '- [NOTE] Check 4: the PR body names the helper `fooBar` while the diff calls it `foo_bar`.',
    '',
    'NOT READY',
    '',
]
BASELINE_BODY_ONLY = [
    '- [BLOCKING] Check 4: the PR body claims a "23-case" test file; `ibl5/tests/FooTest.php` carries 26 `test` methods.',
    '- [NOTE] Check 4: the PR body names the helper `fooBar` while the diff calls it `foo_bar`.',
]


class TestVerdictParsingBaseline:
    def test_baseline_parse_verdict_word(self):
        assert fidelity.parse_verdict(BASELINE) == BASELINE_WORD

    def test_baseline_findings_section(self):
        assert fidelity._findings_section(_baseline_text().splitlines()) == BASELINE_FINDINGS

    def test_baseline_body_only_findings(self):
        assert fidelity.body_only_findings_text(_baseline_text()) == BASELINE_BODY_ONLY

    def test_baseline_digest_is_last(self):
        headings = [ln for ln in _baseline_text().splitlines() if ln.startswith("## ")]
        assert headings[-1] == fidelity.DIGEST_CUT


# Pinned from one run of unmodified master code (Phase 1). `ConditionResult` names the hold
# flag `blocked`. The replay fixtures are gitignored, so these skip where they are absent.
ARM_BASELINE_DOCS_ONLY = (False, [(1, True), (2, False), (3, False), (4, False), (5, False), (6, False), (7, False), (8, False), (9, False), (10, False), (11, False), (12, False), (13, False), (14, False), (15, False), (16, False), (17, False), (18, True)])
ARM_BASELINE_AUTO_MERGE_FALSE = (False, [(1, True), (2, False), (3, True), (4, False), (5, False), (6, False), (7, True), (8, False), (9, True), (10, False), (11, False), (12, False), (13, False), (14, False), (15, False), (16, False), (17, False), (18, True)])


def _arm_snapshot(res):
    return res.arm.armed, sorted((c.number, c.blocked) for c in res.arm.conditions)


class TestArmingBaseline:
    def test_baseline_arm_decision_docs_only(self):
        res, _ = run_slug("backlog-l6-done")
        assert _arm_snapshot(res) == ARM_BASELINE_DOCS_ONLY

    def test_baseline_arm_decision_auto_merge_false(self):
        res, _ = run_slug("request-event-logging")
        assert _arm_snapshot(res) == ARM_BASELINE_AUTO_MERGE_FALSE


# ---- Phase 2: shadow-block validator -------------------------------------------------------

VALID_SHADOW = {
    "schema": "fidelity-shadow/1",
    "planned_landed": {"hold": True, "missing": [
        {"kind": "file", "token": "tools/x.py", "why": "planned in Phase 2, absent from diff"}]},
    "merge_danger": {"door": "one-way", "hold": True, "why": "drops column ibl_x.y with no backfill"},
    "body_numbers": {"consistent": False, "mismatches": [
        {"claim": "adds 4 tests", "actual": "diff adds 3 test functions"}]},
}
CLEAR_SHADOW = {
    "schema": "fidelity-shadow/1",
    "planned_landed": {"hold": False, "missing": []},
    "merge_danger": {"door": "two-way", "hold": False, "why": "additive change only"},
    "body_numbers": {"consistent": True, "mismatches": []},
}
ALL_UNKNOWN = {"3": "unknown", "18": "unknown", "body_numbers": "unknown"}


def shadow_block(obj, raw: str | None = None) -> str:
    payload = raw if raw is not None else json.dumps(obj, indent=2)
    return f"{schemas.FIDELITY_SHADOW_HEADING}\n```json\n{payload}\n```\n"


def verdict_with_block(block: str, base: str | None = None) -> str:
    """The Phase 1 baseline with `block` between the findings and the terminal word."""
    text = base if base is not None else _baseline_text()
    return text.replace("\nNOT READY\n", f"\n{block}\nNOT READY\n", 1)


def _copy(obj):
    return json.loads(json.dumps(obj))


class TestShadowValidator:
    def test_valid_block_accepted(self):
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(VALID_SHADOW)))
        assert rec["status"] == "present"
        assert rec["fields"] == VALID_SHADOW
        assert schemas.shadow_condition_states(rec) == {
            "3": "hold", "18": "hold", "body_numbers": "mismatch"}

    def test_missing_block_is_typed_invalid(self):
        rec = schemas.extract_fidelity_shadow(_baseline_text())
        assert rec == {"status": "invalid:missing-block"}
        assert schemas.shadow_condition_states(rec) == ALL_UNKNOWN

    def test_bad_json_is_typed_invalid(self):
        raw = json.dumps(VALID_SHADOW)[:-1] + ",}"
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(None, raw)))
        assert rec["status"] == "invalid:bad-json"

    def test_duplicate_block_is_typed_invalid(self):
        two = shadow_block(VALID_SHADOW) + "\n" + shadow_block(CLEAR_SHADOW)
        rec = schemas.extract_fidelity_shadow(verdict_with_block(two))
        assert rec["status"] == "invalid:duplicate-block"

    def test_unclosed_fence_is_typed_invalid(self):
        text = (_baseline_text().split(fidelity.DIGEST_CUT)[0]
                + f"{schemas.FIDELITY_SHADOW_HEADING}\n```json\n{json.dumps(VALID_SHADOW)}\n")
        rec = schemas.extract_fidelity_shadow(text)
        assert rec["status"] == "invalid:no-fence"

    def test_hold_flag_contradicts_missing_list_rejected(self):
        bad = _copy(VALID_SHADOW)
        bad["planned_landed"]["hold"] = False
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(bad)))
        assert rec["status"] == "invalid:schema"
        assert "planned_landed.hold" in rec["detail"]

    def test_unknown_door_rejected(self):
        bad = _copy(VALID_SHADOW)
        bad["merge_danger"]["door"] = "maybe"
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(bad)))
        assert rec["status"] == "invalid:schema"
        assert "door" in rec["detail"]

    def test_extra_top_level_key_rejected(self):
        bad = dict(VALID_SHADOW, extra_field=1)
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(bad)))
        assert rec["status"] == "invalid:schema"

    def test_envelope_wrapped_block_unwrapped(self):
        rec = schemas.extract_fidelity_shadow(
            verdict_with_block(shadow_block({"shadow": VALID_SHADOW})))
        assert rec["status"] == "present"
        assert rec["fields"] == VALID_SHADOW

    def test_bare_list_envelope_is_invalid_never_clear(self):
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block([CLEAR_SHADOW])))
        assert rec["status"] == "invalid:schema"
        states = schemas.shadow_condition_states(rec)
        assert states == ALL_UNKNOWN
        assert "clear" not in states.values()

    def test_block_below_digest_ignored(self):
        text = _baseline_text() + "\n" + shadow_block(CLEAR_SHADOW)
        rec = schemas.extract_fidelity_shadow(text)
        assert rec == {"status": "invalid:missing-block"}


# ---- Phase 3: packet inputs ----------------------------------------------------------------

TWO_HUNK_DIFF = ("diff --git a/a.py b/a.py\n--- a/a.py\n+++ b/a.py\n"
                 "@@ -1,2 +1,2 @@\n-old\n+new\n ctx\n"
                 "@@ -10,1 +10,2 @@\n ctx2\n+added\n")


def _packet_plan():
    return types.SimpleNamespace(found=False, path="", auto_merge_false=False)


def _packet_context(tmp_path, diff=TWO_HUNK_DIFF, **kw):
    d = fidelity.build_packet(str(tmp_path / "out"), "deadbeef", "a" * 40, _packet_plan(),
                              diff, "body", 42, False, worktree=str(tmp_path), **kw)
    with open(os.path.join(d, "context.md")) as fh:
        return fh.read()


class TestShadowPacket:
    def test_packet_requests_shadow_when_flag_set(self, tmp_path):
        ctx = _packet_context(tmp_path, shadow_request=True)
        assert fidelity.SHADOW_REQUEST_LINE in ctx
        assert "DIFF_FILES:" in ctx
        assert "a.py +2 -1" in ctx

    def test_packet_default_has_no_shadow_request(self, tmp_path):
        ctx = _packet_context(tmp_path)
        assert fidelity.SHADOW_REQUEST_LINE not in ctx
        assert "DIFF_FILES:" not in ctx

    def test_diff_files_capped(self, tmp_path):
        diff = "".join(f"diff --git a/f{i}.py b/f{i}.py\n--- a/f{i}.py\n+++ b/f{i}.py\n"
                       f"@@ -0,0 +1,1 @@\n+x\n" for i in range(401))
        ctx = _packet_context(tmp_path, diff=diff, shadow_request=True)
        rows = [ln for ln in ctx.splitlines() if ln.startswith("f") and ln.endswith(" +1 -0")]
        assert len(rows) == 400
        assert "(+1 more files)" in ctx

    def test_packet_never_carries_old_conformance(self, tmp_path):
        ctx = _packet_context(tmp_path, shadow_request=True)
        assert "MISSING" not in ctx
        assert "**Merge danger:**" not in ctx

    def test_contract_docs_name_shadow_heading(self):
        repo = os.path.dirname(os.path.dirname(ROOT))
        for rel in (".claude/review-shared/_plan-fidelity-review.md",
                    ".claude/agents/pr-ready-phase6.md"):
            with open(os.path.join(repo, rel)) as fh:
                text = fh.read()
            assert schemas.FIDELITY_SHADOW_HEADING in text, rel
            assert fidelity.SHADOW_REQUEST_LINE in text, rel


# ---- Phase 4: parser invariance and the per-run record -------------------------------------

def _with_valid_block():
    return verdict_with_block(shadow_block(VALID_SHADOW))


class TestShadowParsingInvariance:
    def test_shadow_block_leaves_verdict_word_unchanged(self, tmp_path):
        p = tmp_path / "v.md"
        p.write_text(_with_valid_block())
        assert fidelity.parse_verdict(str(p)) == BASELINE_WORD

    def test_shadow_block_leaves_findings_unchanged(self):
        assert fidelity._findings_section(_with_valid_block().splitlines()) == BASELINE_FINDINGS

    def test_shadow_block_leaves_body_only_findings_unchanged(self):
        assert fidelity.body_only_findings_text(_with_valid_block()) == BASELINE_BODY_ONLY

    def test_findings_excerpt_strips_shadow_block(self, tmp_path):
        p = tmp_path / "v.md"
        p.write_text(_with_valid_block())
        excerpt = fidelity.findings_excerpt(str(p), True)
        assert "[BLOCKING]" in excerpt
        assert "NOT READY" in excerpt
        assert "SHADOW-ARMING" not in excerpt
        assert '"planned_landed"' not in excerpt


class TestShadowRecord:
    def test_read_shadow_present_records_source_and_tree(self, tmp_path):
        p = tmp_path / "v.md"
        p.write_text(_with_valid_block())
        rec = fidelity.read_shadow(str(p), source="verdict-1", reviewed_tree="t" * 40,
                                   plan_blind=False)
        assert rec["status"] == "present"
        assert rec["source"] == "verdict-1"
        assert rec["reviewed_tree"] == "t" * 40
        assert rec["plan_blind"] is False
        assert rec["states"] == {"3": "hold", "18": "hold", "body_numbers": "mismatch"}

    def test_read_shadow_unreadable_file_is_absent(self, tmp_path):
        rec = fidelity.read_shadow(str(tmp_path / "nope.md"), source="verdict-1",
                                   reviewed_tree=None, plan_blind=False)
        assert rec["status"] == "absent:verdict-unreadable"
        assert rec["states"] == ALL_UNKNOWN


def _review_doc(word, block=""):
    return (f"## 6d checks\n\nCheck 3: the diff matches the plan.\n\n{block}\n{word}\n"
            "\n## DIGEST\n\n**What changed:** a synthetic replay change\n")


def _canned(doc):
    return dict(CANNED, **{"plan-fidelity-review": doc})


SHADOW_SLUG = "request-event-logging"


class TestShadowRunnerPaths:
    def test_replay_without_canned_review_is_absent_synthetic(self):
        res, _ = run_slug(SHADOW_SLUG)
        assert res.fidelity["shadow"]["status"] == "absent:replay-synthetic"

    def test_canned_review_with_valid_block_is_present(self):
        res, _ = run_slug(SHADOW_SLUG, _canned(_review_doc("READY", shadow_block(VALID_SHADOW))))
        shadow = res.fidelity["shadow"]
        assert shadow["status"] == "present"
        assert shadow["source"] == "verdict-1"
        assert shadow["states"] == {"3": "hold", "18": "hold", "body_numbers": "mismatch"}

    def test_canned_review_with_bad_json_is_invalid_and_verdict_unchanged(self):
        good, _ = run_slug(SHADOW_SLUG, _canned(_review_doc("READY", shadow_block(VALID_SHADOW))))
        raw = json.dumps(VALID_SHADOW)[:-1] + ",}"
        bad, _ = run_slug(SHADOW_SLUG, _canned(_review_doc("READY", shadow_block(None, raw))))
        assert bad.fidelity["shadow"]["status"] == "invalid:bad-json"
        assert bad.fidelity["verdict_1"] == good.fidelity["verdict_1"]

    def test_packet_error_is_absent_packet_error(self, monkeypatch):
        def _boom(*a, **kw):
            raise HarnessError("fidelity-procedure-missing", "x")

        monkeypatch.setattr(runner.fidelity, "build_packet", _boom)
        res, _ = run_slug(SHADOW_SLUG, _canned(_review_doc("READY")))
        assert res.fidelity["shadow"]["status"] == "absent:packet-error:fidelity-procedure-missing"

    def test_body_recheck_round_keeps_prior_shadow(self, tmp_path, rounds_git_shim):
        from test_fidelity_body_recheck import VERDICT1_PURE_BODY, _drive_body_only, _pass_all
        from test_fidelity_rounds import _cleanup
        try:
            res, llm = _drive_body_only(tmp_path, 9961, VERDICT1_PURE_BODY,
                                        {"plan-fidelity-body-recheck-2": [_pass_all]})
            assert ("plan-fidelity-body-recheck-2", "sonnet") in llm.calls
            assert res.fidelity["shadow"]["source"] == "verdict-1"
        finally:
            _cleanup(9961, "9961-2")


@pytest.fixture()
def rounds_git_shim(tmp_path, monkeypatch):
    """The `git` shim from test_fidelity_rounds, under a name that cannot clash."""
    from test_fidelity_rounds import GIT_SHIM
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    return monkeypatch


@pytest.fixture()
def cf_git_shim(tmp_path, monkeypatch):
    """The carry-forward `git` shim: `show` is stubbed, `patch-id` reaches the real git."""
    real_git = shutil.which("git")
    shim_bin = tmp_path / "shimbin"
    shim_bin.mkdir()
    shim = shim_bin / "git"
    shim.write_text(
        "#!/usr/bin/env bash\n"
        'if [ "$1" = "show" ]; then\n  echo "PROCEDURE BODY"\n  exit 0\nfi\n'
        f'if [ "$1" = "patch-id" ]; then\n  exec "{real_git}" "$@"\nfi\n'
        "exit 0\n")
    shim.chmod(shim.stat().st_mode | stat.S_IEXEC | stat.S_IXGRP | stat.S_IXOTH)
    monkeypatch.setenv("PATH", str(shim_bin) + ":" + os.environ.get("PATH", ""))
    return str(shim_bin)


# ---- Phase 5: persistence ------------------------------------------------------------------

def _arm(cond3_blocked, cond18_blocked):
    return ArmDecision(armed=False, conditions=[
        ConditionResult(number=18, name="merge-danger", blocked=cond18_blocked),
        ConditionResult(number=3, name="conformance", blocked=cond3_blocked),
    ])


def _finished(tmp_path, res):
    runner._finish(res, str(tmp_path))
    with open(tmp_path / "result.json") as fh:
        return json.load(fh)


def _present_record():
    rec = schemas.extract_fidelity_shadow(_with_valid_block())
    rec["states"] = schemas.shadow_condition_states(rec)
    return rec


class TestShadowPersistence:
    def test_finish_writes_not_reached_when_phase55_skipped(self, tmp_path):
        blob = _finished(tmp_path, RunResult(terminal=TerminalState.FAILED))
        assert blob["fidelity"]["shadow"]["status"] == "absent:not-reached"

    def test_finish_attaches_old_outcomes_from_arm(self, tmp_path):
        res = RunResult(terminal=TerminalState.SHIPPED_HELD, arm=_arm(True, False))
        blob = _finished(tmp_path, res)
        assert blob["fidelity"]["shadow"]["old"] == {"3": "hold", "18": "clear"}

    def test_finish_old_outcomes_not_evaluated_without_arm(self, tmp_path):
        res = RunResult(terminal=TerminalState.FAILED)
        res.arm = None
        blob = _finished(tmp_path, res)
        assert blob["fidelity"]["shadow"]["old"] == {"3": "not-evaluated", "18": "not-evaluated"}

    def test_finish_does_not_mutate_live_result(self, tmp_path):
        res = RunResult(terminal=TerminalState.SHIPPED_HELD, arm=_arm(True, True),
                        fidelity={"shadow": _present_record()})
        before = json.dumps(res.fidelity, sort_keys=True)
        _finished(tmp_path, res)
        assert "old" not in res.fidelity["shadow"]
        assert json.dumps(res.fidelity, sort_keys=True) == before

    def test_audit_log_carries_shadow_line(self, tmp_path):
        res = RunResult(terminal=TerminalState.SHIPPED_HELD, arm=_arm(True, False),
                        fidelity={"shadow": _present_record()})
        runner._finish(res, str(tmp_path))
        audit = (tmp_path / "audit.log").read_text()
        assert "phase5.5 shadow: status=present cond3=hold/" in audit
        assert "cond3=hold/hold cond18=hold/clear body_numbers=mismatch" in audit

    def _fid(self, shadow):
        return {"verdict_1": "READY", "verdict_2": None, "diff_id": "d" * 40,
                "plan_hash": "p" * 64, "remediation_sha": None, "shadow": shadow}

    def test_vet_fidelity_caches_present_shadow(self):
        rec = reviewcache.vet_fidelity(self._fid(_present_record()), "run-1")
        assert rec is not None
        assert rec["shadow"]["status"] == "present"

    def test_vet_fidelity_skips_invalid_shadow(self):
        rec = reviewcache.vet_fidelity(self._fid({"status": "invalid:bad-json"}), "run-1")
        assert rec is not None
        assert "shadow" not in rec

    def _drive_carried(self, tmp_path, cached_shadow):
        from test_fidelity_carryforward import DIFF, _cache, _drive_cf, _sticky
        plan_body = b"# plan\n"
        plan_hash = hashlib.sha256(plan_body).hexdigest()
        diff_id = fidelity.diff_patch_id(DIFF)
        assert diff_id, "git shim must pass patch-id through to real git"
        sticky = _sticky(verdict="READY", diff_id=diff_id, plan_hash=plan_hash)
        cache = _cache(plan_body)
        if cached_shadow is not None:
            cache["cache_rec"]["fidelity"]["shadow"] = cached_shadow
        res, spawns, _ = _drive_cf(tmp_path, prior_sticky=sticky, plan_body=plan_body, **cache)
        assert spawns == 0
        assert res.fidelity.get("carried_forward") is True
        return res

    def test_carried_run_copies_cached_shadow(self, tmp_path, cf_git_shim):
        res = self._drive_carried(tmp_path, _present_record())
        shadow = res.fidelity["shadow"]
        assert shadow["status"] == "carried"
        assert shadow["carried_from"]
        assert shadow["states"] == {"3": "hold", "18": "hold", "body_numbers": "mismatch"}

    def test_carried_run_without_cached_shadow_is_absent(self, tmp_path, cf_git_shim):
        res = self._drive_carried(tmp_path, None)
        assert res.fidelity["shadow"]["status"] == "absent:carried-no-shadow"
        assert res.fidelity["shadow"]["states"] == ALL_UNKNOWN


# ---- Arming is unchanged -------------------------------------------------------------------

HOLD_BOTH = {
    "schema": "fidelity-shadow/1",
    "planned_landed": {"hold": True, "missing": [
        {"kind": "file", "token": "tools/x.py", "why": "planned, absent from diff"}]},
    "merge_danger": {"door": "one-way", "hold": True, "why": "drops a column"},
    "body_numbers": {"consistent": True, "mismatches": []},
}


class TestArmingUnchanged:
    @pytest.mark.parametrize("slug", ["backlog-l6-done", "request-event-logging"])
    def test_shadow_hold_does_not_change_arm_decision(self, slug):
        plain, _ = run_slug(slug, _canned(_review_doc("READY")))
        held, _ = run_slug(slug, _canned(_review_doc("READY", shadow_block(HOLD_BOTH))))
        assert held.fidelity["shadow"]["status"] == "present"
        assert held.fidelity["shadow"]["states"]["3"] == "hold"
        assert _arm_snapshot(plain) == _arm_snapshot(held)

    @pytest.mark.parametrize("slug", ["backlog-l6-done", "request-event-logging"])
    def test_invalid_shadow_does_not_change_arm_decision(self, slug):
        plain, _ = run_slug(slug, _canned(_review_doc("READY")))
        raw = json.dumps(HOLD_BOTH)[:-1] + ",}"
        bad, _ = run_slug(slug, _canned(_review_doc("READY", shadow_block(None, raw))))
        assert bad.fidelity["shadow"]["status"] == "invalid:bad-json"
        assert _arm_snapshot(plain) == _arm_snapshot(bad)

    def test_arm_inputs_has_no_shadow_field(self):
        assert not [f.name for f in dataclasses.fields(armable.ArmInputs) if "shadow" in f.name]
        assert "shadow" not in inspect.getsource(armable)


# ---- Phase 6: seeded false-arm cases ----------------------------------------------------

SEEDED = os.path.join(FIXTURES, "seeded")

_SEEDED_FILES = {"plan.md", "diff.patch", "pr-body.md", "expect.json"}
_SEEDED_ALLOWED = {
    "3": {"hold", "clear"},
    "18": {"hold", "clear"},
    "body_numbers": {"consistent", "mismatch"},
}


def _seeded_cases():
    return sorted(d for d in os.listdir(SEEDED) if os.path.isdir(os.path.join(SEEDED, d)))


def _seeded_read(case, name):
    with open(os.path.join(SEEDED, case, name), encoding="utf-8") as fh:
        return fh.read()


def _seeded_expect(case):
    return json.loads(_seeded_read(case, "expect.json"))["expect"]


def _seeded_door(case):
    """Old mechanical merge-danger derivation, same call shape as test_merge_danger_fixtures."""
    from harness import merge_danger as md
    from harness.gate_backtest import changes_from_unified_diff

    text = _seeded_read(case, "diff.patch")
    changed = changes_from_unified_diff(text)
    return md.derive_merge_danger(changed, text, [], lambda _path: None)


class TestSeededCases:
    def test_seeded_cases_well_formed(self):
        cases = _seeded_cases()
        assert len(cases) == 7
        for case in cases:
            assert set(os.listdir(os.path.join(SEEDED, case))) == _SEEDED_FILES, case
            data = json.loads(_seeded_read(case, "expect.json"))
            assert data["case"] == case
            assert isinstance(data["why"], str) and data["why"]
            assert data["expect"], case
            for key, value in data["expect"].items():
                assert key in _SEEDED_ALLOWED, (case, key)
                assert value in _SEEDED_ALLOWED[key], (case, key, value)

    def test_seeded_cases_cover_each_condition(self):
        expects = [_seeded_expect(c) for c in _seeded_cases()]
        assert any(e.get("3") == "hold" for e in expects)
        assert any(e.get("18") == "hold" for e in expects)
        assert any(e.get("body_numbers") == "mismatch" for e in expects)
        clears = sum(1 for e in expects for k in ("3", "18") if e.get(k) == "clear")
        assert clears >= 2

    def test_seeded_one_way_case_is_one_way_in_old_code(self):
        d = _seeded_door("one-way-migration")
        assert d.door == "one-way"
        assert any(t.startswith("destructive-migration:ibl5/migrations/") for t in d.triggers)

    def test_seeded_sql_comment_control_is_two_way_in_old_code(self):
        d = _seeded_door("sql-comment-only")
        assert d.door == "two-way"
        assert d.triggers == ()

    def test_seeded_packet_builds_with_shadow_request(self, tmp_path):
        repo_root = os.path.dirname(os.path.dirname(ROOT))
        for case in _seeded_cases():
            plan_stub = types.SimpleNamespace(found=True, path=os.path.join(SEEDED, case, "plan.md"))
            out = tmp_path / case
            out.mkdir()
            packet = fidelity.build_packet(
                str(out), "HEAD", "0" * 40, plan_stub,
                _seeded_read(case, "diff.patch"), _seeded_read(case, "pr-body.md"),
                "seed", False, worktree=repo_root, shadow_request=True)
            with open(os.path.join(packet, "context.md"), encoding="utf-8") as fh:
                assert "SHADOW_ARMING: requested" in fh.read(), case
