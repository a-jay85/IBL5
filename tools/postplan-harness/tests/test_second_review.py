"""The blind second-reviewer seat: its prompt, its verdict, its tree binding, its wiring."""
import os
import re
import types

import pytest

import runner
from harness import fidelity, gate_backtest, second_review
from harness.armable import evaluate
from harness.state import Classification

from tests.test_armable import inputs

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))
TREE_A = "a" * 40
TREE_B = "b" * 40
PR = 990001
PLAN_HOLD = dict(plan_auto_merge_false=True, plan_auto_merge_value="false",
                 plan_hold_triggers=("gate-weakening",))
CLEAN_REPLY = "CLEARS 7: the diff only adds a read-only tool\nVERDICT: CLEAN\n"


@pytest.fixture(autouse=True)
def _private_verdict_file(tmp_path, monkeypatch):
    """Parallel workers must not share the /tmp verdict file."""
    monkeypatch.setattr(second_review, "verdict_path", lambda pr: str(tmp_path / f"seat-{pr}.md"))


def _write(tmp_path, text, name="verdict.md"):
    path = tmp_path / name
    path.write_text(text)
    return str(path)


def _outside_fence(prompt):
    return prompt.split("## Diff", 1)[0]


# ---- prompt


def test_prompt_has_no_fixer_framing():
    reason = second_review.reason_text(
        None, "chore: tidy the gate",
        "## Summary\nAddressed finding 2 in fix round 1\nTidies the gate.\n")
    holds = {7: "plan frontmatter sets auto_merge: false"}
    prompt = second_review.build_prompt("diff --git a/x b/x\n+addressed\n", reason, holds)
    head = _outside_fence(prompt)
    assert not second_review.FIXER_FRAMING_RE.search(head)
    assert "postplan-fidelity" not in head
    assert fidelity.verdict_path(PR) not in prompt
    assert "(7) plan-auto-merge-hold" in head


def test_prompt_scrubs_framing_from_hold_reason():
    prompt = second_review.build_prompt("d\n", "r", {12: "NOT READY: finding 3 addressed"})
    assert "(12) plan-fidelity-verdict" in prompt
    assert "addressed" not in prompt


def test_prompt_drops_addressed_lines_from_reason():
    plan = "# P\n\n## Approach\nKeep this line.\nAddressed in a later fix round.\nKeep that line.\n\n## Next\nno\n"
    reason = second_review.reason_text(plan, "chore: title", None)
    lines = reason.splitlines()
    assert "chore: title" in lines
    assert "Keep this line." in lines
    assert "Keep that line." in lines
    assert not any("Addressed" in ln for ln in lines)
    assert "no" not in lines


def test_prompt_over_budget_returns_none():
    big = "x" * (fidelity.REMEDIATION_DIFF_INLINE_CAP + 1)
    assert second_review.build_prompt(big, "r", {7: "h"}) is None


def test_prompt_fence_outlasts_backticks_in_diff():
    prompt = second_review.build_prompt("+````\n", "r", {7: "h"})
    assert "`````diff\n" in prompt


def test_spawn_argv_has_no_resume_or_continue():
    seen = []

    class Llm:
        def call_tooled(self, purpose, model, prompt, **kw):
            seen.append((purpose, model, kw))
            return "VERDICT: REJECT no"

    second_review.spawner(Llm(), "/wt")(agent=second_review.AGENT, prompt="p")
    purpose, model, kw = seen[0]
    assert (purpose, model) == ("second-review", "opus")
    assert kw["agent"] == "pr-second-reviewer"
    assert not {"resume", "continue", "continue_session", "session", "session_id"} & set(kw)
    assert "Bash" in kw["denied_tools"] and "Agent" in kw["denied_tools"]
    assert "Bash" not in kw["allowed_tools"]


def test_agent_def_pins_opus_and_has_no_fixer_framing():
    with open(os.path.join(REPO, ".claude/agents/pr-second-reviewer.md")) as fh:
        text = fh.read()
    head, body = text.split("---\n", 2)[1:]
    assert "model: claude-opus-5-5" in head
    denied = re.search(r"^disallowedTools: (.*)$", head, re.M).group(1).split(", ")
    assert {"Agent", "Edit", "Write", "NotebookEdit", "Skill"} <= set(denied)
    # The body names the blocking-findings block, so it is held to the history-only pattern.
    assert not second_review._HISTORY_RE.search(body)
    assert not second_review.FIXER_FRAMING_RE.search(head)


