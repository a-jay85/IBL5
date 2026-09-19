"""Phase 6.7 — execute manual-testing rows and tick passing checkboxes in the PR body."""
from __future__ import annotations

import os
import re
import shlex
import shutil
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Callable, Optional

from harness.adapters import probe as probe_mod

BRINGUP_SCRIPT_PATHS = (
    ".claude/review-shared/scripts/wt-bring-up.sh",
    ".claude/skills/pr-ready/scripts/wt-bring-up.sh",
)
ROWS_SCRIPT_PATHS = (
    ".claude/review-shared/scripts/manual-rows.sh",
    ".claude/skills/pr-ready/scripts/manual-rows.sh",
)
TICK_SCRIPT_PATHS = (
    ".claude/review-shared/scripts/tick-rows.sh",
    ".claude/skills/pr-ready/scripts/tick-rows.sh",
)

WORKTREES_ROOT = "/Users/ajaynicolas/GitHub/IBL5-worktrees"
BRINGUP_TIMEOUT_S = 120
ROW_TIMEOUT_S = 30
ROWS_TIMEOUT_CEILING_S = 300
DOCKER_PROBE_TIMEOUT_S = 10

ROW_RE = re.compile(r"^- \[([ x])\] \*\*([^*]+)\*\*(.*)$")
RESULT_RE = re.compile(
    r"^ROW (.+?) (PASS|FAIL|SKIP-HUMAN|SKIP-NOURL|SKIP-AUTH|SKIP-DONE)(?:\s+(.*))?$"
)
_CMD_SPAN_RE = re.compile(r"`([^`]+)`")

BRINGUP_SENTINEL = "BRINGUP-COMPLETE"
ROWS_SENTINEL = "MANUAL-ROWS-COMPLETE"
TICK_SENTINEL = "TICK-COMPLETE"
ROWS_TMPFILE = "/tmp/pr-ready-manual-rows-{pr}.txt"


@dataclass
class ManualTestingResult:
    ran: bool = False
    skipped_reason: str = ""
    bringup: str = ""
    rows_total: int = 0
    rows: list[dict] = field(default_factory=list)
    ticked: list[str] = field(default_factory=list)
    all_ticked: bool = False
    probed_tree: str = ""
    errors: list[str] = field(default_factory=list)

    def to_dict(self) -> dict:
        from dataclasses import asdict
        return asdict(self)


def pending_rows(body: str) -> list[tuple[str, str, bool]]:
    """Return (row_id, row_text, already_ticked) for each harness row in the Manual Testing section."""
    lines = (body or "").splitlines()
    section: list[str] = []
    in_sec = False
    for line in lines:
        if re.match(r"^## Manual Testing", line):
            in_sec = True
            continue
        if in_sec and re.match(r"^## ", line):
            break
        if in_sec:
            section.append(line)
    result: list[tuple[str, str, bool]] = []
    for line in section:
        m = ROW_RE.match(line)
        if m:
            ticked_char, row_id, row_text = m.group(1), m.group(2), m.group(3)
            result.append((row_id, row_text.strip(), ticked_char == "x"))
    return result


def _load_script(
    show_blob: Callable[[str], str],
    master_sha: str,
    candidates: tuple[str, ...],
    pr: int,
) -> Optional[Path]:
    """Load first non-empty candidate blob from master, write to /tmp, return Path."""
    for candidate in candidates:
        content = show_blob(f"{master_sha}:{candidate}")
        if content and content.strip():
            basename = os.path.basename(candidate)
            dest = Path(f"/tmp/postplan-manual-{basename}-{pr}.sh")
            dest.write_text(content)
            dest.chmod(0o755)
            return dest
    return None


def docker_available() -> tuple[bool, str]:
    """Return (available, reason). Fail-closed on every branch."""
    if shutil.which("docker") is None:
        return False, "docker-not-on-path"
    try:
        proc = subprocess.run(
            ["docker", "info", "--format", "{{.ServerVersion}}"],
            capture_output=True,
            text=True,
            timeout=DOCKER_PROBE_TIMEOUT_S,
        )
        if proc.returncode != 0:
            return False, "docker-daemon-unreachable"
        return True, ""
    except subprocess.TimeoutExpired:
        return False, "docker-probe-timeout"
    except Exception as exc:  # noqa: BLE001
        return False, f"docker-probe-error:{exc}"


