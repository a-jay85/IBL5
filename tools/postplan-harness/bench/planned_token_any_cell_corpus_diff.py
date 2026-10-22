"""Diff the planned token per matrix row: old joined-row first-token gate vs new any-cell gate.

Usage:
  python3 planned_token_any_cell_corpus_diff.py --harness-root <dir containing harness/> \
      [--plans-dir ~/claude-plans] [--repo-root DIR] [--report PATH]

The "old" side is an EMBEDDED copy of the joined-row first-token gate, so the result
does not depend on what `planfile._planned_token` currently is. The "new" side is the
live `planfile._planned_token`. Per unfenced matrix row:
  LOST     old planned a token, new plans none. Must be 0.
  NEWLY    old planned nothing, new plans a token. Labelled with a skip reason (why the
           old gate skipped it) and a triage class (what the new token looks like).
  CHANGED  both plan a token and the tokens differ.
  DROPPED  a CHANGED row's old token that no row of the same plan plans afterward.
Files under `_reports/` and `.drafts/` directories are skipped: they hold earlier runs
of this tool and unfinished plans.
Exits 1 when lost>0 or a class/reason sum misses the newly-planned count; a positive
newly_planned is the expected result of the change and does not fail the run.
Stdlib only.
"""
import argparse
import os
import re
import subprocess
import sys

_OLD_TOKEN = re.compile(r"`([^`]*(?:test|spec|Test)[^`]*)`")
_BARE_METHOD = re.compile(r"^[A-Za-z_][\w:.\\-]*$")
_COMMAND_CHARS = ("&", "|", ";", ">", "<", "$", "\\")
_COMMAND_STARTS = ("-", "'", '"', "$", "@")
_TESTISH_BASENAME = re.compile(r"test|spec|Test")
_SKIP_DIRS = ("_reports", ".drafts")


def old_row_token(planfile, cells):
    """Pre-#1501 gate: first test-ish token of the JOINED row must be a path."""
    if not planfile._has_planning_type_cell(cells):
        return None
    m = _OLD_TOKEN.search(" | ".join(cells))
    if m and planfile._is_test_path(m.group(1)):
        return m.group(1)
    return None


def _cells(line: str) -> list[str]:
    return [c.strip() for c in line.strip().strip("|").split("|")]


def _excerpt(line: str) -> str:
    return " ".join(line.split())[:160]


def skip_reason(cells) -> str:
    """Why the old gate skipped this row, from the first test-ish token of the joined row."""
    m = _OLD_TOKEN.search(" | ".join(cells))
    if not m:
        return "other"
    f = m.group(1)
    if (re.search(r"\s", f) or "://" in f or any(ch in f for ch in _COMMAND_CHARS)
            or f.startswith(_COMMAND_STARTS)):
        return "command-or-url-first"
    if "/" not in f and _BARE_METHOD.match(f):
        return "bare-method-first"
    return "other"


def triage_class(planfile, row: str, token: str, tracked: set[str]) -> str:
    """Exactly one class per newly planned token. Total function."""
    t = token.split("::", 1)[0]
    tok = r"`" + re.escape(token) + r"`\s*"
    if (re.search(tok + planfile._RUN_ONLY_MARKER, row, re.I)
            or re.search(tok + planfile._NO_CHANGE_MARKER, row, re.I)):
        return "marked"
    if any(ch in t for ch in ("*", "?", "[")):
        return "glob"
    base = t.rsplit("/", 1)[-1]
    is_tracked_dir = any(p.startswith(t.rstrip("/") + "/") for p in tracked)
    launcher = t.startswith("bin/") and not _TESTISH_BASENAME.search(base)
    if (t.endswith("/") or is_tracked_dir or base == "bootstrap.php" or t.endswith(".golden.html")
            or "/fixtures/" in t or launcher):
        return "phantom-prone"
    if t in tracked:
        return "tracked-file"
    return "absent"


def _tracked_files(repo_root: str) -> set[str]:
    try:
        out = subprocess.run(["git", "-C", repo_root, "ls-files"], capture_output=True,
                             text=True, check=True).stdout
    except (OSError, subprocess.CalledProcessError):
        return set()
    return set(out.splitlines())