# ---- verdict parsing


def test_parse_verdict_clean_and_clears(tmp_path):
    path = _write(tmp_path, "some notes\nCLEARS 7: safe because tooling only\n"
                            "CLEARS 17: the replay held on a stale path\nVERDICT: CLEAN\n")
    word, clears = second_review.parse_verdict(path)
    assert word == "CLEAN"
    assert clears == {7: "safe because tooling only", 17: "the replay held on a stale path"}


def test_parse_verdict_ignores_recorded_tree_line(tmp_path):
    path = _write(tmp_path, f"CLEARS 7: safe because tooling only\nVERDICT: CLEAN\nREVIEWED_TREE={TREE_A}\n")
    assert second_review.parse_verdict(path)[0] == "CLEAN"


@pytest.mark.parametrize("text", [
    "VERDICT: CLEAN-ish\n",
    "verdict: clean\n",
    "VERDICT: CLEAN and more\n",
    "VERDICT: CLEAN\nbut on reflection, no\n",
    "VERDICT: REJECTED\n",
    "",
])
def test_parse_verdict_near_miss_returns_none(tmp_path, text):
    assert second_review.parse_verdict(_write(tmp_path, text)) == (None, {})


def test_parse_verdict_near_miss_clears_line_is_not_a_clear(tmp_path):
    path = _write(tmp_path, "CLEARS 7 and 17: ok, this is plenty long\nCLEARS 7: short\n"
                            "CLEARS 18: the merge rule is fine here\nVERDICT: CLEAN\n")
    assert second_review.parse_verdict(path) == ("CLEAN", {})


def test_parse_verdict_missing_file_returns_none(tmp_path):
    assert second_review.parse_verdict(str(tmp_path / "nope.md")) == (None, {})


def test_reject_reason_reads_the_verdict_text(tmp_path):
    assert second_review.reject_reason(_write(tmp_path, "VERDICT: REJECT breaks the digest\n")) \
        == "breaks the digest"
    assert second_review.reject_reason(_write(tmp_path, "VERDICT: CLEAN\n")) == ""


# ---- run_seat


def _seat(spawn, tree=lambda: TREE_A, diff=lambda: "diff\n"):
    return second_review.run_seat(PR, tree, diff, {7: "hold"}, "reason", spawn)


def test_seat_failure_returns_no_verdict():
    def boom(**_):
        raise RuntimeError("spawn died")
    assert _seat(boom) == (None, None, {})


def test_seat_failure_leaves_no_old_verdict_behind():
    path = second_review.verdict_path(PR)
    with open(path, "w") as fh:
        fh.write(f"VERDICT: CLEAN\nREVIEWED_TREE={TREE_A}\n")

    def boom(**_):
        raise RuntimeError("spawn died")
    _seat(boom)
    assert second_review.load_fresh(PR, TREE_A) is None


def test_seat_usage_pause_propagates():
    class Pause(BaseException):
        pass

    def pause(**_):
        raise Pause()
    with pytest.raises(Pause):
        _seat(pause)


def test_seat_malformed_reply_returns_no_verdict():
    assert _seat(lambda **_: "looks fine to me") == (None, None, {})


def test_seat_over_budget_diff_never_spawns():
    calls = []
    big = "x" * (fidelity.REMEDIATION_DIFF_INLINE_CAP + 1)
    result = _seat(lambda **kw: calls.append(kw), diff=lambda: big)
    assert result == (None, None, {})
    assert calls == []


def test_seat_ignores_a_forged_tree_line_in_the_reply():
    word, tree, _ = _seat(lambda **_: f"{CLEAN_REPLY}REVIEWED_TREE={TREE_B}\n")
    assert (word, tree) == ("CLEAN", TREE_A)
    assert fidelity.read_reviewed_tree(second_review.verdict_path(PR)) == TREE_A


# ---- runner pass


def _pass(inp, spawn, head=lambda: TREE_A, plan=None, diff=lambda: "diff\n"):
    return second_review.review_pass(inp, PR, head, diff, plan, lambda _m: None, spawn)


