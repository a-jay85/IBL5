"""Default whole-suite harness runs to pytest-xdist workers.

tests/conftest.py imports the hooks below. A run whose positional args are all
directories (the bare `python3 -m pytest` from tools/postplan-harness, or
`tools/postplan-harness/tests/` from the repo root) gets DEFAULT_WORKERS workers
with --dist loadgroup. File or nodeid args, selection filters, debuggers, an
explicit -n, or missing xdist leave the run serial and untouched.
"""
from __future__ import annotations

import os

import pytest

DEFAULT_WORKERS = 8
_DECISION_ATTR = "_harness_parallel_reason"


def decide(*, has_xdist, is_worker, numprocesses, args, invocation_dir,
           keyword="", markexpr="", last_failed=False, stepwise=False,
           usepdb=False, trace=False):
    """Return (workers to set or None, reason). None means leave -n alone."""
    if is_worker:
        return None, "worker"
    if not has_xdist:
        return None, "xdist-unavailable"
    if numprocesses is not None:
        return None, "explicit"
    if usepdb or trace:
        return None, "debugger"
    if keyword or markexpr or last_failed or stepwise:
        return None, "filtered"
    for arg in args:
        if "::" in arg or not os.path.isdir(os.path.join(invocation_dir, arg)):
            return None, "targeted"
    return DEFAULT_WORKERS, "whole-suite"


def apply_default(config):
    opt = config.option
    workers, reason = decide(
        has_xdist=config.pluginmanager.hasplugin("xdist"),
        is_worker=hasattr(config, "workerinput"),
        numprocesses=getattr(opt, "numprocesses", None),
        args=[str(a) for a in config.args],
        invocation_dir=str(config.invocation_params.dir),
        keyword=getattr(opt, "keyword", ""),
        markexpr=getattr(opt, "markexpr", ""),
        last_failed=bool(getattr(opt, "lf", False)),
        stepwise=bool(getattr(opt, "stepwise", False)),
        usepdb=bool(getattr(opt, "usepdb", False)),
        trace=bool(getattr(opt, "trace", False)),
    )
    if reason == "worker":
        return
    if workers is not None:
        opt.numprocesses = workers
    if getattr(opt, "numprocesses", None) and getattr(opt, "dist", "no") == "no":
        opt.dist = "loadgroup"
        # xdist 3.8.0's loadscope family requeues a crashed test, so a restarted worker
        # crashes on it again and the run can hang. Stop at the first crash instead.
        if getattr(opt, "maxworkerrestart", None) is None:
            opt.maxworkerrestart = "0"
    setattr(config, _DECISION_ATTR, reason)


def pytest_configure(config):
    config.addinivalue_line(
        "markers", "xdist_group(name): run on one xdist worker with its group")


@pytest.hookimpl(wrapper=True)
def pytest_cmdline_main(config):
    apply_default(config)
    return (yield)


def pytest_terminal_summary(terminalreporter, exitstatus, config):
    reason = getattr(config, _DECISION_ATTR, None)
    if reason is None:
        return
    opt = config.option
    workers = getattr(opt, "numprocesses", None) or 0
    dist = getattr(opt, "dist", "no")
    terminalreporter.write_line(
        f"harness-pytest: workers={workers} dist={dist} reason={reason}")
