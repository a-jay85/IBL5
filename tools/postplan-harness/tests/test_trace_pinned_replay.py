"""Replay the five engine golden-snapshot PRs against the `trace-pinned` token.

PRs #3908, #3918, #3949, #4008 and #4040 ported JSB foul and outcome routines and the owner
merged each one by hand, because condition (5) holds every golden change in a headless run
and condition (7) held their `golden-new` plan token. As merged they carry no trace evidence,
so they stay held. With synthetic evidence declaring every non-test .go file, #3908, #3918,
#3949 and #4008 clear; #4040 stays held because a test in internal/sim cannot execute its
engine/cmd change. Each `engine-golden-*` corpus entry holds the PR's file list, pasted from
`gh pr diff <N> --name-only -R a-jay85/IBL5`."""
import dataclasses
import json

import pytest

from harness import arming_replay
from harness import trace_pinned as tp
from harness.arming_replay_driver import decode
from harness.armable import ArmInputs, evaluate, second_review_candidates
from harness.state import Classification, Finding
from tests.test_trace_pinned import (BRANCH, EVIDENCE, PACKAGE, TEST, _capture_text, _commit,
                                     _fake_go, _git, _stream, _write)

ENGINE_PRS = ("3908", "3918", "3949", "4008", "4040")


def _corpus():
    return json.loads(arming_replay.CORPUS_PATH.read_text(encoding="utf-8"))


def _engine_records():
    records = {r["id"]: r for r in _corpus() if r["id"].startswith("engine-golden-")}
    assert sorted(records) == [f"engine-golden-{n}" for n in ENGINE_PRS]
    return records


def _decode(record) -> ArmInputs:
    return decode(record, ArmInputs, Classification, Finding)


def _blocked(decision):
    return {c.number for c in decision.conditions if c.blocked}


@pytest.mark.parametrize("pr", ENGINE_PRS)
def test_as_merged_engine_prs_stay_held(pr):
    inp = _decode(_engine_records()[f"engine-golden-{pr}"])
    decision = evaluate(inp)
    blocked = _blocked(decision)
    assert not decision.armed
    assert {5, 7} <= blocked
    by_number = {c.number: c for c in decision.conditions}
    assert by_number[5].name == "golden-snapshot-headless"
    assert by_number[7].name == "plan-auto-merge-hold"
    assert second_review_candidates(inp, decision.conditions) == {}


# ---- synthetic trace-pinned replay ----

SYN_CAPTURE_NAME = "synthetic.jsonl"
SYN_CAPTURE = f"{tp.CAPTURE_DIR}/{SYN_CAPTURE_NAME}"
SYN_TEST_NAME = "TestTracePinnedSynthetic"
SYN_TEST_FILE = "engine/internal/sim/synthetic_trace_test.go"
SYN_SOURCE = (f'package sim\n\nimport (\n\t"testing"\n\n\t"github.com/a-jay85/IBL5/engine/internal/jsbtrace"\n)\n\n'
              f'func {SYN_TEST_NAME}(t *testing.T) {{\n\tc := jsbtrace.MustLoad(t, "{SYN_CAPTURE_NAME}")\n\t_ = c\n}}\n')
SEEDS = "engine/internal/sim/testdata/golden_seeds.sha256"
CLEARING_PRS = ("3908", "3918", "3949", "4008")
UNREACHABLE_PREFIX = "engine/cmd/"  # package main: a test in internal/sim never executes it


def _syn_ported(files):
    return [f for f in files if f.endswith(".go") and not f.endswith("_test.go")]


def _syn_go(paths, **kw):
    covered = [p for p in _syn_ported(paths) if not p.startswith(UNREACHABLE_PREFIX)]
    return _fake_go(capture_name=SYN_CAPTURE_NAME, **{"covered": covered, **kw})


def _engine_paths(files):
    return [f for f in files if (f.startswith("engine/") and f.endswith(".go")) or f in tp.GOLDEN_FILES]


def _stream_verdict(name):
    return tp.parse_go_test_json(_stream(name), TEST)


def _replay_repo(tmp_path, files, *, capture="base", with_test=True, ported=None):
    """Temp repo: base holds the capture (`capture="base"`), head holds stubs for `files`.

    `capture` is "base", "head" (added by the same PR) or "none" (on neither commit).
    Returns (root, base)."""
    root = tmp_path / "repo"
    root.mkdir()
    _git(root, "init", "-q")
    _write(root, "engine/go.mod", "module github.com/a-jay85/IBL5/engine\n\ngo 1.22\n")
    if capture == "base":
        _write(root, SYN_CAPTURE, _capture_text())
    base = _commit(root, "base")
    _git(root, "update-ref", "refs/remotes/origin/master", base)
    for path in files:
        _write(root, path, "package x\n" if path.endswith(".go") else "{}\n")
    if capture == "head":
        _write(root, SYN_CAPTURE, _capture_text())
    if with_test:
        _write(root, SYN_TEST_FILE, SYN_SOURCE)
        entry = {"package": PACKAGE, "test": SYN_TEST_NAME, "file": SYN_TEST_FILE,
                 "test_blob": _git(root, "hash-object", SYN_TEST_FILE), "capture": SYN_CAPTURE,
                 "capture_sha256": tp._sha256(_capture_text().encode()),
                 "ported_files": _syn_ported(files) if ported is None else list(ported)}
        _write(root, EVIDENCE, json.dumps({"schema": tp.SCHEMA, "token": "trace-pinned",
                                           "branch": BRANCH, "tests": [entry]}))
    _commit(root, "head")
    return root, base