def _corpus_paths(plans_dir: str, report: str | None) -> list[str]:
    # os.walk, not glob: glob never enters a dot-directory, which would leave the
    # `.drafts` skip dead code.
    paths = []
    for root, dirs, files in os.walk(plans_dir):
        dirs[:] = [d for d in dirs if d not in _SKIP_DIRS]
        for f in files:
            p = os.path.join(root, f)
            if f.endswith(".md") and os.path.abspath(p) != report:
                paths.append(p)
    return sorted(paths)


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--harness-root", required=True)
    ap.add_argument("--plans-dir", default=os.path.expanduser("~/claude-plans"))
    ap.add_argument("--repo-root", default="")
    ap.add_argument("--report", default="")
    args = ap.parse_args(argv)
    harness_root = os.path.abspath(args.harness_root)
    sys.path.insert(0, harness_root)
    from harness import planfile  # noqa: E402  (tree chosen by --harness-root)

    repo_root = args.repo_root or os.path.dirname(os.path.dirname(harness_root))
    tracked = _tracked_files(repo_root)
    # The report lives under the corpus by default; reading it back would feed its own rows in.
    report = os.path.abspath(os.path.expanduser(args.report)) if args.report else None
    paths = _corpus_paths(args.plans_dir, report)

    newly: list[tuple] = []
    lost: list[tuple] = []
    changed: list[tuple] = []
    dropped: list[tuple] = []
    for path in paths:
        with open(path, encoding="utf-8", errors="replace") as fh:
            content = fh.read()
        rel = os.path.relpath(path, args.plans_dir)
        new_tokens: set[str] = set()
        plan_changed: list[tuple] = []
        for line in planfile._strip_fenced(content):
            if not line.strip().startswith("|"):
                continue
            cells = _cells(line)
            old = old_row_token(planfile, cells)
            new = planfile._planned_token(cells)
            if new is not None:
                new_tokens.add(new)
            if old == new:
                continue
            if old is None:
                row = " | ".join(cells)
                newly.append((triage_class(planfile, row, new, tracked), skip_reason(cells),
                              rel, _excerpt(line)))
            elif new is None:
                lost.append((rel, old, _excerpt(line)))
            else:
                plan_changed.append((rel, old, new, _excerpt(line)))
        changed += plan_changed
        seen: set[str] = set()
        for r, old, new, _e in plan_changed:
            if old not in new_tokens and old not in seen:
                seen.add(old)
                dropped.append((r, old, new))

    classes = ("marked", "glob", "phantom-prone", "tracked-file", "absent")
    reasons = ("bare-method-first", "command-or-url-first", "other")
    cls_n = {c: sum(1 for n in newly if n[0] == c) for c in classes}
    rsn_n = {r: sum(1 for n in newly if n[1] == r) for r in reasons}
    sums_ok = sum(cls_n.values()) == len(newly) and sum(rsn_n.values()) == len(newly)

    out = [f"NEWLY-PLANNED {c} {r} {p} :: {e}" for c, r, p, e in newly]
    out += [f"LOST\t{r}\t{o}\t{e}" for r, o, e in lost]
    out += [f"CHANGED\t{r}\t{o}\t{n}\t{e}" for r, o, n, e in changed]
    out += [f"DROPPED {r} {o} -> {n}" for r, o, n in dropped]
    out.append(
        f"SUMMARY newly_planned={len(newly)} plans={len({n[2] for n in newly})} lost={len(lost)}"
        f" changed={len(changed)} dropped_from_plan={len(dropped)}"
        f" bare_method_first={rsn_n['bare-method-first']}"
        f" command_or_url_first={rsn_n['command-or-url-first']} other_reason={rsn_n['other']}"
        f" marked={cls_n['marked']} glob={cls_n['glob']} phantom_prone={cls_n['phantom-prone']}"
        f" tracked_file={cls_n['tracked-file']} absent={cls_n['absent']}")
    print("\n".join(out))
    if not sums_ok:
        print("ERROR class or reason counts do not sum to newly_planned", file=sys.stderr)

    if args.report:
        def esc(s: str) -> str:
            return s.replace("|", "\\|")
        rows = ["| plan | old token | new token |", "|---|---|---|"]
        rows += [f"| {esc(r)} | `{esc(o)}` | `{esc(n)}` |" for r, o, n in dropped]
        with open(args.report, "w", encoding="utf-8") as fh:
            fh.write("\n".join(out[-1:] + [""] + rows) + "\n")
    return 1 if lost or not sums_ok else 0


if __name__ == "__main__":
    sys.exit(main())
