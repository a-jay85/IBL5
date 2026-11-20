"""Go test funcs and `_method_declared` (backlog#1934). Each test is named for the mutation it kills.

Phase 1 tests pin the Go `func Name(` / receiver `func (r *T) Name(` arm. Phase 2 adds the
real-file check against jsbtrace_test.go and an opt-in corpus diff (POSTPLAN_CORPUS_DIFF=1).
"""
import glob
import os
import re
import subprocess
import sys
from pathlib import PurePosixPath

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conformance import check, _method_declared
from harness.planfile import locate_plan
from harness.state import PlanInfo

_REPO_ROOT = str(PurePosixPath(os.path.abspath(__file__)).parents[3])


def _legacy_method_declared(name: str, text: str) -> bool:
    """Literal copy of the pre-#1934 two-arm regex, used as the BEFORE side of the diffs."""
    return re.search(
        rf"(function|def)\s+{re.escape(name)}\b"
        rf"|^[+\s]*{re.escape(name)}\s*\(\s*\)",
        text,
        re.MULTILINE,
    ) is not None


def test_go_plain_test_func_is_declared():
    """Mutation: deleting the Go arm."""
    assert _method_declared("TestFoo", "func TestFoo(t *testing.T) {")


def test_go_added_diff_line_is_declared():
    """Mutation: deleting the Go arm."""
    assert _method_declared("TestFoo", "+func TestFoo(t *testing.T) {")
    assert _method_declared("TestFoo", " func TestFoo(t *testing.T) {")


def test_go_receiver_method_is_declared():
    """Mutation: removing the optional receiver group."""
    assert _method_declared("TestFoo", "func (s *Suite) TestFoo(t *testing.T) {")
    assert _method_declared("TestFoo", "func (Suite) TestFoo(t *testing.T) {")
    assert _method_declared("TestFoo", "func (s *Suite[T]) TestFoo(t *testing.T) {")


def test_go_generic_func_is_declared():
    """Mutation: narrowing the terminator from `[\\[(]` to `\\(`."""
    assert _method_declared("TestFoo", "func TestFoo[T any](t *testing.T) {")


def test_go_prefix_name_is_not_a_declaration():
    """Mutation: dropping the `\\s*[\\[(]` terminator."""
    assert not _method_declared("TestFoo", "func TestFooBar(t *testing.T) {")
    assert not _method_declared("Foo", "func (s *S) FooBar() {")


def test_go_func_keyword_without_paren_is_not_a_declaration():
    """Mutation: dropping the terminator (near-miss prose)."""
    assert not _method_declared("TestFoo", "func TestFoo is the entry point")
    assert not _method_declared("TestFoo", "func TestFoo")


def test_go_comment_and_string_mentions_are_not_declarations():
    """Mutation: dropping the line-start anchor `^[+\\s]*`."""
    assert not _method_declared("TestFoo", "// func TestFoo(t *testing.T) {")
    assert not _method_declared("TestFoo", '\tfmt.Println("func TestFoo(t)")')
    assert not _method_declared("TestFoo", "+// func TestFoo(t *testing.T) {")


def test_go_call_site_is_not_a_declaration():
    """Mutation: making the `func` keyword optional."""
    assert not _method_declared("TestFoo", "    TestFoo(t)")
    assert not _method_declared("TestFoo", "    s.TestFoo(t)")
    assert not _method_declared("TestFoo", '    t.Run("TestFoo", TestFoo)')


def test_go_removed_line_is_not_a_declaration():
    """Mutation: widening the prefix class to `[-+\\s]`."""
    assert not _method_declared("TestFoo", "-func TestFoo(t *testing.T) {")


_GO_PATH = "engine/internal/foo/foo_test.go"


def _foo_plan() -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, required_test_methods=["TestFoo"])


def _missing_foo(items: list[str]) -> list[str]:
    return [i for i in items if i.startswith("MISSING-METHOD: TestFoo")]


def test_go_missing_method_clears_via_check_diff():
    """Mutation: deleting the Go arm (the MISSING-METHOD item reappears)."""
    diff = f"+++ b/{_GO_PATH}\n+func TestFoo(t *testing.T) {{\n+}}\n"
    items = check(_foo_plan(), [_GO_PATH], diff)
    assert _missing_foo(items) == []


def test_go_method_in_changed_tree_clears_missing_method():
    """Mutation: deleting the Go arm (the changed-file fallback finds nothing)."""
    diff = f"+++ b/{_GO_PATH}\n+\tassert.True(t, true)\n"
    tree = {_GO_PATH: "package foo\n\nfunc TestFoo(t *testing.T) {}\n"}
    notes: list[str] = []
    items = check(_foo_plan(), [_GO_PATH], diff, notes=notes, read_file=tree.get)
    assert _missing_foo(items) == []
    assert any(n.startswith(f"METHOD-IN-TREE: TestFoo — declared in {_GO_PATH}") for n in notes)


def test_go_method_absent_from_changed_go_file_stays_missing():
    """Mutation: replacing the name in the Go arm with `\\w+`."""
    diff = f"+++ b/{_GO_PATH}\n+\tassert.True(t, true)\n"
    tree = {_GO_PATH: "package foo\n\nfunc TestOther(t *testing.T) {}\n"}
    items = check(_foo_plan(), [_GO_PATH], diff, read_file=tree.get)
    assert len(_missing_foo(items)) == 1


