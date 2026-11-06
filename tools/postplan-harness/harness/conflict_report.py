"""Display-only report for a conflict a model resolved on this branch.

The sticky comment used to show only `verdict=ABSENT` or a held condition (14) with no
reason. This module records the facts of a resolution at resolve time, recomputes which
unique lines the resolution dropped, and renders a short, escaped, size-bounded Markdown
section for the sticky.

Nothing here feeds arming. `harness/armable.py` and `bin/lib/pr-armable.sh` are not
edited, and no hold, verdict or exit code reads this module. A failure inside it
returns one failure line (`FAILURE_LINE`) and nothing else.

Entry points:
- `write_record(...)`   called by gitad at resolve time; writes `<marker>.report.json`
- `section_for(...)`    called by the runner (and the skill fallback through `main`)
- `python3 -m harness.conflict_report --branch B --repo R`   prints the section
"""
from __future__ import annotations

import argparse
import html
import json
import os
import re
import subprocess
import sys
from collections import Counter
from dataclasses import dataclass, field
from pathlib import Path

from .armable import FLAG_STALE, conflict_flag_path, conflict_verdict_for
from .conflict import VERDICT_CLEAN, VERDICT_FOUND_PROBLEM
from .fidelity import MERGE_DIGEST_HEADING, STICKY_MARKER

REPORT_SUFFIX = ".report.json"
SECTION_BEGIN = "<!-- conflict-report:begin -->"
SECTION_END = "<!-- conflict-report:end -->"
HEADING = "### Conflict resolved by Claude"
FAILURE_LINE = "Conflict resolved by Claude: the report could not be built."
EXCERPT_CHARS = 300

PER_FILE_LINES = 40
TOTAL_LINES = 120
LINE_CHARS = 200
_ESCAPES = {"#": "&#35;", "*": "&#42;", "`": "&#96;", "|": "&#124;", "[": "&#91;", "_": "&#95;"}
CLEAN_WORDS = "The review ran and found no problem."
PROBLEM_WORDS = "The review ran and found a problem."
UNREADABLE_WORDS = "The review ran, but its reply could not be read."
PURGED_WORDS = "The review ran on an earlier run ({recorded}), but a later rebase cleared its result file, so the merge check reads it as missing (verdict=ABSENT)."
INCOMPLETE_WORDS = "The review did not finish after {n} attempts, so the merge check reads it as missing (verdict=ABSENT)."
NOT_RUN_WORDS = "The review did not run for this resolution, so the merge check reads it as missing (verdict=ABSENT)."
REVIEW_REASON_FMT = "The review recorded: \"{excerpt}\""
RUNG_FMT = " Then escalation rung {rung} changed the resolution: \"{fix}\""
NO_REASON = "No reason was recorded."
TRUNC_FMT = "... and {hidden} more lines not shown ({total} {kind} in this file)"
DROPPED_LINE = "An earlier conflict resolution by Claude was dropped by a later rebase. None of it remains on this branch."
REWRITTEN_LINE = "An earlier conflict resolution by Claude is no longer in this branch's history, because a later rebase rewrote its commit. Some of its lines may still be on this branch."
NO_RECORD_LINE = "Claude resolved a merge conflict on this branch, but no report record was found for it."

# Rungs that are the resolver's own pass, not an escalation that amended its result.
_NON_ESCALATION_RUNGS = ("", "first-pass", "regen")
_NO_FIX_SUMMARY = "no summary recorded"

_GIT_TIMEOUT = 30
_GIT_FAILED = 255
MAX_PATHS_SHOWN = 30
MAX_DETAIL_FILES = 20
MAX_RECOVERED_CHARS = 20000
_MAX_FILES_COMPARED = 200
_MAX_BYTES_READ = 2 * 1024 * 1024


# --------------------------------------------------------------------------- record


def record_path(branch: str, tmp_dir: str = "/tmp") -> Path:
    """Sits beside the marker, which purge_verdict_artifacts never deletes."""
    return Path(str(conflict_flag_path(branch, tmp_dir)) + REPORT_SUFFIX)


def _git(repo: str, *args: str) -> tuple[int, str]:
    try:
        proc = subprocess.run(
            ["git", "-C", repo, *args], capture_output=True, text=True,
            errors="replace", timeout=_GIT_TIMEOUT)
    except (OSError, subprocess.SubprocessError):
        return _GIT_FAILED, ""  # never 1: `merge-base --is-ancestor` uses 1 for "no"
    return proc.returncode, proc.stdout


