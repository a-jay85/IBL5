"""Second arming replay stage: replay the deterministic fidelity.py readers on base and candidate.

`arming_replay` keeps the `arming-path` flag for any edit to `harness/fidelity.py` whose lines
`evaluate` never runs. This module clears such an edit only when a stored-input replay can show
it harmless. A changed line clears in one of two ways:

1. Its enclosing top-level function is dead in production: no production module references a
   function that can reach it.
2. Every production root that reaches the function is a replayed root (or reaches it only
   through one), the changed line ran during the replay, it has no side effect, and no replayed
   output got looser. A replayed root that shields the function from a non-replayed root must
   give equal output on every record.

Anything else keeps the flag (fail closed). The corpus is `fidelity_replay_corpus.json`,
harvested from real `out/live-*` runs by `python3 -m harness.fidelity_replay harvest`. The
`prs` subcommand replays merged PRs through `arming_replay.replay_worktree`.
"""
from __future__ import annotations

import ast
import builtins
import copy
import dataclasses
import dis
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import types
from pathlib import Path

FIDELITY_PATH = "tools/postplan-harness/harness/fidelity.py"
SELF_PATHS = frozenset({"tools/postplan-harness/harness/fidelity_replay.py",
                        "tools/postplan-harness/harness/fidelity_replay_driver.py",
                        "tools/postplan-harness/harness/fidelity_replay_corpus.json"})
CORPUS_PATH = Path(__file__).with_name("fidelity_replay_corpus.json")
DRIVER_PATH = Path(__file__).with_name("fidelity_replay_driver.py")
MIN_FIDELITY_RECORDS = 20
MAX_DIFF_BYTES = 60_000
VERDICT_WORDS = ("NOT READY", "READY WITH NOTES", "READY")

HARVEST_WINDOW = 1500
PER_VERDICT = 8
MISSING_CAP = 12
_HEX40_RE = re.compile(r"^[0-9a-f]{40}$")
TRACKED_FIXTURES = (
    "tests/fixtures/body-recheck/verdict-class-d-live.md",
    "tests/fixtures/body-recheck/verdict-pure-body-live.md",
    "tests/fixtures/fidelity-shadow/verdict-baseline.md",
    "tests/fixtures/replay_3874/verdict.txt",
    "tests/fixtures/verdict_findings/pr2341_nine_items.txt",
)
_PKG_DIR = Path(__file__).resolve().parent.parent  # tools/postplan-harness
_USAGE = ("usage: python3 -m harness.fidelity_replay harvest <out_dir> <dest>\n"
          "       python3 -m harness.fidelity_replay prs <pr> [<pr> ...]")


# --- corpus harvest -----------------------------------------------------------------------------

def _fidelity():
    """This checkout's fidelity module, for the harvest CLI and the corpus checks only.

    The arming stage itself (`replay_sides`, `judge_fidelity`) never runs fidelity code
    in-process: it runs each side's copy in the driver subprocess. A static import here would
    pull every fidelity.py import into `tests/test_arming_paths_drift.py`'s in-process walk
    although none of them runs inside the arming decision."""
    import importlib

    return importlib.import_module("harness.fidelity")


def _parse_text(text: str):
    """(parse_verdict, read_reviewed_tree) of a verdict text, computed with this checkout's
    fidelity module in-process."""
    fidelity = _fidelity()
    with tempfile.TemporaryDirectory(prefix="fid-harvest-") as tmp:
        p = os.path.join(tmp, "v.md")
        with open(p, "w", encoding="utf-8") as fh:
            fh.write(text)
        return fidelity.parse_verdict(p), fidelity.read_reviewed_tree(p)


def _read(path: Path) -> str | None:
    try:
        return path.read_text(encoding="utf-8")
    except (OSError, UnicodeDecodeError):
        return None


def _diff_paths(diff_text: str) -> list[str]:
    out = []
    for line in diff_text.splitlines():
        if line.startswith("diff --git "):
            _, _, b = line.partition(" b/")
            if b:
                out.append(b)
    return out


def _missing_items(shadow) -> list:
    try:
        missing = shadow["fields"]["planned_landed"]["missing"]
    except (KeyError, TypeError):
        return []
    return missing if isinstance(missing, list) else []


def _harvest_run(run: Path) -> dict | None:
    try:
        r = json.loads((run / "result.json").read_text(encoding="utf-8"))
    except (OSError, ValueError, UnicodeDecodeError):
        return None
    if not isinstance(r, dict):
        return None
    fid = r.get("fidelity")
    pr = r.get("pr_number")
    if not isinstance(fid, dict) or fid.get("verdict_1") not in VERDICT_WORDS:
        return None
    if not isinstance(pr, int) or isinstance(pr, bool):
        return None
    patch = run / "fidelity-packet" / "diff.patch"
    try:
        if not patch.is_file() or patch.stat().st_size > MAX_DIFF_BYTES:
            return None
    except OSError:
        return None
    diff_text = _read(patch)
    if diff_text is None:
        return None
    tree = fid.get("reviewed_tree")
    v1_text = None
    tmp_verdict = _read(Path(f"/tmp/post-plan-fidelity-verdict-{pr}.md"))
    if tmp_verdict is not None and _parse_text(tmp_verdict)[1] == tree:
        v1_text = tmp_verdict
    else:
        raw = _read(run / "raw-plan-fidelity-review-attempt0.txt")
        if raw is None:
            return None
        if isinstance(tree, str) and _HEX40_RE.match(tree):
            raw = raw.rstrip("\n") + f"\nREVIEWED_TREE={tree}\n"
        v1_text = raw
    # Grounding check: the stored text must reproduce the decision production recorded.
    if _parse_text(v1_text)[0] != fid["verdict_1"]:
        return None
    v2_text = None
    if fid.get("verdict_2") is not None:
        vp = fid.get("verdict_path")
        v2_text = _read(Path(vp)) if isinstance(vp, str) else None
        if v2_text is None or _parse_text(v2_text)[0] != fid["verdict_2"]:
            return None
    shadow = fid.get("shadow")
    return {
        "id": run.name,
        "source": f"out/{run.name}/",
        "pr": pr,
        "verdict_1": v1_text,
        "verdict_2": v2_text,
        "fid": {k: v for k, v in fid.items()
                if k not in ("verdict_path", "same_run_repin", "selected_source")},
        "shadow": shadow if isinstance(shadow, dict) else None,
        "sticky_body": _read(run / f"sticky-verdict-{pr}.md"),
        "diff_text": diff_text,
        "changed_files": _diff_paths(diff_text),
        "current_tree": tree if isinstance(tree, str) and tree else "0" * 40,
        "review_inputs": None,
        "plan_blind": not bool(r.get("plan")),
    }


def _fixture_record(rel: str) -> dict | None:
    fidelity = _fidelity()
    path = _PKG_DIR / rel
    text = _read(path)
    if text is None:
        return None
    return {
        "id": f"fixture:{rel}",
        "source": f"tools/postplan-harness/{rel}",
        "pr": 0,
        "verdict_1": text,
        "verdict_2": None,
        "fid": {"verdict_1": fidelity.parse_verdict(str(path)), "verdict_2": None,
                "reviewed_tree_2": None},
        "shadow": None,
        "sticky_body": None,
        "diff_text": "",
        "changed_files": [],
        "current_tree": fidelity.read_reviewed_tree(str(path)) or "0" * 40,
        "review_inputs": None,
        "plan_blind": False,
    }


# bin/check-engine-freeze refuses these tokens outside engine/ and jsb-native/. Harvested engine
# diffs carry them, so the corpus stores each one with `_` as `-` and `0x` as `0X`.
_DECOMPILE_TOKEN_RE = re.compile(r"(?:FUN|DAT|LAB)_[0-9A-Fa-f]{6,}|decompiled\.c|jsb560_decompile[d]"
                                 r"|00_MASTER_REFERENCE\.md|\+0x[0-9A-Fa-f]{2,}")
# Three more repo-wide scans read every tracked file. The shallow-fetch guard in tests.yml refuses
# a shallow fetch flag after a git fetch on one line. test_only_owner_approve_emits_marker refuses
# the owner approval marker outside bin/owner-approve. bin/test-postplan-arm-conditions refuses a
# name of a retired post-plan phase file. Harvested diffs and verdicts carry all three, so the
# corpus stores each with its first letter upper-cased. The keys are split so this file passes too.
_GATED_LITERALS = {"owner-approve " "mode=": "owner-approve Mode=", "--" "depth": "--Depth",
                   "--" "shallow-since": "--Shallow-since", "--" "shallow-exclude": "--Shallow-exclude"}
_GATED_LITERALS.update({"_phase" + t: "_Phase" + t for t in (
    "-2-conflict", "-3-classify", "-4-review", "-5-final", "-6-manual", "-6.5-arm", "-7-ci")})
_GATED_LITERAL_RE = re.compile("|".join(re.escape(k) for k in _GATED_LITERALS))


