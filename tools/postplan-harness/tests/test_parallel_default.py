"""The harness suite's whole-suite xdist default: the decision and its real-run effects."""
from __future__ import annotations

import os
import re
import subprocess
import sys

import pytest

from parallel_default import decide

TESTS_DIR = os.path.dirname(os.path.abspath(__file__))


# ---- decide(): the pure decision ------------------------------------------------------------

def _decide(invocation_dir, **overrides):
    kwargs = dict(has_xdist=True, is_worker=False, numprocesses=None, args=["tests"],
                  invocation_dir=str(invocation_dir))
    kwargs.update(overrides)
    return decide(**kwargs)


@pytest.fixture
def project_dir(tmp_path):
    (tmp_path / "tests").mkdir()
    (tmp_path / "tests" / "test_x.py").write_text("def test_y(): pass\n")
    return tmp_path


def test_decide_whole_suite_dir_args_gets_default_workers(project_dir):
    assert _decide(project_dir, args=["tests"]) == (8, "whole-suite")
    assert _decide(project_dir, args=[str(project_dir / "tests")]) == (8, "whole-suite")


@pytest.mark.parametrize("args", [
    ["tests/test_x.py"],
    ["tests/test_x.py::test_y"],
    ["tests", "tests/test_x.py"],
])
def test_decide_file_and_nodeid_args_stay_serial(project_dir, args):
    assert _decide(project_dir, args=args) == (None, "targeted")


@pytest.mark.parametrize("numprocesses", [0, 4, "auto"])
def test_decide_explicit_numprocesses_wins(project_dir, numprocesses):
    assert _decide(project_dir, numprocesses=numprocesses) == (None, "explicit")


def test_decide_missing_xdist_stays_serial(project_dir):
    assert _decide(project_dir, has_xdist=False) == (None, "xdist-unavailable")


def test_decide_leaves_xdist_worker_untouched(project_dir):
    assert _decide(project_dir, is_worker=True) == (None, "worker")


@pytest.mark.parametrize("selection", [
    {"keyword": "gitad"},
    {"markexpr": "slow"},
    {"last_failed": True},
    {"stepwise": True},
])
def test_decide_selection_filters_stay_serial(project_dir, selection):
    assert _decide(project_dir, **selection) == (None, "filtered")


@pytest.mark.parametrize("debugger", [{"usepdb": True}, {"trace": True}])
def test_decide_debugger_stays_serial(project_dir, debugger):
    assert _decide(project_dir, **debugger) == (None, "debugger")


def test_decide_empty_keyword_is_not_a_filter(project_dir):
    assert _decide(project_dir, keyword="", markexpr="") == (8, "whole-suite")


# ---- Real pytest runs in a throwaway project --------------------------------------------------

def _project(tmp_path, files=None):
    (tmp_path / "pytest.ini").write_text("[pytest]\n")
    (tmp_path / "conftest.py").write_text(
        f"import sys\nsys.path.insert(0, {TESTS_DIR!r})\n"
        "from parallel_default import pytest_cmdline_main, pytest_configure, "
        "pytest_terminal_summary\n")
    (tmp_path / "pkg").mkdir()
    (tmp_path / "pkg" / "test_a.py").write_text(
        "def test_ok(): pass\n\n\ndef test_ok2(): pass\n")
    for name, body in (files or {}).items():
        (tmp_path / name).write_text(body)
    return tmp_path


def _run(project, argv, extra_env=None):
    env = {k: v for k, v in os.environ.items()
           if not k.startswith("PYTEST_XDIST_")
           and k not in ("PYTEST_ADDOPTS", "PYTEST_DISABLE_PLUGIN_AUTOLOAD")}
    env.update(PYTHONDONTWRITEBYTECODE="1")
    env.update(extra_env or {})
    return subprocess.run(
        [sys.executable, "-m", "pytest", "-p", "no:cacheprovider", "-v", *argv],
        cwd=project, env=env, capture_output=True, text=True, timeout=120)


def test_whole_dir_run_spawns_default_workers(tmp_path):
    pytest.importorskip("xdist")
    r = _run(_project(tmp_path), ["pkg"])
    assert r.returncode == 0, r.stdout[-2000:]
    assert "harness-pytest: workers=8 dist=loadgroup reason=whole-suite" in r.stdout
    assert "[gw" in r.stdout
    assert re.search(r"\b2 passed\b", r.stdout)


@pytest.mark.parametrize("argv,extra_env,expected_line,needs_xdist,passed", [
    (["pkg", "-n", "0"], {}, "workers=0 dist=no reason=explicit", True, 2),
    (["pkg", "-p", "no:xdist"], {}, "workers=0 dist=no reason=xdist-unavailable", False, 2),
    (["pkg"], {"PYTEST_DISABLE_PLUGIN_AUTOLOAD": "1"},
     "workers=0 dist=no reason=xdist-unavailable", False, 2),
    (["pkg/test_a.py::test_ok"], {}, "workers=0 dist=no reason=targeted", True, 1),
])
def test_serial_paths_run_without_workers(tmp_path, argv, extra_env, expected_line, needs_xdist,
                                          passed):
    if needs_xdist:
        pytest.importorskip("xdist")
    r = _run(_project(tmp_path), argv, extra_env)
    assert r.returncode == 0, r.stdout[-2000:]
    assert f"harness-pytest: {expected_line}" in r.stdout
    assert "[gw" not in r.stdout
    assert re.search(rf"\b{passed} passed\b", r.stdout)


def test_parallel_run_failure_and_crash_exit_nonzero(tmp_path):
    pytest.importorskip("xdist")
    project = _project(tmp_path)
    (project / "pkg" / "test_fail.py").write_text("def test_boom(): assert False\n")
    (project / "pkg" / "test_crash.py").write_text(
        "import os\n\n\ndef test_crash(): os._exit(3)\n")
    r = _run(project, ["pkg", "-n", "2"])
    assert r.returncode == 1, r.stdout[-2000:]
    assert "crashed" in r.stdout
    assert "test_boom" in r.stdout
    assert re.search(r"\b2 passed\b", r.stdout)


# ---- Grouping --------------------------------------------------------------------------------

def test_gitad_live_module_is_grouped():
    import test_gitad_live
    marks = test_gitad_live.pytestmark
    if not isinstance(marks, list):
        marks = [marks]
    assert any(m.name == "xdist_group" and m.args == ("real_timeout",) for m in marks)
