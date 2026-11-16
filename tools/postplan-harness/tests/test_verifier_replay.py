"""Replay a real verifier reply through the real verdict parser to the preexisting label.

Fixture: tests/fixtures/verifier-reply-captured.txt, model opus, captured 2026-11-16,
verifier_prompt from commit d39a6ded2. Never hand-edit it; re-capture instead. The capture ran
against a throwaway script at PATH below, which was deleted afterwards. The replay needs only
the prompt text and the reply, never the script.
"""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import review
from harness.adapters.llm import extract_json
from harness.state import Finding, HarnessError, PlanInfo
from harness import schemas
from tests.test_review_labels import _Gh, _Llm, _canned, _cls

FIXTURE = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                       "fixtures", "verifier-reply-captured.txt")
PATH = "bin/lib/dir-cleanup.sh"

# New-side lines 6-17 of the target file above. Line 15 is the one added line; 6-14 and 16-17
# are unchanged context. The capture runs from the repo root so the verifier can open it.
DIFF = (
    f"diff --git a/{PATH} b/{PATH}\n"
    f"--- a/{PATH}\n"
    f"+++ b/{PATH}\n"
    "@@ -6,11 +6,12 @@ set -u\n"
    " list_dir() {\n"
    '     local dir="$1"\n'
    "     local n\n"
    "     n=$(ls $dir | wc -l)\n"
    '     echo "$n"\n'
    " }\n"
    " \n"
    " clean_dir() {\n"
    '     local name="$1"\n'
    '+    rm -rf "$workdir/$name"\n'
    '     echo "cleaned $name"\n'
    " }\n"
)


def _verifier_findings() -> list[Finding]:
    return [
        # (a) on the added line 15
        Finding(source="code-review", agent="A", path=PATH, line=15,
                body="changed-line defect: WORKDIR is unset by default, so `$workdir` is empty "
                     "and `rm -rf \"$workdir/$name\"` runs `rm -rf /tmp` when the second "
                     "argument is `tmp`."),
        # (b) on unchanged context line 9, which this diff does not touch
        Finding(source="code-review", agent="A", path=PATH, line=9,
                body="pre-existing defect: `ls $dir` is unquoted, so a first argument of "
                     "`my files` is split into two paths and list_dir prints a wrong count."),
        # (c) a claim the shown code cannot produce
        Finding(source="code-review", agent="A", path=PATH, line=10,
                body="false claim: `local n` declares n as an array, so `echo \"$n\"` prints "
                     "only the first element of the count."),
    ]


def _verifier_hunks() -> dict[str, str]:
    return review._diff_by_path(DIFF)


def prompt_text() -> str:
    return review.verifier_prompt(_verifier_findings(), _verifier_hunks())


def _raw() -> str:
    with open(FIXTURE, encoding="utf-8") as fh:
        return fh.read()


def _captured() -> list[dict]:
    data = schemas.unwrap_verdicts_envelope(extract_json(_raw()))
    schemas.validate_verdicts(data)
    return data


class _ReplayLlm(_Llm):
    """Hands every verifier seat the captured raw text, so extract_json runs on real output."""

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        if purpose not in review.VERIFIER_PURPOSES:
            return super().call_tooled(purpose, model, prompt, cwd=cwd, **kw)
        return _raw()


def _finding_dicts() -> list[dict]:
    # The reviewer hint on (b) is what a review agent says; the verifier must agree with it.
    return [{"path": f.path, "line": f.line, "body": f.body,
             "preexisting": f.line == 9} for f in _verifier_findings()]


def _posted() -> dict[int, Finding]:
    llm, gh = _ReplayLlm(_canned(a=_finding_dicts())), _Gh()
    cls = _cls()
    cls.filtered_diff = DIFF
    cls.files = [PATH]
    review.ReviewPhase(llm, gh, worktree="/tmp/wt").run({}, cls, PlanInfo())
    return {f.line: f for f in gh.findings.get("Code review", [])}


def _run() -> dict[int, str]:
    return {line: f.label for line, f in _posted().items()}


def test_fixture_present_and_not_skipped():
    assert _raw().strip().startswith("[")
    assert len(_captured()) == len(_verifier_findings())


def test_captured_verifier_reply_parses_through_contract():
    data = _captured()
    assert [v["n"] for v in data] == [1, 2, 3]
    assert [v["verdict"] for v in data] == ["real", "real", "not-real"]
    assert all(isinstance(v["preexisting"], bool) for v in data)
    assert all(v["reason"].strip() for v in data)
    assert [v["preexisting"] for v in data] == [False, True, False]


def test_preexisting_verdict_reaches_preexisting_label():
    """The captured preexisting: true entry sets Finding.scorer_preexisting, which classify reads."""
    posted = _posted()
    assert posted[9].scorer_preexisting is True
    assert posted[9].label == "pre-existing"
    assert posted[15].scorer_preexisting is False


def test_changed_line_verdict_stays_important():
    posted = _run()
    assert posted[15] == "important"
    assert 10 not in posted


def test_truncated_reply_fails_parse_not_skip():
    with pytest.raises((ValueError, HarnessError)):
        schemas.validate_verdicts(schemas.unwrap_verdicts_envelope(extract_json("[")))
