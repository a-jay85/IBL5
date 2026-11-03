"""bin/post-plan-now passes POSTPLAN_FIX_ROUND through to the harness, beside POSTPLAN_CONFORMANCE_FIX."""
from test_post_plan_now_fallback import _generate_cmd, _run_foreground

_PROBE_HARNESS = (
    '#!/bin/sh\nprintf \'%s\' "${POSTPLAN_FIX_ROUND-unset}" > "$FORCE_PROBE"\nexit 0\n')


def _probe(tmp_path, monkeypatch, value):
    probe = tmp_path / "fr-probe.txt"
    monkeypatch.setenv("FORCE_PROBE", str(probe))
    if value is None:
        monkeypatch.delenv("POSTPLAN_FIX_ROUND", raising=False)
    else:
        monkeypatch.setenv("POSTPLAN_FIX_ROUND", value)
    _run_foreground(tmp_path, 0, with_claude_stub=True, harness_body=_PROBE_HARNESS)
    return probe.read_text()


def test_fix_round_env_unset_passes_empty(tmp_path, monkeypatch):
    assert _probe(tmp_path, monkeypatch, None) == ""


def test_fix_round_env_empty_passes_empty(tmp_path, monkeypatch):
    assert _probe(tmp_path, monkeypatch, "") == ""


def test_fix_round_env_zero_disables(tmp_path, monkeypatch):
    assert _probe(tmp_path, monkeypatch, "0") == "0"


def test_fix_round_env_beside_confix(tmp_path):
    cmd = _generate_cmd(tmp_path)
    assert "POSTPLAN_CONFORMANCE_FIX='' POSTPLAN_FIX_ROUND='' POSTPLAN_LOG_PATH=" in cmd
    zero = _generate_cmd(tmp_path / "zero", extra_env={"POSTPLAN_FIX_ROUND": "0"})
    assert "POSTPLAN_FIX_ROUND='0' POSTPLAN_LOG_PATH=" in zero
