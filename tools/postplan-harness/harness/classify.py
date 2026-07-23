"""Phase 3 — diff classification. Deterministic port of
.claude/skills/post-plan/_phase-3-classify-diff.md (the bash the model used to run turn-by-turn).

Pure functions: file list + unified diff text in, Classification out.
"""
from __future__ import annotations

import re

from .state import Classification

STRIP_RE = re.compile(r"(migrations/|composer\.lock|package-lock\.json|bun\.lock|__snapshots__/|\.snap$)")
_PHP = re.compile(r"\.php$")
_CSS = re.compile(r"\.css$|^ibl5/design/")
_MD = re.compile(r"\.md$")
_MIGRATION = re.compile(r"^ibl5/migrations/.*\.sql$")
_TEST = re.compile(r"^ibl5/tests/|\.test\.(ts|js|php)$|\.spec\.(ts|js)$")
_E2E = re.compile(r"^ibl5/tests/e2e/.*\.ts$")
_LOCK = re.compile(r"(composer|package|bun)\.lock$")
_SNAP = re.compile(r"__snapshots__/|\.snap$")
_GO = re.compile(r"^engine/.*\.go$")
_IBL5 = re.compile(r"^ibl5/")
_ENGINE = re.compile(r"^engine/")
GOLDEN_PATH = "engine/internal/sim/testdata/golden.json"
_COMMENT_ADDED = re.compile(r"^\+\s*(//|#|/\*|\*)")
_MODULE_REF = re.compile(r"(modules\.php\?name=[A-Za-z][A-Za-z0-9_]*|modules/[A-Za-z][A-Za-z0-9_]*/)")


def files_from_diff(diff_text: str) -> list[str]:
    """Changed-file list from unified diff headers (b-side path)."""
    files: list[str] = []
    for line in diff_text.splitlines():
        if line.startswith("diff --git "):
            m = re.match(r"diff --git a/(.*?) b/(.*)$", line)
            if m:
                path = m.group(2)
                if path not in files:
                    files.append(path)
    return files


def modified_files_from_diff(diff_text: str) -> list[str]:
    """Files modified (not added, not deleted) — replay analogue of
    `git diff --diff-filter=M --name-only`."""
    out: list[str] = []
    cur: str | None = None
    is_new = is_del = False
    def flush():
        if cur and not is_new and not is_del and cur not in out:
            out.append(cur)
    for line in diff_text.splitlines():
        if line.startswith("diff --git "):
            flush()
            m = re.match(r"diff --git a/(.*?) b/(.*)$", line)
            cur = m.group(2) if m else None
            is_new = is_del = False
        elif line.startswith("new file mode"):
            is_new = True
        elif line.startswith("deleted file mode"):
            is_del = True
    flush()
    return out


def filter_diff(diff_text: str) -> str:
    """awk port: drop whole file-sections for migrations/lockfiles/snapshots."""
    out: list[str] = []
    skip = False
    for line in diff_text.splitlines(keepends=True):
        if line.startswith("diff --git"):
            skip = bool(STRIP_RE.search(line))
        if not skip:
            out.append(line)
    return "".join(out)


def _count(files: list[str], rx: re.Pattern) -> int:
    return sum(1 for f in files if rx.search(f))


def classify(files: list[str], diff_text: str, modified_files: list[str] | None = None) -> Classification:
    """Port of the Phase 3 bash block. `modified_files` defaults to derivation
    from the diff headers (live mode passes `git diff --diff-filter=M` output)."""
    c = Classification(files=list(files))
    c.count_total = len([f for f in files if f.strip()])
    c.count_php = _count(files, _PHP)
    c.count_css = _count(files, _CSS)
    c.count_md = _count(files, _MD)
    c.count_migration = _count(files, _MIGRATION)
    c.count_test = _count(files, _TEST)
    c.count_e2e_specs = _count(files, _E2E)
    c.count_lock = _count(files, _LOCK)
    c.count_snapshot = _count(files, _SNAP)
    c.count_non_code = c.count_md + c.count_lock + c.count_snapshot
    c.count_go = _count(files, _GO)
    c.count_ibl5 = _count(files, _IBL5)
    c.go_touched_count = _count(files, _ENGINE)

    c.has_php = c.count_php > 0
    c.has_css = c.count_css > 0
    c.has_migration = c.count_migration > 0
    c.has_test = c.count_test > 0
    c.has_e2e_specs = c.count_e2e_specs > 0
    c.has_go = c.count_go > 0
    c.go_touched = c.go_touched_count > 0
    c.engine_only = c.go_touched and c.count_php == 0 and c.count_ibl5 == 0
    c.golden_changed = GOLDEN_PATH in files

    t = c.count_total
    c.docs_only = t > 0 and c.count_md == t
    c.css_only = t > 0 and c.count_css == t
    c.migration_only = t > 0 and c.count_migration == t
    c.test_only = t > 0 and c.count_test == t
    c.non_code_only = t > 0 and c.count_non_code == t

    if modified_files is None:
        modified_files = modified_files_from_diff(diff_text)
    c.has_modified = len(modified_files) > 0

    c.filtered_diff = filter_diff(diff_text)

    c.has_comments_in_diff = any(
        _COMMENT_ADDED.match(l) for l in c.filtered_diff.splitlines()
    )
    # LINES_PHP_CHANGED: added lines in .php file sections (`^\+[^+]`)
    php_added = 0
    in_php = False
    for line in diff_text.splitlines():
        if line.startswith("diff --git"):
            in_php = bool(re.search(r"\.php\b", line))
        elif in_php and line.startswith("+") and not line.startswith("++"):
            php_added += 1
    c.lines_php_changed = php_added

    # E2E spec module extraction + prod overlap
    if c.count_e2e_specs > 0:
        mods: set[str] = set()
        in_spec = False
        for line in diff_text.splitlines():
            if line.startswith("diff --git"):
                in_spec = bool(re.search(r"ibl5/tests/e2e/.*\.ts\b", line))
            elif in_spec and line.startswith("+"):
                for m in _MODULE_REF.finditer(line):
                    token = m.group(1)
                    token = token.replace("modules.php?name=", "").replace("modules/", "").rstrip("/")
                    mods.add(token)
        c.e2e_spec_modules = sorted(mods)
        for mod in c.e2e_spec_modules:
            if any(re.match(rf"^ibl5/modules/{re.escape(mod)}/", f) for f in files):
                c.has_e2e_prod_overlap = True
                break
    return c


def slice_spec_diffs(filtered_diff: str, e2e_spec_modules: list[str]) -> tuple[str, str]:
    """Agent D pre-slice: (spec portion, production portion) of the diff."""
    spec_lines: list[str] = []
    prod_lines: list[str] = []
    keep_spec = keep_prod = False
    mod_re = None
    if e2e_spec_modules:
        mod_re = re.compile(r"ibl5/modules/(" + "|".join(re.escape(m) for m in e2e_spec_modules) + r")/")
    for line in filtered_diff.splitlines(keepends=True):
        if line.startswith("diff --git"):
            keep_spec = bool(re.search(r"ibl5/tests/e2e/.*\.ts", line))
            keep_prod = bool(mod_re and mod_re.search(line))
        if keep_spec:
            spec_lines.append(line)
        if keep_prod:
            prod_lines.append(line)
    return "".join(spec_lines), "".join(prod_lines)