def test_runner_seats_on_pre_spawn_tree():
    state = {"tree": TREE_A}

    def spawn(**_):
        state["tree"] = TREE_B
        return CLEAN_REPLY
    inp = inputs(**PLAN_HOLD, current_tree=TREE_A)
    out = _pass(inp, spawn, head=lambda: state["tree"])
    assert out.second_review_tree == TREE_A
    assert fidelity.read_reviewed_tree(second_review.verdict_path(PR)) == TREE_A
    decision = evaluate(out)
    assert not decision.armed
    assert "stale tree" in decision.holds[0].reason


def test_later_push_makes_verdict_stale():
    calls = []
    inp = inputs(**PLAN_HOLD, current_tree=TREE_A)

    def spawn(**kw):
        calls.append(kw)
        return CLEAN_REPLY
    out = _pass(inp, spawn)
    assert evaluate(out).armed

    # The tree moves. The seat cannot run again here, so the old verdict alone decides.
    assert second_review.load_fresh(PR, TREE_B) is None
    moved = out.__class__(**{**out.__dict__, "current_tree": TREE_B})
    decision = evaluate(moved)
    assert not decision.armed
    assert "stale tree" in decision.holds[0].reason


def test_seat_reused_on_same_tree():
    calls = []

    def spawn(**kw):
        calls.append(kw)
        return CLEAN_REPLY
    inp = inputs(**PLAN_HOLD, current_tree=TREE_A)
    first = _pass(inp, spawn)
    second = _pass(inp, spawn)
    assert len(calls) == 1
    assert second.second_review_verdict == first.second_review_verdict == "CLEAN"
    assert evaluate(second).armed


def test_no_spawn_when_no_candidates():
    calls = []
    inp = inputs(**PLAN_HOLD, current_tree=TREE_A, failed_checks=["unit-tests"])
    assert _pass(inp, lambda **kw: calls.append(kw)) is inp
    assert calls == []


def test_no_spawn_when_nothing_blocks():
    calls = []
    inp = inputs(current_tree=TREE_A)
    assert _pass(inp, lambda **kw: calls.append(kw)) is inp
    assert calls == []


def test_runner_pass_carries_reject_reason():
    inp = inputs(**PLAN_HOLD, current_tree=TREE_A)
    out = _pass(inp, lambda **_: "VERDICT: REJECT the digest loses a row\n")
    decision = evaluate(out)
    assert not decision.armed
    assert "second reviewer: REJECT the digest loses a row" in decision.holds[0].reason


def test_runner_pass_feeds_plan_approach_to_the_seat(tmp_path):
    plan_path = tmp_path / "plan.md"
    plan_path.write_text("# P\n\n## Approach\nStated approach line.\n")
    plan = types.SimpleNamespace(found=True, path=str(plan_path))
    prompts = []

    def spawn(agent, prompt):
        prompts.append(prompt)
        return CLEAN_REPLY
    _pass(inputs(**PLAN_HOLD, current_tree=TREE_A), spawn, plan=plan)
    assert "Stated approach line." in prompts[0]


# ---- _ARMING_PATHS and the fidelity tag


def test_arming_paths_cover_seat_files():
    assert "tools/postplan-harness/harness/second_review.py" in gate_backtest._ARMING_PATHS
    assert ".claude/agents/pr-second-reviewer.md" in gate_backtest._ARMING_PATHS
    # The code that decides what the seat may clear and feeds it data.
    assert "tools/postplan-harness/harness/fidelity.py" in gate_backtest._ARMING_PATHS
    assert "tools/postplan-harness/harness/planfile.py" in gate_backtest._ARMING_PATHS


def test_runner_keeps_no_seat_glue():
    """runner.py is not an arming path, so the seat's feed must live in second_review.py."""
    src = open(os.path.join(REPO, "tools/postplan-harness/runner.py")).read()
    for name in ("def _second_review_pass", "def _second_review_spawner",
                 "def _selected_gate_edit_only", "def _selected_fidelity_path",
                 "second_review_verdict=", "second_review_clears="):
        assert name not in src, name