def _changed(files, *, extra=()):
    return [*files, SYN_TEST_FILE, EVIDENCE, *extra]


def _arm(inp, evidence, *, capture_changed=False):
    cls = dataclasses.replace(inp.classification, jsb_capture_changed=capture_changed)
    return dataclasses.replace(inp, classification=cls, trace_pinned_evidence=evidence,
                               plan_hold_triggers=("trace-pinned",))


@pytest.mark.parametrize("pr", CLEARING_PRS)
def test_synthetic_trace_pinned_clears_engine_prs(tmp_path, pr):
    record = _engine_records()[f"engine-golden-{pr}"]
    paths = _engine_paths(record["inputs"]["classification"]["files"])
    root, base = _replay_repo(tmp_path, paths)
    verdict = tp.evaluate_evidence(root, BRANCH, base, _changed(paths), run_go=_syn_go(paths))
    assert verdict == "ok"
    decision = evaluate(_arm(_decode(record), verdict))
    assert not _blocked(decision) & {5, 7}


def test_synthetic_trace_pinned_holds_cmd_change_4040(tmp_path):
    record = _engine_records()["engine-golden-4040"]
    paths = _engine_paths(record["inputs"]["classification"]["files"])
    root, base = _replay_repo(tmp_path, paths)
    verdict = tp.evaluate_evidence(root, BRANCH, base, _changed(paths), run_go=_syn_go(paths))
    assert verdict == "unexercised-change: engine/cmd/reallife-fixture/main.go"
    assert {5, 7} <= _blocked(evaluate(_arm(_decode(record), verdict)))


@pytest.mark.parametrize("pr", ENGINE_PRS)
def test_full_file_list_matches_corpus_expectation(tmp_path, pr):
    record = _engine_records()[f"engine-golden-{pr}"]
    files = record["inputs"]["classification"]["files"]
    root, base = _replay_repo(tmp_path, _engine_paths(files))
    verdict = tp.evaluate_evidence(root, BRANCH, base, _changed(files),
                                   run_go=_syn_go(_engine_paths(files)))
    assert verdict == record["full_list_expected"], f"engine-golden-{pr} printed {verdict!r}"


NEGATIVE_IDS = ("engine-copied-expectations", "failing-trace-test", "skipped-trace-test",
                "non-engine-file", "missing-capture", "seeds-only", "capture-added-by-same-pr",
                "undeclared-port", "unexercised-port")


@pytest.mark.parametrize("case", NEGATIVE_IDS)
def test_replay_negative_cases_stay_held(tmp_path, case):
    record = _engine_records()["engine-golden-3918"]
    paths = _engine_paths(record["inputs"]["classification"]["files"])
    go, build, changed, capture_changed = _syn_go(paths), {}, _changed(paths), False
    if case == "engine-copied-expectations":
        go, prefix = _syn_go(paths, perturbed="pass"), "circular:"
    elif case == "failing-trace-test":
        go, prefix = _syn_go(paths, normal=_stream_verdict("fail")), "test-fail"
    elif case == "skipped-trace-test":
        go, prefix = _syn_go(paths, normal=_stream_verdict("skip")), "test-skip"
    elif case == "non-engine-file":
        changed, prefix = _changed(paths, extra=("ibl5/classes/Trade/TradeService.php",)), "non-engine-path:"
    elif case == "missing-capture":
        build, prefix = {"capture": "none"}, "capture-not-on-base"
    elif case == "seeds-only":
        paths, changed, build, prefix = [SEEDS], [SEEDS], {"with_test": False}, "evidence-missing"
    elif case == "undeclared-port":
        build = {"ported": [p for p in _syn_ported(paths) if not p.endswith("/transition.go")]}
        prefix = "undeclared-engine-file:"
    elif case == "unexercised-port":
        go, prefix = _syn_go(paths, covered=()), "unexercised-change:"
    else:
        build, changed, prefix = {"capture": "head"}, _changed(paths, extra=(SYN_CAPTURE,)), "capture-in-diff:"
        capture_changed = True
    root, base = _replay_repo(tmp_path, paths, **build)
    verdict = tp.evaluate_evidence(root, BRANCH, base, changed, run_go=go)
    assert verdict.startswith(prefix), verdict
    # capture-added also stays held with "ok" forced in (the jsb_capture_changed floor)
    forced = "ok" if case == "capture-added-by-same-pr" else verdict
    inp = _arm(_decode(record), forced, capture_changed=capture_changed)
    decision = evaluate(inp)
    blocked = _blocked(decision)
    assert 7 in blocked
    if case in ("seeds-only", "capture-added-by-same-pr"):
        assert 5 in blocked
    assert second_review_candidates(inp, decision.conditions) == {}


def print_replay_verdicts():
    """One line per (PR, file list) with the verdict the synthetic evidence gets.
    Run from tools/postplan-harness: python3 -m tests.test_trace_pinned_replay"""
    import tempfile
    from pathlib import Path
    for pr in ENGINE_PRS:
        files = _engine_records()[f"engine-golden-{pr}"]["inputs"]["classification"]["files"]
        paths = _engine_paths(files)
        for label, changed in (("engine-only", _changed(paths)), ("full-list", _changed(files))):
            with tempfile.TemporaryDirectory() as tmp:
                root, base = _replay_repo(Path(tmp), paths)
                go = _syn_go(paths)
                print(pr, label, tp.evaluate_evidence(root, BRANCH, base, changed, run_go=go))


if __name__ == "__main__":
    print_replay_verdicts()
