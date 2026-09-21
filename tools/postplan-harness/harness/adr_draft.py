"""One-shot ADR auto-draft on a `bin/pre-push-adr-hook` denial.

`bin/pre-push-adr-hook` refuses a push whose diff touches a decision-trigger surface
without an accompanying ADR. That denial reaches `runner.py` as
`HarnessError("local-gate", ...)` and lands on exit 3, the human wall. This module
drafts the missing ADR once, validates it, runs the same local gates CI will run, and
commits it so the caller can re-push exactly once.

Every failure path discards the draft before raising. A rejected file left unstaged in
the worktree would be swept up by the next run's `stage_all()` and committed without
ever passing the gates that rejected it.
"""
from __future__ import annotations

import datetime
import os
import re
import shutil
import subprocess
from dataclasses import dataclass

from .adapters.llm import MODEL_MAP
from .state import HarnessError

ADR_DRAFT_PURPOSE = "adr-draft"
ADR_DRAFT_MODEL = "opus"                      # MODEL_MAP key; resolves to claude-opus-5
ADR_DRAFT_MAX_TURNS = 20
ADR_DRAFT_ALLOWED_TOOLS = ("Read", "Grep", "Glob", "Write")
ADR_DRAFT_DENIED_TOOLS = ("Bash", "Edit", "NotebookEdit", "Agent")
ADR_ATTRIBUTION = ("> This ADR was drafted by the post-plan harness for this PR. "
                   "A human must review and approve it before merging.")
ADR_COMMIT_MSG = "docs: add ADR-{number} {slug} (drafted by the post-plan harness)"
ADR_DIR = "ibl5/docs/decisions"
ADR_INDEX = "ibl5/docs/decisions/README.md"   # the only other path the drafter may touch
REJECTED_DRAFT_NAME = "adr-draft-rejected.md"
_PLAN_HEAD_CHARS, _DIFF_CAP_CHARS, _EXAMPLE_CAP_CHARS, _EXAMPLE_COUNT = 4000, 60_000, 6000, 3
_TEMPLATE_LEFTOVERS = ("<Title>", "ADR-NNNN", "YYYY-MM-DD", "<Alternative 1>",
                       "<tradeoff we accepted>")

_SURFACES_MARKER = "Decision-trigger surfaces detected:"
_COLLISION_MARKER = "Numbering collision:"
_COLLISION_LINE = re.compile(r"^\s*(\d{4}): ")
_FOUR_DIGITS = re.compile(r"^(\d{4})")


@dataclass(frozen=True)
class AdrDraftResult:
    path: str
    number: str
    model: str
    sha: str


def _read_text(path: str) -> str:
    try:
        with open(path) as fh:
            return fh.read()
    except OSError:
        return ""


def _run_script(worktree: str, rel: str, *args: str, stdin: str = "") -> tuple[int, str]:
    """Every `bin/*` invocation in this module goes through this one seam, so a test
    monkeypatches one function instead of six subprocess calls. Shape copied from
    `_remediate_doc_staleness` in runner.py.
    """
    try:
        proc = subprocess.run([os.path.join(worktree, rel), *args], cwd=worktree,
                              input=stdin, capture_output=True, text=True,
                              errors="replace")
    except OSError as exc:
        return 127, f"{rel}: {exc}"
    return proc.returncode, (proc.stdout or "") + (proc.stderr or "")


def _git(worktree: str, *args: str) -> tuple[int, str]:
    proc = subprocess.run(["git", "-C", worktree, *args],
                          capture_output=True, text=True, errors="replace")
    return proc.returncode, (proc.stdout or "")


def _slug_for(branch: str) -> str:
    """Deterministic: no model call decides the filename."""
    slug = re.sub(r"[^a-z0-9]+", "-", (branch or "").lower()).strip("-")[:60].strip("-")
    return slug or "harness-drafted-adr"


def _stray_paths(worktree: str, allowed: set[str]) -> list[str]:
    _, out = _git(worktree, "status", "--porcelain")
    paths = []
    for line in out.splitlines():
        if len(line) < 4:
            continue
        path = line[3:].strip()
        if " -> " in path:               # a rename reports "old -> new"
            path = path.split(" -> ", 1)[1]
        path = path.strip('"')
        if path and path not in allowed:
            paths.append(path)
    return sorted(set(paths))


