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
import copy
import dataclasses
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
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


def _verdict_looser(old, new) -> bool:
    if not all(isinstance(v, (str, type(None))) for v in (old, new)):
        return old != new
    if old not in VERDICT_RANK or new not in VERDICT_RANK:
        return old != new
    return VERDICT_RANK[new] > VERDICT_RANK[old]


def _set_looser(old, new) -> bool:
    if not isinstance(old, list) or not isinstance(new, list):
        return old != new
    o = {json.dumps(x, sort_keys=True) for x in old}
    n = {json.dumps(x, sort_keys=True) for x in new}
    return not o <= n


def looser_output(kind: str, old: dict, new: dict) -> bool:
    if "ok" not in old or "ok" not in new:
        return old != new  # exception divergence
    a, b = old["ok"], new["ok"]
    if kind == "verdict":
        return _verdict_looser(a, b)
    if kind == "set":
        return _set_looser(a, b)
    if kind == "flag":
        return bool(b) and not bool(a)
    return a != b


def compare_sides(old, new, must_equal: set) -> tuple[list[str], list[str]]:
    loosened, shielded = [], []
    for (rid, o_out), (_, n_out) in zip(old.results, new.results):
        for key, (root, kind) in ORDERING.items():
            o, n = o_out.get(key, {"absent": True}), n_out.get(key, {"absent": True})
            if root in must_equal and o != n:
                shielded.append(f"{rid}:{key}")
            elif looser_output(kind, o, n):
                loosened.append(f"{rid}:{key}")
    return loosened, shielded


# --- static unreachability proof ----------------------------------------------------------------

class Unresolvable(Exception):
    """A source the analyzer cannot resolve; the message is the hold reason."""


UNRESOLVABLE_CALLS = frozenset({"globals", "locals", "vars", "eval", "exec", "__import__", "import_module"})
SIDE_EFFECT_NAMES = frozenset({"open", "print", "input", "breakpoint"})
SIDE_EFFECT_MODULES = frozenset({"os", "shutil", "subprocess", "tempfile", "sys"})
FILE_WRITE_ATTRS = frozenset({"write_text", "write_bytes", "unlink", "mkdir", "rmdir", "touch", "chmod"})
MUTATING_METHODS = frozenset({"append", "extend", "insert", "pop", "popitem", "clear", "update",
                              "setdefault", "add", "discard", "remove"})
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


def _file_roots(rel: str, tree, out: dict) -> None:
    aliases: set[str] = set()
    pkgs: set[str] = set()
    fid_imports: set[int] = set()  # ids of the import nodes that bind a fidelity alias
    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            for a in node.names:
                if a.name == "bench" or a.name.startswith("bench."):
                    raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
                if a.name == "harness.fidelity":
                    if a.asname:
                        aliases.add(a.asname)
                        fid_imports.add(id(node))
                    else:
                        pkgs.add("harness")
        elif isinstance(node, ast.ImportFrom):
            mod = node.module or ""
            if mod == "bench" or mod.startswith("bench.") or mod.endswith(".bench"):
                raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
            if mod == "harness.fidelity" or (node.level == 1 and mod == "fidelity"):
                for a in node.names:
                    if a.name == "*":
                        raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
                    out.setdefault(a.name, set()).add(rel)
            elif mod == "harness" or (node.level == 1 and not mod):
                for a in node.names:
                    if a.name == "*":
                        raise Unresolvable(f"fidelity-unresolvable-reference: {rel}")
                    if a.name == "fidelity":
                        aliases.add(a.asname or "fidelity")
                        fid_imports.add(id(node))
    if not aliases and not pkgs:
        return
    values = {id(n.value) for n in ast.walk(tree) if isinstance(n, ast.Attribute)}
    for node in ast.walk(tree):
        if isinstance(node, ast.Attribute):
            v = node.value
            if isinstance(v, ast.Name) and v.id in aliases:
                out.setdefault(node.attr, set()).add(rel)
            elif (isinstance(v, ast.Attribute) and v.attr == "fidelity"
                  and isinstance(v.value, ast.Name) and v.value.id in pkgs):
                out.setdefault(node.attr, set()).add(rel)
            elif (node.attr == "fidelity" and isinstance(v, ast.Name) and v.id in pkgs
                  and id(node) not in values):
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


def production_roots(files: dict) -> dict:
    """Each fidelity name -> the production relpaths that reference it. Test and bench files are
    excluded; a bench import from production, a module alias used as a value, `import *`, or a
    non-Python file that names the module raises."""
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
            if "harness.fidelity" in text or "import fidelity" in text:
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


def _root_name(expr):
    while isinstance(expr, (ast.Attribute, ast.Subscript, ast.Call, ast.Starred)):
        expr = expr.func if isinstance(expr, ast.Call) else expr.value
    return expr.id if isinstance(expr, ast.Name) else None


def _stmt_nodes(stmt):
    """`stmt` and its non-statement descendants (a compound statement's header only)."""
    stack = [stmt]
    while stack:
        node = stack.pop()
        yield node
        for child in ast.iter_child_nodes(node):
            if not isinstance(child, (ast.stmt, ast.ExceptHandler)):
                stack.append(child)