def resolve_slug(worktree: str) -> tuple[str, str]:
    """Return (slug, error_reason). slug is empty on failure."""
    slug = os.path.basename(os.path.normpath(os.path.abspath(worktree)))
    if not os.path.isdir(os.path.join(WORKTREES_ROOT, slug)):
        return "", "worktree-not-under-worktrees-root"
    return slug, ""


def _run_script(
    path: Path,
    args: list[str],
    timeout_s: float,
    cwd: Optional[str] = None,
) -> tuple[int, str, str]:
    """Run a bash script, return (rc, stdout, stderr). Never raises."""
    try:
        proc = subprocess.run(
            ["bash", str(path), *args],
            capture_output=True,
            text=True,
            errors="replace",
            cwd=cwd,
            timeout=timeout_s,
        )
        return proc.returncode, proc.stdout, proc.stderr
    except subprocess.TimeoutExpired:
        return -1, "", "timeout"
    except Exception as exc:  # noqa: BLE001
        return -1, "", str(exc)


def bring_up(script_path: Path, pr: int, slug: str, worktree: str) -> str:
    """Run wt-bring-up.sh and return the bringup state token."""
    rc, stdout, stderr = _run_script(script_path, [str(pr), slug], BRINGUP_TIMEOUT_S, worktree)
    if rc == -1 and "timeout" in stderr:
        return "bringup-timeout"
    for line in stdout.splitlines():
        line = line.strip()
        if line.startswith("BRINGUP: UP"):
            bringup_state = "UP"
            break
        if line.startswith("BRINGUP: ALREADY-UP"):
            bringup_state = "ALREADY-UP"
            break
        if line.startswith("BRINGUP: SKIP peer-dirty"):
            bringup_state = "SKIP-peer-dirty"
            break
        if line.startswith("BRINGUP: NOT-READY"):
            bringup_state = "NOT-READY"
            break
        if line.startswith("BRINGUP: FAILED"):
            bringup_state = "FAILED"
            break
    else:
        return "incomplete"
    if BRINGUP_SENTINEL not in stdout:
        return "incomplete"
    return bringup_state


def run_http_rows(
    script_path: Path,
    pr: int,
    slug: str,
    worktree: str,
    n_pending: int,
) -> tuple[list[dict], str]:
    """Run manual-rows.sh and return (rows, error_reason)."""
    timeout_s = max(ROW_TIMEOUT_S, min(ROW_TIMEOUT_S * n_pending, ROWS_TIMEOUT_CEILING_S))
    rc, stdout, stderr = _run_script(script_path, [str(pr), slug], timeout_s, worktree)
    if rc == -1 and "timeout" in stderr:
        return [], "rows-timeout"
    if ROWS_SENTINEL not in stdout:
        return [], "rows-incomplete"
    rows: list[dict] = []
    for line in stdout.splitlines():
        m = RESULT_RE.match(line.strip())
        if m:
            row_id, verdict, detail = m.group(1), m.group(2), m.group(3) or ""
            rows.append({"id": row_id, "verdict": verdict, "detail": detail[:200], "source": "http"})
    return rows, ""


def cli_argv(row_text: str) -> Optional[list[str]]:
    """Return the first allowlisted argv found in backtick spans, or None."""
    for span in _CMD_SPAN_RE.findall(row_text):
        try:
            argv = shlex.split(span)
        except ValueError:
            continue
        if argv and probe_mod.allowed(argv):
            return argv
    return None


def run_cli_rows(
    probe: object,
    pending: list[tuple[str, str, bool]],
    http_verdicts: dict[str, str],
) -> list[dict]:
    """Run CLI rows through the probe adapter, returning row dicts."""
    rows: list[dict] = []
    for row_id, row_text, already_ticked in pending:
        if already_ticked:
            continue
        argv = cli_argv(row_text)
        if argv is None:
            continue
        # Override guard: only proceed when absent or exactly SKIP-NOURL
        existing = http_verdicts.get(row_id)
        if existing is not None and existing != "SKIP-NOURL":
            continue
        ok, detail = probe.run(argv, timeout=ROW_TIMEOUT_S)
        verdict = "PASS" if ok else "FAIL"
        rows.append({"id": row_id, "verdict": verdict, "detail": detail[:200], "source": "cli"})
    return rows