def _discard(worktree: str, rel: str, out_dir: str, log, phase: str, reason: str,
             strays=()) -> None:
    """Unstage and remove the draft, revert any stray write, park the rejected text in
    the run directory for a human to read.
    """
    _git(worktree, "reset", "-q", "--", rel)
    target = os.path.join(worktree, rel)
    rejected = os.path.join(out_dir, REJECTED_DRAFT_NAME)
    if os.path.exists(target):
        try:
            os.makedirs(out_dir, exist_ok=True)
            shutil.move(target, rejected)
        except OSError:
            try:
                os.unlink(target)
            except OSError:
                pass
    for stray in strays:
        rc, _ = _git(worktree, "ls-files", "--error-unmatch", stray)
        if rc == 0:
            _git(worktree, "reset", "-q", "--", stray)
            _git(worktree, "checkout", "--", stray)
        else:
            try:
                os.unlink(os.path.join(worktree, stray))
            except OSError:
                pass
    log(f"{phase}: ADR draft discarded -> {rejected} ({reason})")


def validate_adr_text(text: str, number: str, today: str) -> list[str]:
    """Return every problem found; an empty list means the draft may be committed."""
    problems: list[str] = []
    body = text
    if not text.startswith("---\n"):
        problems.append("frontmatter does not open with ---")
    else:
        end = text.find("\n---", 4)
        if end == -1:
            problems.append("frontmatter is not closed with ---")
        else:
            front, body = text[4:end], text[end + 4:]
            if not re.search(r"^description:\s*\S", front, re.M):
                problems.append("frontmatter has no non-empty description:")
            if not re.search(r"^last_verified:\s*\d{4}-\d{2}-\d{2}\s*$", front, re.M):
                problems.append("frontmatter has no last_verified: YYYY-MM-DD")
    first = next((ln for ln in body.splitlines() if ln.strip()), "")
    if first.strip() != ADR_ATTRIBUTION:
        problems.append("first body line is not the harness attribution line")
    if f"# ADR-{number}:" not in text:
        problems.append(f"missing the title line '# ADR-{number}: <title>'")
    for heading in ("## Context", "## Decision"):
        if heading not in text:
            problems.append(f"missing the {heading} section")
    for token in _TEMPLATE_LEFTOVERS:
        if token in text:
            problems.append(f"template placeholder left in place: {token}")
    if "no-adr" in text.lower():
        problems.append("the text 'no-adr' appears; the drafter may never bypass the gate")
    if len(text) < 600:
        problems.append(f"draft is only {len(text)} chars; too thin to review")
    return problems


def _examples(worktree: str, rel: str) -> str:
    adr_dir = os.path.join(worktree, ADR_DIR)
    try:
        names = sorted(n for n in os.listdir(adr_dir)
                       if re.match(r"^\d{4}-.*\.md$", n) and not n.startswith("0000-"))
    except OSError:
        return "(no existing ADRs to show)"
    names = [n for n in names if os.path.join(ADR_DIR, n) != rel][-_EXAMPLE_COUNT:]
    blocks = [f"----- {n} -----\n{_read_text(os.path.join(adr_dir, n))[:_EXAMPLE_CAP_CHARS]}"
              for n in names]
    return "\n\n".join(blocks) or "(no existing ADRs to show)"


