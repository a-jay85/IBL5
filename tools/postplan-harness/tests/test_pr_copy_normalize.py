"""Tests for normalize_pr_copy and its wiring into runner._pr_copy.

Sections:
  1. validate_pr_copy characterization (locks today's rejections; validator is not edited)
  2. normalize_pr_copy unit tests
  3. runner._pr_copy wiring and degraded-fallback locks
  4. End-to-end through the real ClaudeCli.call with a bash shim
"""
import copy
import inspect
import json
import os
import stat
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import schemas
from harness.adapters.llm import ClaudeCli
from harness.schemas import COMMIT_TYPES, normalize_pr_copy, validate_pr_copy
from harness.state import Classification, HarnessError, UsageLedger

# Raw replies from real runs live in the gitignored out/ dir, so the three coherent-ish
# mismatch cases are inlined. Only type / title / commit_subject matter.
REPLAY_PIN_A0 = {"type": "docs", "title": "chore(burndown): pin sonnet",
                 "commit_subject": "docs(burndown): pin sonnet", "summary_md": "## Summary\n- x\n"}
REPLAY_PIN_A1 = {"type": "chore", "title": "chore(burndown): pin sonnet",
                 "commit_subject": "docs(burndown): pin sonnet", "summary_md": "## Summary\n- x\n"}
REPLAY_SWEEP_A1 = {"type": "chore", "title": "feat(burndown): sweep bare paths",
                   "commit_subject": "chore(burndown): sweep bare paths",
                   "summary_md": "## Summary\n- x\n"}
REPLAYS = [REPLAY_PIN_A0, REPLAY_PIN_A1, REPLAY_SWEEP_A1]


def _valid(**over):
    d = {"type": "feat", "title": "feat: a", "commit_subject": "feat: a", "summary_md": ""}
    d.update(over)
    return d


def _detail(excinfo):
    return excinfo.value.detail or ""


# ---------------------------------------------------------------------------
# 1. validate_pr_copy characterization
# ---------------------------------------------------------------------------
@pytest.mark.parametrize("fx", REPLAYS)
def test_validate_rejects_replay_mismatch_unnormalized(fx):
    with pytest.raises(HarnessError) as e:
        validate_pr_copy(fx)
    assert e.value.kind == "schema"
    assert "must start with its conventional-commit type" in _detail(e)


def test_validate_rejects_unknown_type():
    d = {"type": "wip", "title": "wip: x", "commit_subject": "wip: x", "summary_md": ""}
    with pytest.raises(HarnessError) as e:
        validate_pr_copy(d)
    assert "not a conventional-commit type" in _detail(e)


def _drop_subject():
    d = _valid()
    del d["commit_subject"]
    return d


@pytest.mark.parametrize("d", [_drop_subject(), _valid(title=123), _valid(type=None)],
                         ids=["no-subject", "numeric-title", "none-type"])
def test_validate_rejects_missing_or_non_string_field(d):
    with pytest.raises(HarnessError) as e:
        validate_pr_copy(d)
    assert "missing string field" in _detail(e)


@pytest.mark.parametrize("d", [[], "feat: x"])
def test_validate_rejects_non_dict(d):
    with pytest.raises(HarnessError) as e:
        validate_pr_copy(d)
    assert "must be a JSON object" in _detail(e)


# ---------------------------------------------------------------------------
# 2. normalize_pr_copy unit tests
# ---------------------------------------------------------------------------
@pytest.mark.parametrize("fx,expected", [(REPLAY_PIN_A0, "docs"), (REPLAY_PIN_A1, "chore"),
                                         (REPLAY_SWEEP_A1, "feat")])
def test_normalize_replays_validate(fx, expected):
    out = normalize_pr_copy(fx)
    validate_pr_copy(out)
    assert out["type"] == expected
    assert out["title"].startswith(expected + "(burndown):")
    assert out["commit_subject"].startswith(expected + "(burndown):")
    assert out["summary_md"] == fx["summary_md"]


@pytest.mark.parametrize("d", [
    _valid(type="feat", title="chore: a", commit_subject="docs: a"),
    _valid(type="chore", title="feat: a", commit_subject="chore: a"),
    _valid(type="chore", title="chore: a", commit_subject="feat: a"),
], ids=["type", "title", "subject"])
def test_normalize_feat_wins_in_every_position(d):
    out = normalize_pr_copy(d)
    assert out["type"] == "feat"
    assert out["title"].startswith("feat:")
    assert out["commit_subject"].startswith("feat:")
    validate_pr_copy(out)


def test_normalize_never_lowers_feat():
    d = _valid(type="feat", title="feat!: a", commit_subject="feat(x): a")
    assert normalize_pr_copy(d) is d
    validate_pr_copy(d)
    d2 = _valid(type="docs", title="feat!: a", commit_subject="docs: a")
    out = normalize_pr_copy(d2)
    assert out["type"] == "feat"
    assert out["title"] == "feat!: a"
    assert out["commit_subject"] == "feat: a"