def append_cli_passes(pr: int, cli_rows: list[dict]) -> Optional[str]:
    """Append PASS lines from cli_rows to the tick input file. Returns error reason or None."""
    tmpfile = ROWS_TMPFILE.format(pr=pr)
    try:
        with open(tmpfile, "a") as f:
            for r in cli_rows:
                if r["verdict"] == "PASS":
                    f.write(f"ROW {r['id']} PASS\n")
        return None
    except FileNotFoundError:
        return "rows-file-missing"
    except Exception as exc:  # noqa: BLE001
        return f"append-error:{exc}"


def tick(
    script_path: Path,
    pr: int,
    slug: str,
    worktree: str,
    pass_ids: list[str],
) -> tuple[int, list[str]]:
    """Run tick-rows.sh. Returns (ticked_count, ticked_ids). Skips when pass_ids empty."""
    if not pass_ids:
        return 0, []
    rc, stdout, stderr = _run_script(script_path, [str(pr), slug], ROW_TIMEOUT_S, worktree)
    ticked_count = 0
    ticked_ids: list[str] = []
    for line in stdout.splitlines():
        m = re.match(r"TICKED:\s*(\d+)", line.strip())
        if m:
            ticked_count = int(m.group(1))
        tm = re.match(r"TICKED-ROW:\s*(.+)", line.strip())
        if tm:
            ticked_ids.append(tm.group(1).strip())
    return ticked_count, ticked_ids


def confirm(
    gh: object,
    pass_ids: list[str],
    errors: list[str],
) -> tuple[list[str], bool]:
    """Re-fetch PR body and confirm ticks landed. Returns (confirmed_ids, all_ticked)."""
    refetched = gh.pr_body() or ""
    if not refetched:
        errors.append("tick-unconfirmed:empty-body")
        return [], False
    rows = pending_rows(refetched)
    confirmed = [rid for rid, _text, is_ticked in rows if is_ticked and rid in pass_ids]
    for pid in pass_ids:
        if pid not in confirmed:
            errors.append(f"tick-unconfirmed:{pid}")
    all_ticked_val = bool(rows) and not any(not is_ticked for _rid, _text, is_ticked in rows)
    return confirmed, all_ticked_val


def show_blob_for(worktree: str) -> Callable[[str], str]:
    """Pinned-blob reader for the dual-path script loader."""
    def _show(ref: str) -> str:
        import subprocess as _sp
        proc = _sp.run(
            ["git", "-C", worktree, "show", ref],
            capture_output=True, text=True, errors="replace",
        )
        return proc.stdout if proc.returncode == 0 else ""
    return _show