def _rev(repo: str, ref: str) -> str:
    rc, out = _git(repo, "rev-parse", "--verify", "--quiet", f"{ref}^{{commit}}")
    return out.strip() if rc == 0 else ""


def _read_review(verdict_path) -> dict:
    """Verdict token plus the first reasoning line after it. Raw text; rendering escapes."""
    review = {"verdict": "", "excerpt": ""}
    if verdict_path is None:
        return review
    try:
        lines = Path(verdict_path).read_text(errors="replace").splitlines()
    except OSError:
        return review
    if not lines:
        return review
    review["verdict"] = lines[0].strip()
    tokens = (VERDICT_CLEAN, VERDICT_FOUND_PROBLEM)
    for line in lines[1:]:
        stripped = line.strip()
        if stripped and stripped not in tokens:
            review["excerpt"] = stripped[:EXCERPT_CHARS]
            break
    return review


def write_record(
    *,
    repo: str,
    branch: str,
    resolving_sha: str,
    resolved_files: list[str],
    target_ref: str,
    review_verdict_path,
    retries: int,
    rung: str | None,
    fix_summary: str | None,
    branch_sha: str | None = None,
    tmp_dir: str = "/tmp",
) -> Path | None:
    """Write the facts needed to recompute losses later, beside the condition-(14) marker.

    `branch_sha` is the pre-rebase branch tip; when omitted it is read from ORIG_HEAD.
    Returns None for an empty `resolved_files` (the mechanical --onto replay).
    """
    if not resolved_files:
        return None
    onto_sha = _rev(repo, target_ref)
    branch_tip = branch_sha or _rev(repo, "ORIG_HEAD")
    base_sha = ""
    if onto_sha and branch_tip:
        rc, out = _git(repo, "merge-base", branch_tip, onto_sha)
        base_sha = out.strip() if rc == 0 else ""
    review = _read_review(review_verdict_path)
    review["retries"] = int(retries or 0)
    entry = {
        "version": 1,
        "resolving_sha": resolving_sha,
        "target_ref": target_ref,
        "onto_sha": onto_sha,
        "branch_sha": branch_tip,
        "base_sha": base_sha,
        "files": list(resolved_files),
        "review": review,
        "rung": rung,
        "fix_summary": fix_summary,
    }
    path = record_path(branch, tmp_dir)
    entries = [e for e in read_records(branch, tmp_dir) if e.get("resolving_sha") != resolving_sha]
    entries.append(entry)
    _write_records(path, entries)
    return path


def _write_records(path: Path, entries: list[dict]) -> None:
    tmp = Path(str(path) + ".tmp")
    tmp.write_text(json.dumps(entries))
    os.replace(tmp, path)


def rebind_records(branch: str, new_sha: str, old_shas: list[str], tmp_dir: str = "/tmp") -> None:
    """A clean replay re-stamps the resolving commit. Replace the records keyed to `old_shas`
    with one copy of the newest, keyed to `new_sha`, so the report still finds it for the
    live marker entry. The marker only grows, so keeping the old entries would double the
    list on each replay. `original_sha` keeps the commit that made the resolution, the only
    place the "missing at the resolving commit" filter can read it from."""
    entries = read_records(branch, tmp_dir)
    matched = [e for e in entries if e.get("resolving_sha") in old_shas]
    if not matched or matched[-1].get("resolving_sha") == new_sha:
        return
    newest = matched[-1]
    copy = dict(newest, resolving_sha=new_sha,
                original_sha=newest.get("original_sha") or newest["resolving_sha"])
    kept = [e for e in entries if e.get("resolving_sha") not in old_shas and e.get("resolving_sha") != new_sha]
    _write_records(record_path(branch, tmp_dir), kept + [copy])


def read_records(branch: str, tmp_dir: str = "/tmp") -> list[dict]:
    """The recorded resolutions for this branch; [] when missing or unparsable."""
    try:
        data = json.loads(record_path(branch, tmp_dir).read_text())
    except (OSError, ValueError):
        return []
    if not isinstance(data, list):
        return []
    return [e for e in data if isinstance(e, dict)]


# ------------------------------------------------------------------------ lost code

MARKER_LINE_RE = re.compile(r"^(<{7}|={7}|>{7}|\|{7})( |$)")


def _norm(line: str) -> str:
    return " ".join(line.split())