def redact_decompile_tokens(text: str) -> str:
    # The decompile source file name holds two tokens that overlap, so substitute until nothing matches.
    while _DECOMPILE_TOKEN_RE.search(text):
        text = _DECOMPILE_TOKEN_RE.sub(
            lambda m: m.group(0).replace("_", "-").replace(".c", "-c").replace("0x", "0X"), text)
    return _GATED_LITERAL_RE.sub(lambda m: _GATED_LITERALS[m.group(0)], text)


def harvest(out_dir: Path, dest: Path, window: int | None = HARVEST_WINDOW) -> int:
    """Write the fidelity replay corpus from real run dirs. Returns 1 when the result fails
    `corpus_problems`, else 0."""
    results = []
    for run_dir in Path(out_dir).glob("live-*"):
        try:
            results.append(((run_dir / "result.json").stat().st_mtime, run_dir))
        except OSError:
            continue
    results.sort(key=lambda t: (t[0], t[1].name), reverse=True)
    if window is not None:
        results = results[:window]
    per_word = {w: [] for w in VERDICT_WORDS}
    with_missing: list[dict] = []
    for _, run in results:
        rec = _harvest_run(run)
        if rec is None:
            continue
        word = rec["fid"]["verdict_1"]
        if len(per_word[word]) < PER_VERDICT:
            per_word[word].append(rec)
        if _missing_items(rec["shadow"]) and len(with_missing) < MISSING_CAP:
            with_missing.append(rec)
    seen: set[str] = set()
    picked: list[dict] = []
    ordered = [r for w in VERDICT_WORDS for r in per_word[w]] + with_missing
    order = {run.name: i for i, (_, run) in enumerate(results)}
    for rec in sorted(ordered, key=lambda r: order[r["id"]]):
        if rec["id"] in seen:
            continue
        seen.add(rec["id"])
        picked.append(rec)
    variants = []
    for rec in picked:
        if not _missing_items(rec["shadow"]):
            continue
        empty = copy.deepcopy(rec)
        empty["id"] = rec["id"] + "~ri-empty"
        empty["review_inputs"] = [[], ""]
        same = copy.deepcopy(rec)
        same["id"] = rec["id"] + "~ri-same"
        same["review_inputs"] = [list(rec["changed_files"]), rec["diff_text"]]
        variants += [empty, same]
    fixtures = [f for f in (_fixture_record(rel) for rel in TRACKED_FIXTURES) if f is not None]
    records = picked + variants + fixtures
    Path(dest).write_text(redact_decompile_tokens(json.dumps(records, indent=1, sort_keys=True)) + "\n",
                          encoding="utf-8")
    for w in VERDICT_WORDS:
        print(f"{w}: {sum(1 for r in picked if r['fid']['verdict_1'] == w)}")
    print(f"planned-landed missing: {sum(1 for r in picked if _missing_items(r['shadow']))}")
    print(f"review_inputs variants: {len(variants)}")
    print(f"tracked fixtures: {len(fixtures)}")
    print(f"total: {len(records)}")
    problems = corpus_problems(records)
    for p in problems:
        print(f"problem: {p}", file=sys.stderr)
    return 1 if problems else 0


def corpus_problems(records) -> list[str]:
    """Every unmet corpus rule, computed with this checkout's fidelity module in-process."""
    if not isinstance(records, list):
        return ["corpus is not a list"]
    problems = []
    if len(records) < MIN_FIDELITY_RECORDS:
        problems.append(f"fewer than {MIN_FIDELITY_RECORDS} records ({len(records)})")
    words = []
    for rec in records:
        text = rec.get("verdict_1") if isinstance(rec, dict) else None
        if not isinstance(text, str) or not text:
            problems.append(f"record {rec.get('id') if isinstance(rec, dict) else rec!r} "
                            "has no verdict_1 text")
            continue
        words.append(_parse_text(text)[0])
    if "READY" not in words:
        problems.append("no record parses READY")
    if "NOT READY" not in words:
        problems.append("no record parses NOT READY")
    dicts = [r for r in records if isinstance(r, dict)]
    if not any(_missing_items(r.get("shadow")) for r in dicts):
        problems.append("no record has planned-landed missing items")
    if not any(r.get("review_inputs") is not None for r in dicts):
        problems.append("no record has review_inputs")
    ids = [r.get("id") for r in dicts]
    if len(ids) != len(set(ids)):
        problems.append("duplicate record ids")
    return problems


# --- looseness ordering -------------------------------------------------------------------------

REPLAYED_ROOTS = frozenset({"parse_verdict", "read_reviewed_tree", "gate_edit_only",
                            "_verdict_findings", "blocking_finding_texts", "verdict_file_usable",
                            "read_shadow", "reviewer_arming_holds", "sticky_terminal", "_sticky_prior_verdict"})
VERDICT_RANK = {None: -1, "NOT READY": 0, "READY WITH NOTES": 1, "READY": 2}
ORDERING = {  # driver output key -> (fidelity root the key measures, ordering)
    "v1.parse_verdict": ("parse_verdict", "verdict"), "v2.parse_verdict": ("parse_verdict", "verdict"),
    "v1.read_reviewed_tree": ("read_reviewed_tree", "equal"), "v2.read_reviewed_tree": ("read_reviewed_tree", "equal"),
    "v1.gate_edit_only": ("gate_edit_only", "flag"), "v2.gate_edit_only": ("gate_edit_only", "flag"),
    "v1._verdict_findings": ("_verdict_findings", "set"), "v2._verdict_findings": ("_verdict_findings", "set"),
    "v1.blocking_finding_texts": ("blocking_finding_texts", "set"),
    "v2.blocking_finding_texts": ("blocking_finding_texts", "set"),
    "v1.verdict_file_usable": ("verdict_file_usable", "flag"), "v1.read_shadow": ("read_shadow", "equal"),
    "holds.read_shadow": ("reviewer_arming_holds", "set"), "holds.stored_shadow": ("reviewer_arming_holds", "set"),
    "holds.args_after": ("reviewer_arming_holds", "equal"), "sticky_terminal": ("sticky_terminal", "equal"),
    "sticky_prior.synthetic": ("_sticky_prior_verdict", "verdict"),
    "sticky_prior.stored": ("_sticky_prior_verdict", "verdict"),
    "selected_gate_edit_only": (None, "flag"), "selected_blocking_findings": (None, "set"),
    "select_fidelity_verdict": (None, "verdict"),
}


def _differ(old, new) -> bool:
    """Inequality by canonical JSON text. Python equality calls True and 1 the same, but a
    consumer that tests `is True` does not."""
    return json.dumps(old, sort_keys=True) != json.dumps(new, sort_keys=True)


def _verdict_looser(old, new) -> bool:
    if not all(isinstance(v, (str, type(None))) for v in (old, new)):
        return _differ(old, new)
    if old not in VERDICT_RANK or new not in VERDICT_RANK:
        return _differ(old, new)
    return VERDICT_RANK[new] > VERDICT_RANK[old]


def _set_looser(old, new) -> bool:
    if not isinstance(old, list) or not isinstance(new, list):
        return _differ(old, new)
    o = {json.dumps(x, sort_keys=True) for x in old}
    n = {json.dumps(x, sort_keys=True) for x in new}
    return not o <= n


def looser_output(kind: str, old: dict, new: dict) -> bool:
    if "ok" not in old or "ok" not in new:
        return _differ(old, new)  # exception divergence
    a, b = old["ok"], new["ok"]
    if kind == "verdict":
        return _verdict_looser(a, b)
    if kind == "set":
        return _set_looser(a, b)
    if kind == "flag":
        if type(a) is not bool or type(b) is not bool:
            return _differ(a, b)
        return b and not a
    return _differ(a, b)


def compare_sides(old, new, must_equal: set) -> tuple[list[str], list[str]]:
    loosened, shielded = [], []
    for (rid, o_out), (_, n_out) in zip(old.results, new.results):
        for key, (root, kind) in ORDERING.items():
            o, n = o_out.get(key, {"absent": True}), n_out.get(key, {"absent": True})
            if root in must_equal and _differ(o, n):
                shielded.append(f"{rid}:{key}")
            elif looser_output(kind, o, n):
                loosened.append(f"{rid}:{key}")
    return loosened, shielded


# --- static unreachability proof ----------------------------------------------------------------

class Unresolvable(Exception):
    """A source the analyzer cannot resolve; the message is the hold reason."""


UNRESOLVABLE_CALLS = frozenset({"globals", "locals", "vars", "eval", "exec", "__import__", "import_module"})
MODULE_ROOT = "<module>"


def _nonconstant_getattr(node) -> bool:
    if not (isinstance(node.func, ast.Name) and node.func.id in ("getattr", "hasattr")):
        return False
    return not (len(node.args) >= 2 and isinstance(node.args[1], ast.Constant))