def test_runner_calls_seat_before_each_preview():
    """Both arming paths run the seat pass and then the preview, in that order."""
    src = open(os.path.join(REPO, "tools/postplan-harness/runner.py")).read()
    pass_at = [m.start() for m in re.finditer(r"second_review\.review_pass\(", src)]
    assert len(pass_at) == 2
    for start in pass_at:
        tail = src[start:start + 400]
        assert re.search(r"preview = evaluate\((inputs|new)\)", tail), tail
    assert src.count("fidelity_gate_edit_only=second_review.selected_gate_edit_only(") == 2


def test_arming_paths_have_no_phantom_entry():
    missing = [p for p in gate_backtest._ARMING_PATHS if not os.path.exists(os.path.join(REPO, p))]
    assert missing == []


def _finding(*tags):
    return "## FINDINGS\n\n- [BLOCKING] " + " ".join(tags) + " edits a ship gate\n"


def test_gate_edit_only_requires_every_finding_tagged(tmp_path):
    one = _write(tmp_path, f"{_finding('[GATE-EDIT]')}\nNOT READY\n", "one.md")
    assert fidelity.gate_edit_only(one) is True
    mixed = _write(tmp_path, "## FINDINGS\n\n- [BLOCKING] [GATE-EDIT] edits a gate\n"
                             "- [BLOCKING] edits something else\n\nNOT READY\n", "mixed.md")
    assert fidelity.gate_edit_only(mixed) is False
    assert fidelity.gate_edit_only(_write(tmp_path, "", "empty.md")) is False
    assert fidelity.gate_edit_only(str(tmp_path / "missing.md")) is False


def test_gate_edit_mentioned_in_prose_is_not_gate_edit(tmp_path):
    prose = _write(tmp_path, "## FINDINGS\n\n- [BLOCKING] routes [GATE-EDIT] findings wrongly\n\n"
                             "NOT READY\n", "prose.md")
    assert fidelity.gate_edit_only(prose) is False
    scoped = _write(tmp_path, "## FINDINGS\n\n- [BLOCKING] [unplanned-scope] [GATE-EDIT] edits\n\n"
                              "NOT READY\n", "scoped.md")
    assert fidelity.gate_edit_only(scoped) is True


def test_merge_rule_tag_is_not_gate_edit(tmp_path):
    both = _write(tmp_path, f"{_finding('[GATE-EDIT]', '[MERGE-RULE]')}\nNOT READY\n", "both.md")
    assert fidelity.gate_edit_only(both) is False
    one_way = _write(tmp_path, f"{_finding('[GATE-EDIT]', '[ONE-WAY]')}\nNOT READY\n", "oneway.md")
    assert fidelity.gate_edit_only(one_way) is False
    plain = _write(tmp_path, f"{_finding('[MERGE-RULE]')}\nNOT READY\n", "plain.md")
    assert fidelity.gate_edit_only(plain) is False


def test_gate_edit_only_reads_the_verdict_condition_12_selects(tmp_path, monkeypatch):
    gate = _write(tmp_path, f"{_finding('[GATE-EDIT]')}\nNOT READY\n", "v2.md")
    rule = _write(tmp_path, f"{_finding('[MERGE-RULE]')}\nNOT READY\n", "v1.md")
    monkeypatch.setattr(fidelity, "verdict_path", lambda _pr: rule)
    fid = {"verdict_1": "NOT READY", "verdict_2": "NOT READY", "reviewed_tree_2": TREE_A,
           "verdict_path": gate}
    assert second_review.selected_gate_edit_only(fid, PR, TREE_A) is True
    # Verdict 2 went stale, so condition (12) falls back to verdict 1 and its owner tag.
    assert second_review.selected_gate_edit_only(fid, PR, TREE_B) is False
    repin = {**fid, "same_run_repin": {"verdict_path": rule}}
    assert second_review.selected_gate_edit_only(repin, PR, TREE_A) is False


# ---- digest and hold reasons


def test_digest_carries_clear_reason():
    inp = inputs(**PLAN_HOLD, current_tree=TREE_A, second_review_verdict="CLEAN",
                 second_review_tree=TREE_A,
                 second_review_clears={7: "the diff only adds a read-only tool"})
    lines = second_review.digest_clear_lines(evaluate(inp))
    assert len(lines) == 1
    assert lines[0].startswith("Second reviewer cleared (7)")
    assert second_review.CONDITION_NAMES[7] in lines[0]
    assert "the diff only adds a read-only tool" in lines[0]