def _is_noise(norm: str) -> bool:
    return not norm or MARKER_LINE_RE.match(norm) is not None or sum(ch.isalnum() for ch in norm) < 3


def _counter(text: str) -> Counter[str]:
    return Counter(n for n in (_norm(x) for x in text.splitlines()) if not _is_noise(n))


def lost_lines(base_text: str, side_text: str, resolved_text: str, other_lines: dict[str, list[str]]) -> tuple[list[str], list[tuple[str, str]]]:
    side_added = _counter(side_text) - _counter(base_text)
    # Copies the file already had at the merge base cannot cancel a loss of an added copy.
    missing = side_added - (_counter(resolved_text) - _counter(base_text))
    lost: list[str] = []
    moved: list[tuple[str, str]] = []
    # One gained copy elsewhere accounts for one missing line. A second missing copy is lost.
    targets = {norm: list(paths) for norm, paths in other_lines.items()}
    for raw in side_text.splitlines():
        norm = _norm(raw)
        if missing.get(norm, 0) <= 0:
            continue
        missing[norm] -= 1
        if targets.get(norm):
            moved.append((raw.rstrip(), targets[norm].pop(0)))
            continue
        lost.append(raw.rstrip())
    return lost, moved


@dataclass
class FileLoss:
    path: str
    master_lost: list[str] = field(default_factory=list)
    branch_lost: list[str] = field(default_factory=list)
    moved: list[tuple[str, str]] = field(default_factory=list)
    compare_failed: bool = False


def _show(repo: str, sha: str, path: str) -> str | None:
    """File text at `sha`, "" when the path is absent there, None when the object is gone."""
    if not sha:
        return None
    rc, out = _git(repo, "show", f"{sha}:{path}")
    if rc == 0:
        return out[:_MAX_BYTES_READ]
    # A failed show is not proof the path is absent: it may be a timeout or a bad object.
    # Only a readable tree without the path counts as absent.
    ls_rc, listing = _git(repo, "ls-tree", sha, "--", path)
    return "" if ls_rc == 0 and not listing.strip() else None


def _renames(repo: str, base_sha: str, side_sha: str) -> dict[str, str] | None:
    """Old path to new path for files `side_sha` renamed since the merge base."""
    rc, out = _git(repo, "diff", "-z", "--find-renames", "--name-status", "--diff-filter=R", base_sha, side_sha)
    if rc != 0:
        return None
    fields = out.split("\0")
    return {fields[i + 1]: fields[i + 2] for i in range(0, len(fields) - 2, 3)}


def _side_paths(repo: str, record: dict, path: str) -> tuple[str, str, str] | None:
    """Where the resolved `path` lived at the merge base, on master and on the branch."""
    base_sha = record.get("base_sha", "")
    on_master = _renames(repo, base_sha, record.get("onto_sha", ""))
    on_branch = _renames(repo, base_sha, record.get("branch_sha", ""))
    if on_master is None or on_branch is None:
        return None
    origin = path
    for renames in (on_master, on_branch):
        for old, new in renames.items():
            if new == path:
                origin = old
    return origin, on_master.get(origin, origin), on_branch.get(origin, origin)


def _maybe_unseen_rename(repo: str, base_sha: str, adder_sha: str) -> bool:
    """A path one side added may be a rename too heavily edited for git to detect. A side
    that also deleted a file could have done that, so its copy cannot be compared."""
    rc, out = _git(repo, "diff", "--name-only", "--diff-filter=D", base_sha, adder_sha)
    return rc != 0 or bool(out.strip())


def _other_lines(repo: str, base_sha: str, onto_sha: str, branch_sha: str, head: str, skip: str) -> dict[str, list[str]] | None:
    """Normalized lines the other changed files hold at `head` beyond what master and the
    branch each gave them, mapped to one path per gained copy. A copy either side already
    had (at the merge base or added since) is not a place a line moved to, so it cannot
    hide a real loss. None when a read fails: lost and moved cannot be told apart then."""
    rc, out = _git(repo, "diff", "--name-only", base_sha, head)
    if rc != 0:
        return None
    mapping: dict[str, list[str]] = {}
    budget = _MAX_BYTES_READ
    for other in [p for p in out.splitlines() if p.strip()][:_MAX_FILES_COMPARED]:
        if other == skip or budget <= 0:
            continue
        text, at_onto, at_branch = (_show(repo, sha, other) for sha in (head, onto_sha, branch_sha))
        if text is None or at_onto is None or at_branch is None:
            return None
        budget -= len(text)
        before = _counter(at_onto) | _counter(at_branch)
        for norm, copies in (_counter(text) - before).items():
            mapping.setdefault(norm, []).extend([other] * copies)
    return mapping


