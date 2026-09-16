"""Tests for _discharge_hold_sentences and split_hold_justification (Phase 6).

Each test is named for the mutation it kills.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.state import HarnessError, UsageLedger
from harness.adapters.llm import FixtureLlm


# ---------------------------------------------------------------------------
# Minimal fake objects
# ---------------------------------------------------------------------------

class _NoCallLlm:
    """LLM stub that fails the test if called."""
    def __init__(self):
        self.calls = 0
        self.ledger = UsageLedger()

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.calls += 1
        raise AssertionError(f"LLM must not be called; got purpose={purpose!r}")


class _FakeLlm:
    """LLM stub that returns a canned response for one purpose."""
    def __init__(self, purpose, response):
        self._purpose = purpose
        self._response = response
        self.calls = 0
        self.ledger = UsageLedger()

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.calls += 1
        if purpose != self._purpose:
            raise HarnessError("llm-fixture-missing", purpose)
        validate(self._response)
        return self._response


class _NullProbe:
    """Probe stub that never runs anything."""
    def run(self, argv):
        return False, "null probe"


_log_lines = []


def _log(msg):
    _log_lines.append(msg)


def _run(justification, llm):
    """Call _discharge_hold_sentences via runner import."""
    global _log_lines
    _log_lines = []
    import runner
    return runner._discharge_hold_sentences(llm, _NullProbe(), justification, _log)


# ---------------------------------------------------------------------------
# Test: decision-only section skips LLM
# ---------------------------------------------------------------------------

def test_decision_only_section_skips_llm():
    """A justification with only **Decision:** lines produces (unchanged, [])
    without making any LLM call.  Kills a wiring that spawns on every held PR."""
    justification = (
        "**Decision:** The team has reviewed the tradeoff and accepts this risk.\n"
        "Additional decision prose here.\n"
    )
    llm = _NoCallLlm()
    residual, discharged = _run(justification, llm)

    assert llm.calls == 0, "LLM must not be called for a decision-only section"
    assert residual == justification
    assert discharged == []


# ---------------------------------------------------------------------------
# Test: discharged sentence leaves decision sentence in residual
# ---------------------------------------------------------------------------

def test_discharged_sentence_leaves_residual():
    """A two-sentence section (one decision, one cli-executable) yields a
    residual containing only the decision sentence, and one discharged entry
    with the probe."""
    justification = (
        "**Decision:** The risk is accepted.\n"
        "\n"
        "Verify all docs links resolve."
    )
    canned = [
        {"n": 1, "category": "cli-executable", "probe": ["bin/check-docs"],
         "rationale": "bin/check-docs settles this"},
    ]
    llm = _FakeLlm("hold-discharge", canned)
    residual, discharged = _run(justification, llm)

    assert "**Decision:** The risk is accepted." in residual
    assert "Verify all docs links resolve" not in residual
    assert len(discharged) == 1
    assert discharged[0]["category"] == "cli-executable"
    assert discharged[0]["probe"] == ["bin/check-docs"]
    assert discharged[0]["text"] == "Verify all docs links resolve."


# ---------------------------------------------------------------------------
# Test: empty residual falls back to full section (negative)
# ---------------------------------------------------------------------------

def test_empty_residual_falls_back_to_full_section():
    """Every sentence classified non-decision and no **Decision:** line ⇒ the
    residual is the full original section, not "".  Kills the
    upsert_manual_confirmation-removes-on-empty inversion."""
    justification = "Verify docs links resolve.\nRun the test suite."
    canned = [
        {"n": 1, "category": "cli-executable", "probe": ["bin/check-docs"],
         "rationale": "docs check"},
        {"n": 2, "category": "cli-executable", "probe": ["bin/test-phpunit"],
         "rationale": "phpunit"},
    ]
    llm = _FakeLlm("hold-discharge", canned)
    residual, discharged = _run(justification, llm)

    # The fallback must fire — residual must not be empty.
    assert residual.strip(), (
        "empty residual from non-empty input must fall back to the full section"
    )
    assert residual == justification
    # Discharged is empty because the fallback fires.
    assert discharged == []


# ---------------------------------------------------------------------------
# Test: schema rejection falls back (negative)
# ---------------------------------------------------------------------------

def test_schema_rejection_falls_back():
    """The fake LLM returns a hallucinated category; validate_hold_discharge
    rejects it; the function returns the unchanged section.  Kills a permissive
    validator."""
    justification = "Verify all docs links resolve."

    class _BadLlm:
        def __init__(self):
            self.calls = 0
            self.ledger = UsageLedger()

        def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
            self.calls += 1
            data = [{"n": 1, "category": "automatic"}]
            validate(data)   # will raise HarnessError
            return data      # unreachable

    llm = _BadLlm()
    residual, discharged = _run(justification, llm)

    assert residual == justification
    assert discharged == []
    assert any("LLM call failed" in line for line in _log_lines), (
        "expected a log line when schema validation fails"
    )


# ---------------------------------------------------------------------------
# Test: disallowed probe is dropped from rendering (negative)
# ---------------------------------------------------------------------------

def test_disallowed_probe_is_dropped_not_rendered():
    """A returned probe that fails the allowlist (e.g. 'python3 -c x') means
    the entry is still discharged but the bullet renders without the command."""
    from harness.classify import render_reviewer_verification

    discharged = [
        {
            "text": "Run the verification script.",
            "category": "cli-executable",
            "probe": ["python3", "-c", "import sys; sys.exit(0)"],
            "rationale": "could run inline",
        }
    ]
    block = render_reviewer_verification(discharged)

    assert "python3" not in block, (
        "disallowed probe argv[0] must not appear in rendered block"
    )
    # The entry should still appear as a bullet (it's discharged, just no command)
    assert "Run the verification script." in block
