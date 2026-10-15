"""Phase-evidence tests for the `NNN_` / `NNNN-` number-placeholder tokens.

Each test is named for the mutation it kills. The `(example)` marker normally drops a
token from evidence; the two contract placeholder shapes are the one exception.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import planfile


def test_non_placeholder_example_token_stays_skipped():
    """Mutation caught: delete the `_EXAMPLE_SUFFIX_RE` skip in `_phase_evidence_paths`."""
    body = "Create `ibl5/modules/Foo/index.php` (example) — NEW, then edit `ibl5/modules.php`."
    assert planfile._phase_evidence_paths(body) == ["ibl5/modules.php"]