def file_loss(repo: str, record: dict, path: str, head: str, resolving: str | None = None) -> FileLoss:
    """Lines unique to master or to the branch that the resolved file at `head` lacks.

    Computed against `head`, so a line a later commit restored drops out of the list.
    When `resolving` is given, a line also has to be missing at the resolving commit, so a
    later commit's own deletion is not blamed on the resolution. Each side is read at the
    path it gave the file, so a rename on either side does not hide that side's lines.
    """
    paths = _side_paths(repo, record, path)
    if paths is None:
        return FileLoss(path=path, compare_failed=True)
    base = _show(repo, record.get("base_sha", ""), paths[0])
    master = _show(repo, record.get("onto_sha", ""), paths[1])
    branch = _show(repo, record.get("branch_sha", ""), paths[2])
    resolved = _show(repo, head, path)
    at_resolve = _show(repo, resolving, path) if resolving else resolved
    if base is None or master is None or branch is None or resolved is None or at_resolve is None:
        return FileLoss(path=path, compare_failed=True)
    if not base and bool(master) != bool(branch):
        adder = record["onto_sha"] if master else record["branch_sha"]
        if _maybe_unseen_rename(repo, record["base_sha"], adder):
            return FileLoss(path=path, compare_failed=True)
    others = _other_lines(repo, record["base_sha"], record["onto_sha"], record["branch_sha"], head, path)
    if others is None:
        return FileLoss(path=path, compare_failed=True)
    out = FileLoss(path=path)
    for side, lost_attr in ((master, "master_lost"), (branch, "branch_lost")):
        lost, moved = lost_lines(base, side, resolved, others)
        if resolving:
            early, _ = lost_lines(base, side, at_resolve, others)
            allowed = Counter(_norm(x) for x in early)
            kept = []
            for line in lost:
                n = _norm(line)
                if allowed.get(n, 0) > 0:
                    allowed[n] -= 1
                    kept.append(line)
            lost = kept
        setattr(out, lost_attr, lost)
        out.moved += moved
    return out


# ---------------------------------------------------------------------- rendering


def neutralize(text: str, limit: int) -> str:
    """Make untrusted text inert inside a sticky: no control characters, no newlines, and
    no `<!--`, `-->`, `#`, `*` or backtick, so it cannot form STICKY_MARKER, SECTION_BEGIN
    or SECTION_END, a heading, or a bold verdict line."""
    text = str(text).replace("\t", " ").replace("\r", " ").replace("\n", " ")
    text = "".join(ch for ch in text if ch.isprintable())
    clipped = text if len(text) <= limit else text[:limit] + "..."
    escaped = html.escape(clipped, quote=False)
    return "".join(_ESCAPES.get(ch, ch) for ch in escaped)


def review_status_words(current: str | None, record: dict) -> str:
    """Why the merge check reads the review the way it does. `current` is
    conflict_verdict_for(branch); a bare ABSENT never reaches the owner unexplained."""
    if current == VERDICT_CLEAN:
        return CLEAN_WORDS
    if current is not None and current.startswith(VERDICT_FOUND_PROBLEM):
        return PROBLEM_WORDS
    if current is not None:
        return UNREADABLE_WORDS
    review = record.get("review") or {}
    recorded = review.get("verdict") or ""
    if recorded:
        return PURGED_WORDS.format(recorded=neutralize(recorded, LINE_CHARS))
    retries = int(review.get("retries") or 0)
    if retries > 0:
        return INCOMPLETE_WORDS.format(n=retries + 1)
    return NOT_RUN_WORDS


def reason_words(record: dict) -> str:
    """Only recorded text: the review excerpt, then the ladder's own fix summary."""
    review = record.get("review") or {}
    parts = ""
    excerpt = review.get("excerpt") or ""
    if excerpt:
        parts += REVIEW_REASON_FMT.format(excerpt=neutralize(excerpt, EXCERPT_CHARS))
    rung = record.get("rung") or ""
    if rung not in _NON_ESCALATION_RUNGS:
        fix = record.get("fix_summary") or _NO_FIX_SUMMARY
        parts += RUNG_FMT.format(rung=neutralize(rung, LINE_CHARS), fix=neutralize(fix, EXCERPT_CHARS))
    return parts or NO_REASON


