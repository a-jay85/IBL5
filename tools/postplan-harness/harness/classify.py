"""Phase 3 — diff classification. Deterministic port of
.claude/skills/post-plan/_phase-3-classify-diff.md (the bash the model used to run turn-by-turn).

Pure functions: file list + unified diff text in, Classification out.
"""
from __future__ import annotations

import re

from .state import Classification

FILES_CHANGED_BEGIN = "<!-- files-changed:begin -->"
FILES_CHANGED_END = "<!-- files-changed:end -->"
MERGE_DIGEST_BEGIN = "<!-- merge-digest:begin -->"
MERGE_DIGEST_END = "<!-- merge-digest:end -->"
TESTS_CHANGED_BEGIN = "<!-- tests-changed:begin -->"
TESTS_CHANGED_END = "<!-- tests-changed:end -->"
RESIDUAL_PHASES_BEGIN = "<!-- residual-phases:begin -->"
RESIDUAL_PHASES_END = "<!-- residual-phases:end -->"
SCOPE_NOTES_BEGIN = "<!-- scope-notes:begin -->"
SCOPE_NOTES_END = "<!-- scope-notes:end -->"
MANUAL_CONFIRMATION_BEGIN = "<!-- manual-confirmation:begin -->"
MANUAL_CONFIRMATION_END = "<!-- manual-confirmation:end -->"
REVIEWER_VERIFICATION_BEGIN = "<!-- reviewer-verification:begin -->"
REVIEWER_VERIFICATION_END = "<!-- reviewer-verification:end -->"

STRIP_RE = re.compile(r"(migrations/|composer\.lock|package-lock\.json|bun\.lock|__snapshots__/|\.snap$)")
_PHP = re.compile(r"\.php$")
_CSS = re.compile(r"\.css$|^ibl5/design/")
_MD = re.compile(r"\.md$")
_MIGRATION = re.compile(r"^ibl5/migrations/.*\.sql$")
_TEST = re.compile(r"^ibl5/tests/|\.test\.(ts|js|php)$|\.spec\.(ts|js)$")
# Test files outside ibl5/ that _TEST does not cover: Go engine tests,
# the harness's own pytest files, and bin/ shell test harnesses.
_TEST_EXTRA = re.compile(r"_test\.go$|(^|/)test_[^/]*\.py$|^bin/test-")


def is_test_path(p: str) -> bool:
    """True when ``p`` is a test file for the tests-changed PR-body block."""
    return bool(_TEST.search(p)) or bool(_TEST_EXTRA.search(p))


_E2E = re.compile(r"^ibl5/tests/e2e/.*\.ts$")
_LOCK = re.compile(r"(composer|package|bun)\.lock$")
_SNAP = re.compile(r"__snapshots__/|\.snap$")
_GO = re.compile(r"^engine/.*\.go$")
_IBL5 = re.compile(r"^ibl5/")
_ENGINE = re.compile(r"^engine/")
GOLDEN_PATH = "engine/internal/sim/testdata/golden.json"
_COMMENT_ADDED = re.compile(r"^\+\s*(//|#|/\*|\*)")
_MODULE_REF = re.compile(r"(modules\.php\?name=[A-Za-z][A-Za-z0-9_]*|modules/[A-Za-z][A-Za-z0-9_]*/)")
_RETRO_ROW_RE = re.compile(r"^\|\s*\d{4}-\d{2}-\d{2}\b.*class:.*routed to:")

# Agent E surface (mirrors _phase-3-classify-diff.md exactly)
_SHELL_INC = re.compile(r"(^|/)bin/|\.sh$")
_SHELL_EXC = re.compile(r"\.(php|md|json|py|ts|tsx|css|sql|ya?ml|lock|txt|neon)$")
_WORKFLOW = re.compile(r"^\.github/workflows/.*\.ya?ml$")
_SKILL_PROSE = re.compile(r"^\.claude/.*\.md$")
# Paths a league GM can never see change: dev tooling, docs, tests, build config.
# FAIL-SAFE DENYLIST: a path that matches nothing here counts as GM-visible, so an
# unknown new directory keeps whatever type the model chose (never retyped).
_NON_RUNTIME = re.compile(
    r"^(bin|tools|\.claude|\.github)/"        # repo-level tooling roots
    r"|^[^/]+$"                               # root-level files: README.md, CLAUDE.md, .gitignore
    r"|^ibl5/(tests|docs|bin|node_modules|vendor|worktrees)/"
    r"|^ibl5/(phpstan|phpunit|playwright|vitest|coverage|infection|test-results|tmp)"  # stem match: files and dirs
    r"|^ibl5/(eslint\.config\.js|package\.json|composer\.(json|lock)|bun\.lock|[^/]*\.neon)$"
    r"|\.md$"                                 # markdown anywhere (the app renders PHP, never markdown)
    r"|_test\.go$"                            # Go engine tests
)


