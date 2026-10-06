"""Tests for the pr-copy JSON recovery path.

Sections:
  1. Characterization of the shared extract_json against real raw pr-copy replies
  2. extract_pr_copy_json unit tests
  3. End-to-end through the real ClaudeCli.call with a bash shim
"""
import json
import os
import re
import stat
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import schemas
from harness.adapters.llm import ClaudeCli, extract_json
from harness.schemas import normalize_pr_copy, validate_pr_copy
from harness.state import Classification, HarnessError, UsageLedger

FIXTURES = os.path.join(os.path.dirname(__file__), "fixtures")

# label -> (attempt, today's outcome via extract_json+normalize+validate)
STILL_FAILING = [
    ("unescaped-quote-mixed", 0), ("unescaped-quote", 0), ("unescaped-quote", 1),
    ("nested-fence", 0), ("nested-fence", 1),
    ("prose-backticks", 0), ("prose-backticks", 1),
]
RECOVERS_TODAY = [
    ("type-mismatch-pin", 0), ("type-mismatch-pin", 1),
    ("type-mismatch-ci", 0), ("type-mismatch-ci", 1),
    ("unescaped-quote-mixed", 1),
]


def _raw(label, attempt):
    path = os.path.join(FIXTURES, f"raw-pr-copy-{label}-attempt{attempt}.txt")
    with open(path) as fh:
        return fh.read()


def _raw_type(raw):
    return re.search(r'"type"\s*:\s*"([a-z]+)"', raw).group(1)


# ---------------------------------------------------------------------------
# 1. Characterization (shared parser, normalizer and validator are not edited)
# ---------------------------------------------------------------------------
@pytest.mark.parametrize("label,attempt", STILL_FAILING)
def test_characterize_extract_json_rejects_still_failing_raws(label, attempt):
    with pytest.raises(ValueError, match="no parseable JSON"):
        extract_json(_raw(label, attempt))


@pytest.mark.parametrize("label,attempt", RECOVERS_TODAY)
def test_characterize_mismatch_raws_recover_today(label, attempt):
    d = normalize_pr_copy(extract_json(_raw(label, attempt)))
    validate_pr_copy(d)
    assert d["title"].startswith(d["type"])


def test_characterize_mixed_attempt1_normalizes_to_feat():
    d = normalize_pr_copy(extract_json(_raw("unescaped-quote-mixed", 1)))
    assert d["type"] == "feat"


STILL_FAILING_RAW_IDS = {
    "raw-pr-copy-unescaped-quote-mixed-0",
    "raw-pr-copy-unescaped-quote-0",
    "raw-pr-copy-unescaped-quote-1",
    "raw-pr-copy-nested-fence-0",
    "raw-pr-copy-nested-fence-1",
    "raw-pr-copy-prose-backticks-0",
    "raw-pr-copy-prose-backticks-1",
}


def test_still_failing_raw_ids_match_table():
    assert {f"raw-pr-copy-{label}-{attempt}" for label, attempt in STILL_FAILING} \
        == STILL_FAILING_RAW_IDS


def test_purpose_key_matches_peer_tests_and_runner():
    """_extractor_for dispatches on the literal "pr-copy"; the runner and the peer-owned
    test_body_check.py (DegradingLlm bad-purpose set) must spell it the same way."""
    here = os.path.dirname(os.path.abspath(__file__))
    with open(os.path.join(here, "test_body_check.py"), encoding="utf-8") as fh:
        assert '"pr-copy"' in fh.read()
    with open(os.path.join(os.path.dirname(here), "runner.py"), encoding="utf-8") as fh:
        assert '"pr-copy"' in fh.read()


def test_pr_copy_fixture_set_complete():
    pairs = STILL_FAILING + RECOVERS_TODAY
    assert len(set(pairs)) == 12
    for label, attempt in pairs:
        path = os.path.join(FIXTURES, f"raw-pr-copy-{label}-attempt{attempt}.txt")
        assert os.path.isfile(path), path