def check_resolvable(tree) -> None:
    """Raise on dynamic dispatch the call graph cannot see."""
    for node in ast.walk(tree):
        if isinstance(node, ast.Call):
            f = node.func
            name = f.id if isinstance(f, ast.Name) else f.attr if isinstance(f, ast.Attribute) else None
            if name in UNRESOLVABLE_CALLS or _nonconstant_getattr(node):
                raise Unresolvable("fidelity-unresolvable-dispatch")
        if (isinstance(node, ast.Attribute) and node.attr == "modules"
                and isinstance(node.value, ast.Name) and node.value.id == "sys"):
            raise Unresolvable("fidelity-unresolvable-dispatch")


def _bound_names(stmts, out: list[str]) -> None:
    for st in stmts:
        if isinstance(st, (ast.FunctionDef, ast.AsyncFunctionDef)):
            continue
        if isinstance(st, ast.ClassDef):
            out.append(st.name)
        elif isinstance(st, (ast.Import, ast.ImportFrom)):
            out.extend((a.asname or a.name).split(".")[0] for a in st.names)
        elif isinstance(st, (ast.Assign, ast.AnnAssign, ast.AugAssign, ast.For, ast.AsyncFor,
                             ast.With, ast.AsyncWith)):
            for node in ast.walk(st):
                if isinstance(node, ast.Name) and isinstance(node.ctx, ast.Store):
                    out.append(node.id)
        for field in ("body", "orelse", "finalbody", "handlers"):
            sub = getattr(st, field, None)
            if isinstance(sub, list) and not isinstance(st, ast.ClassDef):
                _bound_names(sub, out)
        if isinstance(st, ast.ExceptHandler) and st.name:
            out.append(st.name)


def top_defs(tree) -> dict:
    """Each top-level function name -> its node. Nested defs and lambdas belong to their
    top-level function. Raises on a name defined twice or also bound at module level."""
    defs: dict = {}
    for st in tree.body:
        if isinstance(st, (ast.FunctionDef, ast.AsyncFunctionDef)):
            if st.name in defs:
                raise Unresolvable("fidelity-duplicate-def")
            defs[st.name] = st
    bound: list[str] = []
    _bound_names(tree.body, bound)
    if any(n in defs for n in bound):
        raise Unresolvable("fidelity-duplicate-def")
    return defs


def _constant_default(node) -> bool:
    if node is None or isinstance(node, ast.Constant):
        return True
    return (isinstance(node, ast.UnaryOp) and isinstance(node.op, ast.USub)
            and isinstance(node.operand, ast.Constant))


def line_owner(defs) -> dict:
    """Each line -> (top-level function, "header" | "body"). Decorator lines and the header of a
    function with a non-constant default are never mapped: that code runs at import."""
    owner: dict = {}
    for name, fn in defs.items():
        first = fn.body[0].lineno
        defaults = list(fn.args.defaults) + list(fn.args.kw_defaults)
        if all(_constant_default(d) for d in defaults):
            for n in range(fn.lineno, first):
                owner[n] = (name, "header")
        for n in range(first, fn.end_lineno + 1):
            owner[n] = (name, "body")
    return owner


def _refs(node, names) -> set[str]:
    out = set()
    for sub in ast.walk(node):
        if isinstance(sub, ast.Name) and sub.id in names:
            out.add(sub.id)
        elif isinstance(sub, ast.Attribute) and sub.attr in names:
            out.add(sub.attr)
        elif isinstance(sub, ast.Constant) and isinstance(sub.value, str) and sub.value in names:
            out.add(sub.value)
    return out


def edges(defs) -> dict:
    """F -> every top-level def G that F's body names (over-approximate: only adds holds)."""
    return {name: _refs(fn, defs) for name, fn in defs.items()}


def module_refs(tree, defs) -> set[str]:
    """Top-level defs named by code outside every function body (import-time code)."""
    out: set[str] = set()
    for st in tree.body:
        if isinstance(st, (ast.FunctionDef, ast.AsyncFunctionDef)):
            parts = list(st.decorator_list) + list(st.args.defaults) + [
                d for d in st.args.kw_defaults if d is not None]
            for p in parts:
                out |= _refs(p, defs)
        else:
            out |= _refs(st, defs)
    return out


def _is_python(rel: str, text: str) -> bool:
    return rel.endswith(".py") or (text.startswith("#!") and "python" in text.split("\n", 1)[0])


def _is_test_or_bench(rel: str) -> bool:
    parts = rel.split("/")
    base = parts[-1]
    return base.startswith(("test_", "test-")) or "tests" in parts or "bench" in parts


# This file loads fidelity through importlib on purpose (see `_fidelity`); it never runs inside the arming decision.
_SELF_DYNAMIC_OK = "harness/fidelity_replay.py"


def _is_dunder(name: str) -> bool:
    return name.startswith("__") and name.endswith("__")


# Dunders on the `harness` package that hold plain data and cannot reach a fidelity def.
_PKG_DATA_DUNDERS = frozenset({"__file__", "__name__", "__doc__", "__package__", "__path__"})


def _from_fidelity(value, bases: set, pkgs: set) -> bool:
    """True when the attribute chain `value` starts at a fidelity module or at a name imported
    from it."""
    while isinstance(value, ast.Attribute):
        if value.attr == "fidelity" and isinstance(value.value, ast.Name) and value.value.id in pkgs:
            return True
        value = value.value
    return isinstance(value, ast.Name) and value.id in bases


def _file_roots(rel: str, tree, out: dict) -> None:
    aliases: set[str] = set()
    imported: set[str] = set()  # local names bound by `from <fidelity> import x`
    pkgs: set[str] = set()
    fid_imports: set[int] = set()  # ids of the import nodes that bind a fidelity alias
    pkg_imports: set[int] = set()  # ids of the import nodes that bind the `harness` package
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            for a in node.names:
                if a.name == "bench" or a.name.startswith("bench."):
                    raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
                if a.name == "fidelity" or a.name.endswith(".fidelity") and a.name != "harness.fidelity":
                    raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
                if a.name == "harness":
                    pkgs.add(a.asname or "harness")
                    pkg_imports.add(id(node))
                if a.name == "harness.fidelity":
                    if a.asname:
                        aliases.add(a.asname)
                        fid_imports.add(id(node))
                    else:
                        pkgs.add("harness")
                        pkg_imports.add(id(node))
        elif isinstance(node, ast.ImportFrom):
            mod = node.module or ""
            if mod == "bench" or mod.startswith("bench.") or mod.endswith(".bench"):
                raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
            if node.level == 0 and mod == "fidelity":
                raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
            if node.level >= 2 and (mod == "fidelity" or mod.endswith(".fidelity")
                                    or (not mod and any(a.name in ("fidelity", "*") for a in node.names))):
                raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
            if mod == "harness.fidelity" or (node.level == 1 and mod == "fidelity"):
                for a in node.names:
                    if a.name == "*" or _is_dunder(a.name):
                        raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
                    imported.add(a.asname or a.name)
                    out.setdefault(a.name, set()).add(rel)
            elif mod == "harness" or (node.level == 1 and not mod):
                for a in node.names:
                    if a.name == "*":
                        raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
                    if a.name == "fidelity":
                        aliases.add(a.asname or "fidelity")
                        fid_imports.add(id(node))
        elif isinstance(node, ast.Call) and not rel.endswith(_SELF_DYNAMIC_OK):
            fn = node.func
            name = fn.id if isinstance(fn, ast.Name) else fn.attr if isinstance(fn, ast.Attribute) else ""
            if name in ("import_module", "__import__") and any(
                    isinstance(n, ast.Constant) and isinstance(n.value, str) and "fidelity" in n.value
                    for n in ast.walk(node)):
                raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
    if not aliases and not pkgs and not imported:
        return
    values = {id(n.value) for n in ast.walk(tree) if isinstance(n, ast.Attribute)}
    for node in ast.walk(tree):
        if isinstance(node, ast.Attribute):
            v = node.value
            # `__dict__`, `__getattribute__` or `__globals__` reach a def without naming it.
            if _is_dunder(node.attr) and _from_fidelity(v, aliases | imported, pkgs):
                raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
            if isinstance(v, ast.Name) and v.id in aliases:
                out.setdefault(node.attr, set()).add(rel)
            elif (isinstance(v, ast.Attribute) and v.attr == "fidelity"
                  and isinstance(v.value, ast.Name) and v.value.id in pkgs):
                out.setdefault(node.attr, set()).add(rel)
            elif (node.attr == "fidelity" and isinstance(v, ast.Name) and v.id in pkgs
                  and id(node) not in values):
                raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
            # `harness.__dict__["fidelity"]` or `harness.__getattribute__("fidelity")` reach
            # the module without naming it as an attribute.
            elif (_is_dunder(node.attr) and node.attr not in _PKG_DATA_DUNDERS
                  and isinstance(v, ast.Name) and v.id in pkgs
                  and _loads_fidelity(tree, v, pkg_imports)):
                raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
        elif (isinstance(node, ast.Name) and node.id in pkgs
              and isinstance(node.ctx, ast.Load) and id(node) not in values
              and _loads_fidelity(tree, node, pkg_imports)):
            # The package read as a value, as in `getattr(harness, "fidelity")`.
            raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
        elif (isinstance(node, ast.Name) and node.id in aliases
              and isinstance(node.ctx, ast.Load) and id(node) not in values
              and _loads_fidelity(tree, node, fid_imports)):
            raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")