def is_gm_visible_path(p: str) -> bool:
    """True when ``p`` is a runtime file whose change a league GM could notice.

    Not GM-visible: `bin/`, `tools/`, `.claude/`, `.github/`, root-level files, markdown
    anywhere, `ibl5/tests/`, `ibl5/docs/`, `ibl5/bin/`, ibl5 build/lint/test config, and
    Go `_test.go` files. Everything else under `ibl5/` and `engine/` (and any unknown root)
    is GM-visible.
    """
    p = p.strip()
    return bool(p) and not _NON_RUNTIME.search(p)

def is_shell_path(p: str) -> bool:
    return bool(_SHELL_INC.search(p)) and not _SHELL_EXC.search(p)

def is_agent_e_path(p: str) -> bool:
    return is_shell_path(p) or bool(_WORKFLOW.match(p)) or bool(_SKILL_PROSE.match(p))


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


def rename_sources_from_diff(diff_text: str) -> list[str]:
    """OLD paths of every rename in a unified diff, diff order, de-duplicated.

    Keys on the `rename from ` extended header, which git emits only for a detected
    rename, so a copy (`copy from `) or a plain add/delete pair contributes nothing.
    Companion of `files_from_diff` (b-side paths): the union of the two is the set the
    conformance check reads (`ReplayGit.conformance_files`), while `files_from_diff`
    alone stays the set classify() and scope conformance read.
    """
    out: list[str] = []
    for line in diff_text.splitlines():
        if line.startswith("rename from "):
            src = line[len("rename from "):]
            if src and src not in out:
                out.append(src)
    return out


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


def name_status_from_diff(diff_text: str) -> list[tuple[str, str]]:
    """Name-status pairs from unified diff, diff order, de-duplicated by path.

    Pure port of `git diff --name-status origin/master...HEAD` parsed from the
    unified diff text.  Status letters: A=added, D=deleted, R=renamed, M=modified.
    Rename paths are rendered as ``<from> → <to>`` (U+2192).
    """
    out: list[tuple[str, str]] = []
    seen: set[str] = set()
    cur_a: str | None = None
    cur_b: str | None = None
    is_new = is_del = is_rename = False
    rename_from: str | None = None
    rename_to: str | None = None

    def flush() -> None:
        if cur_b is None and cur_a is None:
            return
        if is_rename and rename_from and rename_to:
            path = f"{rename_from} → {rename_to}"
            if path not in seen:
                out.append(("R", path))
                seen.add(path)
        elif is_new and cur_b:
            if cur_b not in seen:
                out.append(("A", cur_b))
                seen.add(cur_b)
        elif is_del and cur_a:
            if cur_a not in seen:
                out.append(("D", cur_a))
                seen.add(cur_a)
        elif cur_b:
            if cur_b not in seen:
                out.append(("M", cur_b))
                seen.add(cur_b)

    for line in diff_text.splitlines():
        if line.startswith("diff --git "):
            flush()
            m = re.match(r"diff --git a/(.*?) b/(.*)$", line)
            if m:
                cur_a, cur_b = m.group(1), m.group(2)
            else:
                cur_a = cur_b = None
            is_new = is_del = is_rename = False
            rename_from = rename_to = None
        elif line.startswith("new file mode"):
            is_new = True
        elif line.startswith("deleted file mode"):
            is_del = True
        elif line.startswith("rename from "):
            is_rename = True
            rename_from = line[len("rename from "):]
        elif line.startswith("rename to "):
            rename_to = line[len("rename to "):]
    flush()
    return out


def name_status_text(diff_text: str) -> str:
    """Tab-separated status/path rows from unified diff, compatible with git --name-status."""
    pairs = name_status_from_diff(diff_text)
    if not pairs:
        return ""
    return "\n".join(f"{status}\t{path}" for status, path in pairs)


