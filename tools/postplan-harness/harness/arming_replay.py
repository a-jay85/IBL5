"""Replay old and new arming code over a fixed corpus to decide whether an arming edit loosens.

`merge_danger.derive_merge_danger` sets Self-governing on any edit to an arming path. For a
`.py` arming file under the harness, this module replays the base and candidate `evaluate`
over `arming_replay_corpus.json` in two subprocesses and clears the `arming-path` kind only
when no corpus decision got looser and every changed line either ran during the replay or is
a comment or blank line. Every other outcome keeps the flag (fail closed).

Three halves: the `ArmInputs` codec (`encode_inputs`; the decoder lives in the driver so each
side decodes with its own classes), the pure judge (`changed_lines`, `inert_lines`, `judge`),
and the IO runner (`replay_worktree`) with its per-process cache. This module never imports
`harness.merge_danger`, which imports it.
"""
from __future__ import annotations

import dataclasses
import hashlib
import io
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import tokenize
from pathlib import Path

# --- codec ------------------------------------------------------------------------------------

_TUPLE_FIELDS = ("exec_mutations_holding", "plan_hold_triggers", "plan_checks_holding")


def encode_inputs(inp) -> dict:
    """An `ArmInputs` as the JSON-ready `inputs` dict of a corpus record. `dep_state_lookup`
    becomes `dep_states` (one entry per `Depends-on:` number in the body, an exception stored
    as UNKNOWN) plus `dep_default`; dataclasses become `asdict` output; tuples become lists;
    `second_review_clears` keys become strings."""
    from harness.armable import dep_numbers

    out: dict = {}
    for f in dataclasses.fields(inp):
        value = getattr(inp, f.name)
        if f.name == "dep_state_lookup":
            states = {}
            for n in dep_numbers(inp.pr_body):
                try:
                    states[str(n)] = value(n)
                except Exception:  # noqa: BLE001 — a failing lookup is data, not a crash
                    states[str(n)] = "UNKNOWN"
            out["dep_states"] = states
            out["dep_default"] = "UNKNOWN"
            continue
        if f.name == "classification":
            value = dataclasses.asdict(value)
        elif f.name == "findings":
            value = [dataclasses.asdict(x) for x in value]
        elif f.name == "second_review_clears":
            value = {str(k): v for k, v in value.items()}
        elif isinstance(value, tuple):
            value = list(value)
        out[f.name] = value
    return out


# --- pure judge -------------------------------------------------------------------------------

MIN_RECORDS = 20


@dataclasses.dataclass(frozen=True)
class SideResult:
    fields: tuple[str, ...]
    results: tuple[tuple[str, bool, frozenset[int]], ...]
    executed: dict[str, frozenset[int]]


@dataclasses.dataclass(frozen=True)
class ReplayVerdict:
    cleared: bool
    note: str
    reason: str


def _kept(reason: str) -> ReplayVerdict:
    return ReplayVerdict(False, f"replay kept arming-path ({reason})", reason)


_HUNK_RE = re.compile(r"^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@")


def changed_lines(section: str) -> tuple[set[int], set[int]]:
    """(old line numbers removed, new line numbers added) for one file's diff section.

    Each hunk consumes exactly its header's old and new counts, so a removed line whose
    content starts with `-- ` (rendered `--- x`) counts as removed. A missing count means 1.
    A malformed or truncated hunk raises ValueError."""
    old: set[int] = set()
    new: set[int] = set()
    lines = section.splitlines()
    i = 0
    while i < len(lines):
        line = lines[i]
        i += 1
        if not line.startswith("@@"):
            continue
        m = _HUNK_RE.match(line)
        if not m:
            raise ValueError(f"malformed hunk header: {line!r}")
        old_no, new_no = int(m.group(1)), int(m.group(3))
        old_left = int(m.group(2) or 1)
        new_left = int(m.group(4) or 1)
        while old_left > 0 or new_left > 0:
            if i >= len(lines):
                raise ValueError("hunk ends before its header counts")
            body = lines[i]
            i += 1
            if body.startswith("\\"):
                continue
            if body.startswith("-") and old_left > 0:
                old.add(old_no)
                old_no += 1
                old_left -= 1
            elif body.startswith("+") and new_left > 0:
                new.add(new_no)
                new_no += 1
                new_left -= 1
            elif (body == "" or body.startswith(" ")) and old_left > 0 and new_left > 0:
                old_no += 1
                new_no += 1
                old_left -= 1
                new_left -= 1
            else:
                raise ValueError(f"hunk line does not fit its header counts: {body!r}")
    return old, new


_INERT_TOKENS = frozenset({tokenize.COMMENT, tokenize.NL, tokenize.NEWLINE, tokenize.INDENT,
                           tokenize.DEDENT, tokenize.ENDMARKER})


