import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.body_numbers import (
    _count_declarations,
    annotate_test_count_mismatches,
    check_test_count_claims,
)
from harness.classify import (FILES_CHANGED_BEGIN, FILES_CHANGED_END,
                              MERGE_DIGEST_BEGIN, MERGE_DIGEST_END)

PHP_PATH = "ibl5/tests/Foo/BarTest.php"
PY_PATH = "tools/postplan-harness/tests/test_thing.py"
BASH_PATH = "bin/test-thing"


def _diff(path, added_lines, status="M"):
    head = f"diff --git a/{path} b/{path}\n"
    if status == "A":
        head += "new file mode 100644\n--- /dev/null\n"
    else:
        head += f"--- a/{path}\n"
    head += f"+++ b/{path}\n@@ -1,0 +1,{len(added_lines)} @@\n"
    return head + "".join(f"+{ln}\n" for ln in added_lines)


_PHP_ADDED = [
    "    public function testOne(): void {}",
    "    #[Test]",
    "    public function itDoesTwo(): void {}",
]


def _only(checks):
    assert len(checks) == 1
    return checks[0]


def test_declaration_counting_per_language():
    php = [
        "    #[Test]",
        "    public function testBoth(): void {}",
        "    #[Test]",
        "    #[DataProvider('x')]",
        "    public function onlyAttribute(): void {}",
        "    public function helper(): void {}",
        "    private function testPrivateCounts(): void {}",
    ]
    assert _count_declarations(PHP_PATH, php) == 3
    py = ["def test_a():", "    async def test_b():", "def helper():"]
    assert _count_declarations(PY_PATH, py) == 2
    js = [
        "test.describe('x', () => {",
        "  test.beforeEach(() => {});",
        "  test('a', async () => {});",
        "  it.skip('b', () => {});",
        "  it('c', () => {});",
    ]
    assert _count_declarations("ibl5/tests/e2e/x.spec.ts", js) == 3
    go = ["func TestA(t *testing.T) {", "func helper() {}", "func TestB(t *testing.T) {"]
    assert _count_declarations("engine/x_test.go", go) == 2
    bash = ["case_one() {", "function case_two() {", "run_case_three"]
    assert _count_declarations(BASH_PATH, bash) == 2
    assert _count_declarations("ibl5/tests/fixture.json", ["{}"]) is None


def test_claim_equal_to_added_count_is_match():
    body = f"Adds 2 tests in `{PHP_PATH}`."
    c = _only(check_test_count_claims(body, _diff(PHP_PATH, _PHP_ADDED)))
    assert (c.verdict, c.claimed, c.added) == ("match", 2, 2)


def test_claim_equal_to_head_total_is_match():
    body = f"`{PHP_PATH}` now has 7 tests."
    read_file = {PHP_PATH: "\n".join(["public function testX(): void {}"] * 7)}.get
    c = _only(check_test_count_claims(body, _diff(PHP_PATH, _PHP_ADDED), read_file))
    assert (c.verdict, c.added, c.total) == ("match", 2, 7)


def test_claim_matching_neither_count_is_mismatch():
    body = f"`{PHP_PATH}`: 5 tests."
    read_file = {PHP_PATH: "\n".join(["public function testX(): void {}"] * 7)}.get
    c = _only(check_test_count_claims(body, _diff(PHP_PATH, _PHP_ADDED), read_file))
    assert (c.verdict, c.claimed, c.added, c.total) == ("mismatch", 5, 2, 7)
    new_body, findings = annotate_test_count_mismatches(
        body, _diff(PHP_PATH, _PHP_ADDED), read_file)
    assert findings == [
        f"test-count claim mismatch: body says 5 for {PHP_PATH}; diff adds 2, file has 7 at head"]
    assert "2 added / 7 total" in new_body


def test_bash_harness_without_case_functions_is_unverifiable():
    body = f"`{BASH_PATH}` covers 4 cases."
    diff = _diff(BASH_PATH, ["echo hi", "run_check"], status="A")
    assert _only(check_test_count_claims(body, diff)).verdict == "unverifiable"
    assert annotate_test_count_mismatches(body, diff) == (body, [])


def test_two_test_paths_in_one_line_is_unverifiable():
    body = f"3 tests across `{PHP_PATH}` and `{PY_PATH}`."
    diff = _diff(PHP_PATH, _PHP_ADDED) + _diff(PY_PATH, ["def test_a():"])
    assert _only(check_test_count_claims(body, diff)).verdict == "unverifiable"


def test_non_test_path_claim_is_unverifiable():
    body = "Adds 9 tests for `ibl5/classes/Foo.php` (example)."
    diff = _diff("ibl5/classes/Foo.php", ["function testX() {"])
    assert _only(check_test_count_claims(body, diff)).verdict == "unverifiable"


def test_unreadable_head_with_nonmatching_claim_is_unverifiable():
    body = f"`{PHP_PATH}` has 9 tests."
    diff = _diff(PHP_PATH, _PHP_ADDED)
    c = _only(check_test_count_claims(body, diff, {}.get))
    assert (c.verdict, c.total) == ("unverifiable", None)
    assert annotate_test_count_mismatches(body, diff, {}.get) == (body, [])


def test_test_files_and_rate_phrases_are_not_claims():
    diff = _diff(PHP_PATH, _PHP_ADDED)
    for phrase in ("10 test files", "2 tests/sec", "3 test suites", "4 test runs"):
        assert check_test_count_claims(f"`{PHP_PATH}` {phrase}", diff) == []
    for phrase in ("5 tests", "1 test", "3 new test cases", "2 test methods"):
        assert len(check_test_count_claims(f"`{PHP_PATH}` {phrase}", diff)) == 1


def test_claim_inside_protected_spans_is_ignored():
    diff = _diff(PHP_PATH, _PHP_ADDED)
    line = f"9 tests in `{PHP_PATH}`"
    blocks = [
        f"{FILES_CHANGED_BEGIN}\n{line}\n{FILES_CHANGED_END}\n",
        f"{MERGE_DIGEST_BEGIN}\n{line}\n{MERGE_DIGEST_END}\n",
        f"## Manual Testing\n{line}\n",
        f"```\n{line}\n```\n",
    ]
    for block in blocks:
        assert check_test_count_claims(block, diff, {}.get) == []
    read_file = {PHP_PATH: "public function testX(): void {}\n"}.get
    plain = check_test_count_claims(f"{line}\n", diff, read_file)
    assert [c.verdict for c in plain] == ["mismatch"]


def test_annotation_appends_measured_counts_without_rewriting_claim():
    body = f"Intro.\n- 5 tests in `{PHP_PATH}`\n"
    read_file = {PHP_PATH: "public function testX(): void {}\n"}.get
    new_body, findings = annotate_test_count_mismatches(
        body, _diff(PHP_PATH, _PHP_ADDED), read_file)
    assert f"5 tests [harness: measured 2 added / 1 total in `{PHP_PATH}`] in" in new_body
    assert len(findings) == 1


def test_annotation_is_idempotent():
    body = f"- 5 tests in `{PHP_PATH}`\n"
    read_file = {PHP_PATH: "public function testX(): void {}\n"}.get
    diff = _diff(PHP_PATH, _PHP_ADDED)
    once, first = annotate_test_count_mismatches(body, diff, read_file)
    twice, second = annotate_test_count_mismatches(once, diff, read_file)
    assert first and twice == once and second == []