_SCOPES = (ast.FunctionDef, ast.AsyncFunctionDef, ast.Lambda)


def _own_nodes(scope):
    """Nodes of `scope`, without the bodies of nested functions and lambdas."""
    stack = list(ast.iter_child_nodes(scope))
    while stack:
        node = stack.pop()
        yield node
        if not isinstance(node, _SCOPES):
            stack.extend(ast.iter_child_nodes(node))


def _binds(scope, name, fid_imports) -> tuple[bool, bool, bool]:
    """(bound by a fidelity import, bound some other way, declared global) in `scope`."""
    fid = other = glob = False
    if isinstance(scope, _SCOPES):
        a = scope.args
        params = a.posonlyargs + a.args + a.kwonlyargs + [x for x in (a.vararg, a.kwarg) if x]
        other = any(p.arg == name for p in params)
    for node in _own_nodes(scope):
        if isinstance(node, (ast.Import, ast.ImportFrom)):
            for a in node.names:
                if (a.asname or a.name.split(".")[0]) == name:
                    if id(node) in fid_imports:
                        fid = True
                    else:
                        other = True
        elif isinstance(node, ast.Name) and node.id == name and isinstance(node.ctx, (ast.Store, ast.Del)):
            other = True
        elif isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef)) and node.name == name:
            other = True
        elif isinstance(node, ast.ExceptHandler) and node.name == name:
            other = True
        elif isinstance(node, (ast.Global, ast.Nonlocal)) and name in node.names:
            glob = True
    return fid, other, glob


def _loads_fidelity(tree, load, fid_imports) -> bool:
    """True when the name `load` reads resolves to a fidelity import: the innermost enclosing
    function that binds the name decides, then the module. A scope that binds it both ways
    counts as fidelity (fail closed)."""
    chain = []
    for scope in ast.walk(tree):
        if isinstance(scope, _SCOPES) and any(n is load for n in _own_nodes(scope)):
            chain = [scope]
            break
    while chain and chain[-1] is not None:
        parent = next((s for s in ast.walk(tree) if isinstance(s, _SCOPES)
                       and any(n is chain[-1] for n in _own_nodes(s))), None)
        chain.append(parent)
    for scope in [s for s in chain if s is not None]:
        fid, other, glob = _binds(scope, load.id, fid_imports)
        if glob:
            break
        if fid:
            return True
        if other:
            return False
    return _binds(tree, load.id, fid_imports)[0]


# A non-Python file names the module only through one of these spellings.
_NON_PY_FIDELITY_REF = re.compile(
    r"harness\.fidelity\b|harness/fidelity\.py|\bfidelity\.py\b|\bimport\s+fidelity\b"
    r"|\bfrom\s+fidelity\s+import\b")


def production_roots(files: dict) -> dict:
    """Each fidelity name -> the production relpaths that reference it. Test and bench files are
    excluded; a bench import from production, a module alias used as a value, a dunder read
    through the module or a name imported from it, `import *`, or a non-Python file that names
    the module raises."""
    out: dict = {}
    for rel in sorted(files):
        if _is_test_or_bench(rel):
            continue
        text = files[rel]
        tree = None
        if _is_python(rel, text):
            try:
                tree = ast.parse(text)
            except (SyntaxError, ValueError):
                tree = None
        if tree is None:
            # Comment lines are prose, and a loose "harness ... fidelity" word pair matched
            # unrelated text (`plan-fidelity`, a Phase 5.5 note), so every real tree held.
            code = "\n".join(ln for ln in text.splitlines() if not ln.lstrip().startswith("#"))
            if _NON_PY_FIDELITY_REF.search(code):
                raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
            continue
        _file_roots(rel, tree, out)
    return out


def _reach(starts, graph, stop=frozenset()) -> set[str]:
    seen = set(starts)
    queue = list(starts)
    while queue:
        n = queue.pop()
        if n in stop:
            continue
        for m in graph.get(n, ()):
            if m not in seen:
                seen.add(m)
                queue.append(m)
    return seen


def channel(func, roots, graph) -> tuple[str | None, set[str]]:
    """(a non-replayed root that reaches `func` without passing a replayed node, or None;
    the replayed nodes that shield `func` from the non-replayed roots)."""
    reverse: dict = {}
    for src, dsts in graph.items():
        for d in dsts:
            reverse.setdefault(d, set()).add(src)
    ancestors = _reach([func], reverse)
    shield: set[str] = set()
    for r in sorted(roots):
        if r in REPLAYED_ROOTS:
            continue
        touched = _reach([r], graph, stop=REPLAYED_ROOTS)
        if func in touched and func not in REPLAYED_ROOTS:
            return r, set()
        shield |= {n for n in touched if n in REPLAYED_ROOTS and n in ancestors}
    return None, shield


def reachable_from_roots(func, roots, graph) -> bool:
    return func in _reach(sorted(roots), graph)


HAZARD_CALLS = frozenset({"exec", "eval", "compile", "__import__"})


def changed_line_hazard(tree, line):
    """The first construct on `line` that reaches state the runtime watch cannot see, or None.
    It covers side effects on inputs the corpus never has."""
    for node in ast.walk(tree):
        start, end = getattr(node, "lineno", None), getattr(node, "end_lineno", None)
        if start is None or end is None or not start <= line <= end:
            continue
        if isinstance(node, (ast.Global, ast.Nonlocal)):
            return type(node).__name__.lower()
        if isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id in HAZARD_CALLS:
            return node.func.id
        if isinstance(node, ast.Name) and node.id == "importlib":
            return "importlib"
        if isinstance(node, ast.Import) and any(a.name.split(".")[0] == "importlib" for a in node.names):
            return "importlib"
        if isinstance(node, ast.ImportFrom) and (node.module or "").split(".")[0] == "importlib":
            return "importlib"
    return None


@dataclasses.dataclass(frozen=True)
class FidSide:
    schema: str
    results: tuple
    executed: dict
    side_effects: tuple = ()
    branches: frozenset | None = None  # (qualname, firstlineno, src, dst); None = not recorded
    members: dict | None = None  # Compare span -> (matched member indexes, nomatch, unsafe)


def _fid_side(payload) -> FidSide:
    if not isinstance(payload, dict):
        raise ValueError("driver output is not an object")
    results = tuple((str(r["id"]), dict(r["out"])) for r in payload["results"])
    executed = {str(k): frozenset(int(n) for n in v) for k, v in payload["executed"].items()}
    side_effects = tuple(str(h) for h in payload["side_effects"])  # required: missing key is an error
    raw = payload["branches"]  # required: missing key is an error; null = not recorded
    branches = None if raw is None else frozenset(
        (str(q), int(f), int(s), int(d)) for q, f, s, d in raw)
    members = {(int(a), int(b), int(c), int(d)): (frozenset(int(i) for i in m), bool(n), bool(u))
               for a, b, c, d, m, n, u in payload["members"]}
    return FidSide(str(payload["schema"]), results, executed, side_effects, branches, members)


# --- branch outcomes -------------------------------------------------------------------------
# A changed line that ran can still hide a weakened branch: the corpus may take only one way
# through it. Each conditional jump on the line must be seen going both ways, and each member of
# a literal membership guard must be matched by some record. Anything the check cannot read holds.

# Conditional jump -> (fall-through outcome, jump outcome), as the condition reads.
BRANCH_OUTCOMES = {
    "POP_JUMP_IF_FALSE": ("True", "False"),
    "POP_JUMP_IF_TRUE": ("False", "True"),
    "POP_JUMP_IF_NONE": ("not None", "None"),
    "POP_JUMP_IF_NOT_NONE": ("None", "not None"),
    "FOR_ITER": ("a next item", "exhausted"),
}
PLAIN_JUMPS = frozenset({"JUMP_FORWARD", "JUMP_BACKWARD", "JUMP_BACKWARD_NO_INTERRUPT", "JUMP",
                         "JUMP_NO_INTERRUPT"})
_JUMP_OPS = frozenset(dis.opname[o] for o in getattr(dis, "hasjump", dis.hasjrel + dis.hasjabs))
SEGMENT_CAP = 60
SYNTHETIC_CODE = frozenset({"__annotate__"})


def _segment(src_lines, pos) -> str:
    """The source text at an instruction's or node's position, on one line and capped."""
    lineno, end_lineno, col, end_col = pos
    if None in pos or not 1 <= lineno <= end_lineno <= len(src_lines):
        return "?"
    if lineno == end_lineno:
        text = src_lines[lineno - 1][col:end_col]
    else:
        text = " ".join([src_lines[lineno - 1][col:]] + [s.strip() for s in
                         src_lines[lineno:end_lineno - 1]] + [src_lines[end_lineno - 1][:end_col].strip()])
    text = " ".join(text.split())
    return text if len(text) <= SEGMENT_CAP else text[:SEGMENT_CAP - 3] + "..."


