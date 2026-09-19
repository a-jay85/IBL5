"""Phase 5.5 — the plan-intent fidelity review.

The two places a silent wrong answer here arms a PR nobody reviewed are the verdict
parse and the `REVIEWED_TREE` record, so both live behind module constants that Phase 4
imports rather than re-deriving. That import *is* the drift guard the skill enforces
with prose.

`None` from `parse_verdict` means **indeterminate**, never `NOT READY`.
"""
from __future__ import annotations

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
    ".claude/skills/pr-ready/_plan-fidelity-review.md",
    ".claude/review-shared/_plan-fidelity-review.md",
)

REMEDIATION_PATHS = (
    ".claude/skills/pr-ready/_phase65-remediation.md",
    ".claude/review-shared/_phase65-remediation.md",
)

# The remediation model edits files and nothing else. `Bash` absent from the allowlist AND
# present in the deny list is the enforcement of "the model never pushes": with no command
# execution it cannot reach the VCS, even if a settings file carries a Bash allow rule. The
# harness commits and pushes afterwards.
REMEDIATION_ALLOWED_TOOLS = ("Read", "Grep", "Glob", "Edit", "Write")
REMEDIATION_DENIED_TOOLS = ("Bash", "Agent")

REMEDIATION_COMMIT_MSG = "chore: address Phase 5.5 plan-fidelity findings"

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


def _noop_log(_msg: str) -> None:
    return


def remediate(llm, gitad, out_dir: str, worktree: str, packet_dir: str,
              verdict1_path: str, master_sha: str, log=None, *,
              commit=None) -> str | None:
    """Fix the blocking findings behind a `NOT READY`. Returns the commit sha, or None.

    The model edits; the HARNESS commits and pushes. That split is not a convention —
    `Bash` is withheld from the model on both the allow and the deny side, so it has no
    path to a command at all.

    `commit` is the message -> sha callable. runner passes its Phase 2 gate wrapper so a
    doc-staleness denial gets the same one-shot last_verified bump here; it is injected
    rather than imported because runner imports this module.
    """
    log = log or _noop_log
    commit = commit or gitad.commit_all
    if parse_verdict(verdict1_path) != "NOT READY":
        return None
    if gitad.is_dirty():
        # _phase65-remediation.md's own precondition: a single commit_all on a dirty tree
        # would sweep unrelated edits into the remediation commit.
        log("phase5.5: remediation skipped - dirty worktree")
        return None

    procedure = _find_procedure(worktree, master_sha, REMEDIATION_PATHS,
                               "remediation-procedure-missing")
    with open(os.path.join(packet_dir, "remediation.md"), "w") as fh:
        fh.write(procedure)

    prompt = (
        "Remediate the blocking findings from the plan-intent fidelity review.\n\n"
        f"  - {os.path.join(packet_dir, 'remediation.md')} - the remediation procedure; follow it\n"
        f"  - {verdict1_path} - the verdict; its blocking findings are your work list\n"
        f"  - {os.path.join(packet_dir, 'diff.patch')}     - the diff under review\n\n"
        "Edit the worktree you are running in. You have no Bash and no Agent tool: do not\n"
        "try to commit, push, or delegate. The harness commits and pushes your edits.\n"
    )
    llm.call_tooled(
        "fidelity-remediation", "sonnet", prompt, cwd=worktree,
        allowed_tools=REMEDIATION_ALLOWED_TOOLS, denied_tools=REMEDIATION_DENIED_TOOLS,
        add_dirs=(packet_dir,),
    )
    sha = commit(REMEDIATION_COMMIT_MSG)
    if not sha:
        # commit_all returns "" when nothing was staged: the model made no edits.
        log("phase5.5: remediation made no edits - nothing committed or pushed")
        return None
    gitad.push()
    log(f"phase5.5: remediation committed {str(sha)[:12]} and pushed")
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
    """Extract non-blocking notes from a READY WITH NOTES verdict."""
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
        return [d for d in raw if isinstance(d, dict)
                and d.get("title") and d.get("detail")]
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

# Byte-for-byte from .claude/skills/pr-ready/scripts/digest.sh. bin/digest-dm-build
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
    ".claude/skills/pr-ready/scripts/digest.sh",
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
                   diff_id: str = "", plan_hash: str = "") -> str:
    """The full sticky comment body, marker last.

    Ordering is a contract, not a style: every line the DM parser must NOT read as a digest
    label sits ABOVE `### Merge digest`, and the marker is the final line.
    """
    fid = fid or {}
    out = [rebase_line, ci_line, ""]

    out.append(f"Plan-fidelity verdict: {fid.get('verdict_1') or 'missing'} — "
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
    rounds = fid.get("rounds") or []
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

    if sha:
        out.append("")
        out.append(f"Remediation: commit {sha} addresses the verdict-1 NOT READY findings; "
                   "the re-review result is on the Re-reviewed tree line.")

    out.append("")
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
    # used verbatim, exactly as the skill pastes them.
    return lines
