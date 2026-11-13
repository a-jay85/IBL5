"""Opt-in corpus diff for keeping a prior conflict review across a clean re-run (backlog#1778).

Runs only with POSTPLAN_CORPUS_DIFF=1 and a local audit-log corpus; CI sets neither,
so it skips there. Scans tools/postplan-harness/out/*/audit.log for runs that held
condition (14) with verdict=ABSENT and logged no conflict of their own. Those are the
only runs the park-and-restore change can flip, and only when the rebase was a no-op
and a prior review covered that exact HEAD, so the set is an upper bound.

Forms of the condition-(14) reason in audit.log at the 2026-11-13 scan
(`command grep -h -o -E 'conflict-review verdict=[^;]{0,20}' out/*/audit.log | sort | uniq -c`):

     78 conflict-review verdict=ABSENT
      4 conflict-review verdict='CONFLICT-REVIEW=FOU
      1 conflict-review verdict='CONFLICT-REVIEW=ABS

Only the bare `verdict=ABSENT` form counts. The quoted forms come from a review that ran
in that same run (a FOUND-PROBLEM or a re-review that still gave no token).
"""
import datetime
import glob
import os
import re
import subprocess
from pathlib import Path

import pytest

CORPUS_SCAN_DATE = datetime.date(2026, 11, 12)
# First opt-in run, 2026-11-13: scanned=2787 of 3046 logs, conflict_runs=183,
# absent_holds=78, flip_candidates=65 (runs stamped on or before CORPUS_SCAN_DATE).
EXPECTED_FLIP_CANDIDATES: frozenset[str] = frozenset({
    "live-automouse-queue-numeric-order-20261023-064424-47070",
    "live-bin-harness-lib-families-20261010-073543-21132",
    "live-burndown-wt-sync-tick-branch-tests-1173-20261005-150337-72074",
    "live-css-legacy-markup-styles-20261022-042449-61226",
    "live-css-legacy-markup-styles-20261023-134200-33521",
    "live-css-legacy-markup-styles-20261023-182113-20085",
    "live-css-legacy-markup-styles-20261026-174653-68205",
    "live-daisyui-contracts-20261027-042325-95875",
    "live-draft-info-hub-20261022-220625-19957",
    "live-durable-absence-checks-20261023-015610-62925",
    "live-executable-mutation-claims-20261021-201652-79721",
    "live-executable-mutation-claims-20261024-112616-88747",
    "live-executable-mutation-claims-20261025-185155-97654",
    "live-fix-sim-recap-admin-chat-status-20261021-185943-49578",
    "live-fix-sim-recap-admin-chat-status-20261022-083224-64676",
    "live-gm-trade-suggestions-panel-20261023-162128-37245",
    "live-google-sheet-oauth-export-20261015-072759-40197",
    "live-history-shape-tests-catch-mutants-20261103-144542-44238",
    "live-lighten-navy-palette-20261026-161210-75888",
    "live-make-plan-test-rows-revert-proof-20261011-220854-31742",
    "live-module-front-controller-composition-root-211-20261021-181940-89832",
    "live-must-preserve-impl-self-review-20261022-202256-49406",
    "live-must-preserve-impl-self-review-20261023-203243-29943",
    "live-must-preserve-impl-self-review-20261025-185155-97681",
    "live-must-preserve-impl-self-review-20261026-130454-87159",
    "live-must-preserve-impl-self-review-20261026-150928-60134",
    "live-note-filing-tooling-harm-bar-20261104-191128-8214",
    "live-note-filing-tooling-harm-bar-20261104-204507-77177",
    "live-on-this-day-20261023-134200-33640",
    "live-plan-tier-judge-20261102-020024-54419",
    "live-plan-tier-judge-20261102-040418-68140",
    "live-player-views-ibl-data-table-202-20261008-020942-68487",
    "live-player-views-ibl-data-table-202-20261022-223946-34523",
    "live-player-views-ibl-data-table-202-20261023-114816-85792",
    "live-post-merge-auto-and-ping-1-digest-ping-20261023-160048-22402",
    "live-post-merge-auto-and-ping-1-digest-ping-20261023-165341-20884",
    "live-post-merge-auto-and-ping-1-digest-ping-20261023-223459-66295",
    "live-post-merge-auto-and-ping-1-digest-ping-20261023-233752-97813",
    "live-postplan-golden-snapshot-regen-20261025-125956-93757",
    "live-postplan-in-run-fix-round-20261102-131635-79836",
    "live-postplan-retry-absent-arming-verdicts-20261023-111704-65966",
    "live-postplan-retry-absent-arming-verdicts-20261023-131907-82094",
    "live-postplan-skill-shrink-20261023-163123-62140",
    "live-pr-cycle-conflict-retry-probe-20261023-133341-20668",
    "live-pr-cycle-conflict-retry-probe-20261023-140909-26388",
    "live-records-hub-20261010-132023-39954",
    "live-records-hub-20261022-121953-45365",
    "live-review-preexisting-tag-bug-class-20261024-122444-56159",
    "live-review-preexisting-tag-bug-class-20261024-131215-85832",
    "live-rivalries-20261022-223621-6315",
    "live-rivalries-20261023-002437-28789",
    "live-rivalries-20261027-015141-45019",
    "live-sticky-plain-holds-20261107-100352-29151",
    "live-vr-pr-body-changed-screens-20261010-151354-33724",
    "live-vr-pr-keyed-phone-review-20261007-182157-16273",
    "live-vr-pr-keyed-phone-review-20261007-193602-87221",
    "live-vr-pr-keyed-phone-review-20261008-151600-86106",
    "live-vr-pr-keyed-phone-review-20261008-190917-36454",
    "live-vr-pr-keyed-phone-review-20261010-013140-23618",
    "live-vr-pr-keyed-phone-review-20261019-034656-40505",
    "live-watch-max-doc-sync-1105-20261007-225311-16950",
    "live-worktree-abort-cleanup-backup-namespace-20261023-110159-74609",
    "live-worktree-abort-cleanup-backup-namespace-20261023-162253-92263",
    "live-worktree-abort-cleanup-backup-namespace-20261023-203244-28994",
    "live-wt-gc-stop-idle-stacks-20261112-084327-61072",
})
_ABSENT_HOLD = re.compile(r"conflict-review verdict=ABSENT")
_CONFLICT_LINE = re.compile(r"conflicted paths \((?:plain rebase|probe)\) = (.*)$", re.M)
_RUN_STAMP = re.compile(r"-(\d{8})-\d{6}-\d+$")