def _count_words(n: int, noun: str) -> str:
    return f"{n} {noun}" if n == 1 else f"{n} {noun}s"


def _loss_words(losses: list[FileLoss]) -> str:
    lost_files = [fl for fl in losses if fl.master_lost or fl.branch_lost]
    failed = sum(1 for fl in losses if fl.compare_failed)
    moved = sum(len(fl.moved) for fl in losses)
    if lost_files:
        words = f"Unique code was lost in {len(lost_files)} of {len(losses)} files (details below)."
    elif failed:
        words = "No lost code was found in the files that could be compared."
    else:
        words = "No unique code was lost."
    if moved:
        words += f" {_count_words(moved, 'line')} moved to another file (listed below)."
    if failed:
        words += f" {_count_words(failed, 'file')} could not be compared."
    return words


def _detail_block(fl: FileLoss, budget: int) -> tuple[list[str], int]:
    """One <details> block for a file, plus the number of lines it used from the total budget."""
    entries = [f"- (master) {neutralize(x, LINE_CHARS)}" for x in fl.master_lost]
    entries += [f"- (branch) {neutralize(x, LINE_CHARS)}" for x in fl.branch_lost]
    entries += [f"- (moved to {neutralize(to, LINE_CHARS)}) {neutralize(x, LINE_CHARS)}" for x, to in fl.moved]
    count_bits = []
    if fl.master_lost:
        count_bits.append(f"{len(fl.master_lost)} master lines")
    if fl.branch_lost:
        count_bits.append(f"{len(fl.branch_lost)} this branch lines")
    if fl.moved:
        count_bits.append(f"{len(fl.moved)} moved lines")
    allowed = max(0, min(PER_FILE_LINES, budget))
    shown = entries[:allowed]
    out = [
        f"<details><summary><code>{neutralize(fl.path, LINE_CHARS)}</code>: "
        f"{' and '.join(count_bits)} not in the resolved file</summary>",
        "<pre>",
        *shown,
    ]
    if len(shown) < len(entries):
        kind = "lost or moved" if fl.moved else "lost"
        out.append(TRUNC_FMT.format(hidden=len(entries) - len(shown), total=len(entries), kind=kind))
    out.append("</pre></details>")
    return out, len(shown)


def render_section(records: list[dict], losses: list[FileLoss], current_verdict: str | None) -> str:
    """The sticky section. Several records merge: paths deduplicated, newest record wins."""
    newest = records[-1] if records else {}
    paths = list(dict.fromkeys(p for r in records for p in r.get("files", [])))
    shown_paths = ", ".join(f"<code>{neutralize(p, LINE_CHARS)}</code>" for p in paths[:MAX_PATHS_SHOWN])
    if len(paths) > MAX_PATHS_SHOWN:
        shown_paths += f" and {len(paths) - MAX_PATHS_SHOWN} more"
    summary = (
        f"Claude resolved a merge conflict in {_count_words(len(paths), 'file')}: {shown_paths}. "
        f"{review_status_words(current_verdict, newest)} {_loss_words(losses)} "
        f"Why: {reason_words(newest)}"
    )
    lines = [SECTION_BEGIN, HEADING, summary]
    budget = TOTAL_LINES
    blocks = 0
    for fl in losses:
        if not (fl.master_lost or fl.branch_lost or fl.moved):
            continue
        if blocks >= MAX_DETAIL_FILES:
            break
        blocks += 1
        block, used = _detail_block(fl, budget)
        budget -= used
        lines.extend(block)
    lines.append(SECTION_END)
    return "\n".join(lines)


# --------------------------------------------------------------------- entry points


_SECTION_RE = re.compile(
    rf"^{re.escape(SECTION_BEGIN)}\n{re.escape(HEADING)}\n.*?^{re.escape(SECTION_END)}$",
    re.MULTILINE | re.DOTALL)


def _extract_section(body: str | None) -> str | None:
    """The last section in a prior sticky body, markers included."""
    if not body:
        return None
    # Markers must sit alone at column 0, with our heading right under the begin marker.
    # A reviewer excerpt that quotes them is indented, quoted or escaped, so it never matches.
    found = _SECTION_RE.findall(body)
    if not found:
        return None
    section = found[-1]
    if len(section) > MAX_RECOVERED_CHARS or STICKY_MARKER in section or MERGE_DIGEST_HEADING in section:
        return None
    return section


