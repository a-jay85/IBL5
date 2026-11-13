"""Phase 1 tests: schema, digest, surface hash, score line."""
import copy

import pytest

from harness import second_review_eval as sre
from harness.reviewer_eval import EvalUsageError

SHA = "a" * 40
AGENT = ".claude/agents/pr-second-reviewer.md"


def case(**over):
    c = {
        "id": "pr3891-clear17", "pr": 3891, "status": "confirmed",
        "expected": "clear", "label_basis": "seat-cleared-clean",
        "base_sha": SHA, "replay_sha": SHA,
        "holds": {"17": "hold text"}, "reason": "frozen reason",
        "reason_source": "plan", "evidence": "label",
    }
    c.update(over)
    return c


def corp(*cases):
    return {"version": 1, "cases": list(cases)}


def test_schema_accepts_valid_case():
    assert sre.validate_schema(corp(case())) == []


def test_schema_rejects_unclearable_hold():
    errs = sre.validate_schema(corp(case(holds={"8": "x"})))
    assert any("clearable" in e for e in errs)


def test_schema_rejects_numeric_id():
    errs = sre.validate_schema(corp(case(id="3874")))
    assert any("id must match" in e for e in errs)


def test_digest_ignores_unconfirmed():
    base = corp(case())
    more = corp(case(), case(id="pr1-unconfirmed", status="unconfirmed"))
    assert sre.corpus_digest(base) == sre.corpus_digest(more)


def test_digest_changes_on_hold_edit():
    a = corp(case())
    b = copy.deepcopy(a)
    b["cases"][0]["holds"]["17"] = "edited"
    assert sre.corpus_digest(a) != sre.corpus_digest(b)


def test_score_line_round_trip():
    s = sre.SecondScore("a" * 12, "b" * 12, 1, 2, 0, 3, 0)
    line = sre.format_score_line(s)
    assert line == ("second-review-eval v=aaaaaaaaaaaa c=bbbbbbbbbbbb "
                    "missed_holds=1/2 extra_holds=0/3 degraded=0")
    assert sre.parse_score_line(line) == s


def test_parse_score_line_rejects_near_miss():
    line = sre.format_score_line(sre.SecondScore("a" * 12, "b" * 12, 1, 2, 0, 3, 0))
    with pytest.raises(EvalUsageError):
        sre.parse_score_line(line + " trailing")
    with pytest.raises(EvalUsageError):
        sre.parse_score_line(line.replace("missed_holds=1/2", "missed_holds=1"))


def test_partial_clear_counts_as_held():
    assert sre.seat_cleared("CLEAN", [7], {"7": "a", "12": "b"}) is False
    assert sre.seat_cleared("CLEAN", [7, 12], {"7": "a", "12": "b"}) is True
    assert sre.seat_cleared("REJECT", [7, 12], {"7": "a", "12": "b"}) is False


def test_compute_score_degraded_leaves_denominators():
    outs = [(case(expected="clear"), None, []),
            (case(expected="hold"), "CLEAN", [17])]
    s = sre.compute_score(outs, v="a" * 12, c="b" * 12)
    assert (s.missed, s.should_hold, s.extra, s.should_clear, s.degraded) == (1, 1, 0, 0, 1)


def test_surface_hash_tracks_agent_def():
    files = {
        sre.SURFACE_PATHS[0]: b"seat",
        AGENT: b"agent v1",
        sre.FIDELITY_PATH: b"X = 1\nREMEDIATION_DIFF_INLINE_CAP = 100\n",
    }
    h1 = sre.surface_hash(files.get)
    files[AGENT] = b"agent v2"
    assert sre.surface_hash(files.get) != h1


def test_surface_hash_ignores_other_fidelity_lines():
    files = {sre.FIDELITY_PATH: b"A = 1\nREMEDIATION_DIFF_INLINE_CAP = 100\n"}
    h1 = sre.surface_hash(files.get)
    files[sre.FIDELITY_PATH] = b"A = 2\nREMEDIATION_DIFF_INLINE_CAP = 100\n"
    assert sre.surface_hash(files.get) == h1


def test_validate_main_missing_corpus_exits_2(tmp_path):
    assert sre.main(["validate", "--corpus", str(tmp_path / "nope.json")]) == 2
