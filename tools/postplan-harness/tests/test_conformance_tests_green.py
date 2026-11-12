"""`stop_condition: tests-green` against Phase 5 statuses once phpunit and shellcheck
report "ci" (CI covers them). "skipped" meets the contract only on a diff that carries
a shell file; a docs-only "skipped", "fail" and None stay unmet."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness.classify import is_shell_path
from harness.state import PlanInfo

SHELL = "bin/lib/shell-scripts.sh"
README = "tools/postplan-harness/README.md"
PREFIX = "UNMET-CONTRACT: stop_condition tests-green"


def _plan():
    return PlanInfo(found=True, stop_condition="tests-green")


def test_tests_green_met_on_pass_status():
    assert conformance._contract_items(_plan(), ["ibl5/classes/Foo.php"], "pass") == []


def test_tests_green_met_on_skipped_shell_only_diff():
    assert is_shell_path(SHELL)
    assert conformance._contract_items(_plan(), [SHELL, README], "skipped") == []


def test_tests_green_unmet_on_skipped_docs_only_diff():
    assert not is_shell_path(README)
    items = conformance._contract_items(_plan(), [README], "skipped")
    assert len(items) == 1
    assert items[0].startswith(PREFIX)
    assert items[0].endswith("PHASE5_VERIFY_STATUS=skipped")


def test_tests_green_unmet_on_fail_or_none_with_shell_file():
    fail = conformance._contract_items(_plan(), [SHELL], "fail")
    assert len(fail) == 1
    assert fail[0].startswith(PREFIX)
    assert fail[0].endswith("PHASE5_VERIFY_STATUS=fail")
    none = conformance._contract_items(_plan(), [SHELL], None)
    assert len(none) == 1
    assert none[0].startswith(PREFIX)
    assert none[0].endswith("PHASE5_VERIFY_STATUS=none")
