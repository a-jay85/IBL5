"""parse_phases: the `**No diff:**` phase marker."""
import os
import sys
import textwrap

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import planfile


def _parse(body: str):
    return planfile.parse_phases(textwrap.dedent(body))


def test_no_diff_marker_sets_reason():
    phases = _parse("""\
        ## Phase 1: Close it

        Touch `bin/wt-up` later.

        **No diff:** closes the backlog issue through the PR body

        ## Phase 2: Other
        """)
    assert phases[0].no_diff_reason == "closes the backlog issue through the PR body"
    assert phases[0].no_diff_rejected is False
    assert "bin/wt-up" in phases[0].evidence_paths


def test_no_diff_bullet_form_accepted():
    phases = _parse("""\
        ## Phase 1: Queue it

        - **No diff:** queues the parked plan with bin/automouse/queue
        """)
    assert phases[0].no_diff_reason == "queues the parked plan with bin/automouse/queue"


def test_no_diff_short_reason_rejected():
    for body in ("**No diff:** done", "**No diff:**"):
        phases = _parse(f"## Phase 1: X\n\n{body}\n")
        assert phases[0].no_diff_reason == ""
        assert phases[0].no_diff_rejected is True


def test_no_diff_in_fenced_block_ignored():
    phases = _parse("""\
        ## Phase 1: X

        ```
        **No diff:** a long enough reason text here
        ```
        """)
    assert phases[0].no_diff_reason == ""
    assert phases[0].no_diff_rejected is False


def test_no_diff_mid_sentence_and_inline_code_ignored():
    phases = _parse("""\
        ## Phase 1: X

        Write the `**No diff:**` line here.
        We rely on **No diff:** semantics for this one.
        """)
    assert phases[0].no_diff_reason == ""
    assert phases[0].no_diff_rejected is False


def test_no_diff_marker_scoped_to_its_phase():
    phases = _parse("""\
        ## Phase 1: X

        **No diff:** closes the backlog issue through the PR body

        ## Phase 2: Y

        Edit `bin/wt-up`.
        """)
    assert phases[0].no_diff_reason
    assert phases[1].no_diff_reason == ""
    assert phases[1].no_diff_rejected is False


def test_no_diff_lowercase_not_honoured():
    phases = _parse("""\
        ## Phase 1: X

        **no diff:** a long enough reason text here
        """)
    assert phases[0].no_diff_reason == ""
    assert phases[0].no_diff_rejected is False