# ---------------------------------------------------------------------------
# 2. extract_pr_copy_json unit tests
# ---------------------------------------------------------------------------
@pytest.mark.parametrize("label,attempt", STILL_FAILING)
def test_extractor_recovers_still_failing_raw(label, attempt):
    raw = _raw(label, attempt)
    d = schemas.extract_pr_copy_json(raw)
    assert d["type"] == _raw_type(raw)
    validate_pr_copy(normalize_pr_copy(d))


@pytest.mark.parametrize("label,attempt", RECOVERS_TODAY)
def test_extractor_matches_extract_json_when_it_parses(label, attempt):
    raw = _raw(label, attempt)
    assert schemas.extract_pr_copy_json(raw) == extract_json(raw)


def test_extractor_salvage_keeps_feat():
    reply = ('{"type": "feat", "title": "feat: add x", "commit_subject": "feat: add x", '
             '"summary_md": "## Summary\\n- says "hi" here"}')
    d = schemas.extract_pr_copy_json(reply)
    assert d["type"] == "feat"
    assert d["title"] == "feat: add x"
    assert d["commit_subject"] == "feat: add x"
    assert 'says "hi" here' in d["summary_md"]


def test_extractor_salvage_refuses_quote_in_short_field():
    reply = ('{"type": "feat", "title": "feat: add "x" now", "commit_subject": "feat: add x", '
             '"summary_md": "## Summary\\n- a"}')
    with pytest.raises(ValueError):
        schemas.extract_pr_copy_json(reply)


def test_extractor_salvage_refuses_duplicate_anchor():
    reply = ('{"type": "feat", "title": "feat: add x", "commit_subject": "feat: add x", '
             '"summary_md": "## Summary\\n- he said "title": "x" oddly"}')
    with pytest.raises(ValueError):
        schemas.extract_pr_copy_json(reply)


@pytest.mark.parametrize("reply", [
    "I can't produce that.",
    '{"type": "fix", "title": "fix: a',
    "",
], ids=["prose-only", "truncated", "empty"])
def test_extractor_raises_with_hint(reply):
    with pytest.raises(ValueError) as e:
        schemas.extract_pr_copy_json(reply)
    assert "summary_md" in str(e.value)
    assert "pr-copy reply" in str(e.value)


def test_extractor_does_not_unwrap_envelope():
    reply = ('{"pr_copy": {"type": "fix", "title": "fix: a", "commit_subject": "fix: a", '
             '"summary_md": "s"}}')
    d = schemas.extract_pr_copy_json(reply)
    assert list(d) == ["pr_copy"]
    with pytest.raises(HarnessError) as e:
        validate_pr_copy(d)
    assert e.value.kind == "schema"


# ---------------------------------------------------------------------------
# 3. End-to-end through the real ClaudeCli.call with a bash shim
# ---------------------------------------------------------------------------
SHIM = """#!/usr/bin/env bash
printf '%s\\n' "$*" >> "$CLAUDE_SHIM_LOG"
cat > /dev/null
printf '%s' "${CLAUDE_SHIM_REPLY}"
exit 0
"""

# Unescaped inner double quote inside summary_md (same literal as test_pr_copy_normalize).
BAD_JSON_REPLAY = ('{"type": "chore", "title": "chore(docfix): reap", '
                   '"commit_subject": "chore(docfix): reap", '
                   '"summary_md": "## Summary\\n- marks the run as "not merged" and exits\\n"}')
TRUNCATED_REPLAY = ('{"type": "chore", "title": "chore(docfix): reap", '
                    '"summary_md": "## Summary\\n- marks')
GARBAGE_REPLIES = ["I can't produce that.", TRUNCATED_REPLAY]
GARBAGE_IDS = ["prose-only", "truncated"]


class _Gh:
    def pr_exists(self):
        return False

    def pr_title(self):
        return ""