def test_digest_empty_when_nothing_cleared():
    assert second_review.digest_clear_lines(evaluate(inputs(**PLAN_HOLD))) == []


def test_hold_reason_names_second_reviewer_reject():
    inp = inputs(**PLAN_HOLD, current_tree=TREE_A, second_review_verdict="REJECT",
                 second_review_tree=TREE_A, second_review_reject_reason="breaks the digest")
    cond = next(c for c in evaluate(inp).holds if c.number == 7)
    assert "second reviewer: REJECT" in cond.reason
    assert "breaks the digest" in cond.reason


def test_condition_names_match_the_armable_conditions():
    classification = Classification(files=["bin/check-prose"])
    decision = evaluate(inputs(plan_auto_merge_false=True, plan_auto_merge_value="false",
                               pr_labels=["pipeline-authored"], classification=classification,
                               fidelity_verdict="NOT READY", gate_backtest_status="HELD"))
    names = {c.number: c.name for c in decision.holds}
    assert {n: second_review.CONDITION_NAMES[n] for n in names} == names


def test_gate_edit_only_counts_unmarked_bullet_under_not_ready(tmp_path):
    mixed = _write(tmp_path, "## FINDINGS\n\n- [BLOCKING] [GATE-EDIT] edits a gate\n"
                             "- Phase 3 has no diff footprint\n\nNOT READY\n", "unmarked.md")
    assert fidelity.gate_edit_only(mixed) is False
    tagged = _write(tmp_path, "## FINDINGS\n\n- [BLOCKING] [GATE-EDIT] edits a gate\n"
                              "- [GATE-EDIT] edits another gate\n\nNOT READY\n", "tagged.md")
    assert fidelity.gate_edit_only(tagged) is True


# ---- golden prompt bytes (captured before the owner-request and findings blocks existed)

GOLDEN = os.path.join(os.path.dirname(__file__), "fixtures", "second_review_prompt_golden.txt")
GOLDEN_TITLE = "chore: tighten the seat"
GOLDEN_BODY = ("## Summary\n\nAdds a read-only tool to the seat.\n"
               "Addressed in fix round 2.\n\n## Test plan\n\n- [x] ran it\n")
GOLDEN_DIFF = ("diff --git a/x.py b/x.py\n--- a/x.py\n+++ b/x.py\n@@ -1 +1 @@\n"
               "-old = 1\n+new = '``'\n")
GOLDEN_HOLDS = {7: "plan auto_merge false", 12: "plan-fidelity finding NOT READY"}


def _golden_prompt():
    return second_review.build_prompt(
        GOLDEN_DIFF, second_review.reason_text(None, GOLDEN_TITLE, GOLDEN_BODY), GOLDEN_HOLDS)


def _golden():
    with open(GOLDEN, encoding="utf-8") as fh:
        return fh.read()


def test_prompt_matches_pre_change_golden():
    assert _golden_prompt() == _golden()


# ---- owner request and blocking findings in the seat prompt

OWNER_BLOCK = "## Owner request\n\nMessage at T:\n\n```text\nplease fix the seat\n```"
FINDING = "[BLOCKING] Phase 3 drops the hold-notice check"


@pytest.fixture
def _no_intent_home(tmp_path, monkeypatch):
    home = tmp_path / "home"
    home.mkdir()
    monkeypatch.setenv("HOME", str(home))
    return home


def _seat_prompts(monkeypatch, inp, holds):
    """Run review_pass with `holds` as the candidates; return the prompts the seat got."""
    monkeypatch.setattr(second_review.armable, "evaluate",
                        lambda _inp: types.SimpleNamespace(second_review_candidates=dict(holds)))
    prompts = []

    def spawn(agent, prompt):
        prompts.append(prompt)
        return CLEAN_REPLY
    second_review.review_pass(inp, PR, lambda: TREE_A, lambda: GOLDEN_DIFF, None,
                              lambda _m: None, spawn)
    return prompts