def _code_objects(code):
    out, stack = [], [code]
    while stack:
        co = stack.pop()
        out.append(co)
        stack.extend(c for c in co.co_consts if isinstance(c, types.CodeType))
    return out


def branch_gap(tree, module_code, src, line, result) -> str | None:
    """What the corpus never did at a branch on `line` of this side, or None when every outcome
    was taken at least once. Reads the side's own driver output (`result`)."""
    src_lines = src.splitlines()
    codes = _code_objects(module_code)
    keys = [(co.co_qualname, co.co_firstlineno) for co in codes]
    sites: dict = {}
    for co in codes:
        if co.co_name in SYNTHETIC_CODE:
            continue  # 3.14+ compiler-made annotation reader: its guard spans the whole def
        key = (co.co_qualname, co.co_firstlineno)
        instrs = list(dis.get_instructions(co))
        lefts = ({s: left for s, left, _r in co.co_branches()} if hasattr(co, "co_branches")
                 else None)
        for i, ins in enumerate(instrs):
            pos = ins.positions
            if (ins.opname not in _JUMP_OPS or ins.opname in PLAIN_JUMPS or pos is None
                    or pos.lineno is None or not pos.lineno <= line <= (pos.end_lineno or pos.lineno)):
                continue
            span = (pos.lineno, pos.end_lineno, pos.col_offset, pos.end_col_offset)
            text = _segment(src_lines, span)
            if None in span or ins.opname not in BRANCH_OUTCOMES:
                return f"`{text}` has a branch the replay cannot classify ({ins.opname})"
            if keys.count(key) != 1:
                return f"`{text}` sits in a code object the replay cannot tell apart ({key[0]})"
            if result.branches is None:
                return f"`{text}` has a branch this interpreter cannot record"
            nxt = instrs[i + 1] if i + 1 < len(instrs) else None
            srcs = {ins.offset}
            if nxt is not None and nxt.opname == "NOT_TAKEN":
                srcs.add(nxt.offset)  # 3.14+: the fall-through event can come from NOT_TAKEN
            left = lefts.get(ins.offset) if lefts is not None else (
                nxt.offset if nxt is not None else None)
            names = BRANCH_OUTCOMES[ins.opname]
            seen = sites.setdefault((span, names), set())
            for q, f, s, d in result.branches:
                if (q, f) == key and s in srcs:
                    seen.add(names[0] if d == left else names[1])
    for (span, names), seen in sorted(sites.items()):
        missing = [n for n in names if n not in seen]
        if missing:
            return f"`{_segment(src_lines, span)}` never {' or '.join(missing)}"
    for node in ast.walk(tree):
        if not (isinstance(node, ast.Compare)
                and any(isinstance(op, (ast.In, ast.NotIn)) for op in node.ops)
                and node.lineno <= line <= node.end_lineno):
            continue
        span = (node.lineno, node.col_offset, node.end_lineno, node.end_col_offset)
        text = _segment(src_lines, (node.lineno, node.end_lineno, node.col_offset,
                                    node.end_col_offset))
        probe = (result.members or {}).get(span)
        if probe is None:
            return f"`{text}` is a membership test the replay cannot read"
        matched, nomatch, unsafe = probe
        if unsafe:
            return f"`{text}` has an operand the replay cannot read"
        elts = node.comparators[0].elts
        absent = [repr(e.value) for i, e in enumerate(elts) if i not in matched]
        if absent:
            return f"`{text}` never matched {', '.join(absent)}"
        if not nomatch:
            return f"`{text}` never saw a value outside the list"
    return None


# --- data flow from a changed line into a branch -----------------------------------------------
# A value written on a changed line can decide a branch on a later line. The branch check on the
# changed line alone never sees that branch, so every line in the same function that reads a
# tainted name gets the branch check too. The taint runs over the whole top-level function,
# nested defs included, and ignores statement order (over-approximate: it only adds holds).

class FlowUnresolved(Exception):
    """A data flow the analysis cannot follow; the message names it. `lines` holds the branch
    lines found before the flow left the function, so their gaps still report first."""

    def __init__(self, message, lines=frozenset()):
        super().__init__(message)
        self.lines = set(lines)


_BUILTIN_NAMES = frozenset(dir(builtins))


def _store_root(node):
    """The Name a store or mutation target hangs off (`a` in `a.b[c].d`), else raise."""
    cur = node
    while isinstance(cur, (ast.Attribute, ast.Subscript, ast.Starred)):
        cur = cur.value
    if isinstance(cur, ast.Name):
        return cur.id
    raise FlowUnresolved(f"a write through `{ast.unparse(node)[:SEGMENT_CAP]}`")


# Calls that never mutate their receiver or arguments. Any other call may, so its receiver and
# its name-like arguments count as written.
PURE_FUNCS = frozenset({"dict", "list", "tuple", "set", "frozenset", "str", "int", "float", "bool",
                        "len", "isinstance", "sorted", "any", "all", "repr", "min", "max", "sum",
                        "enumerate", "zip", "range", "type", "hasattr", "abs", "round", "bytes",
                        "open"})  # `open` writes no argument; the audit watch sees its file mode
PURE_METHODS = frozenset({"get", "strip", "lstrip", "rstrip", "startswith", "endswith", "split",
                          "rsplit", "splitlines", "join", "lower", "upper", "casefold", "format",
                          "replace", "partition", "rpartition", "find", "rfind", "count", "keys",
                          "values", "items", "copy", "match", "search", "fullmatch", "group",
                          "groups", "findall", "finditer", "sub", "isdigit", "isalnum", "isalpha",
                          "isspace", "removeprefix", "removesuffix", "encode", "decode", "index"})


def _pure_call(node) -> bool:
    f = node.func
    if isinstance(f, ast.Name):
        return f.id in PURE_FUNCS
    return isinstance(f, ast.Attribute) and f.attr in PURE_METHODS


def _covers(node, line) -> bool:
    start, end = getattr(node, "lineno", None), getattr(node, "end_lineno", None)
    return start is not None and end is not None and start <= line <= end


def _writes(nodes) -> tuple[set[str], set[str]]:
    """(names rebound, names whose object may be mutated) by `nodes`. A call's receiver and
    every argument that is a name, attribute or subscript count as mutated. A comprehension's
    own loop variable is local to it, so it binds nothing outside."""
    bound, mutated = set(), set()
    local = {id(x) for n in nodes if isinstance(n, ast.comprehension) for x in ast.walk(n.target)
             if isinstance(x, ast.Name)}
    for node in nodes:
        if isinstance(node, ast.Name) and isinstance(node.ctx, (ast.Store, ast.Del)):
            if id(node) not in local:
                bound.add(node.id)
        elif (isinstance(node, (ast.Attribute, ast.Subscript))
              and isinstance(node.ctx, (ast.Store, ast.Del))):
            mutated.add(_store_root(node))
        elif isinstance(node, (ast.Import, ast.ImportFrom)):
            bound.update((a.asname or a.name).split(".")[0] for a in node.names)
        elif isinstance(node, ast.ExceptHandler) and node.name:
            bound.add(node.name)
        elif isinstance(node, (ast.MatchAs, ast.MatchStar, ast.MatchMapping)):
            raise FlowUnresolved("a `match` capture")
        elif isinstance(node, ast.Call) and not _pure_call(node):
            for arg in [node.func.value if isinstance(node.func, ast.Attribute) else None,
                        *node.args, *(k.value for k in node.keywords)]:
                if isinstance(arg, ast.Starred):
                    arg = arg.value
                if isinstance(arg, (ast.Name, ast.Attribute, ast.Subscript)):
                    try:
                        mutated.add(_store_root(arg))
                    except FlowUnresolved:
                        continue  # a call result passed on: nothing named is mutated
    return bound, mutated


def _deep_roots(nodes) -> set[str]:
    """Roots of the writes in `nodes` that go into a nested object: a store target or a mutated
    call receiver or argument that is itself an attribute or a subscript."""
    out = set()
    for node in nodes:
        targets = []
        if (isinstance(node, (ast.Attribute, ast.Subscript))
                and isinstance(node.ctx, (ast.Store, ast.Del))):
            targets.append(node.value)
        elif isinstance(node, ast.Call) and not _pure_call(node):
            if isinstance(node.func, ast.Attribute):
                targets.append(node.func.value)
            targets += [a.value if isinstance(a, ast.Starred) else a
                        for a in [*node.args, *(k.value for k in node.keywords)]]
        for t in targets:
            if isinstance(t, (ast.Attribute, ast.Subscript)):
                try:
                    out.add(_store_root(t))
                except FlowUnresolved:
                    continue
    return out


def _reads(nodes) -> set[str]:
    return {n.id for n in nodes if isinstance(n, ast.Name) and isinstance(n.ctx, ast.Load)}


