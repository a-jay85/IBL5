import glob
import os
import sys
from datetime import datetime, timedelta, timezone

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.gate_backtest import (HistoricalPR, ReplaySpec, detect_gate_changes, expand_argv,
                                   resolve_spec)

REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
T0 = datetime(2026, 9, 1, 12, 0, tzinfo=timezone.utc)


def _detect(changed, texts=None, check_sources=None):
    texts = texts or {}
    return detect_gate_changes(changed, texts.get, check_sources or {})


# --- detector -----------------------------------------------------------------------------

def test_detect_check_script_added():
    out = _detect([("A", "bin/check-foo")],
                  {"bin/check-foo": "#!/bin/bash\n# gate-backtest-argv: --since={base}\n"})
    assert len(out) == 1
    assert out[0].kind == "check-script" and out[0].state == "replayable"
    assert out[0].spec.argv == ("--since={base}",)


def test_detect_non_gate_paths_empty():
    assert _detect([("M", "ibl5/classes/Foo.php"), ("M", "README.md")]) == []


def test_detect_transitive_lib_change():
    sources = {"bin/check-a": "source bin/lib/x.sh\n", "bin/check-docs": "no mention\n"}
    out = _detect([("M", "bin/lib/x.sh")], check_sources=sources)
    assert [(c.path, c.kind) for c in out] == [("bin/check-a", "transitive")]


def test_ci_workflow_and_arming_not_replayable():
    out = _detect([("M", ".github/workflows/tests.yml"),
                   ("M", "tools/postplan-harness/harness/armable.py")])
    by_path = {c.path: c for c in out}
    wf = by_path[".github/workflows/tests.yml"]
    assert wf.state == "not-replayable" and wf.reason == "CI workflow: needs a GitHub runner event"
    arm = by_path["tools/postplan-harness/harness/armable.py"]
    assert arm.state == "not-replayable" and arm.reason == "arming condition: reads live run state"


def test_removed_gate_is_removed():
    out = _detect([("D", "bin/check-foo")])
    assert out[0].state == "removed" and out[0].spec is None


def test_check_script_dedupes_with_transitive():
    sources = {"bin/check-docs": "bin/lib/x.sh\n"}
    out = _detect([("M", "bin/check-docs"), ("M", "bin/lib/x.sh")],
                  {"bin/check-docs": "bin/lib/x.sh\n"}, sources)
    assert [(c.path, c.kind) for c in out] == [("bin/check-docs", "check-script")]


def test_lib_gate_in_registry_is_replayable():
    out = _detect([("M", "bin/lib/plan-matrix-assertions")])
    assert out[0].kind == "lib-gate" and out[0].state == "replayable"
    assert out[0].spec.needs_plan


# --- spec resolution ----------------------------------------------------------------------

def test_header_overrides_registry():
    state, spec, reason = resolve_spec("bin/check-docs",
                                       "#!/bin/bash\n# gate-backtest-argv: --since={base} --strict\n")
    assert state == "replayable" and reason == "header"
    assert spec.argv == ("--since={base}", "--strict")


def test_unknown_placeholder_is_unspecified():
    state, spec, reason = resolve_spec("bin/check-zzz", "# gate-backtest-argv: --pr={pr}\n")
    assert state == "unspecified" and spec is None
    assert "{pr}" in reason


def test_check_script_without_spec_is_unspecified():
    state, spec, _ = resolve_spec("bin/check-zzz", "#!/bin/bash\necho hi\n")
    assert state == "unspecified" and spec is None


def test_header_opt_out_on_check_script_holds():
    state, spec, reason = resolve_spec(
        "bin/check-zzz", "# gate-backtest: not-replayable because it is hard to replay here\n")
    assert state == "unspecified" and spec is None
    assert "self-declared" in reason


def test_header_flag_forms():
    _, spec, _ = resolve_spec("bin/check-zzz",
                              "# gate-backtest-argv:\n# gate-backtest-flag: exit=1,3\n")
    assert spec.argv == () and spec.flag_exits == frozenset({1, 3})
    _, spec, _ = resolve_spec("bin/check-zzz",
                              "# gate-backtest-argv: {diff_file}\n# gate-backtest-flag: stdout=^BAD\n")
    assert spec.flag_stdout_re == "^BAD" and spec.flag_exits == frozenset()


