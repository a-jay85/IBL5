"""bin/lib/pr-armable.sh pr_pipeline_authored_hold mirrors the Python clear for condition 10."""
import json
import os
import re
import subprocess

from harness import armable

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))
LIB = os.path.join(REPO, "bin/lib/pr-armable.sh")
TREE = "a" * 40
LABELS = json.dumps([{"name": "pipeline-authored"}])
CLEAN = f"CLEARS 10: the diff only touches a linter\nVERDICT: CLEAN\nREVIEWED_TREE={TREE}\n"


def _call(*args):
    proc = subprocess.run(
        ["bash", "-c", 'source "$0"; pr_pipeline_authored_hold "$@"', LIB, *args],
        capture_output=True, text=True, check=True)
    return proc.stdout.strip()


def _files(tmp_path, verdict, paths):
    v = tmp_path / "verdict.md"
    v.write_text(verdict)
    f = tmp_path / "files.txt"
    f.write_text("".join(p + "\n" for p in paths))
    return str(v), str(f)


def test_shell_pipeline_hold_ui_stays_held(tmp_path):
    v, f = _files(tmp_path, CLEAN, ["bin/check-prose", "ibl5/design/input.css"])
    assert _call(LABELS, "", v, TREE, f) == "pipeline-authored"
    v, f = _files(tmp_path, CLEAN, ["ibl5/classes/Foo/FooView.php"])
    assert _call(LABELS, "", v, TREE, f) == "pipeline-authored"


def test_shell_pipeline_hold_non_ui_clears_with_fresh_verdict(tmp_path):
    v, f = _files(tmp_path, CLEAN, ["bin/check-prose"])
    assert _call(LABELS, "", v, TREE, f) == ""
    assert _call(LABELS, "bug-12-x", v, TREE, f) == ""


def test_shell_pipeline_hold_without_verdict_holds(tmp_path):
    assert _call(LABELS) == "pipeline-authored"
    assert _call(LABELS, "bug-12-x") == "pipeline-authored\npipeline-branch"
    assert _call("[]", "bug-12-x") == "pipeline-branch"
    v, f = _files(tmp_path, CLEAN, ["bin/check-prose"])
    assert _call(LABELS, "", v, "b" * 40, f) == "pipeline-authored"      # stale tree
    assert _call(LABELS, "", v, "", f) == "pipeline-authored"            # empty tree
    assert _call(LABELS, "", str(tmp_path / "gone.md"), TREE, f) == "pipeline-authored"
    assert _call(LABELS, "", v, TREE) == "pipeline-authored"             # no files list


def test_shell_pipeline_hold_near_miss_verdicts_hold(tmp_path):
    for verdict in (f"VERDICT: CLEAN\nREVIEWED_TREE={TREE}\n",                      # no CLEARS 10
                    f"CLEARS 10: short\nVERDICT: CLEAN\nREVIEWED_TREE={TREE}\n",    # reason too short
                    f"CLEARS 7: the diff only touches a linter\nVERDICT: CLEAN\nREVIEWED_TREE={TREE}\n",
                    f"CLEARS 10: the diff only touches a linter\nVERDICT: CLEAN and more\nREVIEWED_TREE={TREE}\n",
                    f"CLEARS 10: the diff only touches a linter\nVERDICT: REJECT no\nREVIEWED_TREE={TREE}\n",
                    "CLEARS 10: the diff only touches a linter\nVERDICT: CLEAN\n"):  # no tree line
        v, f = _files(tmp_path, verdict, ["bin/check-prose"])
        assert _call(LABELS, "", v, TREE, f) == "pipeline-authored", verdict


def test_shell_silent_when_no_axis_fires(tmp_path):
    assert _call("[]") == ""
    assert _call("[]", "bug-pipeline-reproduce-gate") == ""


def _shell_array(name):
    with open(LIB) as fh:
        text = fh.read()
    body = re.search(rf"^{name}=\((.*?)^\)", text, re.S | re.M).group(1)
    return tuple(re.findall(r'"([^"]+)"', body))


def test_shell_ui_prefixes_match_python():
    assert _shell_array("PR_ARMABLE_UI_PREFIXES") == armable.UI_PATH_PREFIXES
    assert _shell_array("PR_ARMABLE_UI_SUFFIXES") == armable.UI_PATH_SUFFIXES
