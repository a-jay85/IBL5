"""GitHub adapter — the side-effect gate.

Replay/isolated modes NEVER execute a mutating `gh` command. Every would-be
mutation (pr create / comment / review / edit / merge --auto) is appended as a
typed intent record to <out>/actions.jsonl. Reads are served from fixtures
(replay) or recorded local state (isolated).

LiveGh is the installed mode (README §Installation, approved 2026-07-16): it
executes exactly the eight allowlisted mutations via `gh` — there is no generic
"run a gh command" escape hatch — and still appends every executed action to
actions.jsonl (executed=true) so the audit trail survives the install.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import tempfile
import threading
import time

from pathlib import Path

from .. import followcap, netretry
from ..labels import render_body, visible_right_lines
from ..state import HarnessError

# bin/lib/pr-sticky.sh in the harness's own checkout. Not under pr-ready/,
# so no either-location lookup applies.
STICKY_LIB = Path(__file__).resolve().parents[4] / "bin" / "lib" / "pr-sticky.sh"
# bin/lib/pr-armable.sh, pinned the same way: condition (11) must never read the helper
# from the worktree it is judging, or a branch could edit it to clear its own hold.
ARMABLE_LIB = Path(__file__).resolve().parents[4] / "bin" / "lib" / "pr-armable.sh"
PRF_LIB = ARMABLE_LIB.parent / "post-review-findings.sh"

# Duplicated from fidelity.STICKY_MARKER on purpose: adapters must not import the core
# phase modules. tests/test_fidelity_carryforward.py asserts the two stay equal.
PR_STICKY_MARKER = "<!-- pr-ready-verdict -->"

# The heading `.claude/review-shared/scripts/4b-probe.sh` scans comments for. `\b` keeps
# `## Code reviewer notes` from matching.
CODE_REVIEW_RE = re.compile(r"(?m)^#{1,6} +Code review\b")


def _last_code_review(data) -> dict:
    """{"scanned": True, "comment": {created_at, url, summary} | None} from a comments fetch.

    The last non-sticky comment whose body opens a `Code review` heading wins. Malformed
    data scans as "found nothing".
    """
    found = None
    try:
        for c in (data.get("comments") or []):
            body = c.get("body") or ""
            if PR_STICKY_MARKER in body:
                continue
            m = CODE_REVIEW_RE.search(body)
            if not m:
                continue
            rest = [ln.strip() for ln in body[m.end():].splitlines()[1:] if ln.strip()]
            found = {"created_at": c.get("createdAt"), "url": c.get("url"),
                     "summary": (rest[0] if rest else "")[:120]}
    except (AttributeError, TypeError):
        found = None
    return {"scanned": True, "comment": found}


_LABEL_WORDS = {"important": "Important", "nit": "Nit", "pre-existing": "Pre-existing"}


def _summary_lines(fs: list) -> list[str]:
    """One summary-comment line per finding; an unset label renders as Important."""
    out = []
    for f in fs:
        line = f"- **{_LABEL_WORDS.get(f.label, 'Important')}** `{f.path}:{f.line}`"
        if f.bug_class:
            line += f" (bug class: `{f.bug_class}`)"
        if f.other_instances:
            line += " other instances: " + ", ".join(f"`{x}`" for x in f.other_instances)
        out.append(line + f" — {f.body}\n\n<!-- score: {f.score} -->")
    return out


class RecordingGh:
    MUTATIONS = ("pr_create", "pr_comment", "pr_review_findings", "pr_edit_body",
                 "pr_merge_auto", "label_add", "pr_status_label",
                 "pr_sticky_verdict", "issue_create", "followup_create",
                 "pr_disable_auto_merge",
                 "pr_checks_json", "run_log_failed", "run_rerun_failed",
                 "pr_resolve_thread", "pr_reply_thread", "label_remove")

    def __init__(self, out_dir: str, fixture: dict | None = None):
        self.out_dir = out_dir
        self.fixture = fixture or {}
        self.trusted_open_threads_flags: list[bool] = []
        os.makedirs(out_dir, exist_ok=True)
        self.actions_path = os.path.join(out_dir, "actions.jsonl")
        # Review and verification phases run on parallel threads; serialize appends
        # so two long records never interleave into an unparseable line.
        self._actions_lock = threading.Lock()
        self._body_override: str | None = None
        # Stands in for GitHub's backlog repo: a test seeds prior state through the
        # fixture key `backlog_issues`, the way a retry sees issues an earlier run filed.
        self._followup_store = followcap.MemoryStore(
            [followcap.Issue(**i) for i in self.fixture.get("backlog_issues", [])])

    # -- side-effect intents (recorded, never executed) -----------------
    def record(self, action: str, **payload) -> None:
        assert action in self.MUTATIONS, f"unknown mutation {action}"
        line = json.dumps({"ts": time.time(), "action": action, **payload}) + "\n"
        with self._actions_lock, open(self.actions_path, "a") as fh:
            fh.write(line)

    def pr_create(self, title: str, body: str, base: str) -> int:
        self.record("pr_create", title=title, body=body[:8000], base=base)
        return int(self.fixture.get("pr_number") or 0)

    def unresolved_findings(self, pr: int) -> list[str]:
        return []

    def pr_thread_ids(self, pr: int) -> set[int]:
        return {int(x) for x in self.fixture.get("thread_ids", [])}

    def trusted_open_threads(self, pr: int, include_outdated: bool = False) -> list[dict] | None:
        # Fixture rows come back verbatim in both modes so the Python recheck in
        # fetch_trusted_threads is what the tests exercise. The flag is kept so a
        # wiring test can assert which pass asked for outdated threads.
        self.trusted_open_threads_flags.append(bool(include_outdated))
        return self.fixture.get("trusted_threads", [])

    def review_threads_all(self, pr: int) -> list[dict] | None:
        return self.fixture.get("all_threads", [])

    def resolve_review_thread(self, pr: int, comment_id: int, body: str) -> None:
        self.record("pr_resolve_thread", pr=pr, comment_id=int(comment_id), body=body[:2000])

    def reply_review_thread(self, pr: int, comment_id: int, body: str) -> None:
        self.record("pr_reply_thread", pr=pr, comment_id=int(comment_id), body=body[:2000])

    def pr_sticky_body(self, pr: int) -> str | None:
        """Prior Phase 5.5 sticky body from the replay fixture, or None.

        A fixture that carries no `prior_sticky_body` key reads as "no prior sticky",
        which declines carry-forward — so every pre-existing replay fixture keeps the
        full-review path it has today, unchanged.
        """
        return self.fixture.get("prior_sticky_body")

    def pr_edit_body(self, pr: int, body: str) -> None:
        self._body_override = body
        self.record("pr_edit_body", pr=pr, body=body[:8000])

    def pr_merge_auto(self, pr: int) -> None:
        self.record("pr_merge_auto", pr=pr, args="--squash --auto")

    def pr_sticky_verdict(self, pr: int, body: str) -> str:
        # Deliberately UNTRUNCATED: replay tests read the terminal line and the marker,
        # both of which sit at the very end of the body.
        self.record("pr_sticky_verdict", pr=pr, body=body)
        return str(self.fixture.get("sticky_comment_id", "replay-sticky"))

    def post_review_findings(self, pr: int, head_sha: str, title: str, findings: list) -> None:
        self.record("pr_review_findings", pr=pr, head_sha=head_sha, title=title,
                    findings=[{"path": f.path, "line": f.line, "body": render_body(f, 1000),
                               "score": f.score, "label": f.label} for f in findings])

    def post_review_summary(self, pr: int, title: str, body: str) -> None:
        self.record("pr_comment", pr=pr, title=title, body=body[:4000])

    def pr_status_label(self, pr: int, label: str) -> None:
        self.record("pr_status_label", pr=pr, label=label)

    def issue_create(self, title: str, body: str, label: str) -> int | None:
        existing = sum(1 for a in self.actions() if a.get("action") == "issue_create")
        self.record("issue_create", title=title, label=label)
        return existing + 1

    def issue_titles(self, label: str | None, *, strict: bool = False) -> list[str]:
        return []

    def followup_create(self, title: str, body: str, label: str) -> int | None:
        """File one PR follow-up under the per-PR cap (harness/followcap.py)."""
        try:
            kind, n = followcap.file_followup(self._followup_store, label, title, body)
        except followcap.FollowcapError as exc:
            raise HarnessError("gh", f"followup: {exc}") from exc
        self.record("followup_create", title=title, body=body, label=label, kind=kind, number=n)
        return n

    # -- reads (fixture-backed) ------------------------------------------
    def merged_pr(self) -> dict | None:
        """{"number", "url", "head"} of this branch's most recent MERGED PR, else None.
        Read-only: the run-end `already-shipped` check, never a mutation."""
        m = self.fixture.get("merged_pr")
        return dict(m) if m else None

    def pr_exists(self) -> bool:
        return bool(self.fixture.get("pr_number"))

    def pr_meta(self) -> dict:
        return dict(self.fixture.get("pr_meta") or {})

    def pr_closing_refs(self, pr: int) -> tuple[str, list[tuple[str, int]]]:
        """(baseRefName, [(owner/repo, number)]) GitHub will close on merge."""
        cr = self.fixture.get("closing_refs") or {}
        return (cr.get("base", "master"),
                [(r["repo"], int(r["number"])) for r in cr.get("refs", [])])

    def pr_title(self) -> str:
        return (self.fixture.get("pr_meta") or {}).get("title") or self.fixture.get("title", "")

    def pr_body(self) -> str:
        if self._body_override is not None:
            return self._body_override
        return (self.fixture.get("pr_meta") or {}).get("body") or self.fixture.get("body", "")

    def pr_body_fresh(self) -> str:
        """Read the body as GitHub currently holds it, ignoring our own last write.

        pr_body() short-circuits on _body_override, which every pr_edit_body sets and
        nothing clears — so a caller that runs downstream of a harness write gets the
        harness's own copy back, never the live one. Any body edit a remediation agent
        made with a raw `gh pr edit` subprocess (fidelity.py) bypasses the adapter, so
        the override cannot know about it. Clearing the override is what makes the read
        genuinely fresh; the replay adapter then answers from the fixture, which stands
        in for GitHub exactly as it does on a first read.
        """
        self._body_override = None
        return self.pr_body()

    def pr_labels(self) -> list[str]:
        labels = self.fixture.get("labels") or []
        return [l["name"] if isinstance(l, dict) else str(l) for l in labels]

    def pr_state(self, pr: int | None = None) -> str:
        if pr is not None and pr != self.fixture.get("pr_number"):
            deps = self.fixture.get("dep_states") or {}
            return deps.get(str(pr), "UNKNOWN")
        return self.fixture.get("final_state") or "OPEN"

    def branch_protection_strict(self) -> bool:
        return bool((self.fixture or {}).get("protection_strict", False))

    def aggregator_required(self, context: str) -> bool:
        return context in ((self.fixture or {}).get("protection_contexts") or [])

    def merge_state_status(self, pr: int | None = None) -> str:
        return str((self.fixture or {}).get("merge_state_status", "CLEAN"))

    def pr_checks_json(self, pr) -> list:
        self.record("pr_checks_json", pr=pr)
        return list(self.fixture.get("ci_checks") or [])

    def run_log_failed(self, run_id, job_id, dest) -> bool:
        self.record("run_log_failed", run_id=run_id, job_id=job_id)
        try:
            with open(dest, "w") as fh:
                fh.write("(replay log)\n")
        except OSError:
            pass
        return True

    def run_rerun_failed(self, run_id) -> bool:
        self.record("run_rerun_failed", run_id=run_id)
        return True

    # -- baseline guard (harness/baseline_guard.py): fresh reads, never the cached meta --
    def pr_has_label_fresh(self, pr: int, label: str) -> bool:
        if self.fixture.get("fresh_labels_error"):
            raise HarnessError("gh", "replay: fresh label read failed")
        return label in (self.fixture.get("fresh_labels") or [])

    def e2e_runs_for_sha(self, sha: str) -> list[dict]:
        if self.fixture.get("e2e_runs_error"):
            raise HarnessError("gh", "replay: e2e run read failed")
        return list(self.fixture.get("e2e_runs") or [])

    def pr_comments_text(self, pr: int) -> str:
        return "\n".join(self.fixture.get("pr_comments") or [])

    def pr_label_remove(self, pr: int, label: str) -> bool:
        self.record("label_remove", pr=pr, label=label)
        return True

    def pr_label_add_checked(self, pr: int, label: str) -> bool:
        failures = int(self.fixture.get("label_add_failures") or 0)
        if failures > 0:
            self.fixture["label_add_failures"] = failures - 1
            return False
        self.record("label_add", pr=pr, label=label)
        return True

    def pr_disable_auto_merge(self, pr: int) -> None:
        self.record("pr_disable_auto_merge", pr=pr)

    def checks_outcome(self) -> dict:
        """Recorded terminal CI outcome: {"exit": 0|8, "failed": [names]}."""
        return dict(self.fixture.get("checks_outcome") or {"exit": 0, "failed": []})

    def pr_number(self) -> int:
        return int(self.fixture.get("pr_number") or 0)

    def actions(self) -> list[dict]:
        if not os.path.exists(self.actions_path):
            return []
        with open(self.actions_path) as fh:
            return [json.loads(l) for l in fh if l.strip()]


class LiveGh(RecordingGh):
    """Installed live adapter. Each MUTATION maps to one fixed `gh`
    invocation built inside its method — the allowlist IS the method set.
    Reads come from live `gh pr view` state. Merge deliberately omits
    --delete-branch: in a multi-worktree clone it errors benignly, and a parent
    merge carrying it permanently closes stacked child PRs."""

    def __init__(self, out_dir: str, worktree: str, branch: str, timeout: int = 120):
        super().__init__(out_dir)
        self.worktree = worktree
        self.branch = branch
        self.timeout = timeout
        self._meta: dict | None = None
        self._code_review_seen: dict = {}

    def _gh(self, *args: str, input_text: str | None = None) -> str:
        """Every gh call. Retries network-transient failures only for argv on
        netretry's read-only/idempotent allowlist; everything else is one attempt.
        Non-idempotent sites opt in through _gh_landed with a landed check."""
        if not netretry.gh_retry_safe(args):
            return self._gh_once(*args, input_text=input_text)
        return netretry.call(lambda: self._gh_once(*args, input_text=input_text),
                             label=f"gh {' '.join(args[:2])}")

    def _gh_once(self, *args: str, input_text: str | None = None) -> str:
        try:
            proc = subprocess.run(["gh", *args], cwd=self.worktree, input=input_text,
                                  capture_output=True, text=True, timeout=self.timeout)
        except subprocess.TimeoutExpired:
            raise HarnessError("gh", f"gh {' '.join(args[:2])}: exceeded {self.timeout}s")
        if proc.returncode != 0:
            raise HarnessError("gh", f"gh {' '.join(args[:3])}: {proc.stderr.strip()[:400]}")
        return proc.stdout

    def _gh_landed(self, argv: tuple[str, ...], *, label: str,
                   landed, input_text: str | None = None) -> str:
        """A non-idempotent call. No landed check means one attempt (today)."""
        if landed is None:
            return self._gh(*argv, input_text=input_text)
        return netretry.call(lambda: self._gh(*argv, input_text=input_text),
                             label=label, landed=landed)

    @staticmethod
    def _count_increase_landed(count_fn):
        """Snapshot count_fn() now. landed() is True once the count grows, so an
        identical earlier comment or review never counts as the retried one.
        A failed snapshot returns None: single attempt."""
        try:
            before = count_fn()
        except Exception:
            return None
        return lambda: ((True, "") if count_fn() > before else (False, None))

    def _open_prs_for_branch(self) -> list:
        return json.loads(self._gh("pr", "list", "--head", self.branch, "--state",
                                   "open", "--json", "number,url"))

    def _graphql_reset(self) -> float:
        """Epoch second the GraphQL bucket refills. `gh api rate_limit` is a REST
        read that does not spend the GraphQL bucket."""
        out = self._gh("api", "rate_limit", "--jq", ".resources.graphql.reset").strip()
        return float(int(out))

    def _matching_count(self, pr: int, field: str, body: str) -> int:
        """Count comments (field="comments") or reviews (field="reviews") on the
        PR whose body equals `body` after CRLF/whitespace normalisation."""
        norm = lambda s: (s or "").replace("\r\n", "\n").strip()
        data = json.loads(self._gh("pr", "view", str(pr), "--json", field))
        return sum(1 for item in data.get(field, []) if norm(item.get("body")) == norm(body))

    def record(self, action: str, **payload) -> None:
        super().record(action, executed=True, **payload)

    def _repo(self) -> str:
        return self._gh("repo", "view", "--json", "nameWithOwner",
                        "-q", ".nameWithOwner").strip()

    # -- mutations: executed, then recorded ------------------------------
    def pr_create(self, title: str, body: str, base: str) -> int:
        def found():
            rows = self._open_prs_for_branch()
            return (True, rows[0]["url"]) if rows else (False, None)
        try:
            before = self._open_prs_for_branch()
        except Exception:
            before = None
        # Transient retry needs a clean baseline: with none, one attempt (today).
        landed = found if before == [] else None  # runner calls this only when no PR exists
        argv = ("pr", "create", "--title", title, "--body", body,
                "--base", base, "--head", self.branch)
        # A rate-limit wait relists after the reset, so a fresh listing is the
        # baseline even when the pre-create snapshot failed.
        out = netretry.call_rate_limited(
            lambda: self._gh_landed(argv, label="gh pr create", landed=landed),
            label="gh pr create", landed=found, reset_at=self._graphql_reset)
        m = re.search(r"/pull/(\d+)", out)
        if not m:
            raise HarnessError("gh", f"pr create returned no PR URL: {out[:200]}")
        self._meta = None
        self.record("pr_create", title=title, body=body[:8000], base=base,
                    pr=int(m.group(1)))
        return int(m.group(1))

    def unresolved_findings(self, pr: int) -> list[str]:
        """Condition (11) — every unresolved review thread (scored or `-`).

        GH_CMD and REPO_SLUG are STRIPPED from the child env so bin/lib/pr-armable.sh
        falls back to its own defaults (`gh` and the repo slug). env=None would INHERIT
        them: a leaked stub answering the condition-(11) GraphQL query with a well-formed
        empty thread list reads as "no unresolved findings", and arming then proceeds on
        fabricated GitHub state. Same strip pr_sticky_verdict does below. Any failure
        returns the API-error sentinel, matching the shell's own contract — a GitHub
        outage must never arm a PR.

        The helper is sourced from the harness's OWN checkout (ARMABLE_LIB), which
        bin/post-plan-now pins to the main checkout (ADR-0092) — the same pin
        pr_sticky_verdict uses. A worktree-relative source would read the branch's copy,
        and a branch that edits bin/lib/pr-armable.sh could clear condition (11) on itself.
        """
        from .llm import _run_reaped
        if not ARMABLE_LIB.exists():
            return ["unresolved-findings-api-error"]
        argv = ["bash", "-c", 'source "$1"; pr_unresolved_findings_hold "$2"', "_",
                str(ARMABLE_LIB), str(pr)]
        env = {k: v for k, v in os.environ.items() if k not in ("GH_CMD", "REPO_SLUG")}
        try:
            proc = _run_reaped(argv, None, 120, self.worktree, env)
        except Exception:
            return ["unresolved-findings-api-error"]
        if proc.returncode != 0:
            return ["unresolved-findings-api-error"]
        return proc.stdout.split()

    def pr_thread_ids(self, pr: int) -> set[int]:
        from .llm import _run_reaped
        if not PRF_LIB.exists():
            return set()
        argv = ["bash", "-c", "source \"$1\"; prf_review_threads \"$2\" | jq -r '.commentId // empty'", "_",
                str(PRF_LIB), str(pr)]
        env = {k: v for k, v in os.environ.items() if k not in ("GH_CMD", "REPO_SLUG")}
        try:
            proc = _run_reaped(argv, None, self.timeout, self.worktree, env)
        except Exception:
            return set()
        if proc.returncode != 0:
            return set()
        result: set[int] = set()
        for line in proc.stdout.splitlines():
            line = line.strip()
            if line:
                try:
                    result.add(int(line))
                except ValueError:
                    pass
        return result

    def trusted_open_threads(self, pr: int, include_outdated: bool = False) -> list[dict] | None:
        extra = ("--include-outdated",) if include_outdated else ()
        return self._prf_rows("list_trusted_open_threads", pr, *extra)

    def review_threads_all(self, pr: int) -> list[dict] | None:
        return self._prf_rows("prf_review_threads", pr)

    def _prf_rows(self, fn_name: str, pr: int, *extra: str) -> list[dict] | None:
        from .llm import _run_reaped
        if not PRF_LIB.exists():
            return None
        argv = ["bash", "-c", 'source "$1"; "$2" "$3" "${@:4}"', "_",
                str(PRF_LIB), fn_name, str(pr), *extra]
        env = {k: v for k, v in os.environ.items() if k not in ("GH_CMD", "REPO_SLUG")}
        try:
            proc = _run_reaped(argv, None, self.timeout, self.worktree, env)
        except Exception:
            return None
        if proc.returncode != 0:
            return None
        rows = []
        for line in proc.stdout.splitlines():
            line = line.strip()
            if not line:
                continue
            try:
                rows.append(json.loads(line))
            except json.JSONDecodeError:
                pass
        return rows

    def resolve_review_thread(self, pr: int, comment_id: int, body: str) -> None:
        from .llm import _run_reaped
        if not PRF_LIB.exists():
            raise HarnessError("thread-resolve-failed", "PRF_LIB missing")
        argv = ["bash", "-c", 'source "$1"; resolve_review_finding "$2" "$3" "$4"', "_",
                str(PRF_LIB), str(pr), str(comment_id), body[:2000]]
        env = {k: v for k, v in os.environ.items() if k not in ("GH_CMD", "REPO_SLUG")}
        try:
            proc = _run_reaped(argv, None, self.timeout, self.worktree, env)
        except Exception as e:
            raise HarnessError("thread-resolve-failed", str(e))
        if proc.returncode != 0:
            raise HarnessError("thread-resolve-failed", proc.stderr.strip()[-400:])
        self.record("pr_resolve_thread", pr=pr, comment_id=int(comment_id), body=body[:2000])

    def reply_review_thread(self, pr: int, comment_id: int, body: str) -> None:
        from .llm import _run_reaped
        if not PRF_LIB.exists():
            raise HarnessError("thread-reply-failed", "PRF_LIB missing")
        argv = ["bash", "-c", 'source "$1"; reply_review_thread "$2" "$3" "$4"', "_",
                str(PRF_LIB), str(pr), str(comment_id), body[:2000]]
        env = {k: v for k, v in os.environ.items() if k not in ("GH_CMD", "REPO_SLUG")}
        try:
            proc = _run_reaped(argv, None, self.timeout, self.worktree, env)
        except Exception as e:
            raise HarnessError("thread-reply-failed", str(e))
        if proc.returncode != 0:
            raise HarnessError("thread-reply-failed", proc.stderr.strip()[-400:])
        self.record("pr_reply_thread", pr=pr, comment_id=int(comment_id), body=body[:2000])

    def pr_edit_body(self, pr: int, body: str) -> None:
        self._gh("pr", "edit", str(pr), "--body", body)
        self._body_override = body
        self.record("pr_edit_body", pr=pr, body=body[:8000])

    def pr_merge_auto(self, pr: int) -> None:
        self._gh("pr", "merge", str(pr), "--squash", "--auto")
        self.record("pr_merge_auto", pr=pr, args="--squash --auto")

    def pr_sticky_verdict(self, pr: int, body: str) -> str:
        """Upsert the Phase 5.5 sticky verdict comment, then read its id back.

        Sourced from the harness's OWN checkout, which bin/post-plan-now pins to the main
        checkout (ADR-0092), so a branch cannot alter the helper that posts its own verdict.
        GH_CMD is stripped so a leaked stub never stands in for real `gh`. Never raises: a
        failed post degrades to an empty id and arming is deliberately unchanged by it.
        """
        from .llm import _run_reaped
        path = os.path.join(self.out_dir, f"sticky-verdict-{pr}.md")
        with open(path, "w") as fh:
            fh.write(body)
        cid = ""
        env = {k: v for k, v in os.environ.items() if k != "GH_CMD"}
        if STICKY_LIB.exists():
            argv = ["bash", "-c", 'source "$1"; pr_sticky_upsert "$2" "$3" "$(cat "$4")"; pr_sticky_find "$2" "$3"', "_", str(STICKY_LIB), str(pr),
                    PR_STICKY_MARKER, path]
            try:
                proc = _run_reaped(argv, None, self.timeout, self.worktree, env)
                out = (proc.stdout or "").strip()
                if re.match(r"^[0-9]+$", out):
                    cid = out
            except (subprocess.TimeoutExpired, OSError):
                cid = ""
        self.record("pr_sticky_verdict", pr=pr, comment_id=cid, body=body)
        return cid

    def pr_sticky_body(self, pr: int) -> str | None:
        """The PR's current Phase 5.5 sticky comment body, or None.

        Fail-closed on every degraded shape — a gh failure, unparseable JSON, zero
        marked comments, or more than one. Returning None declines carry-forward and
        the full Opus review runs, which is the pre-change behaviour.
        """
        try:
            data = json.loads(self._gh("pr", "view", str(pr), "--json", "comments"))
        except (HarnessError, ValueError, OSError):
            self._code_review_seen[pr] = None
            return None
        self._code_review_seen[pr] = _last_code_review(data)
        try:
            bodies = [c.get("body") or "" for c in (data.get("comments") or [])
                      if PR_STICKY_MARKER in (c.get("body") or "")]
        except (AttributeError, TypeError):
            return None
        return bodies[0] if len(bodies) == 1 else None

    def code_review_seen(self, pr: int) -> dict | None:
        """What the last `pr_sticky_body` fetch saw of a code-review comment.

        Reads the cache that fetch filled and never calls `gh`. None means this run did
        not read the comments (no fetch yet, or the fetch failed).
        """
        return self._code_review_seen.get(pr)

    def label_add(self, pr: int, label: str) -> None:
        self._gh("pr", "edit", str(pr), "--add-label", label)
        self.record("label_add", pr=pr, label=label)

    def issue_create(self, title: str, body: str, label: str) -> int | None:
        out = self._gh("issue", "create", "--repo", "a-jay85/IBL5-backlog",
                       "--label", label, "--title", title, "--body", body)
        m = re.search(r"/issues/(\d+)", out)
        if not m:
            raise HarnessError("gh", f"issue create returned no issue URL: {out[:200]}")
        n = int(m.group(1))
        self.record("issue_create", title=title, label=label, issue=n)
        return n

    def followup_create(self, title: str, body: str, label: str) -> int | None:
        try:
            kind, n = followcap.file_followup(followcap.GhStore(self._gh), label, title, body)
        except followcap.FollowcapError as exc:
            raise HarnessError("gh", f"followup: {exc}") from exc
        self.record("followup_create", title=title, label=label, kind=kind, number=n)
        return n

    def issue_titles(self, label: str | None, *, strict: bool = False) -> list[str]:
        argv = ["issue", "list", "--repo", "a-jay85/IBL5-backlog",
                "--state", "all", "--limit", "3000", "--json", "title"]
        if label is not None:
            argv += ["--label", label]
        try:
            items = json.loads(self._gh(*argv))
            return [i.get("title", "") for i in items if i.get("title")]
        except (HarnessError, json.JSONDecodeError) as exc:
            if strict:
                raise HarnessError("gh", f"issue list failed: {exc}") from exc
            return []

    def _visible_right_lines(self, pr: int):
        """{path: visible right-side lines} for the PR diff, or None when unknown."""
        try:
            parsed = visible_right_lines(self._gh("pr", "diff", str(pr)))
        except HarnessError:
            return None
        return parsed or None

    def _post_summary_comment(self, pr: int, comment: str) -> None:
        self._gh_landed(("pr", "comment", str(pr), "--body", comment),
                        label="gh pr comment",
                        landed=self._count_increase_landed(
                            lambda: self._matching_count(pr, "comments", comment)))

    def post_review_findings(self, pr: int, head_sha: str, title: str, findings: list) -> None:
        visible = self._visible_right_lines(pr)
        inline = [f for f in findings
                  if visible is None or (f.line > 0 and f.line in visible.get(f.path, set()))]
        inline_ids = {id(f) for f in inline}
        summary = [f for f in findings if id(f) not in inline_ids]
        if inline:
            payload = {"commit_id": head_sha, "event": "COMMENT", "body": title,
                       "comments": [{"path": f.path, "line": f.line, "side": "RIGHT",
                                     "body": render_body(f, 1000)} for f in inline]}
            try:
                self._gh_landed(("api", f"repos/{self._repo()}/pulls/{pr}/reviews",
                                 "--method", "POST", "--input", "-"),
                                label="gh api reviews POST",
                                landed=self._count_increase_landed(
                                    lambda: self._matching_count(pr, "reviews", title)),
                                input_text=json.dumps(payload))
            except HarnessError:
                # an out-of-diff line anchor 422s the whole review — degrade to a
                # summary comment; findings still hold arming from memory regardless
                self._post_summary_comment(
                    pr, f"## {title}\n\n" + "\n".join(_summary_lines(inline)))
        if summary:
            self._post_summary_comment(
                pr, f"## {title}\n\nFound {len(summary)} issue(s) that could not be "
                    "anchored to a diff line:\n\n" + "\n".join(_summary_lines(summary)))
        self.record("pr_review_findings", pr=pr, head_sha=head_sha, title=title,
                    findings=[{"path": f.path, "line": f.line, "body": render_body(f, 1000),
                               "score": f.score, "label": f.label} for f in findings])

    def post_review_summary(self, pr: int, title: str, body: str) -> None:
        comment = f"## {title}\n\n{body}"
        self._gh_landed(("pr", "comment", str(pr), "--body", comment),
                        label="gh pr comment",
                        landed=self._count_increase_landed(
                            lambda: self._matching_count(pr, "comments", comment)))
        self.record("pr_comment", pr=pr, title=title, body=body[:4000])

    def pr_status_label(self, pr: int, label: str) -> None:
        """Best-effort: mark the PR with the yellow in-flight label."""
        try:
            self._gh("label", "create", label, "--color", "FBCA04",
                     "--description",
                     "post-plan is running on this PR; removed when the run ends")
        except HarnessError:
            pass
        try:
            self._gh("api", "--method", "POST",
                     f"repos/{{owner}}/{{repo}}/issues/{pr}/labels",
                     "-f", f"labels[]={label}")
        except HarnessError:
            pass
        self.record("pr_status_label", pr=pr, label=label)

    def branch_protection_strict(self) -> bool:
        """Fail closed: an unreadable or null protection config is treated as strict."""
        try:
            out = self._gh("api", f"repos/{self._repo()}/branches/master",
                           "--jq", ".protection.required_status_checks.strict")
        except (HarnessError, OSError, subprocess.SubprocessError):
            return True
        val = (out or "").strip().lower()
        if val in ("true", "false"):
            return val == "true"
        return True

    def aggregator_required(self, context: str) -> bool:
        """Fail closed: unreadable protection or a missing context -> False (condition 15 keeps blocking)."""
        try:
            out = self._gh("api", f"repos/{self._repo()}/branches/master",
                           "--jq", ".protection.required_status_checks.contexts[]?")
        except (HarnessError, OSError, subprocess.SubprocessError):
            return False
        return context in (out or "").splitlines()

    def merge_state_status(self, pr: int | None = None) -> str:
        """Fail open with "": an unreadable merge state must not trigger a rebase storm."""
        try:
            out = self._gh("pr", "view", str(pr or self.pr_number()),
                           "--json", "mergeStateStatus", "--jq", ".mergeStateStatus")
        except (HarnessError, OSError, subprocess.SubprocessError):
            return ""
        return (out or "").strip()

    def pr_checks_json(self, pr) -> list:
        try:
            proc = subprocess.run(
                ["gh", "pr", "checks", str(pr), "--json", "name,state,link"],
                cwd=self.worktree, capture_output=True, text=True, timeout=self.timeout,
            )
            if proc.returncode in (0, 8):
                return json.loads(proc.stdout)
        except (OSError, subprocess.TimeoutExpired, json.JSONDecodeError, ValueError):
            pass
        return []

    def run_log_failed(self, run_id, job_id, dest) -> bool:
        try:
            proc = subprocess.run(
                ["gh", "run", "view", str(run_id), "--job", str(job_id), "--log-failed"],
                cwd=self.worktree, capture_output=True, text=True, timeout=self.timeout,
            )
            with open(dest, "w") as fh:
                if proc.returncode == 0:
                    fh.write(proc.stdout)
                else:
                    fh.write(proc.stderr or proc.stdout)
            return proc.returncode == 0
        except (OSError, subprocess.TimeoutExpired):
            try:
                with open(dest, "w") as fh:
                    fh.write("(log unavailable)\n")
            except OSError:
                pass
            return False

    def run_rerun_failed(self, run_id) -> bool:
        try:
            self._gh("run", "rerun", str(run_id), "--failed")
            return True
        except (HarnessError, OSError, subprocess.SubprocessError):
            return False

    # -- baseline guard (harness/baseline_guard.py) ------------------------
    def pr_has_label_fresh(self, pr: int, label: str) -> bool:
        """Live label read. Bypasses `_fetch_meta`: the label changes during the run."""
        out = self._gh("api", f"repos/{{owner}}/{{repo}}/issues/{pr}/labels",
                       "--jq", ".[].name")
        return any(line.strip() == label for line in out.splitlines())

    def e2e_runs_for_sha(self, sha: str) -> list[dict]:
        return json.loads(self._gh(
            "run", "list", "--workflow", "e2e-tests.yml", "--commit", sha,
            "--json", "databaseId,status,conclusion,event,createdAt", "--limit", "20"))

    def pr_comments_text(self, pr: int) -> str:
        return self._gh("api", "--paginate", f"repos/{{owner}}/{{repo}}/issues/{pr}/comments",
                        "--jq", ".[].body")

    def pr_label_remove(self, pr: int, label: str) -> bool:
        try:
            self._gh("api", "--method", "DELETE",
                     f"repos/{{owner}}/{{repo}}/issues/{pr}/labels/{label}")
        except (HarnessError, OSError, subprocess.SubprocessError):
            return False
        self.record("label_remove", pr=pr, label=label)
        return True

    def pr_label_add_checked(self, pr: int, label: str) -> bool:
        try:
            self._gh("api", "--method", "POST",
                     f"repos/{{owner}}/{{repo}}/issues/{pr}/labels",
                     "-f", f"labels[]={label}")
        except (HarnessError, OSError, subprocess.SubprocessError):
            return False
        self.record("label_add", pr=pr, label=label)
        return True

    def pr_disable_auto_merge(self, pr: int) -> None:
        try:
            self._gh("pr", "merge", str(pr), "--disable-auto")
        except (HarnessError, OSError, subprocess.SubprocessError):
            pass
        self.record("pr_disable_auto_merge", pr=pr)

    # -- reads (live) -----------------------------------------------------
    def _fetch_meta(self) -> dict:
        if self._meta is None:
            try:
                out = self._gh("pr", "view", self.branch, "--json",
                               "number,title,body,headRefOid,labels,state")
                self._meta = json.loads(out)
            except (HarnessError, json.JSONDecodeError):
                self._meta = {}
        return self._meta

    def merged_pr(self) -> dict | None:
        try:
            out = self._gh("pr", "list", "--head", self.branch, "--state", "merged",
                           "--json", "number,url,headRefOid", "--limit", "1")
            rows = json.loads(out or "[]")
        except (HarnessError, json.JSONDecodeError):
            return None                             # unknown: keep the failure
        if isinstance(rows, list) and rows and isinstance(rows[0], dict) and rows[0].get("number"):
            return {"number": int(rows[0]["number"]), "url": rows[0].get("url") or "",
                    "head": rows[0].get("headRefOid") or ""}
        return None

    def pr_exists(self) -> bool:
        return self._fetch_meta().get("state") == "OPEN"

    def pr_number(self) -> int:
        return int(self._fetch_meta().get("number") or 0)

    def pr_meta(self) -> dict:
        return dict(self._fetch_meta())

    def pr_closing_refs(self, pr: int) -> tuple[str, list[tuple[str, int]]]:
        data = json.loads(self._gh("pr", "view", str(pr), "--json",
                                   "baseRefName,closingIssuesReferences") or "{}")
        refs = []
        for it in data.get("closingIssuesReferences") or []:
            m = re.search(r"github\.com/([^/]+/[^/]+)/issues/(\d+)", it.get("url", ""))
            if m:
                refs.append((m.group(1), int(m.group(2))))
        return data.get("baseRefName", ""), refs

    def pr_title(self) -> str:
        return self._fetch_meta().get("title", "")

    def pr_body(self) -> str:
        if self._body_override is not None:
            return self._body_override
        return self._fetch_meta().get("body", "")

    def pr_body_fresh(self) -> str:
        """Re-issue `gh pr view` for the body, discarding both caches first.

        _meta must be cleared as well as _body_override: dropping only the override
        would fall through to a _fetch_meta() whose cached dict predates the write,
        which is stale in a different way. See RecordingGh.pr_body_fresh.
        """
        self._meta = None
        return super().pr_body_fresh()

    def pr_labels(self) -> list[str]:
        return [l.get("name", "") for l in self._fetch_meta().get("labels") or []]

    def pr_state(self, pr: int | None = None) -> str:
        if pr is None or pr == self.pr_number():
            self._meta = None                       # re-fetch: state may have moved
            return self._fetch_meta().get("state") or "UNKNOWN"
        try:
            return self._gh("pr", "view", str(pr), "--json", "state",
                            "-q", ".state").strip() or "UNKNOWN"
        except HarnessError:
            return "UNKNOWN"                        # fail-closed for condition (6)
