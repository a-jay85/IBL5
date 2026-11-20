"""Replay five real PRs through the `new-query` read-only scan.

PR #3985 added a read-only Repository query and reaches "ok". PR #3815 and PR #3870
each build a stat label into SQL text through `str_replace`, and that label comes from
a public method's argument. The scan holds both, so they stay owner-held. PR #3816 and
PR #3820 touched `ibl5/api.php`, so the path check holds them before any scan runs."""
from pathlib import Path

import pytest

from harness.sql_equivalence import parse_added_lines
from harness.sql_readonly_scan import SourcePair, scan_sources

FIXTURES = Path(__file__).parent / "fixtures" / "sql_readonly_replay"


def _load(pr: str) -> tuple[list[str], dict[str, SourcePair]]:
    d = FIXTURES / pr
    changed = d.joinpath("changed_files.txt").read_text().split()
    files = {}
    for path in changed:
        if not path.endswith("Repository.php"):
            continue
        stem = Path(path).stem
        base = d / "base" / f"{stem}.php.txt"
        head = (d / "head" / f"{stem}.php.txt").read_text()
        files[path] = SourcePair(
            base.read_text() if base.exists() else None,
            head,
            parse_added_lines((d / f"{stem}.diff").read_text(), len(head.splitlines())),
        )
    return changed, files


@pytest.mark.parametrize("pr", ["3985"])
def test_replay_target_prs_pass(pr):
    changed, files = _load(pr)
    assert scan_sources("new-query", changed, files) == "ok"


@pytest.mark.parametrize("pr, note", [
    # the batch method calls helpers this PR changed, so its unchanged SQL is judged again
    ("3815", "scan: unbound interpolation implode(...) at "
             "ibl5/classes/RecordHolders/PlayerRecordRepository.php:214"),
    ("3870", "scan: unbound interpolation $safeLabel at "
             "ibl5/classes/RecordHolders/TeamRecordRepository.php:314"),
])
def test_replay_target_prs_stay_held(pr, note):
    """Phase 6 step 3: a target that still fails after real bug fixes flips to a hold."""
    changed, files = _load(pr)
    assert scan_sources("new-query", changed, files) == note


@pytest.mark.parametrize("pr", ["3816", "3820"])
def test_replay_auth_prs_hold(pr):
    changed, files = _load(pr)
    assert scan_sources("new-query", changed, files).startswith(
        "new-query: path outside Repository/tests: ibl5/")