def inert_lines(source: str) -> set[int]:
    """Line numbers (3 and up) that hold only a comment or whitespace. A multi-line token covers
    every line from its start row to its end row. Lines 1 and 2 are never inert: a coding
    cookie there changes how the file decodes. Raises tokenize.TokenError or SyntaxError on
    source that does not tokenize."""
    code: set[int] = set()
    for tok in tokenize.generate_tokens(io.StringIO(source).readline):
        if tok.type not in _INERT_TOKENS:
            code.update(range(tok.start[0], tok.end[0] + 1))
    total = len(source.splitlines())
    return {n for n in range(3, total + 1) if n not in code}


def judge(old: SideResult, new: SideResult, sections: dict, sources: dict,
          min_records: int = MIN_RECORDS) -> ReplayVerdict:
    """Clear only when both sides decoded the same field set, the corpus is big and mixed
    enough, every changed line ran (or is inert) on the side where it exists, and no record
    lost a blocking condition or moved from held to armed."""
    if old.fields != new.fields:
        return _kept("inputs-shape-changed")
    if len(old.results) < min_records:
        return _kept("corpus-too-small")
    if [r[0] for r in old.results] != [r[0] for r in new.results]:
        return _kept("result-mismatch")
    if not any(r[1] for r in old.results) or all(r[1] for r in old.results):
        return _kept("corpus-degenerate")
    old_lines: dict[str, set[int]] = {}
    new_lines: dict[str, set[int]] = {}
    for path in sorted(sections):
        section = sections[path]
        if section is None:
            return _kept("unreadable-section")
        try:
            old_lines[path], new_lines[path] = changed_lines(section)
        except ValueError:
            return _kept("unreadable-section")
    for path in sorted(sections):
        pair = sources.get(path)
        if pair is None or pair[0] is None or pair[1] is None:
            return _kept("unreadable-source")
        try:
            inert_lines(pair[0])
            inert_lines(pair[1])
        except (tokenize.TokenError, SyntaxError, ValueError):
            return _kept("unreadable-source")

    for path in sorted(sections):
        for side, lines, source in ((old, old_lines[path], sources[path][0]),
                                    (new, new_lines[path], sources[path][1])):
            executed = side.executed.get(path, frozenset())
            if not executed:
                return _kept("unexercised-change")
            inert = inert_lines(source)
            for line in sorted(lines):
                if line not in inert and line not in executed:
                    return _kept("unexercised-change")
    loosened = tightened = to_armed = to_held = 0
    for (_, old_armed, old_blocked), (_, new_armed, new_blocked) in zip(old.results, new.results):
        if (old_blocked - new_blocked) or (new_armed and not old_armed):
            loosened += 1
        if (new_blocked - old_blocked) or (old_armed and not new_armed):
            tightened += 1
        to_armed += int(new_armed and not old_armed)
        to_held += int(old_armed and not new_armed)
    counts = (f"{len(old.results)} inputs: {loosened} loosened ({to_armed} held->armed), "
              f"{tightened} tightened ({to_held} armed->held)")
    if loosened:
        return _kept(counts)
    return ReplayVerdict(True, f"replay cleared arming-path ({counts})", "clean")


# --- IO runner --------------------------------------------------------------------------------

CORPUS_PATH = Path(__file__).with_name("arming_replay_corpus.json")
DRIVER_PATH = Path(__file__).with_name("arming_replay_driver.py")
HARNESS_DIR = "tools/postplan-harness/harness"
COPY_DIRS = (HARNESS_DIR, "bin/lib")
GIT_APPLY_TIMEOUT = 15
SIDE_TIMEOUT = 45
_CACHE: dict[str, ReplayVerdict] = {}

_STRIP_ENV = ("GIT_DIR", "GIT_WORK_TREE", "GIT_INDEX_FILE", "PYTHONPATH")


def _tree_files(root: Path, rel_dir: str) -> list[tuple[str, bytes]]:
    base = root / rel_dir
    out: list[tuple[str, bytes]] = []
    for dirpath, dirnames, filenames in os.walk(base):
        dirnames[:] = sorted(d for d in dirnames if d != "__pycache__")
        for name in sorted(filenames):
            p = Path(dirpath) / name
            if p.is_file():
                out.append((p.relative_to(root).as_posix(), p.read_bytes()))
    return out


def _cache_key(root: Path, diff_text: str, corpus: bytes) -> str:
    h = hashlib.sha256()
    for chunk in (diff_text.encode("utf-8", "surrogateescape"), corpus):
        h.update(len(chunk).to_bytes(8, "big"))
        h.update(chunk)
    files = sorted(f for d in COPY_DIRS for f in _tree_files(root, d))
    for rel, data in files:
        for chunk in (rel.encode("utf-8", "surrogateescape"), data):
            h.update(len(chunk).to_bytes(8, "big"))
            h.update(chunk)
    return h.hexdigest()


