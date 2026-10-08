"""Required Test Methods parser: hyphenated labels (M1) and inline lists (M2), plus the
conformance literal-presence check for hyphenated labels."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import pytest

from harness import conformance
from harness.conformance import check
from harness.planfile import parse_required_test_methods
from harness.state import PlanInfo


@pytest.fixture(autouse=True)
def _tracked_files(monkeypatch):
    monkeypatch.setattr(conformance, "_tracked_files", lambda *a, **k: None)


def _methods(*bullets: str) -> list[str]:
    body = "\n".join(bullets)
    return parse_required_test_methods(
        f"## Required Test Methods\n\n{body}\n\n## Some Other Section\n- `ignored_one`\n")


def test_hyphenated_backticked_label_yields_full_token():
    got = _methods("- `step67-skipped-no-plan`", "- `prefire-rule-staged-new-file-realised`")
    assert got == ["step67-skipped-no-plan", "prefire-rule-staged-new-file-realised"]
    assert "step67" not in got
    assert "prefire" not in got


def test_hyphenated_label_with_trailing_dash_clause():
    assert _methods("- `step67-clean` — covered by bin/test-plan-now") == ["step67-clean"]


def test_hyphenated_token_malformed_rejected():
    assert _methods("- `step67-a/b.sh`", "- `foo- bar`", "- `foo-`") == []


def test_plain_backticked_names_unchanged():
    assert _methods("- `testFoo`", "- `testBar()`", "- `test_snake`") == \
        ["testFoo", "testBar", "test_snake"]


def test_label_colon_inline_backticked_list_yields_names():
    got = _methods("- New: `testA`, `testB`", "- Supplementary: `test_x`, `test_y`, `test_z`.")
    assert got == ["testA", "testB", "test_x", "test_y", "test_z"]
    assert "New" not in got
    assert "Supplementary" not in got


def test_label_colon_single_backticked_item():
    assert _methods("- New: `testOnly`") == ["testOnly"]


def test_label_colon_items_with_parens():
    assert _methods("- Added: `testA()`, `testB()`") == ["testA", "testB"]


def test_label_colon_prose_after_colon_unchanged():
    assert _methods("- testFoo: covers `bar`") == ["testFoo"]
    assert _methods("- New: `testA` and `testB`") == ["New"]


def test_label_colon_path_items_narrowness_pin():
    assert _methods("- Files: `ibl5/tests/X.php`, `bin/y`") == ["Files"]


def test_new_shapes_inside_fence_ignored():
    content = ("## Required Test Methods\n\n```\n- `step67-clean`\n- New: `testA`, `testB`\n```\n\n"
               "## Some Other Section\n")
    assert parse_required_test_methods(content) == []


# --- conformance: literal presence for hyphenated labels --------------------------------

def _plan(*names: str) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, required_test_methods=list(names))


def _method_items(items):
    return [i for i in items if i.startswith("MISSING-METHOD:")]


def test_hyphen_label_present_in_diff_body_clears():
    diff = "+++ b/bin/test-plan-now\n+  step67-skipped-no-plan)\n"
    items = check(_plan("step67-skipped-no-plan"), ["bin/test-plan-now"], diff)
    assert _method_items(items) == []


def test_hyphen_label_present_only_in_changed_file_tree_clears_with_note():
    diff = "+++ b/bin/test-plan-now\n+  echo unrelated\n"
    tree = {"bin/test-plan-now": "#!/bin/bash\nrun_case step67-skipped-no-plan\n"}
    notes: list[str] = []
    items = check(_plan("step67-skipped-no-plan"), ["bin/test-plan-now"], diff,
                  notes=notes, read_file=tree.get)
    assert _method_items(items) == []
    assert any(n.startswith("METHOD-IN-TREE: step67-skipped-no-plan — declared in bin/test-plan-now")
               for n in notes)


def test_hyphen_label_absent_everywhere_still_holds():
    diff = "+++ b/bin/test-plan-now\n+  step67-skipped-no-plan)\n+  step67-never)\n"
    tree = {"bin/test-plan-now": "run_case step67-never-writte\n"}
    items = check(_plan("step67-never-written"), ["bin/test-plan-now"], diff,
                  read_file=tree.get)
    assert _method_items(items) == [
        "MISSING-METHOD: step67-never-written (plan required a test method the diff never wrote)"]


def test_hyphen_label_substring_of_longer_label_still_holds():
    diff = "+++ b/bin/test-plan-now\n+  step67-clean-extended)\n"
    items = check(_plan("step67-clean"), ["bin/test-plan-now"], diff)
    assert len(_method_items(items)) == 1


def test_hyphen_label_only_in_unchanged_file_still_holds():
    diff = "+++ b/bin/test-plan-now\n+  echo unrelated\n"
    tree = {"bin/other": "run_case step67-clean\n", "bin/test-plan-now": "echo unrelated\n"}
    items = check(_plan("step67-clean"), ["bin/test-plan-now"], diff, read_file=tree.get)
    assert len(_method_items(items)) == 1


def test_plain_method_names_still_need_a_declaration():
    diff = "+++ b/ibl5/tests/FooTest.php\n+        $this->testFoo();\n"
    items = check(_plan("testFoo"), ["ibl5/tests/FooTest.php"], diff)
    assert len(_method_items(items)) == 1


def test_inline_list_names_from_m2_are_checked_individually():
    diff = "+++ b/ibl5/tests/FooTest.php\n+    public function testA(): void\n"
    names = _methods("- New: `testA`, `testB`")
    items = check(_plan(*names), ["ibl5/tests/FooTest.php"], diff)
    got = _method_items(items)
    assert len(got) == 1
    assert got[0].startswith("MISSING-METHOD: testB (")