def test_normalize_preserves_scope_and_bang():
    # Guards the slice point: cutting at mt.end(0) instead of mt.end(1) would drop the
    # (scope) and the bang along with the type token.
    d = _valid(type="chore", title="docs(api)!: x", commit_subject="chore(api): x")
    out = normalize_pr_copy(d)
    assert out["title"] == "chore(api)!: x"
    assert out["commit_subject"] == "chore(api): x"
    assert out["type"] == "chore"


def test_normalize_unknown_type_untouched_and_still_raises():
    d = {"type": "wip", "title": "wip: x", "commit_subject": "wip: x", "summary_md": ""}
    assert normalize_pr_copy(d) is d
    with pytest.raises(HarnessError):
        validate_pr_copy(normalize_pr_copy(d))


def test_normalize_unknown_prefix_type_untouched():
    d = _valid(type="chore", title="wip: x", commit_subject="chore: x")
    assert normalize_pr_copy(d) is d
    with pytest.raises(HarnessError) as e:
        validate_pr_copy(normalize_pr_copy(d))
    assert "title must start" in _detail(e)


def test_normalize_unparseable_prefix_untouched():
    d = _valid(type="chore", title="Pin sonnet for burndown", commit_subject="chore: x")
    assert normalize_pr_copy(d) is d
    with pytest.raises(HarnessError):
        validate_pr_copy(normalize_pr_copy(d))


@pytest.mark.parametrize("d", [_drop_subject(), _valid(title=123), _valid(type=None)],
                         ids=["no-subject", "numeric-title", "none-type"])
def test_normalize_missing_or_non_string_untouched(d):
    assert normalize_pr_copy(d) is d
    with pytest.raises(HarnessError) as e:
        validate_pr_copy(normalize_pr_copy(d))
    assert "missing string field" in _detail(e)


@pytest.mark.parametrize("d,msg", [
    ([], "must be a JSON object"),
    ("feat: x", "must be a JSON object"),
    (None, "must be a JSON object"),
    ({"pr_copy": _valid()}, "missing string field"),
], ids=["list", "str", "none", "envelope"])
def test_normalize_non_dict_passthrough(d, msg):
    out = normalize_pr_copy(d)
    assert out is d
    with pytest.raises(HarnessError) as e:
        validate_pr_copy(out)
    assert msg in _detail(e)


@pytest.mark.parametrize("fx", REPLAYS)
def test_normalize_is_pure_and_idempotent(fx):
    before = copy.deepcopy(fx)
    once = normalize_pr_copy(fx)
    assert fx == before
    assert normalize_pr_copy(once) == once


def test_normalize_case_insensitive_prefix_agrees():
    d = _valid(type="chore", title="Chore: x", commit_subject="CHORE(a): x")
    assert normalize_pr_copy(d) is d
    validate_pr_copy(d)


def test_commit_types_includes_feat():
    # The winner rule hard-codes "feat"; fail loudly if the type set ever drops it.
    assert "feat" in COMMIT_TYPES


# ---------------------------------------------------------------------------
# 3. runner._pr_copy wiring and degraded-fallback locks
# ---------------------------------------------------------------------------
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
    # Default Classification: every *_only flag False, so coerce_commit_subject is a no-op
    # and the assertions isolate _pr_copy's own fallback.
    return Classification()


class _RecordingLlm:
    """Mirrors FixtureLlm.call ordering: normalizer (if any) -> validate -> return."""

    def __init__(self, data):
        self.data, self.calls, self.normalizers = data, 0, []

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.calls += 1
        self.normalizers.append(normalizer)
        data = self.data
        if normalizer is not None:
            data = normalizer(data)
        validate(data)
        return data


class _InvalidJsonLlm(_RecordingLlm):
    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.calls += 1
        self.normalizers.append(normalizer)
        raise HarnessError("llm-invalid-output", "pr-copy: no parseable JSON in reply")


class _UsageLimitLlm(_RecordingLlm):
    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        raise HarnessError("llm-usage-limit", "x")


class _AdapterLikeLlm(_RecordingLlm):
    """Re-raises a schema failure as llm-invalid-output, as ClaudeCli.call does after its last try."""

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        try:
            return super().call(purpose, model, prompt, validate, max_retries, normalizer)
        except HarnessError as e:
            raise HarnessError("llm-invalid-output", f"{purpose}: {e.detail}")


def _run_pr_copy(llm):
    return runner._pr_copy(llm, _Git(), _Gh(), None, "slug", _cls(), _NoPlan(), lambda m: None)


def test_pr_copy_invalid_json_degrades_to_feat_fallback():
    out, degraded = _run_pr_copy(_InvalidJsonLlm(None))
    assert degraded is True
    assert out["title"] == "feat: Pin sonnet for burndown"
    assert out["commit_subject"] == out["title"]
    assert out["summary_md"].startswith("## Summary")