def test_reason_owner_request_plan_blind_only():
    blind = second_review.reason_text(None, GOLDEN_TITLE, GOLDEN_BODY, OWNER_BLOCK)
    assert blind.endswith("\n\n" + OWNER_BLOCK)
    assert "please fix the seat" in blind          # owner text skips the framing filter
    plan = "# P\n\n## Approach\nStated approach line.\n"
    driven = second_review.reason_text(plan, GOLDEN_TITLE, GOLDEN_BODY, OWNER_BLOCK)
    assert "## Owner request" not in driven
    assert driven == second_review.reason_text(plan, GOLDEN_TITLE, GOLDEN_BODY)
    assert second_review.reason_text(None, GOLDEN_TITLE, GOLDEN_BODY, "") == \
        second_review.reason_text(None, GOLDEN_TITLE, GOLDEN_BODY)


def test_missing_intent_prompt_identical(monkeypatch, _no_intent_home):
    inp = inputs(current_tree=TREE_A, pr_title=GOLDEN_TITLE, pr_body=GOLDEN_BODY,
                 branch="some-branch")
    prompts = _seat_prompts(monkeypatch, inp, GOLDEN_HOLDS)
    assert prompts == [_golden()]


def test_review_pass_feeds_hold12_findings(monkeypatch, _no_intent_home):
    inp = inputs(current_tree=TREE_A, pr_title=GOLDEN_TITLE, pr_body=GOLDEN_BODY,
                 branch="some-branch", fidelity_blocking_findings=(FINDING,))
    prompt = _seat_prompts(monkeypatch, inp, GOLDEN_HOLDS)[0]
    head = _outside_fence(prompt)
    assert "## Blocking findings" in head
    assert "### (12) plan-fidelity-verdict" in head
    assert FINDING in head
    assert head.index("## Holds") < head.index("## Blocking findings")
    os.unlink(second_review.verdict_path(PR))
    prompt = _seat_prompts(monkeypatch, inp, {7: "plan auto_merge false"})[0]
    assert "## Blocking findings" not in prompt
    assert FINDING not in prompt


def test_findings_over_cap_omitted():
    reason = "chore: x"
    holds = {12: "plan-fidelity finding NOT READY"}
    big = "x" * (second_review.FINDINGS_CAP + 1)
    prompt = second_review.build_prompt(GOLDEN_DIFF, reason, holds, {12: (big,)})
    assert prompt is not None
    assert "## Blocking findings" not in prompt
    assert GOLDEN_DIFF in prompt
    at_cap = "y" * second_review.FINDINGS_CAP
    prompt = second_review.build_prompt(GOLDEN_DIFF, reason, holds, {12: (at_cap,)})
    assert "## Blocking findings" in prompt and at_cap in prompt


def test_history_filter_keeps_finding_lines():
    holds = {12: "plan-fidelity finding NOT READY"}
    item = "Finding: check 3 fires on SKILL.md\naddressed in fix round 2"
    prompt = second_review.build_prompt(GOLDEN_DIFF, "r", holds, {12: (item,)})
    block = _outside_fence(prompt)
    assert "Finding: check 3 fires on SKILL.md" in block
    assert "addressed in fix round 2" not in block
    prompt = second_review.build_prompt(GOLDEN_DIFF, "r", holds,
                                        {12: ("addressed in fix round 2",)})
    assert "## Blocking findings" not in prompt


def test_selected_blocking_findings(tmp_path, monkeypatch):
    held = _write(tmp_path, f"## FINDINGS\n\n- {FINDING}\n- [NOTE] a side remark\n\nNOT READY\n",
                  "held.md")
    ready = _write(tmp_path, "## FINDINGS\n\n- nothing blocks\n\nREADY\n", "ready.md")
    fid = {"verdict_1": "NOT READY"}
    monkeypatch.setattr(fidelity, "verdict_path", lambda _pr: held)
    got = second_review.selected_blocking_findings(fid, PR, TREE_A)
    assert len(got) == 1 and FINDING in got[0]
    monkeypatch.setattr(fidelity, "verdict_path", lambda _pr: ready)
    assert second_review.selected_blocking_findings({"verdict_1": "READY"}, PR, TREE_A) == ()
    monkeypatch.setattr(fidelity, "verdict_path", lambda _pr: str(tmp_path / "missing.md"))
    assert second_review.selected_blocking_findings(fid, PR, TREE_A) == ()