def _block(*lines: str) -> str:
    return "\n".join([SECTION_BEGIN, *lines, SECTION_END])


def _resolution_gone(repo: str, records: list[dict], head: str) -> bool:
    """True only when the records prove none of the lines a resolution wrote is at `head`.
    A line master already held does not count: it stays whatever happened to the resolution.
    No record, a commit git no longer has, or a failed read all prove nothing."""
    if not records:
        return False
    for record in records:
        resolving = record.get("original_sha") or record.get("resolving_sha", "")
        for path in record.get("files", []):
            paths = _side_paths(repo, record, path)
            if paths is None:
                return False
            written = _show(repo, resolving, path)
            base = _show(repo, record.get("base_sha", ""), paths[0])
            master = _show(repo, record.get("onto_sha", ""), paths[1])
            at_head = _show(repo, head, path)
            if written is None or base is None or master is None or at_head is None:
                return False
            if (_counter(written) - (_counter(base) | _counter(master))) & _counter(at_head):
                return False
    return True


def section_for(branch: str, repo: str, head: str = "HEAD", prior_body: str | None = None,
                tmp_dir: str = "/tmp", flag_state: str = "") -> str:
    """The conflict-report section for the sticky, or "" when no model resolved a conflict
    on this branch. Never raises."""
    try:
        try:
            shas = [s.strip() for s in Path(conflict_flag_path(branch, tmp_dir)).read_text().splitlines()
                    if s.strip()]
        except OSError:
            shas = []
        if shas and not _rev(repo, head):
            raise RuntimeError("head does not resolve")
        codes = {}
        for s in shas:
            rc = _git(repo, "merge-base", "--is-ancestor", s, head)[0]
            if rc == 128 and not _rev(repo, s):
                rc = 1  # the object is gone from this repo: not in history
            codes[s] = rc
        if any(rc not in (0, 1) for rc in codes.values()):
            raise RuntimeError("git could not answer the ancestry question")  # failure block, not "dropped"
        live = [s for s, rc in codes.items() if rc == 0]
        recovered = _extract_section(prior_body)
        if not live and recovered is None:
            return ""  # no model-resolved conflict on this branch
        if not live:
            if not shas and flag_state != FLAG_STALE:
                return recovered  # marker gone: cannot tell dropped from cleared, keep the prior text
            # Ancestry alone cannot say "dropped": a clean rebase rewrites the SHA and keeps
            # the lines. With no SHAs left, reconcile deleted the marker: every record counts.
            candidates = [r for r in read_records(branch, tmp_dir)
                          if not shas or r.get("resolving_sha") in shas]
            return _block(HEADING, DROPPED_LINE if _resolution_gone(repo, candidates, head) else REWRITTEN_LINE)
        records = [r for r in read_records(branch, tmp_dir) if r.get("resolving_sha") in live]
        if not records:
            if recovered is not None:
                return recovered  # carried from the prior sticky
            return _block(HEADING, NO_RECORD_LINE)
        paths = list(dict.fromkeys(p for r in records for p in r.get("files", [])))
        losses = []
        for p in paths:
            # Each file is judged by the newest record that resolved it. Lost lines are
            # computed against `head` (a later fix that restored a line clears it) and
            # must also be missing at the resolving commit (a later deletion is not blamed).
            owner = [r for r in records if p in r.get("files", [])][-1]
            resolving = owner.get("original_sha") or owner["resolving_sha"]
            losses.append(file_loss(repo, owner, p, head, resolving=resolving))
        return render_section(records, losses, conflict_verdict_for(branch, tmp_dir))
    except Exception:  # the report must never raise
        return f"{SECTION_BEGIN}\n{FAILURE_LINE}\n{SECTION_END}"


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="harness.conflict_report")
    parser.add_argument("--branch", required=True)
    parser.add_argument("--repo", default=".")
    parser.add_argument("--head", default="HEAD")
    parser.add_argument("--prior-body-file")
    args = parser.parse_args(argv)
    prior_body = None
    if args.prior_body_file:
        try:
            prior_body = Path(args.prior_body_file).read_text(errors="replace")
        except OSError:
            prior_body = None
    sys.stdout.write(section_for(args.branch, args.repo, args.head, prior_body))
    return 0  # always exit 0


if __name__ == "__main__":
    raise SystemExit(main())