def numstat_text(diff_text: str) -> str:
    """Tab-separated added/deleted/path rows from unified diff, compatible with git --numstat.

    Binary files emit '-\t-\tpath'.
    """
    lines = diff_text.splitlines()
    result: list[str] = []
    current_path: str | None = None
    added = deleted = 0
    is_binary = False
    in_hunk = False  # '---'/'+++' are file headers only before the first '@@'

    def flush() -> None:
        if current_path is None:
            return
        if is_binary:
            result.append(f"-\t-\t{current_path}")
        else:
            result.append(f"{added}\t{deleted}\t{current_path}")

    for line in lines:
        if line.startswith("diff --git "):
            flush()
            # Extract b-side path: "diff --git a/X b/Y" → Y
            parts = line.split(" b/", 1)
            current_path = parts[1] if len(parts) > 1 else line.split()[-1]
            added = deleted = 0
            is_binary = False
            in_hunk = False
        elif not in_hunk and line.startswith("@@"):
            in_hunk = True
        elif line.startswith("Binary files") and "differ" in line:
            is_binary = True
        elif in_hunk and line.startswith("+"):
            added += 1
        elif in_hunk and line.startswith("-"):
            deleted += 1

    flush()
    return "\n".join(result)


def strip_change_blocks(body: str) -> str:
    """Remove the retired files-changed and tests-changed blocks from a PR body.

    Exists to clean bodies written before the harness stopped generating those blocks.
    For each marker pair, when BEGIN and END are both present with BEGIN first, the span
    BEGIN..END inclusive is removed and the whitespace left behind collapses so no run of
    three or more newlines remains there. A body that ended with a block ends with a single
    newline. An orphan or out-of-order marker leaves that pair untouched (never surgery on
    a half-corrupt body). None or empty input returns "".
    """
    body = body or ""
    for begin, end in ((FILES_CHANGED_BEGIN, FILES_CHANGED_END),
                       (TESTS_CHANGED_BEGIN, TESTS_CHANGED_END)):
        b = body.find(begin)
        e = body.find(end)
        if b == -1 or e == -1 or b > e:
            continue
        head = body[:b]
        tail = body[e + len(end):]
        if not tail.strip():
            body = head.rstrip("\n") + "\n" if head.strip() else ""
            continue
        head = head.rstrip("\n")
        tail = tail.lstrip("\n")
        body = head + "\n\n" + tail if head.strip() else tail
    return body


def render_merge_digest(rows: list[str]) -> str:
    """Marker-bounded `## Merge digest` block for the top of a PR body.

    One paragraph per row (blank line between rows) so GitHub renders each bold label
    on its own line. The rows are the exact ones the sticky comment prints.
    """
    return "\n".join([MERGE_DIGEST_BEGIN, "## Merge digest", "",
                      "\n\n".join(rows), MERGE_DIGEST_END])


def upsert_merge_digest(body: str, block: str) -> str:
    """Insert or replace the merge-digest block in a PR body.

    Both markers present, BEGIN before END:
        Replace everything from BEGIN through END inclusive with ``block``;
        surrounding text is left byte-identical.
    Neither marker present:
        PREPEND ``block + "\\n\\n" + body.lstrip("\\n")`` (the digest sits at the TOP of
        the body, unlike the appended blocks).
    Exactly one marker, or END before BEGIN:
        Do not attempt surgery on the body.  Prepend a fresh block exactly as in
        the neither-present case, leaving the orphan marker untouched.
    Empty/None body:
        Return ``block + "\\n"``.
    """
    body = body or ""
    if not body.strip():
        return block + "\n"

    begin_idx = body.find(MERGE_DIGEST_BEGIN)
    end_idx = body.find(MERGE_DIGEST_END)

    if begin_idx != -1 and end_idx != -1 and begin_idx < end_idx:
        after_end = end_idx + len(MERGE_DIGEST_END)
        return body[:begin_idx] + block + body[after_end:]

    return block + "\n\n" + body.lstrip("\n")


def render_residual_phases(items: list[str]) -> str:
    """The `## Residual Phases` block for a PR body, or "" when there are no items.

    `items` are conformance `MISSING-PHASE: N — heading (...)` strings. The block tells
    the reader which plan phases the diff shows no evidence of and that arming condition
    (3) holds auto-merge until they ship or the plan's `## Out of Scope` names them.
    """
    if not items:
        return ""
    parts = [RESIDUAL_PHASES_BEGIN, "## Residual Phases",
             "The diff carries no evidence for these plan phases. Auto-merge is held "
             "(arming condition 3) until they ship or the plan's `## Out of Scope` "
             "section names them as deferred."]
    for it in items:
        parts.append(f"- {it.removeprefix('MISSING-PHASE: ').strip()}")
    parts.append(RESIDUAL_PHASES_END)
    return "\n".join(parts)


