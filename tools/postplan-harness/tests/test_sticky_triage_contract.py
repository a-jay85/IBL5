"""Contract between the sticky builder (Python) and bin/pr-triage's verdict parser (shell).

`bin/lib/held-prs.sh` `hp_verdict_holds` reads the banner `harness.fidelity.compose_sticky`
writes. A builder change that moves the banner or its arm suffix must break a test here,
not silently clear or hold every PR in the next triage sweep.
"""
import os
import subprocess
import sys
import types

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import sticky_shapes

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(
    os.path.dirname(os.path.abspath(__file__)))))
HELD_PRS = os.path.join(ROOT, "bin", "lib", "held-prs.sh")


def _verdict_holds(body: str) -> str:
    res = subprocess.run(
        ["bash", "-c", 'source "$1"; hp_verdict_holds "$2" ok', "_", HELD_PRS, body],
        capture_output=True, text=True, check=True)
    return res.stdout


def _sticky(tmp_path, decision) -> str:
    inputs = sticky_shapes.pr3267_inputs(tmp_path, v1="READY", v2=None)
    inputs["decision"] = decision
    return sticky_shapes.render_sticky(inputs)


def test_triage_parser_clears_armed_builder_sticky(tmp_path):
    body = _sticky(tmp_path, types.SimpleNamespace(armed=True, holds=[]))
    assert "· auto-merge armed" in body
    assert _verdict_holds(body) == ""


def test_triage_parser_holds_held_builder_sticky(tmp_path):
    decision = types.SimpleNamespace(armed=False, holds=[types.SimpleNamespace(
        number=7, name="plan-auto-merge-hold", reason="plan sets auto_merge: false")])
    body = _sticky(tmp_path, decision)
    assert "· auto-merge held" in body
    assert "verdict-hold:7" in _verdict_holds(body)
