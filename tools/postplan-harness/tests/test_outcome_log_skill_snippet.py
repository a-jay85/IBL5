import os
import re
import subprocess

import pytest

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
DOC = os.path.join(REPO, ".claude", "skills", "post-plan", "_phase-4-review-audit.md")
MARKER = "# phase 4.5 outcome log"
DONE_LINE = "phase 4.5: outcome log done"


def extract_block(text):
    """The one fenced block whose first line is the marker. Raises unless exactly one."""
    blocks = [b for b in re.findall(r"```bash\n(.*?)```", text, re.S)
              if b.splitlines() and b.splitlines()[0].strip() == MARKER]
    if len(blocks) != 1:
        raise AssertionError(f"expected exactly one '{MARKER}' block, found {len(blocks)}")
    return blocks[0]


def _doc():
    with open(DOC, encoding="utf-8") as fh:
        return fh.read()


def _run(tmp_path, block):
    env = {"PATH": os.environ["PATH"], "PR": "123"}
    return subprocess.run(["bash", "-e", "-o", "pipefail", "-c", block], cwd=tmp_path, env=env,
                          capture_output=True, text=True)


def _stub(tmp_path, rel, body):
    path = tmp_path / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(body)
    path.chmod(0o755)


def test_skill_outcome_block_swallows_failing_cli(tmp_path):
    _stub(tmp_path, "bin/review-outcomes", "#!/usr/bin/env bash\necho junk\nexit 1\n")
    _stub(tmp_path, "bin/lib/post-review-findings.sh", "prf_review_threads() { return 1; }\n")
    proc = _run(tmp_path, extract_block(_doc()))
    assert proc.returncode == 0, proc.stderr
    assert DONE_LINE in proc.stdout


def test_skill_outcome_block_without_wrapper_is_silent(tmp_path):
    proc = _run(tmp_path, extract_block(_doc()))
    assert proc.returncode == 0, proc.stderr
    assert proc.stdout.strip() == DONE_LINE


def test_skill_outcome_block_marker_unique():
    text = _doc()
    block = extract_block(text)
    with pytest.raises(AssertionError):
        extract_block(text + "\n```bash\n" + block + "```\n")