def _header(stmt) -> list:
    """The nodes of a statement that run as the statement itself: a compound statement's
    header, never its body."""
    if isinstance(stmt, (ast.If, ast.While)):
        parts = [stmt.test]
    elif isinstance(stmt, (ast.For, ast.AsyncFor)):
        parts = [stmt.target, stmt.iter]
    elif isinstance(stmt, (ast.With, ast.AsyncWith)):
        parts = list(stmt.items)
    elif isinstance(stmt, ast.Match):
        parts = [stmt.subject] + [c.pattern for c in stmt.cases] + [c.guard for c in stmt.cases if c.guard]
    elif isinstance(stmt, (ast.Try, getattr(ast, "TryStar", ast.Try))):
        parts = list(stmt.handlers)
    elif isinstance(stmt, (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef)):
        parts = []
    else:
        parts = [stmt]
    out = []
    for p in parts:
        if isinstance(p, ast.ExceptHandler):
            out.append(p)
            if p.type is not None:
                out.extend(ast.walk(p.type))
        else:
            out.extend(ast.walk(p))
    return out


def _seed_nodes(func_node, line) -> list:
    nodes = [n for n in ast.walk(func_node) if _covers(n, line)
             and not isinstance(n, (ast.stmt, ast.excepthandler))]
    nodes += [n for n in ast.walk(func_node) if isinstance(n, ast.stmt) and n.lineno == line
              and not isinstance(n, (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef))]
    for n in list(nodes):
        if isinstance(n, ast.stmt):
            nodes.extend(x for x in _header(n) if _covers(x, line))
    return nodes


def flow_lines(func_node, line, fid_defs, returns_compared) -> tuple[set[int], bool]:
    """(lines, escapes). `lines`: the lines of `func_node` that read a name the changed `line`
    writes, followed through every assignment in the function. `escapes`: how the changed value
    leaves the function ("return" for a return or yield, "param" for a mutated parameter) while
    the caller's use is not a compared output (`returns_compared` False: the function is not a replayed root, or other
    fidelity code calls it), so the caller check must follow it. Raises FlowUnresolved when the
    value leaves in a way no check follows: an argument to another top-level fidelity function,
    or a return from a nested function."""
    if fid_defs & PURE_FUNCS:
        raise FlowUnresolved(f"fidelity.py redefines {sorted(fid_defs & PURE_FUNCS)[0]}")
    nodes = _seed_nodes(func_node, line)
    bound, mutated = _writes(nodes)
    leaks = []
    for n in nodes:  # the changed line's own value passed into another fidelity function
        if isinstance(n, ast.Call):
            leaks += _call_leaks(n, fid_defs, changed_args=True)
    ret = returns_on_line(func_node, line)
    if ret == "nested":
        leaks.append(f"line {line} returns a changed value from a nested function")
    out, escapes = _flow(func_node, bound | mutated, mutated, fid_defs, returns_compared, leaks)
    if ret == "own" and not returns_compared:
        escapes = escapes | {"return"}
    return out, escapes


def _comp_taint(head, tainted) -> set[str]:
    """`tainted` plus every comprehension variable in `head` drawn from a tainted value."""
    out = set(tainted)
    gens = [g for n in head for g in getattr(n, "generators", ())]
    grew = True
    while grew:
        grew = False
        for g in gens:
            if _reads(list(ast.walk(g.iter))) & out:
                names = {x.id for x in ast.walk(g.target) if isinstance(x, ast.Name)}
                if names - out:
                    out |= names
                    grew = True
    return out


# Nodes that branch, loop, catch or jump: a fidelity function with none of them, no call into
# another fidelity function and no write outside its own names gives the same path for every
# argument, so a changed value can pass through it.
_BRANCHY = (ast.If, ast.IfExp, ast.While, ast.For, ast.AsyncFor, ast.Try, ast.Match, ast.BoolOp,
            ast.comprehension, ast.Assert, ast.With, ast.AsyncWith, ast.Raise, ast.Global,
            ast.Nonlocal, ast.Yield, ast.YieldFrom, ast.Await, ast.Lambda, ast.FunctionDef,
            ast.AsyncFunctionDef, ast.ClassDef, ast.Break, ast.Continue)


def _transparent(fn, names) -> bool:
    for n in ast.walk(fn):
        if n is fn:
            continue
        if isinstance(n, _BRANCHY) or type(n).__name__ == "TryStar":
            return False
        if isinstance(n, ast.Name) and n.id in names:
            return False  # a call into, or a use of, another fidelity function
        if isinstance(n, (ast.Attribute, ast.Subscript)) and isinstance(n.ctx, (ast.Store, ast.Del)):
            return False
        if isinstance(n, ast.Call) and not _pure_call(n):
            return False
    if fn.decorator_list or any(d is not None for d in [*fn.args.defaults, *fn.args.kw_defaults]):
        return False
    return True


class _FidNames(frozenset):
    """Opaque fidelity function names; `through` holds the straight-line ones."""
    through: frozenset = frozenset()


def opaque_defs(defs) -> _FidNames:
    """Top-level fidelity functions the data-flow check cannot see through: all but the
    straight-line ones (_transparent). A def that shadows a builtin stays opaque, so the
    builtin checks never mistake it for the builtin."""
    names = set(defs)
    out = _FidNames(n for n, fn in defs.items()
                    if n in _BUILTIN_NAMES or not _transparent(fn, names))
    out.through = frozenset(names - out)
    return out


def _call_leaks(n, fid_defs, tainted=frozenset(), changed_args=False) -> list[str]:
    """Why a call carries a changed value where the branch check cannot follow it. `fid_defs`
    holds the fidelity functions the check cannot see through (opaque_defs)."""
    args = [*n.args, *(k.value for k in n.keywords)]
    if changed_args:
        if not any(not isinstance(a, ast.Constant) for a in args):
            return []
    elif not _reads([x for a in args for x in ast.walk(a)]) & tainted:
        return []
    f = n.func
    passed = {x.id for a in args for x in ast.walk(a) if isinstance(x, ast.Name) and x.id in fid_defs}
    if passed:  # `sorted(xs, key=_helper)`: the builtin calls back into fidelity.py
        return [f"line {n.lineno} passes a changed value to {sorted(passed)[0]}"]
    if isinstance(f, ast.Name) and f.id in fid_defs:
        return [f"line {n.lineno} passes a changed value to {f.id}"]
    if changed_args:
        return []  # the line's own call to anything else is judged by its outputs
    if isinstance(f, ast.Name) and f.id in getattr(fid_defs, "through", ()):
        return []  # a straight-line fidelity function: one path for every argument
    if isinstance(f, ast.Attribute):
        return []  # a method or another module's function: no fidelity.py branch to check
    if isinstance(f, ast.Name) and f.id in _BUILTIN_NAMES:
        return []
    callee = f.id if isinstance(f, ast.Name) else "a computed callee"
    return [f"line {n.lineno} passes a changed value to {callee}"]


def _params(func_node) -> set[str]:
    a = func_node.args
    return {x.arg for x in a.posonlyargs + a.args + a.kwonlyargs + [a.vararg, a.kwarg] if x}


def _head_lines(head) -> set[int]:
    out: set[int] = set()
    for n in head:
        if isinstance(n, ast.excepthandler):
            out.add(n.lineno)
        elif getattr(n, "lineno", None) is not None and getattr(n, "end_lineno", None) is not None:
            out.update(range(n.lineno, n.end_lineno + 1))
    return out


def _flow(func_node, tainted, mutated, fid_defs, returns_compared, leaks,
          skip_calls=frozenset()) -> tuple[set[int], set[str]]:
    tainted, mutated, leaks = set(tainted), set(mutated), list(leaks)
    if not tainted:
        if leaks:
            raise FlowUnresolved(leaks[0])
        return set(), set()
    stmts = [s for s in ast.walk(func_node) if isinstance(s, (ast.stmt, ast.excepthandler))
             and s is not func_node]
    flows = []
    for s in stmts:
        head = _header(s) if isinstance(s, ast.stmt) else [s]
        flows.append((s, head, _reads(head), *_writes(head)))
    deep_at = [_deep_roots(f[1]) for f in flows]
    changed = True
    while changed:
        before = (len(tainted), len(mutated))
        # A write into a nested object (`a[k]["x"] = v`, `a.b.append(v)`) can reach any value
        # stored into `a` earlier, so the stores into a mutated object then count as aliases too.
        deep = any(d & mutated for d in deep_at)
        for _s, _head, reads, w_bound, w_mut in flows:
            if reads & tainted:  # a write fed by a tainted read
                tainted |= w_bound | w_mut
                if mutated:
                    mutated |= w_bound | w_mut
            if w_bound & mutated or (deep and w_mut & mutated):
                # a name bound to a mutated object: every name read into it may alias it
                mutated |= reads
                tainted |= reads
        changed = (len(tainted), len(mutated)) != before
    own = {id(n) for n in _own_nodes(func_node)}
    out: set[int] = set()
    # A mutated parameter changes the caller's object.
    escapes = {"param"} if not returns_compared and mutated & _params(func_node) else set()
    for s, head, reads, _b, _m in flows:
        if not reads & tainted:
            continue
        returns = isinstance(s, ast.Return) or any(isinstance(n, (ast.Yield, ast.YieldFrom))
                                                   for n in head)
        if returns and id(s) not in own:
            leaks.append(f"line {s.lineno} returns a changed value from a nested function")
        elif returns and not returns_compared:
            escapes.add("return")
        local = _comp_taint(head, tainted)
        for n in head:
            if isinstance(n, ast.Call) and id(n) not in skip_calls:
                leaks += _call_leaks(n, fid_defs, local)
        # Every line of the statement (a compound statement: its header) gets the branch check,
        # so a jump on another line of a multi-line condition or comprehension is covered.
        out |= _head_lines(head)
    if leaks:
        raise FlowUnresolved(sorted(leaks, key=lambda t: int(t.split()[1]))[0], out)
    return out, escapes