class _Git:
    def has_changes_to_commit(self):
        return True

    def branch_head_subject(self):
        return "Pin sonnet for burndown"  # deliberately NOT conventional


class _NoPlan:
    found = False
    path = None


def _cls():
    return Classification()


@pytest.fixture()
def shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    claude = bindir / "claude"
    claude.write_text(SHIM)
    claude.chmod(claude.stat().st_mode | stat.S_IEXEC)
    log = tmp_path / "claude-calls.log"
    log.write_text("")
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    monkeypatch.setenv("CLAUDE_SHIM_LOG", str(log))
    return log


def _cli(tmp_path):
    return ClaudeCli(UsageLedger(), workdir=str(tmp_path))


def _call_count(log):
    return len([ln for ln in log.read_text().splitlines() if ln.strip()])


def _envelope(text):
    return json.dumps({"result": text, "subtype": "success"})


def _fenced(text):
    return "Here is the copy.\n```json\n" + text + "\n```\n"


def _call_pr_copy(tmp_path):
    return _cli(tmp_path).call("pr-copy", "sonnet", "p", validate=schemas.validate_pr_copy,
                               normalizer=schemas.normalize_pr_copy)


def _run_pr_copy_live(tmp_path, logs):
    return runner._pr_copy(_cli(tmp_path), _Git(), _Gh(), None, "slug", _cls(), _NoPlan(),
                           logs.append)


@pytest.mark.parametrize("label,attempt", STILL_FAILING)
def test_cli_pr_copy_recovers_real_raw(tmp_path, monkeypatch, shim, label, attempt):
    raw = _raw(label, attempt)
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(raw))
    data = _call_pr_copy(tmp_path)
    assert _call_count(shim) == 1
    assert data["type"] in {_raw_type(raw), "feat"}
    if _raw_type(raw) == "feat":
        assert data["type"] == "feat"


def test_cli_bad_json_replay_now_recovers(tmp_path, monkeypatch, shim):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(_fenced(BAD_JSON_REPLAY)))
    data = _call_pr_copy(tmp_path)
    assert data["type"] == "chore"
    assert _call_count(shim) == 1


@pytest.mark.parametrize("label,attempt", STILL_FAILING)
def test_pr_copy_runner_recovers_real_raw(tmp_path, monkeypatch, shim, label, attempt):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(_raw(label, attempt)))
    logs = []
    out, degraded = _run_pr_copy_live(tmp_path, logs)
    assert degraded is False
    assert _call_count(shim) == 1
    assert not any("DEGRADED" in ln for ln in logs)


def test_cli_other_purpose_keeps_extract_json(tmp_path, monkeypatch, shim):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(_raw("nested-fence", 0)))
    with pytest.raises(HarnessError) as e:
        _cli(tmp_path).call("score-findings", "haiku", "p", validate=schemas.validate_pr_copy)
    assert e.value.kind == "llm-invalid-output"
    assert _call_count(shim) == 2
    assert "no parseable JSON in model reply" in (e.value.detail or "")


@pytest.mark.parametrize("reply", GARBAGE_REPLIES, ids=GARBAGE_IDS)
def test_cli_garbage_reply_degrades_with_hint(tmp_path, monkeypatch, shim, reply):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(reply))
    with pytest.raises(HarnessError) as e:
        _call_pr_copy(tmp_path)
    assert e.value.kind == "llm-invalid-output"
    assert _call_count(shim) == 2
    assert "pr-copy reply" in (e.value.detail or "")
    assert "summary_md" in (e.value.detail or "")


def test_pr_copy_runner_garbage_degrades_with_visible_cause(tmp_path, monkeypatch, shim):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope("I can't produce that."))
    logs = []
    out, degraded = _run_pr_copy_live(tmp_path, logs)
    assert degraded is True
    assert out["title"].startswith("feat: ")
    hits = [ln for ln in logs if "pr-copy DEGRADED" in ln]
    assert len(hits) == 1
    assert "pr-copy reply" in hits[0]
    assert "summary_md" in hits[0]
