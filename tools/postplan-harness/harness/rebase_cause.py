"""Advisory classifier for a blocked Phase 2 rebase.

Names why the rebase declined (a sibling PR merged, unrelated master traffic, a
tree-proof failure) so the RESULT line and blocked-ship.txt can show it. It never
raises, never sleeps, and never changes the exit code: any seam failure yields
`cause=unknown`. git and gh arrive as plain runner callables so tests drive it over
temp repos with a fake gh.
"""
from __future__ import annotations

import json
import re
import subprocess
from dataclasses import dataclass
from typing import Callable

CAUSE_SIBLING_MERGED = "sibling-merged"   # class (a)
CAUSE_MASTER_TRAFFIC = "master-traffic"   # class (b): bot or unrelated master commits
CAUSE_TREE_PROOF = "tree-proof"           # class (c): label only
CAUSE_UNKNOWN = "unknown"                 # classifier could not decide or a seam failed
LINEAGE_CANDIDATE_CAP = 8                 # max PR heads fetched for the lineage check
SEAM_TIMEOUT_S = 30                       # per git/gh call in the live runners

_PATH_RENDER_CAP = 5
_PR_SUFFIX = re.compile(r"\(#(\d+)\)\s*$")

Runner = Callable[[list[str]], tuple[int, str]]   # args -> (returncode, stdout)


def _fmt_nums(nums: tuple[int, ...]) -> str:
    return ",".join(f"#{n}" for n in nums) or "-"


@dataclass(frozen=True)
class RebaseBlockCause:
    cause: str
    paths: tuple[str, ...] = ()
    merged_siblings: tuple[int, ...] = ()
    master_prs: tuple[int, ...] = ()
    bot_commits: int = 0
    open_siblings: tuple[int, ...] = ()
    note: str = ""

    def render(self) -> str:
        shown = list(self.paths[:_PATH_RENDER_CAP])
        paths = ",".join(shown) if shown else "-"
        extra = len(self.paths) - _PATH_RENDER_CAP
        if extra > 0:
            paths += f",+{extra} more"
        out = (f"cause={self.cause}; paths={paths}; "
               f"merged-siblings={_fmt_nums(self.merged_siblings)}; "
               f"master-prs={_fmt_nums(self.master_prs)}; "
               f"bot-commits={self.bot_commits}; "
               f"open-siblings={_fmt_nums(self.open_siblings)}")
        if self.note:
            out += f"; note={self.note}"
        return out


def live_runners(worktree: str) -> tuple[Runner, Runner]:
    """(run_git, run_gh) closures; a timeout or OSError degrades to rc 124."""
    def _make(binary: str) -> Runner:
        def run(args: list[str]) -> tuple[int, str]:
            try:
                proc = subprocess.run([binary, *args], cwd=worktree, capture_output=True,
                                      text=True, timeout=SEAM_TIMEOUT_S)
            except (subprocess.TimeoutExpired, OSError):
                return 124, ""
            return proc.returncode, proc.stdout
        return run
    return _make("git"), _make("gh")


def _is_sibling(pr: int, my_mb: str, base: str, run_git: Runner) -> bool:
    """True when the PR head forked from the same master commit as this branch."""
    rc, _ = run_git(["fetch", "--no-tags", "--quiet", "origin", f"pull/{pr}/head"])
    if rc != 0:
        return False
    rc, out = run_git(["merge-base", "FETCH_HEAD", base])
    return rc == 0 and out.strip() == my_mb


def classify_rebase_block(paths, decline_reason, *, head_sha, branch,
                          base="origin/master", run_git: Runner,
                          run_gh: Runner) -> RebaseBlockCause:
    paths = tuple(paths or ())
    try:
        return _classify(paths, decline_reason or "", head_sha, branch, base, run_git, run_gh)
    except Exception as exc:  # advisory only: never let the classifier change the block
        return RebaseBlockCause(CAUSE_UNKNOWN, paths, note=f"classifier error: {type(exc).__name__}")


def _classify(paths, decline_reason, head_sha, branch, base, run_git, run_gh):
    if "tree proof failed" in decline_reason:
        return RebaseBlockCause(CAUSE_TREE_PROOF, paths)
    if not paths:
        return RebaseBlockCause(CAUSE_UNKNOWN, paths, note="no conflicted paths recorded")

    rc, out = run_git(["merge-base", head_sha, base])
    mb = out.strip()
    if rc != 0 or not mb:
        return RebaseBlockCause(CAUSE_UNKNOWN, paths, note="merge-base failed")

    rc, out = run_git(["log", "--format=%H%x1f%an%x1f%s", f"{mb}..{base}", "--", *paths])
    if rc != 0:
        return RebaseBlockCause(CAUSE_UNKNOWN, paths, note="git log failed")

    commits = 0
    bot_commits = 0
    master_prs: list[int] = []
    for line in out.splitlines():
        if not line.strip():
            continue
        parts = line.split("\x1f")
        author = parts[1] if len(parts) > 1 else ""
        subject = parts[2] if len(parts) > 2 else ""
        commits += 1
        if "[auto]" in subject or author.endswith("[bot]"):
            bot_commits += 1
            continue
        m = _PR_SUFFIX.search(subject)
        if m and int(m.group(1)) not in master_prs:
            master_prs.append(int(m.group(1)))

    budget = LINEAGE_CANDIDATE_CAP
    merged_siblings: list[int] = []
    for pr in master_prs:
        if budget <= 0:
            break
        budget -= 1
        if _is_sibling(pr, mb, base, run_git):
            merged_siblings.append(pr)

    open_siblings: list[int] = []
    note = ""
    rc, out = run_gh(["pr", "list", "--state", "open", "--limit", "100",
                      "--json", "number,headRefName,files"])
    try:
        if rc != 0:
            raise ValueError("gh rc")
        listing = json.loads(out)
        overlap = set(paths)
        for pr in listing:
            if pr.get("headRefName") == branch:
                continue
            files = {f.get("path") for f in pr.get("files") or []}
            if not files & overlap:
                continue
            if budget <= 0:
                break
            budget -= 1
            if _is_sibling(int(pr["number"]), mb, base, run_git):
                open_siblings.append(int(pr["number"]))
    except (ValueError, TypeError, AttributeError, KeyError):
        open_siblings = []
        note = "gh pr list failed"

    if merged_siblings:
        cause = CAUSE_SIBLING_MERGED
    elif commits:
        cause = CAUSE_MASTER_TRAFFIC
    else:
        cause = CAUSE_UNKNOWN
        note = note or "no master commit touches the conflicted paths"
    return RebaseBlockCause(cause, paths, tuple(merged_siblings), tuple(master_prs),
                            bot_commits, tuple(open_siblings), note)