def upsert_residual_phases(body: str, block: str) -> str:
    """Insert, replace, or remove the residual-phases block in a PR body.

    For a non-empty block: replace a well-formed pair in place, else append. An EMPTY block removes an
    existing well-formed marker pair (plus one surrounding blank line) so a re-run after the
    phases ship clears the notice; with no markers and an empty block the body is returned
    unchanged. An orphan or reversed marker pair is left untouched and a non-empty block is
    appended after it.
    """
    body = body or ""
    begin_idx = body.find(RESIDUAL_PHASES_BEGIN)
    end_idx = body.find(RESIDUAL_PHASES_END)
    well_formed = begin_idx != -1 and end_idx != -1 and begin_idx < end_idx
    if well_formed:
        after_end = end_idx + len(RESIDUAL_PHASES_END)
        if not block:
            head = body[:begin_idx].rstrip("\n")
            tail = body[after_end:].lstrip("\n")
            return head + ("\n\n" + tail if tail else "\n") if head else tail
        return body[:begin_idx] + block + body[after_end:]
    if not block:
        return body
    if not body.strip():
        return block
    return body.rstrip() + "\n\n" + block + "\n"


def render_scope_notes(notes: list[str]) -> str:
    """The `## Unplanned changes` block for a PR body, or "" when there are no notes.

    `notes` are `unplanned <path> (...)` / `gap <path> (...)` texts from
    scope_conformance.scope_notes. The block is advisory: it never holds auto-merge.
    """
    if not notes:
        return ""
    parts = [SCOPE_NOTES_BEGIN, "## Unplanned changes",
             "The plan did not name these changes, or the diff lacks a planned file. "
             "Auto-merge is not held on them. Check each one."]
    for n in notes:
        m = re.match(r"^(unplanned|gap) (\S+)(.*)$", n)
        if m:
            parts.append(f"- {m.group(1)} `{m.group(2)}`{m.group(3)}")
        else:
            parts.append(f"- {n.strip()}")
    parts.append(SCOPE_NOTES_END)
    return "\n".join(parts)


def upsert_scope_notes(body: str, block: str) -> str:
    """Insert, replace, or remove the scope-notes block in a PR body.

    For a non-empty block: replace a well-formed pair in place, else append. An EMPTY block removes an
    existing well-formed marker pair (plus one surrounding blank line) so a re-run after the
    notes clear removes the notice; with no markers and an empty block the body is returned
    unchanged. An orphan or reversed marker pair is left untouched and a non-empty block is
    appended after it.
    """
    body = body or ""
    begin_idx = body.find(SCOPE_NOTES_BEGIN)
    end_idx = body.find(SCOPE_NOTES_END)
    well_formed = begin_idx != -1 and end_idx != -1 and begin_idx < end_idx
    if well_formed:
        after_end = end_idx + len(SCOPE_NOTES_END)
        if not block:
            head = body[:begin_idx].rstrip("\n")
            tail = body[after_end:].lstrip("\n")
            return head + ("\n\n" + tail if tail else "\n") if head else tail
        return body[:begin_idx] + block + body[after_end:]
    if not block:
        return body
    if not body.strip():
        return block
    return body.rstrip() + "\n\n" + block + "\n"


def _neutralize_headings(text: str) -> list[str]:
    """Blockquote every line so no line of plan prose can present as a heading.

    ARMING-GATE DEFENSE, not formatting. armable.manual_testing_clearance()
    enters on `^## Manual Testing` and breaks at the next `^## ` line; a plan's
    hold justification that quotes either shape would otherwise open a
    counterfeit clearance window in the PR body (a `- [x]` inside quoted prose
    would read as the sentinel). `> ` prefixing makes every emitted line fail
    both `^#`-anchored scans. Same reason classify.strip_manual_testing_section
    (`^#{1,6}\\s`) cannot be tricked into excising the wrong span.
    """
    return [("> " + l) if l.strip() else ">" for l in text.splitlines()]


def render_manual_confirmation(justification: str) -> str:
    """Marker-delimited `## Manual confirmation needed` block, or "" when absent.

    Empty/whitespace-only input returns "" — never an empty heading. Every line
    of the justification is emitted as a blockquote so no line of plan prose can
    present as a markdown heading in the PR body (see _neutralize_headings).
    The block carries one heading and no bold header line.
    """
    text = (justification or "").strip()
    if not text:
        return ""
    parts = [MANUAL_CONFIRMATION_BEGIN, "## Manual confirmation needed", ""]
    parts.extend(_neutralize_headings(text))
    parts.append(MANUAL_CONFIRMATION_END)
    return "\n".join(parts)


def decision_paragraphs(justification: str) -> str:
    """Raw `**Decision:**` paragraph(s) of a hold justification body.

    Byte-identical to `bin/lib/hold-check.sh::hold_decision_paragraphs` run on
    the same plan (pinned by tests/test_decision_render_parity.py): blocks
    joined by one empty line, no trailing newline, "" when there is none.
    """
    # Local import: keeps classify.py free of a module-level planfile import.
    from harness.planfile import split_hold_justification
    decision_block, _ = split_hold_justification(justification or "")
    return decision_block.rstrip("\n")