def test_registry_covers_every_check_script():
    scripts = sorted(glob.glob(os.path.join(REPO_ROOT, "bin", "check-*")))
    assert scripts, "no bin/check-* found; REPO_ROOT is wrong"
    for script in scripts:
        rel = "bin/" + os.path.basename(script)
        state, _, reason = resolve_spec(rel, "")
        assert state in ("replayable", "not-replayable"), f"{rel} unspecified: {reason}"


def test_expand_argv_is_literal():
    spec = ReplaySpec(argv=("--since={base}", "{diff_file}", "{head}"))
    hostile = "x;$(touch /tmp/pwn)`"
    out = expand_argv(spec, {"base": "abc", "diff_file": "/tmp/d", "head": hostile})
    assert out == ["--since=abc", "/tmp/d", hostile]
    assert isinstance(out, list)


# --- ground truth -------------------------------------------------------------------------

def _pr(number, title, hours, files, sha="s", head_ref="b"):
    return HistoricalPR(number, title, sha + str(number), 1, T0 + timedelta(hours=hours),
                        head_ref, tuple(files))


NOW = T0 + timedelta(days=30)


def test_truth_repaired_within_48h():
    from harness.gate_backtest import classify_truth
    prs = [_pr(1, "feat: a", 0, ["a.php"]), _pr(2, "fix(x): a", 48, ["a.php"])]
    t = classify_truth(prs, NOW)
    assert t[1].state == "repaired" and t[1].repaired_by == (2,)


def test_truth_fix_after_48h_is_clean():
    from harness.gate_backtest import classify_truth
    prs = [_pr(1, "feat: a", 0, ["a.php"]), _pr(2, "fix: a", 49, ["a.php"])]
    assert classify_truth(prs, NOW)[1].state == "clean"


def test_truth_no_file_overlap_is_clean():
    from harness.gate_backtest import classify_truth
    prs = [_pr(1, "feat: a", 0, ["a.php"]), _pr(2, "fix: b", 2, ["b.php"])]
    assert classify_truth(prs, NOW)[1].state == "clean"


def test_truth_young_is_unsettled():
    from harness.gate_backtest import classify_truth
    prs = [_pr(1, "feat: a", 0, ["a.php"])]
    assert classify_truth(prs, T0 + timedelta(hours=47))[1].state == "unsettled"


def test_truth_non_fix_title_is_not_repair():
    from harness.gate_backtest import classify_truth
    prs = [_pr(1, "feat: a", 0, ["a.php"]), _pr(2, "chore: a", 1, ["a.php"])]
    assert classify_truth(prs, NOW)[1].state == "clean"


def test_truth_young_but_repaired_is_repaired():
    from harness.gate_backtest import classify_truth
    prs = [_pr(1, "feat: a", 0, ["a.php"]), _pr(2, "fix!: a", 5, ["a.php"])]
    assert classify_truth(prs, T0 + timedelta(hours=10))[1].state == "repaired"


# --- verdict ------------------------------------------------------------------------------

from harness.gate_backtest import (GATE_BACKTEST_BEGIN, GATE_BACKTEST_END, GateChange,  # noqa: E402
                                   ReplayResult, Truth, compute_verdict, parse_gate_backtest_state,
                                   render_gate_backtest, upsert_gate_backtest)

SPEC = ReplaySpec(argv=())


def _g(path="bin/check-foo", state="replayable"):
    return GateChange(path, "check-script", state, SPEC if state == "replayable" else None, "r")


def _results(gate, outcomes):
    """outcomes: list of (pr, outcome)"""
    return [ReplayResult(pr, gate, out, "d") for pr, out in outcomes]


def _truth(clean=(), repaired=(), unsettled=()):
    t = {n: Truth("clean") for n in clean}
    t.update({n: Truth("repaired", (n + 100,)) for n in repaired})
    t.update({n: Truth("unsettled") for n in unsettled})
    return t


def _passes(start, count):
    return [(start + i, "pass") for i in range(count)]


def test_verdict_not_applicable_when_no_gates():
    assert compute_verdict([], [], {}).state == "NOT-APPLICABLE"


def test_verdict_unknown_when_runner_raised():
    v = compute_verdict([_g()], None, {})
    assert v.state == "UNKNOWN"