def run(
    *,
    pr: int,
    worktree: str,
    body: str,
    gh: object,
    probe: object,
    show_blob: Callable[[str], str],
    master_sha: str,
    head_tree: Callable[[], str],
    live: bool,
    log: Callable[[str], None],
    tick_enabled: bool = True,
) -> ManualTestingResult:
    """Execute manual-testing rows for PR and tick passing rows.

    *live* must be True for any subprocess or gh write to occur.
    """
    result = ManualTestingResult()
    try:
        # Gate 1: replay mode
        if not live:
            result.skipped_reason = "replay-mode"
            return result

        # Gate 2: no pending rows
        pending = pending_rows(body)
        result.rows_total = len(pending)
        if not pending:
            result.skipped_reason = "no-manual-rows"
            return result

        # Gate 3: all already ticked
        if all(already for _, _, already in pending):
            result.skipped_reason = "already-all-ticked"
            return result

        # Gate 4: Docker
        ok, reason = docker_available()
        if not ok:
            result.skipped_reason = reason
            return result

        # Gate 5: resolve slug
        slug, slug_err = resolve_slug(worktree)
        if slug_err:
            result.skipped_reason = slug_err
            return result

        # Gate 6: load scripts
        bringup_script = _load_script(show_blob, master_sha, BRINGUP_SCRIPT_PATHS, pr)
        if bringup_script is None:
            result.skipped_reason = "script-missing:wt-bring-up.sh"
            return result

        rows_script = _load_script(show_blob, master_sha, ROWS_SCRIPT_PATHS, pr)
        if rows_script is None:
            result.skipped_reason = "script-missing:manual-rows.sh"
            return result

        tick_script = _load_script(show_blob, master_sha, TICK_SCRIPT_PATHS, pr)
        if tick_script is None:
            result.skipped_reason = "script-missing:tick-rows.sh"
            return result

        result.ran = True

        # Step 7: capture tree before bring-up
        result.probed_tree = head_tree()

        # Step 8: bring up
        bringup_state = bring_up(bringup_script, pr, slug, worktree)
        result.bringup = bringup_state
        log(f"phase6.7: bringup={bringup_state}")
        if bringup_state not in ("UP", "ALREADY-UP"):
            result.errors.append(f"bringup-stopped:{bringup_state}")
            return result

        # Step 9: HTTP rows
        http_rows, http_err = run_http_rows(rows_script, pr, slug, worktree, len(pending))
        result.rows.extend(http_rows)
        if http_err:
            result.errors.append(http_err)
            return result

        # Step 10: CLI rows
        http_verdicts = {r["id"]: r["verdict"] for r in http_rows}
        cli_rows = run_cli_rows(probe, pending, http_verdicts)

        # Step 11: append CLI passes to tick input file and union rows
        cli_err = append_cli_passes(pr, cli_rows)
        if cli_err:
            result.errors.append(cli_err)
        result.rows.extend(cli_rows)

        # Step 12: tick
        pass_ids = [r["id"] for r in result.rows if r["verdict"] == "PASS"]

        if not tick_enabled:
            log(
                f"phase6.7: rows={len(result.rows)} ticked={len(result.ticked)}"
                f" all_ticked={result.all_ticked}"
            )
        else:
            # Tree gate before ticking
            current_tree = head_tree()
            if current_tree and result.probed_tree and current_tree != result.probed_tree:
                result.errors.append("tree-moved-mid-pass")
                return result

            _count, _ticked_ids = tick(tick_script, pr, slug, worktree, pass_ids)
            confirmed, all_ticked_val = confirm(gh, pass_ids, result.errors)
            result.ticked = confirmed
            result.all_ticked = all_ticked_val
            log(
                f"phase6.7: rows={len(result.rows)} ticked={len(result.ticked)}"
                f" all_ticked={result.all_ticked}"
            )

    except Exception as exc:  # noqa: BLE001
        result.errors.append(f"manual-testing-error:{exc}")

    return result


def main(argv: list[str] | None = None) -> int:
    import argparse
    import sys as _sys
    from harness.adapters import probe as probe_mod

    parser = argparse.ArgumentParser(description="Manual-testing dry-run executor")
    parser.add_argument("--pr", type=int, required=True)
    parser.add_argument("--worktree", required=True)
    parser.add_argument("--tick", action="store_true", default=False)
    args = parser.parse_args(argv)

    class _GhShim:
        def __init__(self, pr: int):
            self._pr = pr
        def pr_body(self) -> str:
            import subprocess as _sp
            proc = _sp.run(
                ["gh", "pr", "view", str(self._pr), "--json", "body", "--jq", ".body"],
                capture_output=True, text=True,
            )
            return proc.stdout.strip() if proc.returncode == 0 else ""

    gh = _GhShim(args.pr)
    body = gh.pr_body()
    probe = probe_mod.LiveProbe(repo_root=args.worktree)

    import subprocess as _sp
    sha_proc = _sp.run(
        ["git", "-C", args.worktree, "rev-parse", "origin/master"],
        capture_output=True, text=True,
    )
    master_sha = sha_proc.stdout.strip()

    def _head_tree() -> str:
        p = _sp.run(
            ["git", "-C", args.worktree, "rev-parse", "HEAD^{tree}"],
            capture_output=True, text=True,
        )
        return p.stdout.strip()

    result = run(
        pr=args.pr, worktree=args.worktree, body=body,
        gh=gh, probe=probe, show_blob=show_blob_for(args.worktree),
        master_sha=master_sha, head_tree=_head_tree,
        live=True, log=print,
        tick_enabled=args.tick,
    )

    if result.skipped_reason:
        print(f"skipped: {result.skipped_reason}")
    else:
        for r in result.rows:
            print(f"{r['id']}\t{r['verdict']}\t{r['source']}\t{r.get('detail','')}")
        print(f"bringup={result.bringup} all_ticked={result.all_ticked}"
              + (f" errors={';'.join(result.errors)}" if result.errors else ""))
    return 0


if __name__ == "__main__":
    import sys
    sys.exit(main())