def manual_confirmation_text(justification: str) -> str:
    """What the merger reads: the Decision paragraph(s), else the full text.

    Category, `Discharged by matrix rows`, and the why-line are plan-gate
    bookkeeping and never reach the PR body. A section with no Decision line
    falls back to the whole justification so a held PR never loses its notice.
    """
    decision = decision_paragraphs(justification)
    return decision if decision.strip() else (justification or "")


def upsert_hold_notice(body: str, justification: str) -> str:
    """Phase 6 hold-notice composition, one call for the runner.

    Upserts the Decision-only manual-confirmation block (positioned before
    `## Manual Testing` by upsert_manual_confirmation), then clears any
    `## Reviewer verification` block an earlier run left behind.
    """
    body = upsert_manual_confirmation(
        body, render_manual_confirmation(manual_confirmation_text(justification)))
    return upsert_reviewer_verification(body, "")


def upsert_manual_confirmation(body: str, block: str) -> str:
    """Idempotent upsert, positioned ahead of `## Manual Testing`.

    Both markers present, BEGIN before END: replace BEGIN..END inclusive (empty
    `block` therefore REMOVES the block — a plan whose hold section was deleted
    must not leave a stale one behind). Neither present: insert immediately
    before the first `^#{2,6}\\s*Manual\\s+Testing\\b` heading when one exists,
    else append at the end. Exactly one marker, or END before BEGIN: insert a
    fresh block by the same rule and leave the orphan (mirrors
    the other upserts: never silently rewrite a half-corrupt body).
    Empty body: return `block`.
    """
    body = body or ""
    if not body.strip():
        return block

    begin_idx = body.find(MANUAL_CONFIRMATION_BEGIN)
    end_idx = body.find(MANUAL_CONFIRMATION_END)

    if begin_idx != -1 and end_idx != -1 and begin_idx < end_idx:
        after_end = end_idx + len(MANUAL_CONFIRMATION_END)
        return body[:begin_idx] + block + body[after_end:]

    # Neither both present and in order — insert before ## Manual Testing or append.
    if not block:
        return body

    m = _MANUAL_HEADING_RE.search(body)
    if m:
        return body[:m.start()] + block + "\n\n" + body[m.start():]
    return body.rstrip() + "\n\n" + block + "\n"


_CB_RE = re.compile(r"- \[( |x)\]", re.I)


def _neutralize_checkboxes(text: str) -> str:
    """Rewrite `- [ ]`/`- [x]` in source text to `- ( )`/`- (x)`.

    Prevents reviewer-verification bullets from containing a real unchecked or
    checked checkbox that the manual_testing_clearance scanner would count.
    """
    def _replace(m: re.Match) -> str:
        return "- ( )" if m.group(1) == " " else "- (x)"
    return _CB_RE.sub(_replace, text)


def upsert_reviewer_verification(body: str, block: str) -> str:
    """Idempotent upsert, positioned after `## Manual Testing`.

    Both markers present, BEGIN before END: replace BEGIN..END inclusive (empty
    `block` therefore REMOVES the block). Neither present: insert immediately
    before the first `^#{2,6}\\s*\\S` heading strictly after `## Manual Testing`
    when one exists, else append at end. Exactly one marker, or END before
    BEGIN: insert a fresh block by the same rule and leave the orphan.
    Empty body: return `block`.
    """
    body = body or ""
    if not body.strip():
        return block

    begin_idx = body.find(REVIEWER_VERIFICATION_BEGIN)
    end_idx = body.find(REVIEWER_VERIFICATION_END)

    if begin_idx != -1 and end_idx != -1 and begin_idx < end_idx:
        after_end = end_idx + len(REVIEWER_VERIFICATION_END)
        return body[:begin_idx] + block + body[after_end:]

    # Neither both present and in order — insert after ## Manual Testing, or append.
    if not block:
        return body

    manual_m = _MANUAL_HEADING_RE.search(body)
    if manual_m:
        rest = body[manual_m.end():]
        next_m = re.search(r"^#{2,6}\s*\S", rest, re.M)
        if next_m:
            insert_at = manual_m.end() + next_m.start()
            return body[:insert_at] + block + "\n\n" + body[insert_at:]
        # No following heading — append at end
        return body.rstrip() + "\n\n" + block + "\n"
    return body.rstrip() + "\n\n" + block + "\n"


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


