"""Held-set parity for condition (18) across the shell and Python twins.

LEGACY_BODIES holds lines the pre-self-governing code rendered (pasted from a master run, never
typed from source). The held set they pin is the bar the new code must meet: every row held
before is held after, in both `pr_merge_danger_hold` and `armable.evaluate`.
"""
import os
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import merge_danger
from harness.armable import evaluate
from harness.gate_backtest import GateChange
from test_armable import inputs

REPO = Path(__file__).resolve().parents[3]

_PFX = "**Merge danger:** "
_L1 = _PFX + "Door: two-way · Blast radius: localized · Why: mechanical: no one-way trigger in the realized diff"
_L2 = _PFX + "Door: one-way · Blast radius: site-wide · Why: mechanical: arming-path:bin/lib/pr-armable.sh"

LEGACY_BODIES = [
    ("L1", "## Summary\nx\n\n" + _L1, False),
    ("L2", "## Summary\nx\n\n" + _L2, True),
    ("L3", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: site-wide · Why: mechanical: gate-removed:bin/check-foo", True),
    ("L4", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: site-wide · Why: mechanical: "
       "destructive-migration:ibl5/migrations/200_drop_col.sql", True),
    ("L5", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: external · Why: mechanical: external-path:bin/db-sync-prod", True),
    ("L6", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: external · Why: mechanical: "
       "notify-workflow:.github/workflows/nightly.yml", True),
    ("L7", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: site-wide · Why: mechanical: "
       "destructive-migration:ibl5/migrations/200_drop_col.sql; arming-path:bin/lib/pr-armable.sh", True),
    ("L8", "## Summary\nx\n\nno merge danger line here\n", True),
    ("L9", "## Summary\nx\n\n" + _L1 + "\n" + _L1, True),
    ("L10", "## Summary\nx\n\n" + _PFX
     + "Door: one-way · Blast radius: site-wide · Why: mechanical: arming-path:bin/lib/pr-armable.sh. "
       "edits the arming gate itself, so the PR governs its own merge", True),
]


def _shell_token(body):
    return subprocess.run(
        ["bash", "-c", 'source "$1/bin/lib/pr-armable.sh" && pr_merge_danger_hold "$2"', "_", str(REPO), body],
        capture_output=True, text=True, check=True).stdout.strip()


def _shell_state(body):
    tok = _shell_token(body)
    return "TWO-WAY" if tok == "" else tok.split("merge-danger:", 1)[1]


def _python_held(state):
    return any(c.number == 18 for c in evaluate(inputs(merge_danger_state=state)).holds)


def test_legacy_held_set_parity_both_twins():
    for label, body, held_before in LEGACY_BODIES:
        assert _python_held(merge_danger.classify_body(body)) == held_before, label
        assert (_shell_token(body) != "") == held_before, label


def _mig_diff(path, *added):
    lines = [f"diff --git a/{path} b/{path}", "new file mode 100644", "--- /dev/null", f"+++ b/{path}",
             "@@ -0,0 +1 @@"]
    lines += [f"+{a}" for a in added]
    return "\n".join(lines) + "\n"


_MIG = "ibl5/migrations/200_drop_col.sql"
_WF = ".github/workflows/nightly.yml"
_DROP = _mig_diff(_MIG, "ALTER TABLE ibl_plr DROP COLUMN foo;")
_NOTIFY_READ = (lambda p: "env:\n  DISCORD_WEBHOOK: x\n" if p == _WF else None)

# Same inputs as LEGACY_BODIES L1 to L7: (label, changed, diff, gates, read_candidate).
DIFF_FIXTURES = [
    ("L1", [("M", "ibl5/classes/Foo/Bar.php")], "", [], lambda p: None),
    ("L2", [("M", "bin/lib/pr-armable.sh")], "", [], lambda p: None),
    ("L3", [("D", "bin/check-foo")], "",
     [GateChange("bin/check-foo", "check-script", "removed", None, "gate deleted")], lambda p: None),
    ("L4", [("A", _MIG)], _DROP, [], lambda p: None),
    ("L5", [("M", "bin/db-sync-prod")], "", [], lambda p: None),
    ("L6", [("M", _WF)], "", [], _NOTIFY_READ),
    ("L7", [("M", "bin/lib/pr-armable.sh"), ("A", _MIG)], _DROP, [], lambda p: None),
]


def _new_body(changed, diff, gates, read):
    floor = merge_danger.derive_merge_danger(changed, diff, gates, read)
    return "## Summary\nx\n\n" + merge_danger.render_merge_danger(floor)


def test_new_held_set_matches_legacy_both_twins():
    legacy = {label: held for label, _, held in LEGACY_BODIES}
    # L6 was the notify-workflow trigger. Workflow alerts reach only the owner's channels, so
    # the floor now derives it two-way on purpose. The legacy L6 body itself still holds.
    legacy["L6"] = False
    for label, changed, diff, gates, read in DIFF_FIXTURES:
        body = _new_body(changed, diff, gates, read)
        assert _python_held(merge_danger.classify_body(body)) == legacy[label], label
        assert (_shell_token(body) != "") == legacy[label], label


_SEG_PFX = _PFX + "Door: two-way · Blast radius: localized"
_WHY = " · Why: mechanical: arming-path:bin/lib/pr-armable.sh"
NEAR_MISS_BODIES = [
    ("seg-no", "## Summary\nx\n\n" + _SEG_PFX + " · Self-governing: no" + _WHY),
    ("seg-Yes", "## Summary\nx\n\n" + _SEG_PFX + " · Self-governing: Yes" + _WHY),
]
AFTER_WHY_BODY = "## Summary\nx\n\n" + _SEG_PFX + _WHY + " · Self-governing: yes"
PROSE_ONLY_BODY = ("## Summary\nx\n\n" + _SEG_PFX
                   + " · Why: a self-governing helper rename with no behavior change at all")


def test_near_miss_value_markers_malformed_both_twins():
    for label, body in NEAR_MISS_BODIES:
        assert merge_danger.classify_body(body) == "MALFORMED", label
        assert _shell_state(body) == "MALFORMED", label
        assert _python_held(merge_danger.classify_body(body)), label


def test_marker_after_why_malformed_both_twins():
    assert merge_danger.classify_body(AFTER_WHY_BODY) == "MALFORMED"
    assert _shell_state(AFTER_WHY_BODY) == "MALFORMED"


_SHELL_AFTER_WHY_CASE = """*' · Self-governing:'*) echo "merge-danger:MALFORMED\""""


def test_shell_after_why_case_is_load_bearing(tmp_path):
    # Shell mutation of row 10: rename the misplaced-segment case pattern. The same body then
    # reads TWO-WAY, so the case is what makes the shell twin hold it.
    src = (REPO / "bin/lib/pr-armable.sh").read_text()
    assert src.count(_SHELL_AFTER_WHY_CASE) == 1
    mutated = tmp_path / "pr-armable.sh"
    mutated.write_text(src.replace(_SHELL_AFTER_WHY_CASE,
                                   """*' · NO-SUCH-SEGMENT:'*) echo "merge-danger:MALFORMED\""""))
    out = subprocess.run(
        ["bash", "-c", 'source "$1" && pr_merge_danger_hold "$2"', "_", str(mutated), AFTER_WHY_BODY],
        capture_output=True, text=True, check=True).stdout.strip()
    assert out == ""


def test_shell_python_state_parity():
    bodies = [(label, body) for label, body, _ in LEGACY_BODIES]
    bodies += [(label, _new_body(c, d, g, r)) for label, c, d, g, r in DIFF_FIXTURES]
    bodies += NEAR_MISS_BODIES + [("after-why", AFTER_WHY_BODY), ("prose-only", PROSE_ONLY_BODY)]
    for label, body in bodies:
        assert _shell_state(body) == merge_danger.classify_body(body), label
    states = {label: merge_danger.classify_body(_new_body(c, d, g, r))
              for label, c, d, g, r in DIFF_FIXTURES}
    assert states["L2"] == states["L3"] == "SELF-GOVERNING"
    assert states["L7"] == "ONE-WAY"
    assert _shell_state(PROSE_ONLY_BODY) == merge_danger.classify_body(PROSE_ONLY_BODY) == "TWO-WAY"


def test_shell_docs_refresh_self_governing():
    body = _new_body(*DIFF_FIXTURES[1][1:])
    state = _shell_state(body)
    out = subprocess.run(
        ["bash", "-c", 'source "$1/bin/lib/pr-armable.sh" && pr_docs_refresh_hold "$2" "$3" "$4" "$5"',
         "_", str(REPO), "docs-stale-refresh-20261015", '[{"path": "ibl5/docs/guide.md"}]', state, "READY"],
        capture_output=True, text=True, check=True).stdout.strip().splitlines()
    assert out == ["docs-refresh:merge-danger:SELF-GOVERNING"]


_SG_FIX = Path(__file__).parent / "fixtures" / "merge_danger"
_SG_LINES = ("pr3829-merge-danger-line.txt", "pr3832-merge-danger-line.txt")


def _sg_line(name):
    return (_SG_FIX / name).read_text(encoding="utf-8").rstrip("\n")


@pytest.mark.parametrize("name", _SG_LINES)
def test_pin_sg_lines_python(name):
    assert merge_danger.classify_body(_sg_line(name)) == "SELF-GOVERNING"


@pytest.mark.parametrize("name", _SG_LINES)
def test_pin_sg_lines_shell(name):
    assert "SELF-GOVERNING" in _shell_token(_sg_line(name))


# --- arming replay: the shell twin reads only the body the Python side writes ---------------

from harness.arming_replay import ReplayVerdict  # noqa: E402

_PY_ARM = "tools/postplan-harness/harness/armable.py"


def _body(d):
    seg = " · Self-governing: yes" if d.self_governing else ""
    return ("## Summary\nx\n\n**Merge danger:** "
            f"Door: {d.door} · Blast radius: {d.blast}{seg} · Why: {d.why}")


def _replayed(changed, replay):
    return merge_danger.derive_merge_danger(changed, "", [], lambda _p: None, replay=replay)


def _clear(_paths):
    return ReplayVerdict(True, "replay cleared arming-path (48 inputs: 0 loosened "
                         "(0 held->armed), 0 tightened (0 armed->held))", "clean")


def _loosened(_paths):
    return ReplayVerdict(False, "replay kept arming-path (48 inputs: 1 loosened "
                         "(1 held->armed), 0 tightened (0 armed->held))", "loosened")


def _raising(_paths):
    raise RuntimeError("fixture replay crash")


def test_replay_cleared_body_arms_in_shell_and_python():
    d = _replayed([("M", _PY_ARM)], _clear)
    body = _body(d)
    assert "merge-danger:SELF-GOVERNING" not in _shell_token(body)
    assert _shell_state(body) == "TWO-WAY"
    assert not _python_held(merge_danger.classify_with_floor(body, d))


@pytest.mark.parametrize("fake", [_loosened, _raising])
def test_replay_kept_body_holds_in_shell_and_python(fake):
    d = _replayed([("M", _PY_ARM)], fake)
    body = _body(d)
    assert "merge-danger:SELF-GOVERNING" in _shell_token(body)
    assert _python_held(merge_danger.classify_with_floor(body, d))


def test_shell_path_with_replay_supplied_holds_in_both():
    d = _replayed([("M", "bin/lib/pr-armable.sh")], _clear)
    body = _body(d)
    assert "merge-danger:SELF-GOVERNING" in _shell_token(body)
    assert _python_held(merge_danger.classify_with_floor(body, d))