def clean_rerun_absent_hold(text: str) -> bool:
    """True when one audit log held (14) on a bare ABSENT verdict and logged no conflict."""
    return bool(_ABSENT_HOLD.search(text)) and not _CONFLICT_LINE.search(text)


def _run_date(run_name: str):
    m = _RUN_STAMP.search(run_name)
    if not m:
        return None
    try:
        return datetime.datetime.strptime(m.group(1), "%Y%m%d").date()
    except ValueError:
        return None


def _out_dir() -> str:
    """POSTPLAN_OUT_DIR, else this tree's out/, else the main checkout's out/ (a worktree has none)."""
    override = os.environ.get("POSTPLAN_OUT_DIR")
    if override:
        return override
    here = Path(__file__).resolve().parent.parent
    local = here / "out"
    if glob.glob(str(local / "*" / "audit.log")):
        return str(local)
    try:
        common = subprocess.run(
            ["git", "-C", str(here), "rev-parse", "--path-format=absolute", "--git-common-dir"],
            capture_output=True, text=True, timeout=30, check=True).stdout.strip()
    except (OSError, subprocess.SubprocessError):
        return str(local)
    return str(Path(common).parent / "tools" / "postplan-harness" / "out")


def test_absent_hold_parser_flags_clean_rerun_only():
    """Runs everywhere: pins the parser so the opt-in scan cannot pass vacuously."""
    absent = "[09:12:01] arming: hold (14) conflict-review verdict=ABSENT\n"
    assert clean_rerun_absent_hold(absent)
    assert not clean_rerun_absent_hold(
        "[09:12:01] arming: hold (14) conflict-review verdict='CONFLICT-REVIEW=FOUND-PROBLEM'\n")
    assert not clean_rerun_absent_hold(
        "[09:12:01] arming: hold (14) conflict-review verdict='CONFLICT-REVIEW=ABSENT' after 1 retry\n")
    assert not clean_rerun_absent_hold(
        "[09:11:58] phase2: conflicted paths (plain rebase) = a.php\n" + absent)
    assert not clean_rerun_absent_hold("")


def test_local_audit_corpus_absent_holds():
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local audit-log corpus")
    out_dir = _out_dir()
    logs = sorted(glob.glob(os.path.join(out_dir, "*", "audit.log")))
    if not logs:
        pytest.skip(f"SKIP: no audit logs under {out_dir}")
    run_log: dict[str, str] = {}
    for log_path in logs:
        run = os.path.basename(os.path.dirname(log_path))
        day = _run_date(run)
        if day is not None and day <= CORPUS_SCAN_DATE:
            run_log[run] = log_path
    conflict_runs = 0
    absent_holds = 0
    candidates: set[str] = set()
    for run, log_path in run_log.items():
        text = Path(log_path).read_text(errors="replace")
        if _CONFLICT_LINE.search(text):
            conflict_runs += 1
        if _ABSENT_HOLD.search(text):
            absent_holds += 1
        if clean_rerun_absent_hold(text):
            candidates.add(run)
    print(f"scanned={len(run_log)} of {len(logs)} logs (on or before {CORPUS_SCAN_DATE}) "
          f"conflict_runs={conflict_runs} absent_holds={absent_holds} "
          f"flip_candidates={len(candidates)} {sorted(candidates)}")
    # A run pruned from out/ since the scan is no longer expected.
    expected = {r for r in EXPECTED_FLIP_CANDIDATES if r in run_log}
    assert candidates == expected, (
        f"unexpected={sorted(candidates - expected)} missing={sorted(expected - candidates)}")