def retro_registry_row_from_diff(diff_text: str) -> str:
    """First `## Class registry` row ADDED by this diff, or "".

    Phase 9 routing rows live in ibl5/docs/retrospective-class-registry.md and
    have the shape `| <YYYY-MM-DD> | #<PR> | class: ... | routed to: Rung <n> - ... | prior: ... |`.
    An added row means this branch materializes a retrospective routing, which
    obliges the PR body to explain the class (see /post-plan Phase 2).
    """
    in_backlog = False
    for line in diff_text.splitlines():
        if line.startswith("diff --git "):
            m = re.match(r"diff --git a/(.*?) b/(.*)$", line)
            in_backlog = bool(m and m.group(2) == "ibl5/docs/retrospective-class-registry.md")
        elif in_backlog and line.startswith("+") and not line.startswith("+++"):
            candidate = line[1:].strip()
            if _RETRO_ROW_RE.match(candidate):
                return candidate
    return ""


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
    c.count_shell = sum(1 for f in files if is_shell_path(f))
    c.count_workflow = _count(files, _WORKFLOW)

    c.has_php = c.count_php > 0
    c.has_css = c.count_css > 0
    c.has_migration = c.count_migration > 0
    c.has_test = c.count_test > 0
    c.has_e2e_specs = c.count_e2e_specs > 0
    c.has_go = c.count_go > 0
    c.go_touched = c.go_touched_count > 0
    c.engine_only = c.go_touched and c.count_php == 0 and c.count_ibl5 == 0
    c.golden_changed = GOLDEN_PATH in files
    c.has_shell = c.count_shell > 0
    c.has_workflow = c.count_workflow > 0
    c.has_skill_prose = any(_SKILL_PROSE.match(f) for f in files)
    c.has_gm_visible = any(is_gm_visible_path(f) for f in files)

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
    c.retro_registry_row = retro_registry_row_from_diff(diff_text)

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

    shell_added = 0
    keep = False
    for line in c.filtered_diff.splitlines():
        if line.startswith("diff --git"):
            keep = is_shell_path(line.split()[-1][2:])
        elif keep and line.startswith("+") and not line.startswith("++"):
            shell_added += 1
    c.lines_shell_changed = shell_added

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


_MANUAL_HEADING_RE = re.compile(r"^#{2,6}\s*Manual\s+Testing\b", re.I | re.M)
_NEXT_HEADING_RE = re.compile(r"^#{1,6}\s", re.M)
# Gate parity. armable._manual_section, bin/lib/pr-armable.sh and
# bin/check-pr-manual-testing end the `## Manual Testing` window at the next
# `^## ` line only; a `###` line is inside the window. The restore protects
# exactly that window (backlog#1188), so its end regex is this one, never
# _NEXT_HEADING_RE.
_NEXT_SECTION_RE = re.compile(r"^## ", re.M)


def strip_manual_testing_section(body: str) -> tuple[str, bool]:
    """Remove a `## Manual Testing` section from a PR body.

    Scans for a heading matching `^#{2,6}\\s*Manual\\s+Testing\\b` and drops
    from that heading up to (not including) the next heading or EOF.
    Returns `(new_body, stripped)`. Fence-blind and unconditional — a fenced
    example of the heading is not a case worth preserving.
    """
    m = _MANUAL_HEADING_RE.search(body)
    if not m:
        return body, False
    start = m.start()
    # find the next heading after this one
    rest = body[m.end():]
    next_m = _NEXT_HEADING_RE.search(rest)
    if next_m:
        end = m.end() + next_m.start()
    else:
        end = len(body)
    new_body = body[:start] + body[end:]
    return new_body, True


# The runner's CLEARED sentinel. "covered by", never "verified by": the fidelity
# reviewer's check 4c reads "verified by" as an unevidenced claim that a test run
# happened, and its remediator then rewrote the line and dropped the sentinel (#2489).
MANUAL_TESTING_SENTINEL = (
    "No manual testing needed — all changes are covered by automated tests.")

# Written instead of MANUAL_TESTING_SENTINEL when the located plan's Verification
# Matrix has zero executable rows (.claude/rules/pr-body-test-claim.md mandates this
# text). Same `No manual testing needed` prefix, so armable.SENTINEL_RE and the shell
# twins (bin/lib/pr-armable.sh, bin/check-pr-manual-testing) read it as CLEARED; the
# tail carries no e2e/unit/integration keyword, so the diff-coverage scan stays quiet.
# Never "verified by" (#2489).
MANUAL_TESTING_SENTINEL_STATIC = (
    "No manual testing needed — verification is static; "
    "the plan's Verification Matrix has no executable rows.")

