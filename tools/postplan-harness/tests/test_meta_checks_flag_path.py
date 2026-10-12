"""The pre-push meta-checks flag path helper and its injectable directory."""
from __future__ import annotations

import os
import sys
import unittest.mock as mock

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

import runner
from harness.adapters.gitad import ReplayGit
from harness.armable import meta_checks_flag_path


def test_meta_checks_flag_path_matches_legacy_spelling():
    assert meta_checks_flag_path("feat/x-y") == "/tmp/ibl5-meta-checks-prepush-feat-x-y.failed"
    assert meta_checks_flag_path("a/b", tmp_dir="/t") == "/t/ibl5-meta-checks-prepush-a-b.failed"


def test_runner_honours_meta_checks_tmp_dir(tmp_path, monkeypatch):
    monkeypatch.setattr(runner, "META_CHECKS_TMP_DIR", str(tmp_path))
    git = ReplayGit({"slug": "rearm/tmpdir-probe"})

    def fake_run(argv, **kwargs):
        return mock.MagicMock(returncode=1, stdout="META-CHECK-FAILED: x\n", stderr="")

    with mock.patch.object(runner.subprocess, "run", fake_run), \
         mock.patch.object(runner, "_remediate_doc_staleness", return_value=0):
        result = runner.run_meta_checks_local(
            git, str(tmp_path), "origin/master", lambda m: None, live=True)

    assert result is False
    flag = tmp_path / "ibl5-meta-checks-prepush-rearm-tmpdir-probe.failed"
    assert flag.exists()
    assert not os.path.exists("/tmp/ibl5-meta-checks-prepush-rearm-tmpdir-probe.failed")
