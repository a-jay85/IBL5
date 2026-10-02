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