def test_legacy_copy_matches_real_on_non_go_inputs():
    """Mutation: making the Go arm's `func` keyword a wildcard (`\\w+`) flips `new TestFoo(1);`."""
    cases = [
        ("testFoo", "    public function testFoo(): void {}"),
        ("test_foo", "    def test_foo(self):"),
        ("test_foo", "+test_foo() {"),
        ("testFoo", "        $this->testFoo();"),
        ("TestFoo", "    new TestFoo(1);"),
        ("testFoo", "# function testFoo is mentioned here"),
    ]
    for name, text in cases:
        assert _legacy_method_declared(name, text) == _method_declared(name, text), (name, text)


_JSBTRACE_PATH = "engine/internal/jsbtrace/jsbtrace_test.go"
_JSBTRACE_TESTS = (
    "TestLoadDefaultDirWhenOverrideUnset",
    "TestLoadDefaultDirWhenOverrideEmpty",
    "TestLoadOverrideDirWins",
    "TestLoadRejectsPathEscape",
    "TestLoadRejectsBadHeader",
    "TestLoadRejectsZeroRecords",
    "TestLoadRejectsRecordMissingOut",
)


def test_jsbtrace_test_file_declares_its_test_funcs():
    """Mutation: deleting the Go arm (the real file stops matching AFTER)."""
    path = os.path.join(_REPO_ROOT, _JSBTRACE_PATH)
    assert os.path.isfile(path), f"missing real Go fixture {path}"
    with open(path, encoding="utf-8") as fh:
        text = fh.read()
    assert len(_JSBTRACE_TESTS) == 7
    for name in _JSBTRACE_TESTS:
        assert _method_declared(name, text), name
        assert not _legacy_method_declared(name, text), name
    assert not _method_declared("TestNoSuchFunc", text)


_GO_FUNC_LINE = re.compile(r"^func\s+(?:\([^)]*\)\s*)?(Test\w+)")
_TEXT_SUFFIXES = (".go", ".py", ".sh", ".php", ".ts", ".js")


def _tracked_files() -> list[str]:
    out = subprocess.run(["git", "ls-files"], cwd=_REPO_ROOT,
                         capture_output=True, text=True, check=True).stdout
    return [f for f in out.split("\n") if f]


def _read_text(rel: str) -> str | None:
    try:
        with open(os.path.join(_REPO_ROOT, rel), encoding="utf-8") as fh:
            return fh.read()
    except (UnicodeDecodeError, OSError):
        return None


def _has_witness(name: str, text: str) -> bool:
    """Independent string scan (no regex): a `func ` line that holds ` name(` or ` name[`."""
    for line in text.splitlines():
        s = line.strip()
        if s.startswith("func ") and (f" {name}(" in s or f" {name}[" in s):
            return True
    return False


def _first_line(name: str, text: str) -> int:
    for i, line in enumerate(text.splitlines(), 1):
        if _method_declared(name, line):
            return i
    return 0


def test_go_corpus_diff_flips_only_go_declarations():
    """Mutation: widening the Go arm keyword to `\\w+` flips a non-Go pair and fails (d)."""
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the Go-func corpus")
    plans_dir = os.environ.get("PLANS_DIR") or os.path.expanduser("~/claude-plans")
    assert os.path.isdir(plans_dir), f"POSTPLAN_CORPUS_DIFF=1 but no plan corpus at {plans_dir}"

    plan_paths = [p for p in sorted(glob.glob(os.path.join(plans_dir, "*.md")))
                  if not p.endswith("-shared-context.md")]
    assert plan_paths, f"no *.md plans under {plans_dir}"
    names: set[str] = set()
    for p in plan_paths:
        info = locate_plan("corpus", explicit_path=p)
        if not info.found:
            continue
        names.update(m for m in info.required_test_methods if "-" not in m)

    tracked = _tracked_files()
    texts: dict[str, str] = {}
    for rel in tracked:
        if rel.endswith(_TEXT_SUFFIXES) or (rel.startswith("bin/") and "." not in os.path.basename(rel)):
            body = _read_text(rel)
            if body is not None:
                texts[rel] = body
    for rel, body in texts.items():
        if rel.endswith(".go"):
            for line in body.splitlines():
                m = _GO_FUNC_LINE.match(line)
                if m:
                    names.add(m.group(1))

    pairs = 0
    flips: list[tuple[str, str]] = []
    for name in sorted(names):
        for rel, body in texts.items():
            if name not in body:
                continue
            pairs += 1
            before = _legacy_method_declared(name, body)
            after = _method_declared(name, body)
            assert not (before and not after), f"superset broken: {name} in {rel}"
            if before != after:
                flips.append((name, rel))

    print(f"scanned={len(plan_paths)} names={len(names)} texts={len(texts)} pairs={pairs} flips={len(flips)}")
    for name, rel in flips:
        print(f"  {name} -> {rel}:{_first_line(name, texts[rel])}")

    assert flips, "no flips: the diff passed vacuously"
    for name, rel in flips:
        assert rel.endswith(".go"), f"non-Go flip: {name} -> {rel}"
        assert _has_witness(name, texts[rel]), f"no string-scan witness: {name} -> {rel}"
    flipped = set(flips)
    for name in _JSBTRACE_TESTS:
        assert (name, _JSBTRACE_PATH) in flipped, f"jsbtrace func not flipped: {name}"
