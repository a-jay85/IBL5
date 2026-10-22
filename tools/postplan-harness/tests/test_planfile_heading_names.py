"""parse_phases: PhaseInfo.heading_words."""
import os
import sys
import textwrap

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.planfile import parse_phases


def _parse(body: str):
    return parse_phases(textwrap.dedent(body))


def test_heading_words_strip_prefix_backticks_and_punctuation():
    ph = _parse("""\
        ## Phase 1: Mask key= values in the Apache access log (Dockerfile ibl5.conf)
        body
        """)[0]
    words = ph.heading_words
    assert "Dockerfile" in words
    assert "ibl5.conf" in words
    assert "key=" not in words
    assert "Phase" not in words
    assert "1" not in words
    assert not any("(" in w or ")" in w for w in words)


def test_heading_words_include_backticked_bare_name():
    ph = _parse("""\
        ## Phase 2: Update `Dockerfile` base image
        body
        """)[0]
    assert "Dockerfile" in ph.heading_words
    assert ph.evidence_paths == []


def test_heading_words_trailing_period_and_tier_marker():
    ph = _parse("""\
        ## Phase 3: Rewrite workflow-continuity.md. [phases: S/S]
        body
        """)[0]
    assert "workflow-continuity.md" in ph.heading_words
    assert "[phases:" not in ph.heading_words
    assert "S/S]" not in ph.heading_words


def test_heading_words_never_from_body():
    ph = _parse("""\
        ## Phase 4: Harden the build

        Edit Dockerfile and ibl5.conf.
        """)[0]
    assert "Dockerfile" not in ph.heading_words
    assert "ibl5.conf" not in ph.heading_words


def test_heading_words_merge_on_duplicate_number():
    phases = _parse("""\
        ## Phase 5: Edit Dockerfile
        one

        ## Phase 5: Edit Makefile
        two
        """)
    assert len(phases) == 1
    assert "Dockerfile" in phases[0].heading_words
    assert "Makefile" in phases[0].heading_words