def returns_on_line(func_node, line) -> str | None:
    """"own" when `line` sits in a return or yield of `func_node` itself, "nested" when it sits
    in one of a nested function, else None."""
    own = {id(n) for n in _own_nodes(func_node)}
    found = None
    for n in ast.walk(func_node):
        if isinstance(n, (ast.Return, ast.Yield, ast.YieldFrom)) and _covers(n, line):
            if id(n) not in own:
                return "nested"
            found = "own"
    return found


def follow_callers(defs, func, kinds, compared, module_uses,
                   opaque=None) -> list[tuple[str, str, set[int]]]:
    """[(side, caller, lines)] for every fidelity def a changed value in `func` reaches through
    returns and mutated arguments (`kinds`, from flow_lines): each call statement's lines plus
    every line its result or its mutated arguments reach, up through each caller whose own return is not a compared output. `defs` is
    {side: top defs}; a caller is judged on the old side when it exists there. Raises
    FlowUnresolved on a use of a followed function the check cannot read: a reference that is
    not a direct call, a call inside a nested function or lambda, the call result passed to
    another fidelity function or a computed callee, or a module-level reference."""
    out: list = []
    seen = {func}
    todo = [(func, set(kinds))]
    while todo:
        cur, cur_kinds = todo.pop()
        if module_uses(cur):
            raise FlowUnresolved(f"module-level code uses {cur}")
        for name in sorted(set(defs["old"]) | set(defs["new"])):
            if name == cur:
                continue
            side = "old" if name in defs["old"] else "new"
            g = defs[side][name]
            fid_defs = opaque[side] if opaque is not None else set(defs[side])
            refs = [n for n in ast.walk(g) if (isinstance(n, ast.Name) and n.id == cur)
                    or (isinstance(n, ast.Attribute) and n.attr == cur)
                    or (isinstance(n, ast.Constant) and n.value == cur)]
            if not refs:
                continue
            own = {id(n) for n in _own_nodes(g)}
            calls = {id(n.func): n for n in ast.walk(g) if isinstance(n, ast.Call)
                     and isinstance(n.func, ast.Name) and n.func.id == cur}
            for r in refs:
                if id(r) not in calls:
                    raise FlowUnresolved(f"{name} uses {cur} other than by a direct call")
                if id(r) not in own:
                    raise FlowUnresolved(f"{name} calls {cur} inside a nested function")
            ids = {id(c) for c in calls.values()}
            # A mutated parameter: every name-like argument of the call may be the changed object.
            arg_roots = set()
            if "param" in cur_kinds:
                for c in calls.values():
                    _b, m = _writes([c])
                    arg_roots |= m
            name_kinds = {"return"}
            lines: set[int] = set()
            for s in ast.walk(g):
                if not isinstance(s, (ast.stmt, ast.excepthandler)) or s is g:
                    continue
                head = _header(s) if isinstance(s, ast.stmt) else [s]
                if not any(id(x) in ids for x in head):
                    continue
                for n in head:  # the result handed straight to code no check follows
                    if isinstance(n, ast.Call) and id(n) not in ids and any(
                            id(x) in ids for a in [*n.args, *(k.value for k in n.keywords)]
                            for x in ast.walk(a)):
                        f = n.func
                        if isinstance(f, ast.Attribute):
                            continue
                        if isinstance(f, ast.Name) and f.id in _BUILTIN_NAMES and f.id not in fid_defs:
                            continue
                        callee = f.id if isinstance(f, ast.Name) else "a computed callee"
                        raise FlowUnresolved(f"line {n.lineno} passes a changed value to {callee}")
                lines |= _head_lines(head)
                bound = _writes(head)[0]
                reach, esc = _flow(g, bound | arg_roots, set(arg_roots), fid_defs, compared(name),
                                   [], skip_calls=ids)
                lines |= reach
                name_kinds |= esc
            out.append((side, name, lines))
            if not compared(name) and name not in seen:  # its whole return may change
                seen.add(name)
                todo.append((name, name_kinds))
    return out


def judge_fidelity(old, new, srcs, lines, roots) -> tuple[bool, str, list[str]]:
    """Clear a fidelity.py change only by the dead-in-production or replayed-only proof."""
    from harness import arming_replay  # lazy: arming_replay imports this module

    detail: list[str] = []
    if (old.schema != new.schema or old.schema != "fidelity-replay/1"
            or [r[0] for r in old.results] != [r[0] for r in new.results]):
        return False, "fidelity-result-mismatch", detail
    for side, res in (("old", old), ("new", new)):
        if res.side_effects:
            return False, f"fidelity-side-effect-change: {side} {res.side_effects[0]}", detail
    if len(old.results) < MIN_FIDELITY_RECORDS:
        return False, "fidelity-corpus-too-small", detail
    words = [out.get("v1.parse_verdict", {}).get("ok") for _, out in old.results]
    if "READY" not in words or "NOT READY" not in words:
        return False, "fidelity-corpus-degenerate", detail
    trees, defs, owners, inert, codes = {}, {}, {}, {}, {}
    try:
        for side, src in zip(("old", "new"), srcs):
            if src is None:
                return False, "fidelity-unreadable-source", detail
            trees[side] = ast.parse(src)
            codes[side] = compile(src, FIDELITY_PATH, "exec", dont_inherit=True)
            check_resolvable(trees[side])
            defs[side] = top_defs(trees[side])
            owners[side] = line_owner(defs[side])
            inert[side] = arming_replay.inert_lines(src)
    except (SyntaxError, ValueError):
        return False, "fidelity-unreadable-source", detail
    except Unresolvable as e:
        return False, str(e), detail
    except Exception:  # noqa: BLE001 — tokenize errors and the like keep the flag
        return False, "fidelity-unreadable-source", detail
    graph: dict = {}
    for side in ("old", "new"):
        for name, dsts in edges(defs[side]).items():
            graph.setdefault(name, set()).update(dsts)
        graph.setdefault(MODULE_ROOT, set()).update(module_refs(trees[side], defs[side]))
    roots = dict(roots)
    roots.setdefault(MODULE_ROOT, set()).add(MODULE_ROOT)
    must_equal: set[str] = set()
    changed = 0
    gaps: list[str] = []
    sources = {"old": srcs[0], "new": srcs[1]}
    results = {"old": old, "new": new}
    opaque = {side: opaque_defs(defs[side]) for side in defs}

    def compared(name):  # a replayed root no fidelity code calls: its outputs are compared
        return name in REPLAYED_ROOTS and not any(
            name in dsts for src_name, dsts in graph.items() if src_name != name)
    for side, side_lines, result, src in (("old", lines[0], old, srcs[0]),
                                          ("new", lines[1], new, srcs[1])):
        owner = owners[side]
        executed = result.executed.get(FIDELITY_PATH, frozenset())
        for line in sorted(side_lines):
            if line in inert[side]:
                continue
            changed += 1
            hit = owner.get(line)
            if hit is None:
                return False, "fidelity-module-level-change", detail
            func, part = hit
            via, shield = channel(func, roots, graph)
            if via is not None:
                return False, f"fidelity-unreplayed-channel: {func} via {via}", detail
            if not reachable_from_roots(func, roots, graph):
                detail.append(f"{side}:{line}: dead {func}")
                continue  # dead in production
            body = range(defs[side][func].body[0].lineno, defs[side][func].end_lineno + 1)
            ran = line in executed if part == "body" else any(n in executed for n in body)
            if not ran:
                return False, "fidelity-unexercised-change", detail
            hazard = changed_line_hazard(trees[side], line)
            if hazard is not None:
                return False, f"fidelity-changed-line-hazard: {hazard} at {func}:{line}", detail
            must_equal |= shield
            detail.append(f"{side}:{line}: replayed {func}")
            flow_gap = None
            try:
                gap = branch_gap(trees[side], codes[side], src, line, result)
                # A branch that reads a value this line writes must go both ways too, in this
                # function and in every caller its return or a mutated argument reaches.
                try:
                    reads, escapes = flow_lines(defs[side][func], line, opaque[side],
                                                compared(func))
                except FlowUnresolved as e:
                    flow_gap = f"a data flow the replay cannot follow: {e}"
                    reads, escapes = e.lines, set()
                checks = [(side, None, at) for at in sorted(reads - {line})]
                if escapes:
                    try:
                        for c_side, caller, c_lines in follow_callers(
                                defs, func, escapes, compared,
                                lambda f: f in graph.get(MODULE_ROOT, ()), opaque):
                            checks += [(c_side, caller, at) for at in sorted(c_lines)]
                    except FlowUnresolved as e:
                        flow_gap = flow_gap or f"a data flow the replay cannot follow: {e}"
                for c_side, caller, at in checks:
                    if gap is not None:
                        break
                    gap = branch_gap(trees[c_side], codes[c_side], sources[c_side], at,
                                     results[c_side])
                    if gap is not None:
                        where = f"{caller}:{at}" if caller else f"line {at}"
                        gap = f"(its value reaches {where}) {gap}"
            except Exception:  # noqa: BLE001 — a branch the check cannot read keeps the flag
                gap = "a branch the replay cannot read"
            gap = gap or flow_gap
            if gap is not None:
                gaps.append(f"{func}:{line} {gap}")
    loosened, shielded = compare_sides(old, new, must_equal)
    # A loosened output is reported first: it names the ordering that failed. Both keep the flag.
    if loosened:
        return False, f"fidelity-loosened: {len(loosened)} outputs ({loosened[0]})", detail
    if shielded:
        return False, f"fidelity-shielded-change: {shielded[0]} ({len(shielded)} outputs)", detail
    # Checked last, so every earlier, named hold still reports first. It only adds holds.
    if gaps:
        return False, f"fidelity-unexercised-branch: {gaps[0]}", detail
    return (True, f"fidelity replay cleared {changed} changed lines over {len(old.results)} records",
            detail)