# Written by manual_testing.run() after Phase 6.7 confirms every `- [ ]` row in the
# gate window is ticked. Same `No manual testing needed` prefix, so armable.SENTINEL_RE,
# bin/lib/pr-armable.sh and bin/check-pr-manual-testing all read it as CLEARED; the
# tail names no e2e/playwright/unit/phpunit/integration class, so armable.TAIL_TYPE_RULES
# demands no matching changed file. "covered by", never "verified by" (#2489).
MANUAL_TESTING_SENTINEL_TICKED = (
    "No manual testing needed — every row below was ticked by the harness in "
    "Phase 6.7; each is covered by an automated check that passed.")


def _manual_testing_span(body: str) -> tuple[int, int] | None:
    """`(start, end)` of the arming gate's window: the first `_MANUAL_HEADING_RE`
    line to the next `^## ` line or EOF. Restore-only; strip_manual_testing_section
    keeps its own `_NEXT_HEADING_RE` end.
    """
    m = _MANUAL_HEADING_RE.search(body)
    if not m:
        return None
    next_m = _NEXT_SECTION_RE.search(body, m.end())
    return m.start(), (next_m.start() if next_m else len(body))


_GATE_HEADING_RE = re.compile(r"^## Manual Testing")


def _drop_gate_heading_sections(rest: str, snapshot_rest: str) -> str:
    """Drop `## Manual Testing` sections a fixer added after the protected span.

    armable._manual_section does not end its window at a second
    `^## Manual Testing` line, so a duplicate heading plus a sentinel line would
    read as clearance. When the snapshot's own remainder carries no such
    heading, every one in `rest` is counterfeit and its section (heading down to
    the next other `^## ` line) is removed.
    """
    lines = rest.splitlines(keepends=True)
    if not any(_GATE_HEADING_RE.match(l) for l in lines):
        return rest
    if any(_GATE_HEADING_RE.match(l) for l in snapshot_rest.splitlines()):
        return rest
    kept: list[str] = []
    dropping = False
    for l in lines:
        if _GATE_HEADING_RE.match(l):
            dropping = True
            continue
        if dropping and l.startswith("## "):
            dropping = False
        if not dropping:
            kept.append(l)
    return "".join(kept)


def restore_manual_testing_section(after: str, before: str) -> tuple[str, bool]:
    """Put back the runner-owned `## Manual Testing` section an LLM fixer rewrote.

    `before` is the body snapshot taken just ahead of the fixer; `after` is the live
    body it left. The section is the arming gate's input, so no model edit to it
    survives: the gate window of the returned body equals the snapshot's byte for
    byte. A section the fixer deleted is re-appended.

    When the section was the LAST section of the snapshot, bytes the fixer added
    after it (a 4c evidence line, a `Closes` trailer) sit inside the live window.
    They are moved ABOVE the heading, the same placement upsert_manual_confirmation
    uses, so they survive without becoming gate input. A tail that carries any
    heading-shaped line is dropped instead: relocated, a `##Manual Testing`
    counterfeit would be the first span match on the next round, and a `###` line
    plus sentinel would read as clearance. Any other edit inside the window, and
    any edit-plus-append, is reverted whole. Returns `(body, restored)`.
    """
    b = _manual_testing_span(before)
    if b is None:
        return after, False
    section = before[b[0]:b[1]]
    a = _manual_testing_span(after)
    if a is None:
        return after.rstrip("\n") + "\n\n" + section.rstrip("\n") + "\n", True
    live = after[a[0]:a[1]]
    rest = after[a[1]:]
    clean_rest = _drop_gate_heading_sections(rest, before[b[1]:])
    if live == section and clean_rest == rest:
        return after, False
    rest = clean_rest
    if rest and not section.endswith("\n"):
        # A snapshot that ended without a newline must not glue the next
        # `## ` heading onto the sentinel line (the gate would miss the break).
        rest = "\n" + rest
    if b[1] == len(before) and live.startswith(section):
        tail = live[len(section):]
        if tail.strip() and not (_MANUAL_HEADING_RE.search(tail)
                                 or _NEXT_HEADING_RE.search(tail)):
            head = after[:a[0]].rstrip("\n")
            moved = tail.strip("\n")
            lead = (head + "\n\n" if head else "") + moved + "\n\n"
            return lead + section + rest, True
    return after[:a[0]] + section + rest, True


BACKLOG_REPO = "a-jay85/IBL5-backlog"