def _env(tmp: str) -> dict:
    env = {k: v for k, v in os.environ.items() if k not in _STRIP_ENV}
    env["GIT_CEILING_DIRECTORIES"] = tmp
    env["PYTHONDONTWRITEBYTECODE"] = "1"
    return env


def _side_result(payload) -> SideResult:
    if not isinstance(payload, dict):
        raise ValueError("driver output is not an object")
    results = tuple((str(r["id"]), bool(r["armed"]), frozenset(int(n) for n in r["blocked"]))
                    for r in payload["results"])
    executed = {str(k): frozenset(int(n) for n in v) for k, v in payload["executed"].items()}
    return SideResult(tuple(payload["fields"]), results, executed)


def _read_text(path: Path) -> str | None:
    try:
        return path.read_text(encoding="utf-8") if path.is_file() else None
    except (OSError, UnicodeDecodeError):
        return None


def _replay(root: Path, diff_text: str, sections: dict, arming: frozenset) -> ReplayVerdict:
    try:
        corpus = Path(CORPUS_PATH).read_bytes()
    except OSError:
        return _kept("corpus-missing")
    records = json.loads(corpus)
    if not isinstance(records, list):
        return _kept("error")
    if len(records) < MIN_RECORDS:
        return _kept("corpus-too-small")
    if not all((root / d).is_dir() for d in COPY_DIRS):
        return _kept("tree-missing")
    key = _cache_key(root, diff_text, corpus)
    if key in _CACHE:
        return _CACHE[key]
    verdict = _replay_uncached(root, diff_text, sections, arming)
    _CACHE[key] = verdict
    return verdict


def _replay_uncached(root: Path, diff_text: str, sections: dict,
                     arming: frozenset) -> ReplayVerdict:
    with tempfile.TemporaryDirectory(prefix="arming-replay-") as tmp:
        sides = {name: Path(tmp) / name for name in ("old", "new")}
        for side in sides.values():
            for d in COPY_DIRS:
                shutil.copytree(root / d, side / d,
                                ignore=shutil.ignore_patterns("__pycache__", "*.pyc"))
        env = _env(tmp)
        patch = Path(tmp) / "diff.patch"
        patch.write_text(diff_text, encoding="utf-8", errors="surrogateescape")
        try:
            applied = subprocess.run(
                ["git", "apply", "-R", "--include=tools/postplan-harness/harness/*",
                 "--include=bin/lib/*", str(patch)],
                cwd=str(sides["old"]), env=env, timeout=GIT_APPLY_TIMEOUT,
                stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        except subprocess.TimeoutExpired:
            return _kept("timeout")
        if applied.returncode != 0:
            return _kept("apply-failed")
        out: dict[str, SideResult] = {}
        for name in ("old", "new"):
            side = sides[name]
            pkg = side / "tools" / "postplan-harness"
            result_path = Path(tmp) / f"{name}.json"
            try:
                with open(result_path, "wb") as fh:
                    proc = subprocess.run(
                        [sys.executable, str(DRIVER_PATH), str(CORPUS_PATH), str(pkg), str(side)],
                        cwd=str(pkg), env=env, timeout=SIDE_TIMEOUT,
                        stdout=fh, stderr=subprocess.DEVNULL)
            except subprocess.TimeoutExpired:
                return _kept("timeout")
            if proc.returncode != 0:
                return _kept("error")
            try:
                out[name] = _side_result(json.loads(result_path.read_bytes()))
            except (ValueError, KeyError, TypeError):
                return _kept("error")
        old, new = out["old"], out["new"]
        kept_sections: dict = {}
        sources: dict = {}
        for path, section in sections.items():
            if path in arming or old.executed.get(path) or new.executed.get(path):
                kept_sections[path] = section
                sources[path] = (_read_text(sides["old"] / path), _read_text(sides["new"] / path))
        return judge(old, new, kept_sections, sources)


def replay_worktree(worktree, diff_text: str, sections: dict, arming) -> ReplayVerdict:
    """Replay the base and candidate `evaluate` over the corpus. `sections` maps every diffed
    `.py` path under HARNESS_DIR to its diff section (None when unreadable); `arming` is the
    set of arming-path trigger paths. Never raises: any failure keeps the flag."""
    try:
        return _replay(Path(worktree), diff_text, dict(sections), frozenset(arming))
    except Exception:  # noqa: BLE001 — the caller has no try/except; fail closed
        return _kept("error")