def build_prompt(worktree: str, rel: str, number: str, surfaces: str, diff: str,
                 plan_path: str | None, today: str) -> str:
    plan = _read_text(plan_path)[:_PLAN_HEAD_CHARS] if plan_path else ""
    return f"""You are drafting one Architecture Decision Record for a pull request that
`bin/adr-check` has flagged. The file already exists at `{rel}` holding the raw ADR
template. Rewrite that one file in full.

Hard requirements. An automated validator checks every one of them and discards your
work if it misses:

1. Write ONLY `{rel}`, using the Write tool. Do not create, edit, or delete any other
   file. Do not run any command.
2. Keep the YAML frontmatter. It must carry a non-empty `description:` and
   `last_verified: {today}`.
3. The first line of the body, immediately after the closing `---`, must be this line
   verbatim:
   {ADR_ATTRIBUTION}
4. Title the ADR `# ADR-{number}: <a specific title>`. Then `**Status:** Accepted`,
   `**Date:** {today}`, `**Deciders:** post-plan harness (auto-draft)`.
5. Fill `## Context`, `## Decision`, `## Alternatives Considered` with at least two
   real alternatives and why each was rejected, `## Consequences`, and `## References`.
   Delete the `## Supersedes` section.
6. Leave no template placeholder text behind. The validator rejects the literal tokens
   `<Title>`, `ADR-NNNN`, `YYYY-MM-DD`, `<Alternative 1>` and `<tradeoff we accepted>`.
7. Never write the text `no-adr` anywhere. That string bypasses the gate this ADR
   exists to satisfy.
8. Obey `.claude/rules/prose-style.md`. No em-dash, no "not X but Y" construction, no
   buzzwords. `bin/check-prose` runs on your output and a failure discards it.
9. Every repo path you put in backticks must exist in this worktree. Check with Read or
   Glob before citing it. `bin/check-docs` runs on your output.

Write about the decision the diff below actually makes. Ground the Context in the
surfaces `bin/adr-check` flagged rather than in generic reasoning.

----- bin/adr-check output (the surfaces that require this ADR) -----
{surfaces}

----- the implementation plan for this branch (first {_PLAN_HEAD_CHARS} chars) -----
{plan or "(no plan file for this branch)"}

----- the diff this ADR must describe -----
{diff[:_DIFF_CAP_CHARS]}

----- recent ADRs, for shape and tone only -----
{_examples(worktree, rel)}

Reply with the single line: ADR-WRITTEN: {rel}
"""


def _taken_numbers(worktree: str, seed: set[str]) -> set[str]:
    taken = set(seed)
    for ref in ("HEAD", "origin/master"):
        _, listing = _git(worktree, "ls-tree", "--name-only", ref, f"{ADR_DIR}/")
        for path in listing.splitlines():
            m = _FOUR_DIGITS.match(os.path.basename(path.strip()))
            if m:
                taken.add(m.group(1))
    try:
        names = os.listdir(os.path.join(worktree, ADR_DIR))
    except OSError:
        names = []
    for name in names:
        m = _FOUR_DIGITS.match(name)
        if m:
            taken.add(m.group(1))
    return taken


def fix_numbering_collision(worktree: str, git, rel: str, log, phase: str) -> str:
    """Rename the still-empty template onto a free number BEFORE the model writes.

    Running here rather than after the draft removes any cross-reference rewrite: the
    only text in the file at this point is the untouched template.
    """
    git.stage_all()
    rc, out = _run_script(worktree, "bin/check-numbering", "--staged")
    if rc == 0:
        return rel
    if _COLLISION_MARKER not in out:
        raise HarnessError("adr-draft-gate", f"check-numbering: {out.strip()[:300]}")
    colliding = {m.group(1) for m in
                 (_COLLISION_LINE.match(ln) for ln in out.splitlines()) if m}
    number = os.path.basename(rel)[:4]
    if number not in colliding:
        # The collision belongs to something already on the branch. This handler
        # renames only its own draft, so it cannot fix that.
        raise HarnessError("adr-draft-gate",
                           f"check-numbering collision is not on the draft's number "
                           f"{number}: {out.strip()[:300]}")
    # Compute the free number here rather than calling bin/next-adr a second time: it
    # scans the same sources and can hand back the same value.
    taken = _taken_numbers(worktree, colliding)
    new_number = f"{max(int(n) for n in taken) + 1:04d}"
    new_rel = os.path.join(os.path.dirname(rel), new_number + os.path.basename(rel)[4:])
    _git(worktree, "reset", "-q", "--", rel)
    os.rename(os.path.join(worktree, rel), os.path.join(worktree, new_rel))
    git.stage_all()
    rc2, out2 = _run_script(worktree, "bin/check-numbering", "--staged")
    if rc2 != 0:
        # One rename per run, the same bound every other arm here carries.
        raise HarnessError("adr-draft-gate",
                           f"collision survived the rename to {new_rel}: "
                           f"{out2.strip()[:300]}")
    log(f"{phase}: ADR number collision on {rel}; renumbered to {new_rel}")
    return new_rel


def _doc_base(worktree: str, base: str) -> str:
    """The base bin/pre-commit-hook hands to check-docs, derived identically."""
    rc, out = _git(worktree, "merge-base", "HEAD", "origin/master")
    return out.strip() if rc == 0 and out.strip() else base


