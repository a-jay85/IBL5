"""Corpus diff for the result.json `error` redaction (backlog#1288).

CI half: committed error shapes go through the real writer, runner._finish.
Local half: every out/*/result.json already on disk must be stable under
runner._redact, so the fix rewrites nothing a clean run stored.
"""
import glob
import json
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner  # noqa: E402
from harness.state import RunResult, TerminalState  # noqa: E402

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

_ALNUM36 = "A1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6Q7r8"
CLEAN_ERRORS = [
    "local-gate: push rejected by pre-push hook",
    "rebase-conflict: CONFLICT (content): Merge conflict in ibl5/classes/Foo.php",
    "llm-fixture-missing: pr-copy",
    "ci-failed: Python tests",
    "push-failed: ! [rejected] HEAD -> branch (fetch first)",
]
DIRTY_ERRORS = [  # (stored error, secret that must not reach disk)
    (f"push-failed: fatal: unable to access 'https://x:ghp_{_ALNUM36}@github.com/a/b.git/'",
     f"ghp_{_ALNUM36}"),
    (f"push-failed: remote: https://oauth2:gho_{_ALNUM36}@github.com/a/b.git",
     f"gho_{_ALNUM36}"),
    (f"gh-api: token github_pat_{_ALNUM36}_{_ALNUM36} rejected", f"github_pat_{_ALNUM36}"),
    (f"gh-api: Authorization: Bearer {_ALNUM36}", _ALNUM36),
]


@pytest.mark.parametrize("error", CLEAN_ERRORS)
def test_finish_clean_error_shapes_byte_identical(tmp_path, error):
    res = RunResult(terminal=TerminalState.FAILED, error=error)
    runner._finish(res, str(tmp_path))
    assert (tmp_path / "result.json").read_text() == res.to_json()


@pytest.mark.parametrize("error,secret", DIRTY_ERRORS)
def test_finish_dirty_error_shapes_redacted(tmp_path, error, secret):
    res = RunResult(terminal=TerminalState.FAILED, error=error)
    runner._finish(res, str(tmp_path))
    stored = json.loads((tmp_path / "result.json").read_text())["error"]
    assert secret not in stored
    assert stored.split(":", 1)[0] == error.split(":", 1)[0]


@pytest.mark.parametrize("error,secret", DIRTY_ERRORS)
def test_redact_is_idempotent_on_dirty_shapes(error, secret):
    once = runner._redact(error)
    assert runner._redact(once) == once


def _corpus_dir() -> str | None:
    local = os.path.join(ROOT, "out")
    if glob.glob(os.path.join(local, "*", "result.json")):
        return local
    proc = subprocess.run(["git", "rev-parse", "--path-format=absolute", "--git-common-dir"],
                          cwd=ROOT, capture_output=True, text=True)
    if proc.returncode != 0:
        return None
    main = os.path.join(os.path.dirname(proc.stdout.strip()), "tools", "postplan-harness", "out")
    if glob.glob(os.path.join(main, "*", "result.json")):
        return main
    return None


def test_result_json_corpus_error_fields_are_redaction_stable():
    corpus = _corpus_dir()
    if corpus is None:
        pytest.skip("SKIP: no local tools/postplan-harness/out/*/result.json corpus; "
                    "CI covers _finish via the committed error-shape corpus")
    n_files = n_err = n_bad_json = 0
    changed: list[tuple[str, str]] = []
    for path in sorted(glob.glob(os.path.join(corpus, "*", "result.json"))):
        n_files += 1
        try:
            with open(path) as fh:
                blob = json.load(fh)
        except ValueError:
            n_bad_json += 1
            continue
        if not isinstance(blob, dict):
            continue
        fields: list[tuple[str, str]] = []
        err = blob.get("error")
        if isinstance(err, str) and err:
            fields.append(("error", err))
        ti = blob.get("thread_ingestion")
        if isinstance(ti, dict) and isinstance(ti.get("error"), str) and ti["error"]:
            fields.append(("thread_ingestion.error", ti["error"]))
        audit = blob.get("audit")
        if isinstance(audit, list):
            fields.extend(("audit", ln) for ln in audit if isinstance(ln, str))
        for name, value in fields:
            if name != "audit":
                n_err += 1
            if runner._redact(value) != value:
                changed.append((path, name))
    print(f"corpus: {n_files} result.json, {n_err} error fields, {n_bad_json} unparseable, "
          f"{len(changed)} changed")
    assert not changed, f"unredacted credentials in: {[p for p, _ in changed[:5]]}"