def test_verdict_held_on_unspecified_spec():
    u = GateChange("bin/check-zzz", "check-script", "unspecified", None, "no replay spec")
    assert compute_verdict([u], [], {}).state == "HELD"
    wf = GateChange(".github/workflows/x.yml", "ci-workflow", "not-replayable", None, "ci")
    v = compute_verdict([u, wf], [], {})
    assert v.state == "HELD" and "bin/check-zzz" in v.reason


def test_verdict_unknown_on_error_or_timeout():
    for outcome in ("error", "timeout"):
        res = _results("bin/check-foo", _passes(1, 8) + [(20, outcome)])
        v = compute_verdict([_g()], res, _truth(clean=range(1, 21)))
        assert v.state == "UNKNOWN", outcome
        assert outcome in v.reason


def test_verdict_held_at_threshold():
    res = _results("bin/check-foo", _passes(1, 6) + [(10, "flag"), (11, "flag")])
    v = compute_verdict([_g()], res, _truth(clean=range(1, 12)))
    assert v.state == "HELD" and "2" in v.reason


def test_verdict_cleared_below_threshold():
    res = _results("bin/check-foo", _passes(1, 4) + [(10, "flag"), (11, "flag"), (12, "flag"),
                                                     (13, "flag")])
    t = _truth(clean=range(1, 11), repaired=(11, 12, 13))
    v = compute_verdict([_g()], res, t)
    assert v.state == "CLEARED"
    assert v.per_gate["bin/check-foo"].catches == [11, 12, 13]
    assert v.per_gate["bin/check-foo"].false_flags == [10]


def test_verdict_per_gate_not_summed():
    a = _results("bin/check-a", _passes(1, 6) + [(10, "flag")])
    b = _results("bin/check-b", _passes(1, 6) + [(11, "flag")])
    v = compute_verdict([_g("bin/check-a"), _g("bin/check-b")], a + b,
                        _truth(clean=range(1, 12)))
    assert v.state == "CLEARED"


def test_verdict_unsettled_flags_not_counted():
    res = _results("bin/check-foo", _passes(1, 6) + [(10, "flag"), (11, "flag"), (12, "flag")])
    t = _truth(clean=range(1, 7), unsettled=(10, 11, 12))
    v = compute_verdict([_g()], res, t)
    assert v.state == "CLEARED"
    assert v.per_gate["bin/check-foo"].unsettled_flags == [10, 11, 12]


def test_verdict_thin_sample_unknown():
    res = _results("bin/check-foo", _passes(1, 4))
    v = compute_verdict([_g()], res, _truth(clean=range(1, 5)))
    assert v.state == "UNKNOWN" and "thin sample" in v.reason


def test_verdict_thin_sample_counts_only_matured():
    res = _results("bin/check-foo", _passes(1, 8))
    v = compute_verdict([_g()], res, _truth(clean=range(1, 4), unsettled=range(4, 9)))
    assert v.state == "UNKNOWN"


def test_verdict_not_replayable_only():
    gates = [GateChange(".github/workflows/x.yml", "ci-workflow", "not-replayable", None, "ci"),
             GateChange("tools/postplan-harness/harness/armable.py", "arming-harness",
                        "not-replayable", None, "arm")]
    assert compute_verdict(gates, [], {}).state == "NOT-APPLICABLE"


# --- block render / upsert / parse --------------------------------------------------------

def _verdict_for(state_case):
    gates = [_g()]
    if state_case == "HELD":
        res = _results("bin/check-foo", _passes(1, 6) + [(10, "flag"), (11, "flag")])
        t = _truth(clean=range(1, 12))
    else:
        res = _results("bin/check-foo", _passes(1, 8))
        t = _truth(clean=range(1, 9))
    return gates, res, t, compute_verdict(gates, res, t)


def test_render_has_state_line_and_markers():
    gates, res, t, v = _verdict_for("HELD")
    block = render_gate_backtest(v, gates, res, t, 30)
    assert block.startswith(GATE_BACKTEST_BEGIN) and block.endswith(GATE_BACKTEST_END)
    assert "<!-- gate-backtest-state: HELD -->" in block
    assert "- False flags: #10, #11" in block
    assert "`bin/check-foo`" in block


def test_render_empty_for_non_gate_pr():
    assert render_gate_backtest(compute_verdict([], [], {}), [], [], {}, 30) == ""