def draft(llm, git, worktree: str, out_dir: str, log, *, phase: str = "phase2",
          plan_path: str | None = None, base: str = "origin/master",
          today: str | None = None) -> AdrDraftResult:
    """Draft, validate, gate and commit one ADR. Every failure raises."""
    today = today or datetime.date.today().isoformat()

    # Empty stdin is the form bin/pre-push-adr-hook uses minus the commit blob, so no
    # `gh` call happens.
    rc, surfaces = _run_script(worktree, "bin/adr-check", "--pr", "--bypass-from-stdin",
                               f"--base={base}", stdin="")
    if rc == 0:
        raise HarnessError("adr-draft-nothing", "bin/adr-check reports no missing ADR")
    if _SURFACES_MARKER not in surfaces:
        raise HarnessError("adr-draft-failed",
                           f"bin/adr-check output has no surfaces block: "
                           f"{surfaces.strip()[:300]}")

    slug = _slug_for(git.branch())
    rc, out = _run_script(worktree, "bin/next-adr", slug)
    if rc != 0:
        raise HarnessError("adr-draft-failed", f"bin/next-adr: {out.strip()[:300]}")
    lines = [ln for ln in out.strip().splitlines() if ln.strip()]
    if not lines:
        raise HarnessError("adr-draft-failed", "bin/next-adr printed no path")
    rel = os.path.relpath(lines[-1].strip(), worktree)

    rel = fix_numbering_collision(worktree, git, rel, log, phase)
    number = os.path.basename(rel)[:4]

    prompt = build_prompt(worktree, rel, number, surfaces,
                          git.diff_vs_base(base), plan_path, today)
    try:
        llm.call_tooled(ADR_DRAFT_PURPOSE, ADR_DRAFT_MODEL, prompt, cwd=worktree,
                        allowed_tools=ADR_DRAFT_ALLOWED_TOOLS,
                        denied_tools=ADR_DRAFT_DENIED_TOOLS,
                        max_turns=ADR_DRAFT_MAX_TURNS)
    except HarnessError as exc:
        # The adapter's kind is diagnostic in the audit line. The caller never lets it
        # become the exit code.
        _discard(worktree, rel, out_dir, log, phase, exc.kind)
        raise

    problems = validate_adr_text(_read_text(os.path.join(worktree, rel)), number, today)
    if problems:
        _discard(worktree, rel, out_dir, log, phase, "invalid draft")
        raise HarnessError("adr-draft-invalid", "; ".join(problems)[:400])

    strays = _stray_paths(worktree, {rel, ADR_INDEX})
    if strays:
        # This keeps a Write-enabled model from editing a frozen ADR
        # (.claude/rules/adr-append-only.md) or any source file.
        _discard(worktree, rel, out_dir, log, phase, "wrote outside its allocated path",
                 strays=strays)
        raise HarnessError("adr-draft-scope",
                           ("drafter touched: " + ", ".join(strays))[:400])

    # Stage first: bin/check-docs --since ignores untracked files, and
    # bin/check-numbering --since diffs <sha>...HEAD, which cannot see an uncommitted
    # file. The index is where the new ADR sits before the commit.
    git.stage_all()
    for name, args in (("check-numbering", ("bin/check-numbering", "--staged")),
                       ("check-docs", ("bin/check-docs",
                                       f"--since={_doc_base(worktree, base)}")),
                       ("check-prose", ("bin/check-prose", "--files", rel))):
        grc, gout = _run_script(worktree, *args)
        if grc != 0:
            _discard(worktree, rel, out_dir, log, phase, f"{name} failed")
            raise HarnessError("adr-draft-gate", f"{name}: {gout.strip()[:300]}")

    try:
        sha = git.commit_all(ADR_COMMIT_MSG.format(number=number, slug=slug))
    except HarnessError:
        _discard(worktree, rel, out_dir, log, phase, "commit refused")
        raise

    rc, out = _run_script(worktree, "bin/adr-check", "--pr", "--bypass-from-stdin",
                          f"--base={base}", stdin="")
    if rc != 0:
        # The commit stays. This is the "drafted file left committed locally" case, and
        # the caller parses rel out of the detail so the DM names it.
        raise HarnessError("adr-draft-gate",
                           f"{rel}|{sha}|adr-check still fails: {out.strip()[:300]}")

    log(f"{phase}: ADR drafted at {rel} model={MODEL_MAP[ADR_DRAFT_MODEL]} "
        f"commit={sha[:12]}")
    return AdrDraftResult(rel, number, MODEL_MAP[ADR_DRAFT_MODEL], sha)