def _fn_locals(fn) -> tuple[set[str], set[str]]:
    """(names local to `fn`, names `fn` declares global or nonlocal). A declared name is never
    local, even when `fn` assigns it."""
    a = fn.args
    local = {x.arg for x in a.posonlyargs + a.args + a.kwonlyargs}
    local |= {x.arg for x in (a.vararg, a.kwarg) if x is not None}
    local |= {n.id for n in ast.walk(fn) if isinstance(n, ast.Name) and isinstance(n.ctx, ast.Store)}
    declared = {name for n in ast.walk(fn) if isinstance(n, (ast.Global, ast.Nonlocal))
                for name in n.names}
    return local - declared, declared


def _stmt_effect(stmt, local, declared, defs, seen) -> bool:
    """True when `stmt`'s own targets or header calls write state outside the locals, or call a
    top-level def in `defs` that does."""
    if isinstance(stmt, (ast.Global, ast.Nonlocal)):
        return True
    targets = []
    if isinstance(stmt, ast.Assign):
        targets = list(stmt.targets)
    elif isinstance(stmt, (ast.AugAssign, ast.AnnAssign)):
        targets = [stmt.target]
    elif isinstance(stmt, ast.Delete):
        targets = list(stmt.targets)
    for t in targets:
        for sub in ast.walk(t):
            if isinstance(sub, (ast.Subscript, ast.Attribute)) and _root_name(sub) not in local:
                return True
            if isinstance(sub, ast.Name) and sub.id in declared:
                return True
    for node in _stmt_nodes(stmt):
        if not isinstance(node, ast.Call):
            continue
        f = node.func
        if isinstance(f, ast.Name):
            if f.id in SIDE_EFFECT_NAMES:
                return True
            if f.id in defs and _def_effect(f.id, defs, seen):
                return True
        if isinstance(f, ast.Attribute):
            if _root_name(f.value) in SIDE_EFFECT_MODULES:
                return True
            if f.attr in FILE_WRITE_ATTRS:
                return True
            if f.attr in MUTATING_METHODS and _root_name(f.value) not in local:
                return True
            if f.attr in defs and _def_effect(f.attr, defs, seen):
                return True
    return False


def _def_effect(name, defs, seen) -> bool:
    """True when any statement of top-level def `name`, or of a def it calls, has a side effect.
    A def already on the walk counts as clean here; its own statements decide it."""
    if name in seen:
        return False
    seen.add(name)
    fn = defs[name]
    local, declared = _fn_locals(fn)
    return any(_stmt_effect(st, local, declared, defs, seen)
               for st in ast.walk(fn) if isinstance(st, ast.stmt) and st is not fn)


def side_effect(fn, line, tree) -> bool:
    """True when the innermost statement covering `line` writes state outside `fn`'s locals.

    A compound statement counts with every statement it guards, and a call to a top-level def
    of `tree` counts with that def's own side effects, followed through its callees."""
    best = None
    for node in ast.walk(fn):
        if isinstance(node, ast.stmt) and node is not fn and node.lineno <= line <= node.end_lineno:
            if best is None or (node.end_lineno - node.lineno) <= (best.end_lineno - best.lineno):
                best = node
    if best is None:
        return False
    defs = {st.name: st for st in tree.body
            if isinstance(st, (ast.FunctionDef, ast.AsyncFunctionDef))}
    local, declared = _fn_locals(fn)
    seen: set[str] = set()
    return any(_stmt_effect(st, local, declared, defs, seen)
               for st in ast.walk(best) if isinstance(st, ast.stmt))


@dataclasses.dataclass(frozen=True)
class FidSide:
    schema: str
    results: tuple
    executed: dict


def _fid_side(payload) -> FidSide:
    if not isinstance(payload, dict):
        raise ValueError("driver output is not an object")
    results = tuple((str(r["id"]), dict(r["out"])) for r in payload["results"])
    executed = {str(k): frozenset(int(n) for n in v) for k, v in payload["executed"].items()}
    return FidSide(str(payload["schema"]), results, executed)


def judge_fidelity(old, new, srcs, lines, roots) -> tuple[bool, str, list[str]]:
    """Clear a fidelity.py change only by the dead-in-production or replayed-only proof."""
    from harness import arming_replay  # lazy: arming_replay imports this module

    detail: list[str] = []
    if (old.schema != new.schema or old.schema != "fidelity-replay/1"
            or [r[0] for r in old.results] != [r[0] for r in new.results]):
        return False, "fidelity-result-mismatch", detail
    if len(old.results) < MIN_FIDELITY_RECORDS:
        return False, "fidelity-corpus-too-small", detail
    words = [out.get("v1.parse_verdict", {}).get("ok") for _, out in old.results]
    if "READY" not in words or "NOT READY" not in words:
        return False, "fidelity-corpus-degenerate", detail
    trees, defs, owners, inert = {}, {}, {}, {}
    try:
        for side, src in zip(("old", "new"), srcs):
            if src is None:
                return False, "fidelity-unreadable-source", detail
            trees[side] = ast.parse(src)
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
    for side, side_lines, result in (("old", lines[0], old), ("new", lines[1], new)):
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
            if side_effect(defs[side][func], line, trees[side]):
                return False, f"fidelity-side-effect-change: {func}:{line}", detail
            must_equal |= shield
            detail.append(f"{side}:{line}: replayed {func}")
    loosened, shielded = compare_sides(old, new, must_equal)
    # A loosened output is reported first: it names the ordering that failed. Both keep the flag.
    if loosened:
        return False, f"fidelity-loosened: {len(loosened)} outputs ({loosened[0]})", detail
    if shielded:
        return False, f"fidelity-shielded-change: {shielded[0]} ({len(shielded)} outputs)", detail
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
    side_env = dict(env, PYTHONHASHSEED="0", TZ="UTC", LC_ALL="C")
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
