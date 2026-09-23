"""Phase 5.5 — the plan-intent fidelity review.

The two places a silent wrong answer here arms a PR nobody reviewed are the verdict
parse and the `REVIEWED_TREE` record, so both live behind module constants that Phase 4
imports rather than re-deriving. That import *is* the drift guard the skill enforces
with prose.

`None` from `parse_verdict` means **indeterminate**, never `NOT READY`.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import time

from .state import HarnessError
from . import llm_calls

# Anchored and multiline: a verdict word must own its whole line. Longest alternative
# first so "READY WITH NOTES" is never truncated to "READY".
VERDICT_RE = re.compile(r"^(READY WITH NOTES|NOT READY|READY)[ \t]*$", re.M)
DIGEST_CUT = "## DIGEST"

# Bare hash only. Anything after the hash on that line is NOT a valid record — the
# skill's condition (12) parser has the same property, and prose there silently
# defeats the tree-freshness check.
REVIEWED_TREE_RE = re.compile(r"^REVIEWED_TREE=([0-9a-f]{40})$", re.M)

# The reviewer's tool budget. `Bash` is deliberately absent from the allowlist: every
# input is already on disk in the packet, so it needs no command execution and cannot
# trip `--permission-prompts none`.
REVIEW_ALLOWED_TOOLS = ("Read", "Grep", "Glob")
# The `pr-ready-phase6` def itself grants Bash and Write, and --allowedTools removes
# nothing, so the deny list is what actually takes them away.
REVIEW_DENIED_TOOLS = ("Bash", "Write", "Edit", "NotebookEdit", "Agent")

PROCEDURE_PATHS = (
    ".claude/review-shared/_plan-fidelity-review.md",
)

REMEDIATION_PATHS = (
    ".claude/review-shared/_phase65-remediation.md",
)

# The remediation agent may read files, edit the worktree, and fix PR-body findings with
# `gh pr edit`. It needs Bash for that last one: PR-body findings live on GitHub, not in
# the worktree, and the old no-Bash budget left the agent unable to act on them at all
# (PR #2308 round 3 — both blocking findings were body text, and the agent committed
# nothing). The harness still commits and pushes worktree edits after the agent exits.
#
# Read the deny list as a denylist, not a sandbox. The allow side is bare `Bash`, so
# anything not named below is reachable; these five cover the escape routes that matter
# for "never pushes, never arms a merge". Claude Code matches deny patterns on the
# command prefix, so a compound command could slip past one. The prompt instruction in
# `remediate()` is the contract; this list is the guardrail under it.
REMEDIATION_ALLOWED_TOOLS = ("Read", "Grep", "Glob", "Edit", "Write", "Bash")
REMEDIATION_DENIED_TOOLS = (
    "Agent",
    "Bash(git push:*)",
    "Bash(git commit:*)",
    "Bash(gh pr merge:*)",
    "Bash(gh pr review:*)",
    "Bash(gh api:*)",
)

# Inline diff budget for the remediation prompt. 40 KB is ~10K tokens: large enough
# to carry every diff observed in the three round-two deaths (#2308 8 KB, #2310 14 KB,
# #2315 22 KB) and small enough that the prompt stays well under the Sonnet context
# ceiling with the procedure and verdict beside it. Past the cap the diff is truncated
# with a marker, and the on-disk path is still listed for a targeted Read with
# offset/limit.
REMEDIATION_DIFF_INLINE_CAP = 40_000

REMEDIATION_COMMIT_MSG = "chore: address Phase 5.5 plan-fidelity findings"

# Arming-condition numbers whose machine-fixable work the Phase 5.5 fixer is fed, in
# the order they are rendered into the packet. (12) is the fidelity verdict itself;
# (3) plan-named files the conformance pass could not find; (16) failing meta-checks;
# (2) review findings at or above the same score floor armable uses.
HOLD_SOURCES = ("12", "3", "16", "2")
HIGH_SCORE_FLOOR = 80                  # same floor as armable condition (2)
HOLD_LABELS = {
    "12": "plan-fidelity verdict",
    "3": "plan-named file missing",
    "16": "meta-check failed",
    "2": "review finding scored >= 80",
}
_FINDING_BULLET_RE = re.compile(r"^\s*(?:[-*]|\d+[.)])\s+\S")
_WORK_LIST_OPEN = "=== WORK LIST (each item names the arming hold it clears) ==="
_WORK_LIST_CLOSE = "=== END WORK LIST ==="

# A fixer round must never edit the machinery that decides what arms. The prompt says
# so advisorily; this tuple is the enforcement, checked against the round's own commit
# between commit and push so a violating commit stays local.
GATE_OWNING_PREFIXES = (
    ".claude/rules/",
    ".github/workflows/",
    "bin/check-",
    "tools/postplan-harness/harness/armable.py",
)
GATE_EDIT_DENY_TEXT = (
    "NEVER edit these gate-owning paths: .claude/rules/**, .github/workflows/**, "
    "bin/check-*, tools/postplan-harness/harness/armable.py. A commit touching one "
    "is discarded and ends remediation."
)


def denied_gate_edits(paths) -> list[str]:
    """The subset of `paths` that lies under a gate-owning prefix."""
    return [p for p in (paths or [])
            if any(str(p).startswith(prefix) for prefix in GATE_OWNING_PREFIXES)]


def _verdict_findings(verdict_path: str) -> list[str]:
    """Hold (12): the blocking findings under a NOT READY verdict, one per bullet.

    A verdict that is not NOT READY contributes nothing -- the loop only ever fixes a
    verdict it is still held on. A NOT READY verdict whose findings are prose rather
    than bullets contributes its whole pre-digest body, so a shape the bullet regex
    does not recognise is never silently dropped.

    The body is everything ABOVE the digest cut bar the verdict word lines themselves.
    A real verdict states its 6d checks and their findings first and puts the 6e word
    last, so reading only below the word would make every real verdict contribute
    nothing and skip a remediation the loop is held on.
    """
    if parse_verdict(verdict_path) != "NOT READY":
        return []
    text = _read_or_marker(verdict_path, "")
    lines = text.splitlines()
    for i, line in enumerate(lines):
        if line.strip() == DIGEST_CUT:
            lines = lines[:i]
            break
    body = [ln for ln in lines if not VERDICT_RE.match(ln)]
    bullets = [ln.strip() for ln in body if _FINDING_BULLET_RE.match(ln)]
    if bullets:
        return bullets
    whole = "\n".join(body).strip()
    return [whole] if whole else []


def _render_finding(f: dict) -> str:
    """Hold (2) item text. review.py emits path/line/score/body_head; an unexpected
    shape is dumped rather than crashed on."""
    try:
        return (f"{f['path']}:{f['line']} score={f['score']} {f['body_head']}")
    except (KeyError, TypeError):
        return json.dumps(f, sort_keys=True, default=str)


def build_work_list(verdict_path: str,
                    unresolved_conformance=None,
                    meta_check_failures=None,
                    scored_findings=None) -> list[dict]:
    """The union of every machine-fixable arming hold, each item tagged with its hold.

    Built from inputs the runner already holds upstream of Phase 5.5, never from
    `armable.evaluate` -- that decision is made once, below the loop.
    """
    items: list[dict] = []
    for text in _verdict_findings(verdict_path):
        items.append({"hold": "12", "text": text})
    # UNMET-CONTRACT entries are deliberately excluded: they name evidence the plan
    # declared, and a fixer that "adds" such evidence is the fabrication conformance
    # exists to catch.
    for entry in (unresolved_conformance or []):
        if str(entry).startswith("MISSING"):
            items.append({"hold": "3", "text": str(entry)})
    for fail in (meta_check_failures or []):
        items.append({"hold": "16",
                      "text": f"{fail.get('name', 'unknown')}\n{fail.get('output', '')}"})
    for f in (scored_findings or []):
        try:
            score = int(f.get("score") or 0)
        except (AttributeError, TypeError, ValueError):
            score = 0
        if score >= HIGH_SCORE_FLOOR:
            items.append({"hold": "2", "text": _render_finding(f)})
    ordered: list[dict] = []
    seen: set[tuple[str, str]] = set()
    for hold in HOLD_SOURCES:
        for item in items:
            key = (item["hold"], item["text"])
            if item["hold"] == hold and key not in seen:
                seen.add(key)
                ordered.append(item)
    return ordered


def work_list_sizes(items) -> dict[str, int]:
    """Per-hold counts with every key present, which is what result.json records."""
    sizes = {hold: 0 for hold in HOLD_SOURCES}
    for item in (items or []):
        hold = item.get("hold")
        if hold in sizes:
            sizes[hold] += 1
    return sizes


def render_work_list(items) -> str:
    """The prompt block. An empty list still renders both fences, so the packet shape
    does not change depending on how many holds happen to be live."""
    lines = [_WORK_LIST_OPEN]
    if not items:
        lines.append("(empty)")
    for item in items:
        label = HOLD_LABELS.get(item["hold"], "unknown hold")
        lines.append(f"[hold {item['hold']} \u2014 {label}] {item['text']}")
    lines.append(_WORK_LIST_CLOSE)
    return "\n".join(lines) + "\n"


OVERRIDE = (
    "TOOL-BUDGET OVERRIDE for this invocation. You have NO `Write` tool and NO `Bash` "
    "tool. Do not attempt to write the verdict file — the harness writes it for you. "
    "Emit the COMPLETE verdict document as your final message: the full 6d checks, the "
    "6e verdict word on a line of its own (exactly one of `READY`, `READY WITH NOTES`, "
    "or `NOT READY`), and the 6e(b) digest below a `## DIGEST` line. Read your inputs "
    "from the packet files named in the prompt."
)

PLAN_BLIND_MARKER = (
    "# PLAN-BLIND RUN\n\n"
    "No plan file was located for this branch.\n\n"
    "6d checks 1, 2 and 5 are **not assessable — plan-blind run**; say exactly that for\n"
    "each rather than passing or failing them. Checks 3, 4 and 6 still run normally\n"
    "against the diff and the PR body. A 6e verdict word and the 6e(b) digest are still\n"
    "required. Do not treat the missing plan as a defect in the implementation.\n"
)


def verdict_path(pr_number: int | str) -> str:
    return f"/tmp/post-plan-fidelity-verdict-{pr_number}.md"


def parse_verdict(path: str) -> str | None:
    """Last verdict word ABOVE the digest cut, or None when indeterminate."""
    try:
        with open(path) as fh:
            text = fh.read()
    except OSError:
        return None
    if not text.strip():
        return None
    lines = text.splitlines()
    for i, line in enumerate(lines):
        if line.strip() == DIGEST_CUT:
            lines = lines[:i]
            break
    matches = VERDICT_RE.findall("\n".join(lines))
    if not matches:
        return None
    return matches[-1].rstrip()


def record_reviewed_tree(path: str, tree: str) -> None:
    """Append a bare `REVIEWED_TREE=<tree sha>` line. Never creates the file.

    `tree` must be `git rev-parse HEAD^{tree}` — a *tree* sha. A commit sha here blocks
    condition (12) forever.
    """
    try:
        with open(path) as fh:
            text = fh.read()
    except OSError:
        return
    if not text.strip():
        return
    if re.search(r"^REVIEWED_TREE=", text, re.M):
        return
    if not text.endswith("\n"):
        text += "\n"
    with open(path, "w") as fh:
        fh.write(text + f"REVIEWED_TREE={tree}\n")


def read_reviewed_tree(path: str) -> str | None:
    """The hash of the LAST bare `REVIEWED_TREE=` line, or None."""
    try:
        with open(path) as fh:
            text = fh.read()
    except OSError:
        return None
    matches = REVIEWED_TREE_RE.findall(text)
    return matches[-1] if matches else None


def _git_show(worktree: str, ref_path: str) -> str | None:
    proc = subprocess.run(["git", "show", ref_path], cwd=worktree,
                          capture_output=True, text=True)
    if proc.returncode != 0:
        return None
    return proc.stdout


def _find_procedure(worktree: str, master_sha: str, paths, kind: str) -> str:
    for candidate in paths:
        body = _git_show(worktree, f"{master_sha}:{candidate}")
        if body is not None:
            return body
    raise HarnessError(kind, f"{master_sha}: none of {', '.join(paths)}")


def build_packet(out_dir: str, master_sha: str, reviewed_tree: str, plan, diff: str,
                 pr_body: str, pr_number: int | str, phase4b_ran: bool, *,
                 worktree: str = ".", packet_name: str = "fidelity-packet",
                 extra_context: str = "") -> str:
    """Write the seven `_phase-5.5-fidelity.md` Step-2 inputs as files. Returns the dir."""
    packet = os.path.join(out_dir, packet_name)
    os.makedirs(packet, exist_ok=True)

    procedure = _find_procedure(worktree, master_sha, PROCEDURE_PATHS,
                                "fidelity-procedure-missing")

    def _write(name: str, body: str) -> None:
        with open(os.path.join(packet, name), "w") as fh:
            fh.write(body)

    _write("procedure.md", procedure)

    if getattr(plan, "found", False) and getattr(plan, "path", ""):
        try:
            with open(plan.path) as fh:
                _write("plan.md", fh.read())
        except OSError:
            _write("plan.md", PLAN_BLIND_MARKER)
    else:
        _write("plan.md", PLAN_BLIND_MARKER)

    _write("diff.patch", diff or "")
    _write("pr-body.md", pr_body or "")
    _write("context.md",
           "# Context\n\n"
           "CONFLICT_RESOLVED_PATHS: (none — the harness path rebases cleanly or fails "
           "closed before reaching Phase 5.5)\n"
           f"PHASE_4B_RAN: {'yes' if phase4b_ran else 'no'}\n"
           f"REVIEW_TIMESTAMP: {time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime())}\n"
           f"MASTER_SHA: {master_sha}\n"
           f"REVIEWED_TREE: {reviewed_tree}\n"
           f"PR_NUMBER: {pr_number}\n"
           f"VERDICT_OUTPUT_PATH: {verdict_path(pr_number)}\n"
           + extra_context)
    return packet


def _pointer_prompt(packet_dir: str, pr_number: int | str) -> str:
    return (
        "Perform the /pr-ready runtime Phase 6 plan-intent fidelity review (sections 6b "
        "through 6e) for PR #" f"{pr_number}.\n\n"
        "Every input is already on disk. Read these files:\n"
        f"  - {os.path.join(packet_dir, 'procedure.md')}  — the 6b-6e procedure; follow it exactly\n"
        f"  - {os.path.join(packet_dir, 'plan.md')}       — the plan (or a plan-blind marker)\n"
        f"  - {os.path.join(packet_dir, 'diff.patch')}    — the post-rebase diff under review\n"
        f"  - {os.path.join(packet_dir, 'pr-body.md')}    — the PR body\n"
        f"  - {os.path.join(packet_dir, 'context.md')}    — run context\n\n"
        "You may also Read/Grep/Glob the worktree you are running in to ground a claim.\n"
    )


def review(llm, out_dir: str, worktree: str, packet_dir: str,
           pr_number: int | str, reviewed_tree: str | None = None) -> tuple[str | None, str]:
    """Run the review. Returns (verdict_word_or_None, error_kind_or_'').

    A `HarnessError` from the adapter is an *indeterminate* verdict: condition (12)
    holds and the run continues so the sticky comment can say so. It is never converted
    into a verdict word.
    """
    try:
        text = llm.call_tooled(
            "plan-fidelity-review", "opus", _pointer_prompt(packet_dir, pr_number),
            cwd=worktree, agent="pr-ready-phase6",
            allowed_tools=REVIEW_ALLOWED_TOOLS, denied_tools=REVIEW_DENIED_TOOLS,
            add_dirs=(packet_dir,), append_system_prompt=OVERRIDE,
        )
    except HarnessError as e:
        return None, e.kind

    path = verdict_path(pr_number)
    try:
        with open(path, "w") as fh:
            fh.write(text if text.endswith("\n") else text + "\n")
    except OSError:
        return None, "fidelity-verdict-unwritable"
    if reviewed_tree:
        record_reviewed_tree(path, reviewed_tree)
    return parse_verdict(path), ""


def _read_or_marker(path: str, marker: str) -> str:
    """File text, or `marker` when it cannot be read. Never raises: the prompt must be
    buildable even when a packet file is missing, because the agent can still act on
    the parts that did load."""
    try:
        with open(path) as fh:
            return fh.read()
    except OSError:
        return marker


def _noop_log(_msg: str) -> None:
    return


def remediate(llm, gitad, out_dir: str, worktree: str, packet_dir: str,
              verdict1_path: str, master_sha: str, log=None, *,
              commit=None, push=None, pr_number: int | str | None = None,
              model: str = "sonnet", work_list: list[dict] | None = None,
              outcome: dict | None = None) -> str | None:
    """Fix the blocking findings behind a `NOT READY`. Returns the commit sha, or None.

    The model edits worktree files and may fix PR-body findings via `gh pr edit`. The
    HARNESS commits and pushes worktree edits. `git push`, `git commit`, `gh pr merge`,
    `gh pr review` and `gh api` are denied; that denylist is a guardrail rather than a
    sandbox, and the prompt states the contract it guards.

    `commit` is the message -> sha callable. runner passes its Phase 2 gate wrapper so a
    doc-staleness denial gets the same one-shot last_verified bump here; it is injected
    rather than imported because runner imports this module.

    `push` is the no-arg push callable, injected for the same reason. runner passes its
    retrying push so a master that moved during the review gets a fetch + clean rebase
    before the push instead of a bare `gitad.push()` that the pre-push hook refuses.

    `model` is the alias `call_tooled` validates against its own allowlist; the runner
    escalates it across rounds. `work_list` is the tagged union of machine-fixable
    arming holds; omitted, it falls back to the verdict findings alone. `outcome` is an
    out-dict the runner reads for the round record -- the return stays `str | None` so
    no existing call site changes.
    """
    log = log or _noop_log
    commit = commit or gitad.commit_all
    push = push or gitad.push

    def _out(reason: str) -> None:
        if outcome is not None:
            outcome["reason"] = reason
            outcome["model"] = model
    if parse_verdict(verdict1_path) != "NOT READY":
        _out("not-blocking")
        return None
    if gitad.is_dirty():
        # _phase65-remediation.md's own precondition: a single commit_all on a dirty tree
        # would sweep unrelated edits into the remediation commit.
        log("phase5.5: remediation skipped - dirty worktree")
        _out("dirty-worktree")
        return None
    if work_list is None:
        work_list = build_work_list(verdict1_path, [], [], [])
    if not work_list:
        # Nothing to hand the fixer. Spawning one anyway is a guaranteed no-edit round.
        log("phase5.5: remediation skipped - empty work list")
        _out("empty-work-list")
        return None

    procedure = _find_procedure(worktree, master_sha, REMEDIATION_PATHS,
                               "remediation-procedure-missing")
    with open(os.path.join(packet_dir, "remediation.md"), "w") as fh:
        fh.write(procedure)

    verdict_text = _read_or_marker(verdict1_path, "(verdict unreadable)")
    diff_text = _read_or_marker(os.path.join(packet_dir, "diff.patch"), "(diff unreadable)")
    diff_note = ""
    if len(diff_text) > REMEDIATION_DIFF_INLINE_CAP:
        diff_text = diff_text[:REMEDIATION_DIFF_INLINE_CAP]
        diff_note = (f"\n[diff truncated at {REMEDIATION_DIFF_INLINE_CAP} bytes; the full "
                     f"patch is at {os.path.join(packet_dir, 'diff.patch')} - Read it with "
                     "offset/limit if a finding points past this cut]\n")
    pr_note = (f"\nPR body findings: when a blocking finding is about the PR body, "
               f"fix it with `gh pr edit {pr_number} --body-file <path>`.\n"
               if pr_number is not None else "")
    prompt = (
        "Remediate the blocking findings from the plan-intent fidelity review.\n\n"
        "Every input you need is INLINE below. Do not Read the packet paths first; they\n"
        "are listed only so a finding that cites a line can be re-checked.\n\n"
        "Edit the worktree you are running in. You HAVE Bash for read-only inspection\n"
        "and for `gh pr edit`. The following Bash commands are DENIED and must not be\n"
        "attempted: `git push`, `git commit`, `gh pr merge`, `gh pr review`, `gh api`.\n"
        "The harness commits and pushes your worktree edits after you finish."
        f"{pr_note}\n"
        "No Agent tool: do not delegate.\n"
        f"{GATE_EDIT_DENY_TEXT}\n"
        "When a file you must inspect is large, Read it with offset and limit rather\n"
        "than whole; a whole-file Read of a large file is denied in this session.\n\n"
        "=== REMEDIATION PROCEDURE (follow it exactly) ===\n"
        f"{procedure}\n"
        "=== END PROCEDURE ===\n\n"
        f"{render_work_list(work_list)}\n"
        "=== VERDICT ===\n"
        f"{verdict_text}\n"
        "=== END VERDICT ===\n\n"
        "=== DIFF UNDER REVIEW ===\n"
        f"{diff_text}{diff_note}"
        "=== END DIFF ===\n\n"
        "Reference paths (secondary; content is above):\n"
        f"  - {os.path.join(packet_dir, 'remediation.md')}\n"
        f"  - {verdict1_path}\n"
        f"  - {os.path.join(packet_dir, 'diff.patch')}\n"
    )
    llm.call_tooled(
        "fidelity-remediation", model, prompt, cwd=worktree,
        allowed_tools=REMEDIATION_ALLOWED_TOOLS, denied_tools=REMEDIATION_DENIED_TOOLS,
        add_dirs=(packet_dir,),
    )
    sha = commit(REMEDIATION_COMMIT_MSG)
    if not sha:
        # commit_all returns "" when nothing was staged: the model made no edits.
        log("phase5.5: remediation made no edits - nothing committed or pushed")
        _out("no-edits")
        return None
    # Between commit and push on purpose. Checking after the push would need a
    # force-push to undo; checking before the commit would need a working-tree diff and
    # a reset. A denied commit stays local, which is the same state the other terminal
    # kinds already leave behind.
    hits = denied_gate_edits(gitad.changed_files(f"{sha}^"))
    if hits:
        log(f"phase5.5: remediation touched gate-owning paths ({', '.join(hits)}) "
            "- commit left local, remediation ends")
        _out("gate-path-edit")
        raise HarnessError("gate-path-edit", ", ".join(hits))
    pushed = push()
    if pushed:
        # A stale-base recovery inside `push` rebased the commit onto the fresh master,
        # so the sha `commit` returned no longer names HEAD. Phase 7 watches CI on this
        # value and re_review records it, so it has to be the sha origin now holds. A
        # bare `gitad.push()` returns None and a disabled push returns "": both keep the
        # commit sha, which IS HEAD when nothing rebased.
        sha = pushed
    log(f"phase5.5: remediation committed {str(sha)[:12]} and pushed")
    _out("committed")
    return sha


def re_review(llm, gitad, out_dir: str, worktree: str, plan, master_sha: str,
              pr_body: str, pr_number: int | str, remediation_sha: str | None,
              verdict1_path: str, phase4b_ran: bool = False, *,
              round_num: int = 1,
              log=None) -> tuple[str | None, str | None, str | None]:
    """One review per remediation round. Returns (verdict, reviewed_tree, verdict_path).

    A failure here is "no re-review happened" for this round: the prior verdict stands
    and the loop stops.
    """
    log = log or _noop_log
    if not remediation_sha:
        return None, None, None

    # regenerated AFTER the push, so the diff carries the remediation commit
    diff = gitad.diff_vs_base()
    extra = (
        f"VERDICT_1_PATH: {verdict1_path}\n"
        f"REMEDIATION_COMMIT: {remediation_sha}\n"
        "\nThis is a RE-REVIEW. Read verdict 1 at the path above and confirm each of its\n"
        "blocking findings was addressed by the remediation commit. Report a new finding\n"
        "only if the remediation itself introduced one.\n"
        "A last_verified date bump in a touched doc is the harness clearing the\n"
        "doc-staleness commit hook, not a new finding.\n"
    )
    try:
        packet = build_packet(out_dir, master_sha, gitad.head_tree(), plan, diff,
                              pr_body, pr_number, phase4b_ran, worktree=worktree,
                              packet_name=f"fidelity-packet-{round_num + 1}",
                              extra_context=extra)
        text = llm.call_tooled(
            f"plan-fidelity-re-review-{round_num + 1}", "opus",
            _pointer_prompt(packet, pr_number),
            cwd=worktree, agent="pr-ready-phase6",
            allowed_tools=REVIEW_ALLOWED_TOOLS, denied_tools=REVIEW_DENIED_TOOLS,
            add_dirs=(packet,), append_system_prompt=OVERRIDE,
        )
    except HarnessError as e:
        log(f"phase5.5: re-review unavailable ({e.kind}) - verdict 1 stands")
        return None, None, None

    path2 = verdict_path(f"{pr_number}-{round_num + 1}")
    try:
        with open(path2, "w") as fh:
            fh.write(text if text.endswith("\n") else text + "\n")
    except OSError:
        return None, None, None
    # the tree is read after the push, so the recorded tree is the one the reviewer saw
    record_reviewed_tree(path2, gitad.head_tree())
    # read back from the file, never the in-memory value: a reviewer-written line with
    # prose after the hash must yield None here exactly as the skill's parser does
    return parse_verdict(path2), read_reviewed_tree(path2), path2


def _norm_title(t: str) -> str:
    """Lowercase, strip non-alphanumeric-or-space, collapse spaces, first 60 chars."""
    t = t.lower()
    t = re.sub(r"[^a-z0-9 ]", "", t)
    t = re.sub(r" +", " ", t).strip()
    return t[:60]


def extract_notes(llm, verdict_path: str, log=None) -> list[dict]:
    """Extract the filable non-blocking notes from a READY WITH NOTES verdict.

    Only `kind == "followup"` survives — a note that names code work outliving the
    merge. Anything else (a blessed plan deviation, a PR-copy nit, the reviewer's own
    bookkeeping) is dropped, including a missing or unrecognized kind: dropping is
    fail-closed and matches how this function already handles an LLM failure.
    """
    log = log or _noop_log
    try:
        with open(verdict_path) as fh:
            text = fh.read()
    except OSError:
        return []
    try:
        raw = llm.call("fidelity-notes", "haiku",
                       llm_calls.fidelity_notes_prompt(text),
                       validate=lambda r: isinstance(r, list))
        if not isinstance(raw, list):
            return []
        kept = [d for d in raw if isinstance(d, dict)
                and d.get("title") and d.get("detail")
                and d.get("kind") == "followup"]
        dropped = len([d for d in raw if isinstance(d, dict)]) - len(kept)
        if dropped:
            log(f"phase5.5 notes: dropped {dropped} non-followup note(s)")
        return kept
    except HarnessError:
        return []


def file_note_issues(gh, notes: list[dict], pr_number: int,
                     verdict_text: str, log=None) -> list[int]:
    """File deduped backlog issues for READY WITH NOTES notes."""
    log = log or _noop_log
    if not notes:
        return []
    try:
        existing = gh.issue_titles("maintenance")
    except (HarnessError, OSError):
        existing = []
    seen = {_norm_title(t) for t in existing}
    nums = []
    excerpt = verdict_text[:200]
    if len(verdict_text) > 200:
        excerpt += "…"
    pr_link = f"https://github.com/a-jay85/IBL5/pull/{pr_number}"
    for note in notes:
        title = note.get("title", "")
        detail = note.get("detail", "")
        key = _norm_title(title)
        if key in seen:
            log(f"phase5.5 notes: skipping duplicate '{title[:50]}'")
            continue
        body = f"{pr_link}\n\n{detail}\n\n{excerpt}"
        try:
            n = gh.issue_create(title, body, "maintenance")
            if n is not None:
                nums.append(n)
                seen.add(key)
                log(f"phase5.5 notes: filed issue #{n} '{title[:50]}'")
        except (HarnessError, OSError) as exc:
            log(f"phase5.5 notes: issue_create failed ({exc})")
    return nums


# --- Phase 5: sticky verdict comment composers -------------------------------

# Byte-for-byte from .claude/review-shared/scripts/digest.sh. bin/digest-dm-build
# reads these five labels out of the posted comment, so a drift here silently empties
# the merge DM.
LABELS = (
    "**What changed:**",
    "**Why:**",
    "**Watch:**",
    "**Touches:**",
    "**Machine-authored fixes:**",
)

DIGEST_SCRIPT_PATHS = (
    ".claude/review-shared/scripts/digest.sh",
)

DIGEST_UNAVAILABLE = "digest script did not produce output"
MAX_FIDELITY_ROUNDS = 3  # round 1 is the historical single remediation
STICKY_MARKER = "<!-- pr-ready-verdict -->"
MERGE_DIGEST_HEADING = "### Merge digest"
EXCERPT_LIMIT = 30000

# Sticky-comment field parsers for the Phase 5.5 carry-forward. Anchored and exact-width,
# so a missing or decorated value never satisfies an arm. The diff field has its own label:
# a patch-id is 40 hex like a tree sha, so parsing **Reviewed tree:** for it would
# cross-match. Separate from REVIEWED_TREE_RE, which parses the verdict FILE's bare
# `REVIEWED_TREE=<sha>` line and is unchanged by this PR.
STICKY_REVIEWED_DIFF_RE = re.compile(r"^\*\*Reviewed diff:\*\* ([0-9a-f]{40})$", re.M)
STICKY_PLAN_HASH_RE = re.compile(r"^\*\*Plan hash:\*\* ([0-9a-f]{64})$", re.M)
CARRY_FORWARD_VERDICTS = ("READY", "READY WITH NOTES")

_MERGE_DIGEST_HEADING_RE = re.compile(r"^#{1,6}[ \t]+Merge digest")

# `record_reviewed_tree` appends `REVIEWED_TREE=<sha>` BELOW the verdict file's `## DIGEST`
# section, and digest.sh folds any non-label line into the record above it — so the hash
# rides out on the fifth digest label and into the merge DM. Stripped here rather than in
# digest.sh, which /pr-ready runs too and whose label list is asserted byte-identical
# against skip-review.sh. findings_excerpt drops the same line for the same reason.
_REVIEWED_TREE_TAIL_RE = re.compile(r"[ \t]*REVIEWED_TREE=[0-9a-f]{40}[ \t]*$")


def findings_excerpt(path: str, verdict_present: bool) -> str:
    """The reviewer's findings, quoted safely into the sticky comment.

    Two escapes are load-bearing: the marker must stay unique (bin/digest-dm-build and the
    skill both find the comment by its LAST marker) and `### Merge digest` must occur once
    (the DM parser starts its label scan at the first one it sees).

    `verdict_present=False` short-circuits, the same gate digest_lines takes and for the
    same reason: verdict_path() is a stable /tmp path that is never deleted, and every
    indeterminate branch of _run_fidelity still records it. Without the gate a degraded
    re-run quotes the PREVIOUS run's findings under a "verdict missing" terminal line.
    """
    if not verdict_present:
        return ""
    try:
        with open(path) as fh:
            raw = fh.read()
    except OSError:
        return ""
    if not raw.strip():
        return ""
    out = []
    for line in raw.splitlines():
        if line.strip() == DIGEST_CUT:
            break
        if line.startswith("REVIEWED_TREE="):
            continue
        if _MERGE_DIGEST_HEADING_RE.match(line):
            line = "\\" + line
        out.append(line.replace(STICKY_MARKER, "<!-- pr-ready-verdict (quoted) -->"))
    text = "\n".join(out).strip("\n")
    if len(text) > EXCERPT_LIMIT:
        text = text[:EXCERPT_LIMIT] + "… (truncated)"
    return text


def terminal_line(v1, error_kind, remediation_sha, v2, tree_2, rounds_completed) -> str:
    """The last prose line of the sticky comment. First matching row wins.

    An indeterminate verdict 1 (None) never yields a READY prefix — a missing verdict is
    not a passing one.
    """
    if v1 is None:
        return ("NOT READY — plan-fidelity review produced no verdict "
                f"({error_kind}); re-run /post-plan")
    if v1 == "READY":
        return "READY"
    if v1 == "READY WITH NOTES":
        return ("READY WITH NOTES — notes left for the merging reviewer; "
                "the compiled harness remediates only NOT READY")
    if not remediation_sha:
        return ("NOT READY — the blocking findings listed above remain; "
                "remediate and re-run /post-plan")
    if v2 is None:
        return "NOT READY — re-review produced no verdict; re-run /post-plan"
    if v2 in ("READY", "READY WITH NOTES"):
        return (f"READY (re-review) — findings remediated in {remediation_sha} and "
                f"re-reviewed clean on tree {tree_2 or 'unrecorded'}")
    if rounds_completed > 1:
        return (f"NOT READY (re-review) — {rounds_completed} remediation rounds ran and "
                "the re-review's blocking findings remain; remediate and re-run /post-plan")
    return ("NOT READY (re-review) — the re-review's blocking findings remain; "
            "remediate and re-run /post-plan")


def _sticky_prior_verdict(sticky_body: str) -> str | None:
    """The carry-forwardable verdict word from a prior sticky's terminal line.

    Prefix matching, never substring: terminal_line's READY WITH NOTES text ends with
    "... the compiled harness remediates only NOT READY", so a `"NOT READY" in last`
    test would reject a perfectly valid carry-forward. Order matters too — the longest
    alternative is checked first, the same property VERDICT_RE encodes.

    A remediated run is deliberately excluded. terminal_line emits "READY (re-review) —
    ..." when a remediation round produced the passing verdict, and that verdict belongs
    to the **Re-reviewed tree:** line, not to **Reviewed tree:**. Carrying it forward
    against **Reviewed tree:** would compare the wrong pair, so it falls through to None
    and the full review runs.
    """
    if not sticky_body or STICKY_MARKER not in sticky_body:
        return None
    head = sticky_body.rsplit(STICKY_MARKER, 1)[0]
    lines = [ln.strip() for ln in head.splitlines() if ln.strip()]
    if not lines:
        return None
    last = lines[-1]
    if last == "READY":
        return "READY"
    if last.startswith("READY WITH NOTES"):
        return "READY WITH NOTES"
    return None


def diff_patch_id(diff: str) -> str:
    """`git patch-id --verbatim` of the diff under review, or "" when none can be computed.

    Keys the carry-forward on the branch's own change. HEAD^{tree} moves on every rebase
    onto a newer master; the patch-id does not, because it drops hunk line numbers and
    `index` lines. --verbatim is mandatory: the default mode strips whitespace, so a
    whitespace-only edit (a Python indentation change) would carry a stale verdict.
    Binary, mode-only, and rename changes all move the id. git patch-id needs no
    repository, so this runs from any cwd. "" always declines carry-forward.
    """
    if not diff or not diff.strip():
        return ""
    try:
        proc = subprocess.run(["git", "patch-id", "--verbatim"], input=diff,
                              capture_output=True, text=True, timeout=30)
    except (OSError, ValueError, subprocess.SubprocessError):
        return ""
    lines = proc.stdout.splitlines()
    if proc.returncode != 0 or len(lines) != 1:
        return ""
    pid = lines[0].split()[0] if lines[0].split() else ""
    return pid if re.fullmatch(r"[0-9a-f]{40}", pid) else ""


def carry_forward_predicate(sticky_body, diff_id: str,
                            plan_hash: str) -> tuple[str | None, str]:
    """Reuse a prior Phase 5.5 verdict, or decline. Returns (verdict, "") to skip the
    reviewer spawn, (None, reason) to run the full review.

    Fail-closed on every arm: a missing sticky, an unreadable field, a changed branch
    diff, a changed plan, a plan-blind run, a NOT READY, and a remediated verdict all
    decline. The only path that returns a verdict is an exact three-way match.

    The HEAD tree is deliberately not an arm. The reviewer judges the diff, and a rebase
    onto a moved master changes the tree while leaving the diff's patch-id intact.
    """
    if not sticky_body:
        return None, "no-prior-sticky"
    if not diff_id or not re.fullmatch(r"[0-9a-f]{40}", diff_id):
        return None, "no-diff-id"
    if not plan_hash or not re.fullmatch(r"[0-9a-f]{64}", plan_hash):
        return None, "no-plan-hash"
    m = STICKY_REVIEWED_DIFF_RE.search(sticky_body)
    if not m:
        return None, "no-prior-diff-id"
    if m.group(1) != diff_id:
        return None, "diff-changed"
    h = STICKY_PLAN_HASH_RE.search(sticky_body)
    if not h:
        return None, "no-prior-plan-hash"
    if h.group(1) != plan_hash:
        return None, "plan-changed"
    verdict = _sticky_prior_verdict(sticky_body)
    if verdict not in CARRY_FORWARD_VERDICTS:
        return None, "prior-verdict-not-terminal"
    return verdict, ""


def compose_sticky(rebase_line: str, ci_line: str, fid: dict, decision,
                   digest: list, excerpt: str, terminal: str, *,
                   diff_id: str = "", plan_hash: str = "",
                   posted_at: str = "") -> str:
    """The full sticky comment body, marker last.

    Ordering is a contract, not a style: every line the DM parser must NOT read as a digest
    label sits ABOVE `### Merge digest`, and the marker is the final line.

    `posted_at` — when non-empty, adds a top banner, timestamps the verdict line, and adds
    an italic timestamp line immediately above the terminal line. When empty, the output is
    identical to the pre-timestamp shape so existing callers are unaffected.
    """
    fid = fid or {}

    # `findings_round` names which review the excerpt below was quoted from: 0 for
    # verdict 1, otherwise the 1-based remediation round whose re-review produced it.
    # The runner sets it in the same statement that advances fid["verdict_path"], so
    # the label and the quoted text can never name different reviews. Its verdict word
    # is looked up out of `rounds` rather than re-derived, for the same reason.
    rounds = fid.get("rounds") or []
    findings_round = fid.get("findings_round") or 0
    if findings_round and findings_round <= len(rounds):
        verdict_word = rounds[findings_round - 1].get("verdict") or "missing"
    else:
        verdict_word = fid.get("verdict_1") or "missing"

    out = []
    if posted_at:
        out.append(f"**LATEST VERDICT: {verdict_word}** — posted {posted_at}")
        out.append("")
    out.extend([rebase_line, ci_line, ""])

    # The timestamp is inserted before "reviewer findings follow" so the line reads:
    # "Plan-fidelity verdict: NOT READY — posted <ts> — reviewer findings follow"
    ts_mid = f"posted {posted_at} — " if posted_at else ""
    if findings_round and findings_round <= len(rounds):
        out.append(f"Plan-fidelity verdict: {verdict_word} (re-review after "
                   f"remediation round {findings_round}) — {ts_mid}reviewer findings follow")
    else:
        out.append(f"Plan-fidelity verdict: {verdict_word} — {ts_mid}"
                   "reviewer findings follow")
    out.append(excerpt if excerpt else f"(no verdict file: {fid.get('error_kind')})")

    out.append("")
    out.append(f"**Reviewed tree:** {fid.get('reviewed_tree') or 'unrecorded'}")
    if diff_id:
        out.append(f"**Reviewed diff:** {diff_id}")
    if plan_hash:
        out.append(f"**Plan hash:** {plan_hash}")
    if fid.get("carried_forward"):
        out.append("**Carried forward:** prior review reused; branch diff (patch-id) and "
                   "plan unchanged since the recorded verdict")
    if fid.get("verdict_2") is not None:
        out.append(f"**Re-reviewed tree:** {fid.get('reviewed_tree_2') or 'unrecorded'} "
                   f"({fid.get('verdict_2')})")
    if len(rounds) > 1:
        out.append("**Remediation rounds:** " + ", ".join(
            f"{i + 1}. {(r.get('remediation_sha') or '')[:12]} "
            f"→ {r.get('verdict') or 'INDETERMINATE'}"
            for i, r in enumerate(rounds)))
    nums = fid.get("backlog_issue_numbers") or []
    if nums:
        out.append("**Backlog issues filed:** " + ", ".join(
            f"a-jay85/IBL5-backlog#{n}" for n in nums))

    out.append("")
    if decision is not None and getattr(decision, "armed", False):
        out.append("Arming decision (Phase 6.5): ARM — auto-merge is requested right after "
                   "this comment")
    else:
        out.append("Arming decision (Phase 6.5): HOLD — auto-merge not armed")
    holds = list(getattr(decision, "holds", []) or []) if decision is not None else []
    if holds:
        out.extend(f"- ({c.number}) {c.name} — {c.reason}" for c in holds)
    else:
        out.append("- all fourteen conditions clear")

    out.append("")
    out.append(MERGE_DIGEST_HEADING)
    rows = list(digest)[:5]
    sha = fid.get("remediation_sha")
    if sha and len(rows) == 5:
        rows[4] = rows[4] + f" (post-plan remediation: {sha})"
    out.extend(rows)

    # The digest block must END here. `_digest_labels` in bin/digest-dm-build folds every
    # later non-label, non-blank line into the LAST label's value until it hits a heading
    # or a horizontal rule, so without this terminator the remediation note, the posted-at
    # line, the terminal verdict line and the marker all land inside the Discord DM's
    # `**Machine-authored fixes:**` value. The awk already treats `---` as an end-of-block
    # token for exactly this reason, and /pr-ready emits the same one after its digest.
    out.append("")
    out.append("---")

    if sha:
        out.append("")
        if findings_round:
            # NOT `sha`: remediation_sha is the LAST commit the harness authored, which
            # Phase 7 needs for the CI watch. A trailing ungraded round moves it past the
            # round the excerpt came from, and naming it here would pair a commit with a
            # re-review that never saw it.
            graded_sha = rounds[findings_round - 1].get("remediation_sha") or sha
            out.append(f"Remediation: commit {graded_sha} closed remediation round "
                       f"{findings_round}. The findings above come from the re-review "
                       "that graded it.")
        else:
            out.append(f"Remediation: commit {sha} addresses the verdict-1 NOT READY "
                       "findings. Every re-review round was indeterminate, so the "
                       "findings above are verdict 1's.")

    out.append("")
    if posted_at:
        out.append(f"*Verdict posted {posted_at}.*")
    out.append(terminal)
    out.append(STICKY_MARKER)
    return "\n".join(out) + "\n"


def _digest_degraded(reason: str = DIGEST_UNAVAILABLE) -> list:
    return [f"{lbl} unavailable — {reason}" for lbl in LABELS]


def _digest_source(worktree, master_sha: str) -> str | None:
    """First either-location path that yields a non-empty script body."""
    for rel in DIGEST_SCRIPT_PATHS:
        if worktree:
            body = _git_show(worktree, f"{master_sha}:{rel}")
        else:
            import pathlib
            cand = pathlib.Path(__file__).resolve().parents[3] / rel
            try:
                body = cand.read_text()
            except OSError:
                body = None
        if body:
            return body
    return None


def digest_lines(worktree, master_sha: str, verdict_path: str, out_dir: str,
                 verdict_present: bool) -> list:
    """The five merge-digest lines. Never raises; degrades to five labelled placeholders.

    `verdict_present=False` short-circuits before running anything, so a stale /tmp verdict
    left by an earlier run on another PR is never digested into this comment.
    """
    if not verdict_present:
        return _digest_degraded()
    body = _digest_source(worktree, master_sha)
    if not body:
        return _digest_degraded()
    script = os.path.join(out_dir, "fidelity-digest.sh")
    try:
        with open(script, "w") as fh:
            fh.write(body)
    except OSError:
        return _digest_degraded()
    from .adapters.llm import _run_reaped
    try:
        proc = _run_reaped(["bash", script, verdict_path], None, 60, worktree, os.environ)
    except Exception:
        return _digest_degraded()
    if proc.returncode != 0:
        return _digest_degraded()
    lines = proc.stdout.rstrip("\n").split("\n")
    if len(lines) != 5:
        return _digest_degraded()
    for i, lbl in enumerate(LABELS):
        if not lines[i].startswith(lbl):
            return _digest_degraded()
    # digest.sh's own `<label> unavailable — <reason>` degrades pass this shape test and are
    # used verbatim, exactly as the skill pastes them. The tree-line strip runs AFTER the
    # shape test, so a label reduced to just its label still degrades the same way.
    return [_REVIEWED_TREE_TAIL_RE.sub("", ln) for ln in lines]
