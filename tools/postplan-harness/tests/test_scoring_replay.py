"""Replay a real scorer reply through validation and the Phase 2 label aggregation.

Fixture: tests/fixtures/scorer-reply-captured.txt, model claude-haiku-5-5, captured 2026-10-19,
scoring_prompt from commit d4ae1c72e. Never hand-edit it; re-capture instead.
"""
from __future__ import annotations

import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import labels, reviewcache, schemas
from harness.adapters.llm import extract_json
from harness.state import HarnessError, PlanInfo
from tests.test_review_labels import _Gh, _Llm, _canned, _cls

FIXTURE = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                       "fixtures", "scorer-reply-captured.txt")
PATH = "bin/lib/pr-armable.sh"

# The capture's four findings, in scoring order (n = index + 1):
#   1 added line 347, 2 context line 344 (reviewer preexisting hint),
#   3 a file absent from the diff, 4 a security finding on added line 348.
# Right side of this hunk: 344-346 context, 347-348 added, 349 context.
DIFF = (
    f"diff --git a/{PATH} b/{PATH}\n"
    f"--- a/{PATH}\n"
    f"+++ b/{PATH}\n"
    "@@ -344,4 +344,6 @@\n"
    " ctx344\n"
    " ctx345\n"
    " ctx346\n"
    "+added347\n"
    "+added348\n"
    " ctx349\n"
)


def _captured():
    with open(FIXTURE) as fh:
        data = schemas.unwrap_scores_envelope(extract_json(fh.read()))
    schemas.validate_scores(data)
    return data


def _findings(preexisting_hint):
    return [
        {"path": PATH, "line": 347, "body": "changed-line defect"},
        {"path": PATH, "line": 344, "body": "unchanged-line defect",
         "preexisting": preexisting_hint},
        {"path": "ibl5/classes/Unrelated/Repo.php", "line": 40, "body": "file-absent defect"},
        {"path": PATH, "line": 348, "body": "security defect"},
    ]


def _run(preexisting_hint):
    # Only the capture's `preexisting` flags are replayed: each becomes a unanimous real vote
    # from three verifiers, which scores every finding above its threshold.
    verdicts = [{"n": s["n"], "real_votes": 3, "preexisting": s["preexisting"]}
                for s in _captured()]
    llm, gh = _Llm(_canned(a=_findings(preexisting_hint), verdicts=verdicts)), _Gh()
    cls = _cls()
    cls.filtered_diff = DIFF
    cls.files = [PATH]
    from harness.review import ReviewPhase
    ReviewPhase(llm, gh, worktree="/tmp/wt").run({}, cls, PlanInfo())
    return {f.body: f.label for f in gh.findings.get("Code review", [])}


def test_captured_reply_validates():
    data = _captured()
    assert all(isinstance(s["score"], int) and isinstance(s["preexisting"], bool) for s in data)
    by_n = {s["n"]: s["preexisting"] for s in data}
    assert by_n[2] is True
    assert by_n[1] is False


@pytest.mark.parametrize("hint,expected", [(True, "pre-existing"), (False, "important")])
def test_captured_reply_drives_classify(hint, expected):
    got = _run(hint)
    assert got["changed-line defect"] == "important"
    assert got["unchanged-line defect"] == expected
    assert got["file-absent defect"] == "important"
    assert got["security defect"] == "important"


def _shapes():
    payload = _captured()
    raw = json.dumps(payload)
    return {
        "bare": raw,
        "scores": json.dumps({"scores": payload}),
        "fenced": f"Scores below.\n```json\n{raw}\n```\n",
        "findings": json.dumps({"findings": payload}),
    }


@pytest.mark.parametrize("shape", ["bare", "scores", "fenced", "findings"])
def test_envelope_variants_validate_identically(shape):
    """Bare list, {"scores"}, fenced, and {"findings"} replies validate identically."""
    data = schemas.unwrap_scores_envelope(extract_json(_shapes()[shape]))
    schemas.validate_scores(data)
    assert data == _captured()


def test_scores_dict_not_list_raises():
    data = schemas.unwrap_scores_envelope(extract_json(json.dumps({"scores": {"n": 1}})))
    with pytest.raises(HarnessError):
        schemas.validate_scores(data)


def test_string_preexisting_coerces_to_false_and_classifies_important():
    data = [{"n": 1, "score": 90, "preexisting": "yes"}]
    schemas.validate_scores(data)
    assert data[0]["preexisting"] is False
    changed = labels.changed_right_lines(DIFF)
    from harness.state import Finding
    f = Finding(source="code-review", agent="A", path=PATH, line=344, body="b",
                score=90, preexisting_hint=True, scorer_preexisting=data[0]["preexisting"])
    assert labels.classify(f, 80, changed) == "important"


def test_pre_label_cache_record_is_a_miss(tmp_path):
    old_shape = {"source": "code-review", "agent": "A", "path": PATH, "line": 347,
                 "body": "b", "score": 85}
    arm = {"degraded_agents": [], "gates": {}, "findings": [old_shape], "scored_findings": []}
    assert reviewcache.restore_review(arm, {}) is None
    with open(reviewcache.record_path(str(tmp_path), "slug"), "w") as fh:
        json.dump({"schema_version": 1, "slug": "slug", "arm": arm}, fh)
    assert reviewcache.load_record(str(tmp_path), "slug") is None