def test_pr_copy_reraises_non_invalid_output_errors():
    with pytest.raises(HarnessError) as e:
        _run_pr_copy(_UsageLimitLlm(None))
    assert e.value.kind == "llm-usage-limit"


def test_pr_copy_passes_normalizer_to_llm():
    llm = _RecordingLlm(dict(REPLAY_PIN_A1))
    out, degraded = _run_pr_copy(llm)
    assert degraded is False
    assert llm.calls == 1
    assert llm.normalizers == [schemas.normalize_pr_copy]
    assert out["type"] == "chore"
    assert out["commit_subject"].startswith("chore(burndown):")


def test_pr_copy_source_wires_normalizer():
    src = inspect.getsource(runner._pr_copy)
    assert 'llm.call("pr-copy", "sonnet"' in src
    assert "normalizer=schemas.normalize_pr_copy" in src


def test_pr_copy_envelope_reply_still_degrades():
    llm = _AdapterLikeLlm({"pr_copy": dict(REPLAY_PIN_A1)})
    out, degraded = _run_pr_copy(llm)
    assert degraded is True
    assert out["title"].startswith("feat: ")


# ---------------------------------------------------------------------------
# 4. End-to-end through the real ClaudeCli.call with a bash shim
# ---------------------------------------------------------------------------
SHIM = """#!/usr/bin/env bash
printf '%s\\n' "$*" >> "$CLAUDE_SHIM_LOG"
cat > /dev/null
printf '%s' "${CLAUDE_SHIM_REPLY}"
exit 0
"""


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


def _fenced(obj_or_text):
    body = obj_or_text if isinstance(obj_or_text, str) else json.dumps(obj_or_text)
    return "Here is the copy.\n```json\n" + body + "\n```\n"


# Unescaped inner double quote inside summary_md: the shape of the real docfix-reap and
# sweep-bare-paths attempt-0 replies. Written as a literal; json.dumps would escape it.
BAD_JSON_REPLAY = ('{"type": "chore", "title": "chore(docfix): reap", '
                   '"commit_subject": "chore(docfix): reap", '
                   '"summary_md": "## Summary\\n- marks the run as "not merged" and exits\\n"}')


# No closing brace and no commit_subject: nothing for the pr-copy extractor to salvage.
TRUNCATED_REPLAY = ('{"type": "chore", "title": "chore(docfix): reap", '
                    '"summary_md": "## Summary\\n- marks')


def _call(tmp_path, normalizer):
    kw = {"normalizer": normalizer} if normalizer else {}
    return _cli(tmp_path).call("pr-copy", "sonnet", "write copy",
                               validate=schemas.validate_pr_copy, max_retries=1, **kw)


def test_cli_mismatched_reply_returns_on_attempt_zero(tmp_path, monkeypatch, shim):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(_fenced(REPLAY_PIN_A1)))
    data = _call(tmp_path, schemas.normalize_pr_copy)
    assert data["type"] == "chore"
    assert data["commit_subject"].startswith("chore(burndown):")
    assert data["title"] == REPLAY_PIN_A1["title"]
    assert _call_count(shim) == 1


def test_cli_mismatched_reply_without_normalizer_still_retries_and_degrades(
        tmp_path, monkeypatch, shim):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(_fenced(REPLAY_PIN_A1)))
    with pytest.raises(HarnessError) as e:
        _call(tmp_path, None)
    assert e.value.kind == "llm-invalid-output"
    assert "must start with its conventional-commit type" in _detail(e)
    assert _call_count(shim) == 2


def test_cli_feat_anywhere_ends_feat_end_to_end(tmp_path, monkeypatch, shim):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(_fenced(REPLAY_SWEEP_A1)))
    data = _call(tmp_path, schemas.normalize_pr_copy)
    assert data["type"] == "feat"
    assert data["title"].startswith("feat(burndown):")
    assert data["commit_subject"].startswith("feat(burndown):")
    assert _call_count(shim) == 1


def test_cli_invalid_json_replay_still_degrades_with_normalizer(tmp_path, monkeypatch, shim):
    with pytest.raises(json.JSONDecodeError):
        json.loads(TRUNCATED_REPLAY)
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(_fenced(TRUNCATED_REPLAY)))
    with pytest.raises(HarnessError) as e:
        _call(tmp_path, schemas.normalize_pr_copy)
    assert e.value.kind == "llm-invalid-output"
    assert _call_count(shim) == 2


def test_cli_wrapped_envelope_reply_degrades_not_false_signal(tmp_path, monkeypatch, shim):
    monkeypatch.setenv("CLAUDE_SHIM_REPLY", _envelope(_fenced({"pr_copy": REPLAY_PIN_A1})))
    with pytest.raises(HarnessError) as e:
        _call(tmp_path, schemas.normalize_pr_copy)
    assert e.value.kind == "llm-invalid-output"
    assert "missing string field" in _detail(e)
    assert _call_count(shim) == 2