# "backlog issue #160", "backlog items #12 and #13", "Backlog #7, #8",
# "backlog: #160", "(backlog) #5", "backlog - #7". A bare `#N`
# autolinks to IBL5's own PR/issue N, so backlog refs must carry the repo prefix.
_BACKLOG_REF_RE = re.compile(
    r"(\bbacklog(?:\s+(?:issues?|items?|entry|entries))?(?:\s*[:)\-\u2013\u2014]\s*|\s+))"
    r"(#\d+(?:(?:\s*,\s*|\s*/\s*|,?\s+(?:and|or)\s+)#\d+)*)",
    re.I,
)


def qualify_backlog_refs(body: str) -> tuple[str, int]:
    """Rewrite bare `#N` refs that follow the word "backlog" to
    `a-jay85/IBL5-backlog#N`. Returns `(new_body, refs_rewritten)`.
    Already-qualified refs (`IBL5-backlog#N`) never match."""
    count = 0

    def _sub(m: re.Match) -> str:
        nonlocal count
        refs, n = re.subn(r"#(\d+)", rf"{BACKLOG_REPO}#\1", m.group(2))
        count += n
        return m.group(1) + refs

    return _BACKLOG_REF_RE.sub(_sub, body or ""), count


# GitHub closing keywords (docs: "Linking a pull request to an issue").
_CLOSE_KW = r"(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)"
BACKLOG_CLOSES_START = "<!-- backlog-closes:start -->"
BACKLOG_CLOSES_END = "<!-- backlog-closes:end -->"
_BACKLOG_CLOSES_BLOCK_RE = re.compile(
    re.escape(BACKLOG_CLOSES_START) + r".*?" + re.escape(BACKLOG_CLOSES_END) + r"\n?",
    re.S)


def _closing_ref_re(n: int) -> re.Pattern:
    """Matches a closing keyword + the qualified ref for backlog issue n.
    `(?!\\d)` keeps #1 from matching inside #12."""
    return re.compile(
        rf"\b{_CLOSE_KW}\s*:?\s+({re.escape(BACKLOG_REPO)}#{n})(?!\d)", re.I)


def normalize_backlog_closes(body: str, closes_issues: list[int],
                             refs_issues: list[int]) -> str:
    """Make the PR body close exactly the plan's closes-kind backlog issues.

    Both lists empty (plan-blind run, or plan without `## Backlog issues`) =>
    body returned unchanged. Otherwise: rebuild the marker block with a
    `Closes a-jay85/IBL5-backlog#N` line for each closes-kind issue the body
    does not already close, and strip any closing keyword in front of a
    refs-kind issue. Idempotent; never writes a bare `#N`.
    """
    body = body or ""
    if not closes_issues and not refs_issues:
        return body
    closes = list(dict.fromkeys(closes_issues))
    closes_set = set(closes)
    refs = [n for n in dict.fromkeys(refs_issues) if n not in closes_set]
    out = _BACKLOG_CLOSES_BLOCK_RE.sub("", body).rstrip("\n")
    for n in refs:
        out = _closing_ref_re(n).sub(r"\1", out)
    missing = [n for n in closes if not _closing_ref_re(n).search(out)]
    if missing:
        lines = "\n".join(f"Closes {BACKLOG_REPO}#{n}" for n in missing)
        out = f"{out}\n\n{BACKLOG_CLOSES_START}\n{lines}\n{BACKLOG_CLOSES_END}"
    return out + "\n"


def backlog_closes_mismatch(expected: list[int], base: str,
                            refs: list[tuple[str, int]]) -> str:
    """One log line comparing plan closes-kind issues with GitHub's
    closingIssuesReferences. GitHub links closing keywords only for PRs whose
    base is the default branch; a stacked PR is linked when GitHub retargets
    it to master after its parent merges, so a non-master base is a SKIP."""
    if base != "master":
        return (f"phase2: backlog-closes self-check SKIP (base={base}; "
                "GitHub links closing keywords after retarget to master)")
    got = {n for repo, n in refs if repo.lower() == BACKLOG_REPO.lower()}
    missing = sorted(set(expected) - got)
    if missing:
        return (f"phase2: WARN backlog-closes MISMATCH: GitHub will not close "
                f"{', '.join(f'{BACKLOG_REPO}#{n}' for n in missing)}")
    return f"phase2: backlog-closes self-check OK ({len(expected)} issue(s) linked)"


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


def slice_agent_e_diff(filtered_diff: str) -> str:
    """Agent E pre-slice: shell + workflow + .claude prose sections only."""
    out: list[str] = []
    keep = False
    for line in filtered_diff.splitlines(keepends=True):
        if line.startswith("diff --git"):
            keep = is_agent_e_path(line.split()[-1][2:])
        if keep:
            out.append(line)
    return "".join(out)