def test_render_caps_lists_at_20():
    res = _results("bin/check-foo", _passes(1, 6) + [(100 + i, "flag") for i in range(25)])
    t = _truth(clean=range(1, 7), unsettled=range(100, 125))
    v = compute_verdict([_g()], res, t)
    block = render_gate_backtest(v, [_g()], res, t, 30)
    assert "(+5 more)" in block
    assert "#120" not in block.split("Unsettled flags:")[1].split("\n")[0]


def test_render_strips_backticks_from_reason():
    g = GateChange("bin/check-x", "check-script", "not-replayable", None, "a `tick` reason")
    block = render_gate_backtest(compute_verdict([g], [], {}), [g], [], {}, 30)
    assert "a tick reason" in block


def test_upsert_gate_backtest_replaces_in_place():
    block = f"{GATE_BACKTEST_BEGIN}\nnew\n{GATE_BACKTEST_END}"
    body = f"top\n\n{GATE_BACKTEST_BEGIN}\nold\n{GATE_BACKTEST_END}\n\nbottom\n"
    assert upsert_gate_backtest(body, block) == f"top\n\n{block}\n\nbottom\n"


def test_upsert_gate_backtest_appends_when_absent():
    block = f"{GATE_BACKTEST_BEGIN}\nnew\n{GATE_BACKTEST_END}"
    assert upsert_gate_backtest("body\n", block) == f"body\n\n{block}\n"
    assert upsert_gate_backtest("", block) == block


def test_upsert_gate_backtest_leaves_orphan_marker():
    block = f"{GATE_BACKTEST_BEGIN}\nnew\n{GATE_BACKTEST_END}"
    body = f"top\n{GATE_BACKTEST_END}\n"
    out = upsert_gate_backtest(body, block)
    assert out.startswith(body.rstrip()) and out.rstrip().endswith(GATE_BACKTEST_END)
    assert out.count(GATE_BACKTEST_BEGIN) == 1


def test_parse_state_table():
    assert parse_gate_backtest_state("no block here") == "NOT-APPLICABLE"
    for case in ("HELD", "CLEARED"):
        gates, res, t, v = _verdict_for(case)
        block = render_gate_backtest(v, gates, res, t, 30)
        assert parse_gate_backtest_state("body\n" + block) == v.state
    for tok in ("NOT-APPLICABLE", "UNKNOWN"):
        block = (f"{GATE_BACKTEST_BEGIN}\n<!-- gate-backtest-state: {tok} -->\n"
                 f"{GATE_BACKTEST_END}")
        assert parse_gate_backtest_state(block) == tok
    no_state = f"{GATE_BACKTEST_BEGIN}\nx\n{GATE_BACKTEST_END}"
    assert parse_gate_backtest_state(no_state) == "UNKNOWN"
    two = (f"{GATE_BACKTEST_BEGIN}\n<!-- gate-backtest-state: CLEARED -->\n"
           f"<!-- gate-backtest-state: HELD -->\n{GATE_BACKTEST_END}")
    assert parse_gate_backtest_state(two) == "UNKNOWN"
    unclosed = f"{GATE_BACKTEST_BEGIN}\n<!-- gate-backtest-state: HELD -->\n"
    assert parse_gate_backtest_state(unclosed) == "UNKNOWN"


def test_changes_from_unified_diff():
    from harness.gate_backtest import changes_from_unified_diff
    diff = (
        "diff --git a/bin/check-new b/bin/check-new\n"
        "new file mode 100755\n"
        "--- /dev/null\n+++ b/bin/check-new\n@@ -0,0 +1 @@\n+x\n"
        "diff --git a/bin/check-old b/bin/check-old\n"
        "deleted file mode 100755\n"
        "--- a/bin/check-old\n+++ /dev/null\n@@ -1 +0,0 @@\n-x\n"
        "diff --git a/bin/check-was b/bin/check-now\n"
        "similarity index 90%\nrename from bin/check-was\nrename to bin/check-now\n"
        "diff --git a/ibl5/x.php b/ibl5/x.php\n"
        "--- a/ibl5/x.php\n+++ b/ibl5/x.php\n@@ -1 +1 @@\n-a\n+b\n"
    )
    assert changes_from_unified_diff(diff) == [
        ("A", "bin/check-new"), ("D", "bin/check-old"),
        ("R", "bin/check-now"), ("M", "ibl5/x.php")]
    assert changes_from_unified_diff("") == []