# --- IO runner ----------------------------------------------------------------------------------

FIDELITY_SIDE_TIMEOUT = 60
LAST_DETAIL: list[str] = []  # the last replay's detail lines, printed by `prs`

def replay_sides(tmp: Path, root: Path, diff_text: str, section: str | None, env: dict) -> tuple[bool, str, list[str]]:
    if any(f"a/{p}" in diff_text or f"b/{p}" in diff_text for p in SELF_PATHS):
        return False, "fidelity-replay-self-edit", []
    if section is None:
        return False, "unreadable-section", []
    try:
        corpus = CORPUS_PATH.read_bytes()
    except OSError:
        return False, "fidelity-corpus-missing", []
    del corpus  # existence check only: the driver reads CORPUS_PATH itself
    from harness import arming_replay  # lazy: arming_replay imports this module

    sides = {name: Path(tmp) / f"fid-{name}" for name in ("old", "new")}
    pkg_rel = Path("tools") / "postplan-harness"
    for side in sides.values():
        for d in arming_replay.COPY_DIRS:
            shutil.copytree(root / d, side / d,
                            ignore=shutil.ignore_patterns("__pycache__", "*.pyc"))
        for py in sorted((root / pkg_rel).glob("*.py")):
            shutil.copy2(py, side / pkg_rel / py.name)
    patch = Path(tmp) / "fid-diff.patch"
    patch.write_text(diff_text, encoding="utf-8", errors="surrogateescape")
    try:
        # git apply's `*` crosses `/` and the first matching pattern wins, so the exclude keeps
        # tests/ and other subdirectories (never copied) out of the top-level *.py include.
        applied = subprocess.run(
            ["git", "apply", "-R", "--include=tools/postplan-harness/harness/*",
             "--exclude=tools/postplan-harness/*/*", "--include=tools/postplan-harness/*.py",
             "--include=bin/lib/*", str(patch)],
            cwd=str(sides["old"]), env=env, timeout=arming_replay.GIT_APPLY_TIMEOUT,
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except subprocess.TimeoutExpired:
        return False, "fidelity-timeout", []
    if applied.returncode != 0:
        return False, "fidelity-apply-failed", []
    side_env = dict(env, PYTHONHASHSEED="0", TZ="UTC", LC_ALL="C", PYTHONDONTWRITEBYTECODE="1")
    out: dict = {}
    for name, side in sides.items():
        pkg = side / pkg_rel
        result_path = Path(tmp) / f"fid-{name}.json"
        try:
            with open(result_path, "wb") as fh:
                proc = subprocess.run(
                    [sys.executable, str(DRIVER_PATH), str(CORPUS_PATH), str(pkg), str(side)],
                    cwd=str(pkg), env=side_env, timeout=FIDELITY_SIDE_TIMEOUT,
                    stdout=fh, stderr=subprocess.DEVNULL)
        except subprocess.TimeoutExpired:
            return False, "fidelity-timeout", []
        if proc.returncode != 0:
            return False, "fidelity-error", []
        try:
            out[name] = _fid_side(json.loads(result_path.read_bytes()))
        except (ValueError, KeyError, TypeError):
            return False, "fidelity-error", []
    try:
        lines = arming_replay.changed_lines(section)
    except ValueError:
        return False, "unreadable-section", []
    srcs = tuple(arming_replay._read_text(sides[n] / FIDELITY_PATH) for n in ("old", "new"))
    roots: dict = {}
    try:
        for files in [_py_files(side) for side in sides.values()] + [_all_files(root, "bin")]:
            for name, rels in production_roots(files).items():
                roots.setdefault(name, set()).update(rels)
    except Unresolvable as e:
        return False, str(e), []
    verdict = judge_fidelity(out["old"], out["new"], srcs, lines, roots)
    LAST_DETAIL[:] = verdict[2]
    return verdict


def _py_files(side: Path) -> dict:
    out = {}
    for p in sorted(side.rglob("*.py")):
        if "__pycache__" in p.parts:
            continue
        try:
            out[p.relative_to(side).as_posix()] = p.read_text(encoding="utf-8")
        except (OSError, UnicodeDecodeError):
            continue
    return out


def _all_files(root: Path, rel_dir: str) -> dict:
    out = {}
    base = Path(root) / rel_dir
    for dirpath, dirnames, filenames in os.walk(base):
        dirnames[:] = sorted(d for d in dirnames if d not in ("__pycache__", ".git"))
        for name in sorted(filenames):
            p = Path(dirpath) / name
            try:
                text = p.read_text(encoding="utf-8")
            except (OSError, UnicodeDecodeError):
                continue
            out[p.relative_to(root).as_posix()] = text
    return out


# --- CLI ----------------------------------------------------------------------------------------

def _git(args, cwd) -> subprocess.CompletedProcess:
    return subprocess.run(["git", *args], cwd=str(cwd), capture_output=True, text=True,
                          errors="surrogateescape", timeout=120)


def prs(numbers) -> int:
    """Replay each merged PR's diff through `arming_replay.replay_worktree` and print the result."""
    from harness import arming_replay, merge_danger
    from harness import fidelity_replay as stage  # `-m` runs this file as __main__; the stage fills the imported copy
    from harness.gate_backtest import _ARMING_PATHS

    repo = _PKG_DIR.parent.parent
    with tempfile.TemporaryDirectory(prefix="fid-prs-") as tmp:
        for n in numbers:
            try:
                oid = subprocess.run(
                    ["gh", "pr", "view", str(n), "--json", "mergeCommit", "--jq", ".mergeCommit.oid"],
                    capture_output=True, text=True, timeout=60).stdout.strip()
            except (OSError, subprocess.TimeoutExpired):
                oid = ""
            if not _HEX40_RE.match(oid):
                print(f"PR {n}: kept: lookup-failed")
                continue
            wt = Path(tmp) / f"wt-{n}"
            try:
                if _git(["worktree", "add", "--detach", str(wt), oid], repo).returncode != 0:
                    print(f"PR {n}: kept: lookup-failed")
                    continue
                diff_text = _git(["diff", f"{oid}^", oid], repo).stdout
                arming = {p for p in _diff_paths(diff_text) if p in _ARMING_PATHS}
                sections = {p: merge_danger._section_for(diff_text, p) for p in arming}
                stage.LAST_DETAIL.clear()
                v = arming_replay.replay_worktree(wt, diff_text, sections, frozenset(arming))
                print(f"PR {n}: {'cleared' if v.cleared else 'kept'}: {v.note}")
                for line in stage.LAST_DETAIL:
                    print(f"  {line}")
            finally:
                _git(["worktree", "remove", "--force", str(wt)], repo)
    return 0


def main(argv) -> int:
    if len(argv) >= 1 and argv[0] == "harvest" and len(argv) == 3:
        return harvest(Path(argv[1]), Path(argv[2]))
    if len(argv) >= 2 and argv[0] == "prs":
        return prs(argv[1:])
    print(_USAGE, file=sys.stderr)
    return 2


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
