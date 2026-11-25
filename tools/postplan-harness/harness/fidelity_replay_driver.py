"""Run one side's arming-facing fidelity readers over the fidelity replay corpus under a tracer.

Invoked by harness/fidelity_replay.py as
`python3 fidelity_replay_driver.py <corpus> <side>/tools/postplan-harness <side>`. The parent
always runs the main-checkout copy of this file; the side only supplies `harness.fidelity`,
`harness.second_review` and `harness.armable`. This script never imports
`harness.fidelity_replay`, because the side's copy would be the candidate's.

Exit 4 (before any output) when a record has no verdict-1 text: two empty results are never
compared. Each output is `{"ok": value}`, `{"raise": name}` or `{"absent": true}`.
"""
import __future__
import sys
if __name__ == "__main__" and len(sys.argv) == 4:
    sys.path[0] = sys.argv[2]      # side package root; must precede every other import
    sys.dont_write_bytecode = True  # a side import must not write __pycache__ (a write-mode open)
import atexit
import contextlib
import copy
import dataclasses
import gc
import inspect
import json
import os
import shutil
import tempfile
import threading
import types

OUTPUT_KEYS = (
    "v1.parse_verdict", "v2.parse_verdict", "v1.read_reviewed_tree", "v2.read_reviewed_tree",
    "v1.gate_edit_only", "v2.gate_edit_only", "v1._verdict_findings", "v2._verdict_findings",
    "v1.blocking_finding_texts", "v2.blocking_finding_texts", "v1.verdict_file_usable",
    "v1.read_shadow", "holds.read_shadow", "holds.stored_shadow", "holds.args_after",
    "sticky_terminal", "sticky_prior.synthetic", "sticky_prior.stored",
    "selected_gate_edit_only", "selected_blocking_findings", "select_fidelity_verdict",
)
ABSENT = {"absent": True}

# --- runtime side-effect watch (ADR bypass class 17) --------------------------------------
DENY_PREFIXES = ("os.", "shutil.", "subprocess.", "socket.", "tempfile.", "ctypes.", "sqlite3.",
                 "http.", "urllib.", "smtplib.", "ftplib.", "poplib.", "imaplib.", "nntplib.",
                 "telnetlib.", "fcntl.", "mmap.", "resource.", "signal.", "syslog.",
                 "webbrowser.", "winreg.", "_winapi.", "msvcrt.", "builtins.input",
                 "builtins.breakpoint", "_thread.")  # `_thread.` raises on 3.13+ only; see _call
READ_ONLY_EVENTS = frozenset({"os.listdir", "os.scandir", "os.walk", "os.fwalk", "os.getxattr",
                              "os.listxattr", "os.listdrives", "os.listmounts", "os.listvolumes"})
CALL_ONLY_EVENTS = frozenset({"object.__setattr__", "object.__delattr__", "sys.settrace",
                              "sys.setprofile", "sys.addaudithook",
                              "sys.monitoring.register_callback"})
_WRITE_FLAGS = os.O_WRONLY | os.O_RDWR | os.O_APPEND | os.O_CREAT | os.O_TRUNC
_WATCH = {"phase": None, "record": "-", "where": "import", "hits": []}
MAX_HITS = 50
_UNCOPYABLE = object()
# Every built-in raise, named one by one: the harness bans a bare BaseException handler
# (tests/test_usage_pause_swallow_sites.py), and this tuple still lets its UsagePause through.
_ANY_RAISE = (Exception, KeyboardInterrupt, SystemExit, GeneratorExit)


def _hit(kind):
    if len(_WATCH["hits"]) < MAX_HITS:
        _WATCH["hits"].append(f"{_WATCH['record']} {_WATCH['where']} {kind}")


def _write_open(args):
    """True when an `open` audit event's (path, mode, flags) can write. Unknown shapes write."""
    try:
        _path, mode, flags = args
    except (TypeError, ValueError):
        return True
    if isinstance(mode, str):
        return any(c in mode for c in "wax+") or (isinstance(flags, int) and bool(flags & _WRITE_FLAGS))
    if mode is None and isinstance(flags, int):
        return bool(flags & _WRITE_FLAGS)
    return True


def _denied(event, phase):
    if event in CALL_ONLY_EVENTS:
        return phase == "call"
    return event.startswith(DENY_PREFIXES) and event not in READ_ONLY_EVENTS


def _audit(event, args):
    phase = _WATCH["phase"]
    try:
        if phase is None:
            # The watch is off only inside `_driver_io`, the driver's own temp-file work, where
            # gc is paused. A thread a replayed call left running is replayed code, so it is
            # watched like a call even there.
            if threading.current_thread() is threading.main_thread():
                return
            phase = "call"
        if event == "open":
            if _write_open(args):
                _hit("audit:open-write")
        elif _denied(event, phase):
            _hit(f"audit:{event}")
    except _ANY_RAISE:  # a raising hook would abort the replayed call
        _hit("audit:hook-error")


@contextlib.contextmanager
def _driver_io():
    """Turn the watch off for the driver's own temp-file writes. Everywhere else after import
    the phase is "between" or "call", so a finalizer, a side `__deepcopy__` or `__eq__`, or any
    other deferred side code that runs between replayed calls is still recorded. Pending
    finalizers run first, watched, and gc stays paused while the watch is off."""
    _untraced(gc.collect)
    prior = _WATCH["phase"]
    gc.disable()
    _WATCH["phase"] = None
    try:
        yield
    finally:
        _WATCH["phase"] = prior
        gc.enable()


def _untraced(fn, *args):
    prior = sys.gettrace()
    sys.settrace(None)  # a side __eq__/__deepcopy__ run by the watch must not count as executed
    branch_events = _branches_off()
    try:
        return fn(*args)
    finally:
        _branches_on(branch_events)
        sys.settrace(prior)


# --- branch outcomes (ADR-0283 residual gap: a weakened branch the corpus never takes) -------
FIDELITY_REL = os.path.join("tools", "postplan-harness", "harness", "fidelity.py")
_BRANCH = {"tool": None, "events": 0}
_PRIMITIVE_TYPES = frozenset({str, int, float, bool, type(None), bytes})
_MEMBER_PARENTS = ("If", "While", "Return", "Assign", "AnnAssign", "Expr")


def _branches_off():
    tool = _BRANCH["tool"]
    if tool is None:
        return 0
    events = sys.monitoring.get_events(tool)
    sys.monitoring.set_events(tool, 0)
    return events


def _branches_on(events):
    if _BRANCH["tool"] is not None and events:
        sys.monitoring.set_events(_BRANCH["tool"], events)


def _start_branches(fid_path, seen):
    """Record (qualname, firstlineno, src, dst) for every branch event in the side's fidelity.py.
    Returns False when this interpreter has no sys.monitoring branch events (the judge then
    holds every changed line that holds a branch)."""
    mon = getattr(sys, "monitoring", None)
    if mon is None:
        return False
    names = [n for n in ("BRANCH_LEFT", "BRANCH_RIGHT") if hasattr(mon.events, n)] or ["BRANCH"]
    if not all(hasattr(mon.events, n) for n in names):
        return False
    tool = next((t for t in range(6) if mon.get_tool(t) is None), None)
    if tool is None:
        return False
    real = {}

    def _on_branch(code, src, dst):
        name = code.co_filename
        path = real.get(name)
        if path is None:
            path = real[name] = "" if name.startswith("<") else os.path.realpath(name)
        if path != fid_path:
            return mon.DISABLE  # foreign code: stop paying for this location
        seen.add((code.co_qualname, code.co_firstlineno, src, dst))
        return None

    mon.use_tool_id(tool, "fidelity-replay-branches")
    events = 0
    for n in names:
        mon.register_callback(tool, getattr(mon.events, n), _on_branch)
        events |= getattr(mon.events, n)
    _BRANCH["tool"], _BRANCH["events"] = tool, events
    mon.set_events(tool, events)
    return True


def _stop_branches():
    tool = _BRANCH["tool"]
    if tool is not None:
        sys.monitoring.set_events(tool, 0)
        _BRANCH["tool"] = None


def _member_sites(source):
    """{line: [(key, name, members)]} for each membership guard the line probe can read.

    The probe reads `name` when its statement's first line starts. That value is the one the
    test sees only when the test is the whole condition or value of the statement, its left
    side is a bare name and its right side is a literal of constants, and nothing in the
    statement rebinds a name. Any other membership shape is left out, so the judge holds it."""
    import ast
    sites = {}
    try:
        tree = ast.parse(source)
    except (SyntaxError, ValueError):
        return sites
    for stmt in ast.walk(tree):
        if type(stmt).__name__ not in _MEMBER_PARENTS:
            continue
        node = getattr(stmt, "test", None) if hasattr(stmt, "test") else getattr(stmt, "value", None)
        if not (isinstance(node, ast.Compare) and len(node.ops) == 1
                and isinstance(node.ops[0], (ast.In, ast.NotIn))
                and isinstance(node.left, ast.Name) and node.lineno == stmt.lineno
                and isinstance(node.comparators[0], (ast.Tuple, ast.List, ast.Set))
                and all(isinstance(e, ast.Constant) for e in node.comparators[0].elts)):
            continue
        head = [stmt.test] if hasattr(stmt, "test") else [stmt]
        if any(isinstance(n, ast.NamedExpr) for h in head for n in ast.walk(h)):
            continue
        key = (node.lineno, node.col_offset, node.end_lineno, node.end_col_offset)
        members = tuple(e.value for e in node.comparators[0].elts)
        sites.setdefault(stmt.lineno, []).append((key, node.left.id, members))
    return sites


def _probe_members(frame, sites, seen):
    """At a line event: record which listed member each site's operand equals (or none)."""
    for key, name, members in sites:
        entry = seen.setdefault(key, {"matched": set(), "nomatch": False, "unsafe": False})
        code = frame.f_code
        try:
            if name in code.co_varnames or name in code.co_cellvars or name in code.co_freevars:
                scope = frame.f_locals
            elif name in frame.f_globals:
                scope = frame.f_globals
            else:
                scope = frame.f_builtins
            value = scope[name]
        except (KeyError, NameError):
            entry["unsafe"] = True  # unbound: the test raises, no outcome to record
            continue
        if type(value) not in _PRIMITIVE_TYPES:
            entry["unsafe"] = True  # a custom __eq__ could decide the test; the probe cannot
            continue
        hit = [i for i, m in enumerate(members) if value is m or value == m]
        entry["matched"].update(hit)
        if not hit:
            entry["nomatch"] = True


def _same(a, b):
    try:
        return bool(a == b)
    except Exception:  # noqa: BLE001
        return False


def _arg_copy(args, kw):
    try:
        before = copy.deepcopy((args, kw))
    except Exception:  # noqa: BLE001 — fail closed: an argument the watch cannot copy holds
        _hit("argument-uncopyable")
        return _UNCOPYABLE
    if not _same(before, (args, kw)):
        _hit("argument-uncomparable")
        return _UNCOPYABLE
    return before


def _arg_check(args, kw, before):
    if before is not _UNCOPYABLE and not _same(before, (args, kw)):
        _hit("argument-mutated")


def _side_modules(root):
    mods = {}
    own = os.path.realpath(__file__)
    for name, mod in list(sys.modules.items()):
        f = getattr(mod, "__file__", None)
        if not isinstance(f, str):
            continue
        path = os.path.realpath(f)
        if path.startswith(root + os.sep) and path != own:  # never watch the driver's own state
            mods[name] = mod
    return mods


def _freeze_globals(mods):
    """{(module, name): ("val", deepcopy) or ("id", id)} over non-dunder, non-module globals."""
    snap = {}
    for name, mod in mods.items():
        for attr, value in list(vars(mod).items()):
            if attr.startswith("__") or isinstance(value, (types.ModuleType, __future__._Feature)):
                continue  # `from __future__ import annotations` binds a fixed, identity-equal flag
            if callable(value):
                snap[(name, attr)] = ("id", id(value))
                continue
            try:
                frozen = copy.deepcopy(value)
            except Exception:  # noqa: BLE001
                frozen = _UNCOPYABLE
            if frozen is _UNCOPYABLE or not _same(frozen, value):
                _hit(f"global-uncopyable:{name}.{attr}")
                snap[(name, attr)] = ("id", id(value))
            else:
                snap[(name, attr)] = ("val", frozen)
    return snap


def _check_globals(mods, before):
    after = _freeze_globals(mods)
    for key in sorted(set(before) | set(after)):
        if key not in before or key not in after or not _same(before[key], after[key]):
            _hit(f"global:{key[0]}.{key[1]}")


def _default(o):
    if isinstance(o, (set, frozenset)):
        return sorted(repr(x) for x in o)
    if dataclasses.is_dataclass(o) and not isinstance(o, type):
        return dataclasses.asdict(o)
    return {"type": type(o).__name__}


def _norm(value):
    return json.loads(json.dumps(value, sort_keys=True, default=_default))


def _call(fn_lookup, *args, **optional):
    """(normalized output, raw value). `fn_lookup` returns the side's callable; an
    AttributeError there (hasattr false) means the side lacks it. An optional keyword is
    passed only when the callable's signature names it."""
    try:
        fn = fn_lookup()
    except AttributeError:
        return dict(ABSENT), None
    try:
        params = inspect.signature(fn).parameters
    except Exception as exc:  # noqa: BLE001
        return {"raise": type(exc).__name__}, None
    var_kw = any(p.kind is inspect.Parameter.VAR_KEYWORD for p in params.values())
    kw = {k: v for k, v in optional.items() if var_kw or k in params}
    _WATCH["where"] = getattr(fn, "__qualname__", "?")
    before = _untraced(_arg_copy, args, kw)
    # A thread start raises no audit event before 3.13 and `atexit.register` never does, so a
    # call that leaves either behind is recorded here, whatever the deferred code does later.
    threads_before, exits_before = set(threading.enumerate()), atexit._ncallbacks()
    _WATCH["phase"] = "call"
    try:
        value = fn(*args, **kw)
    except Exception as exc:  # noqa: BLE001 — a raise is an output, compared as looser
        value, raised = None, {"raise": type(exc).__name__}
    else:
        raised = None
    finally:
        _WATCH["phase"] = "between"
    if any(t.is_alive() for t in set(threading.enumerate()) - threads_before):
        _hit("thread-started")
    if atexit._ncallbacks() != exits_before:
        _hit("atexit-registered")
    _untraced(_arg_check, args, kw, before)
    if raised is not None:
        return raised, None
    try:
        return {"ok": _norm(value)}, value
    except Exception as exc:  # noqa: BLE001
        return {"raise": type(exc).__name__}, None


def _null_log(_msg):
    return None


def main(argv):                    # argv: driver, corpus path, side package root, side root
    if len(argv) != 4:
        return 2
    root = os.path.realpath(argv[3])
    with open(argv[1], encoding="utf-8") as fh:
        records = json.load(fh)
    if not isinstance(records, list) or not records:
        return 4
    for rec in records:
        text = rec.get("verdict_1") if isinstance(rec, dict) else None
        if not isinstance(text, str) or not text:
            return 4
    global fidelity, second_review, armable  # _record reads the side's modules as globals
    sys.addaudithook(_audit)
    _WATCH["phase"] = "import"
    try:
        import harness.fidelity as fidelity
        import harness.second_review as second_review
        import harness.armable as armable
        # Modules that fidelity functions import lazily inside a call. Their `@dataclass`
        # bodies raise call-only events, so they load here, while the import phase is open.
        import harness.schemas  # noqa: F401
        import harness.merge_danger  # noqa: F401
        import harness.reviewcache  # noqa: F401
        import harness.adapters.llm  # noqa: F401
    finally:
        _WATCH["phase"] = "between"

    current = {"v1": None}

    def _patched_verdict_path(_pr):
        return current["v1"]

    fidelity.verdict_path = _patched_verdict_path
    if "verdict_path" in vars(second_review):
        second_review.verdict_path = _patched_verdict_path
    try:
        marker = fidelity.STICKY_MARKER
    except AttributeError:
        marker = ""

    hits, real = {}, {}
    fid_path = os.path.join(root, FIDELITY_REL)
    with _driver_io():
        try:
            with open(fid_path, encoding="utf-8") as fh:
                member_sites = _member_sites(fh.read())
        except (OSError, UnicodeDecodeError):
            member_sites = {}
    members = {key: {"matched": set(), "nomatch": False, "unsafe": False}
               for sites in member_sites.values() for key, _n, _m in sites}
    branches = set()

    def tracer(frame, event, arg):
        name = frame.f_code.co_filename
        if name.startswith("<"):   # <string>, <frozen os>: no file, and realpath would root it at cwd
            return None
        path = real.setdefault(name, os.path.realpath(name))
        if not path.startswith(root + os.sep):
            return None
        if event == "line":
            hits.setdefault(os.path.relpath(path, root), set()).add(frame.f_lineno)
            if path == fid_path and frame.f_lineno in member_sites:
                _probe_members(frame, member_sites[frame.f_lineno], members)
        return tracer

    results = []
    have_branches = _start_branches(fid_path, branches)
    sys.settrace(tracer)
    try:
        for rec in records:
            with _driver_io():
                tmp = tempfile.mkdtemp(prefix="fid-replay-")
                v1 = os.path.join(tmp, "v1.md")
                with open(v1, "w", encoding="utf-8") as fh:
                    fh.write(rec["verdict_1"])
                v2 = None
                if rec.get("verdict_2"):
                    v2 = os.path.join(tmp, "v2.md")
                    with open(v2, "w", encoding="utf-8") as fh:
                        fh.write(rec["verdict_2"])
            try:
                current["v1"] = v1
                mods = _side_modules(root)
                snap, env_before = _untraced(_freeze_globals, mods), dict(os.environ)
                _WATCH["record"] = str(rec["id"])
                out_rec = _record(rec, v1, v2, marker)
                _WATCH["where"] = "record-state"
                _untraced(_check_globals, mods, snap)
                env_after = dict(os.environ)
                if dict(os.environ) != env_before:
                    key = next(k for k in sorted(set(env_before) | set(env_after))
                               if env_before.get(k) != env_after.get(k))
                    _hit(f"environ:{key}")
                results.append({"id": rec["id"], "out": out_rec})
            finally:
                with _driver_io():
                    shutil.rmtree(tmp, ignore_errors=True)
    finally:
        sys.settrace(None)
        _stop_branches()
    # Run exit callbacks a replayed call registered while the watch is on, so a deferred write counts.
    _WATCH["phase"], _WATCH["where"] = "call", "atexit"
    try:
        atexit._run_exitfuncs()
    except _ANY_RAISE:
        _hit("atexit-raised")
    finally:
        _WATCH["phase"] = "between"
    _WATCH["where"] = "exit"
    gc.collect()
    for t in threading.enumerate():
        if t is not threading.main_thread() and t.is_alive():
            _hit(f"thread-alive:{t.name}")  # it could write after the output; __main__ kills it
    json.dump({"schema": "fidelity-replay/1", "results": results,
               "executed": {k: sorted(v) for k, v in hits.items()},
               "branches": sorted(list(b) for b in branches) if have_branches else None,
               "members": sorted([*k, sorted(e["matched"]), e["nomatch"], e["unsafe"]]
                                 for k, e in members.items()),
               "side_effects": list(_WATCH["hits"])}, sys.stdout)
    return 0


def _record(rec, v1, v2, marker):
    out = {}
    fid = rec.get("fid") or {}
    pr = rec.get("pr")
    current_tree = rec.get("current_tree")

    def both(key, lookup):
        out[f"v1.{key}"], r1 = _call(lookup, v1)
        if v2 is None:
            out[f"v2.{key}"], r2 = dict(ABSENT), None
        else:
            out[f"v2.{key}"], r2 = _call(lookup, v2)
        return r1, r2

    word1, word2 = both("parse_verdict", lambda: fidelity.parse_verdict)
    tree1, tree2 = both("read_reviewed_tree", lambda: fidelity.read_reviewed_tree)
    both("gate_edit_only", lambda: fidelity.gate_edit_only)
    both("_verdict_findings", lambda: fidelity._verdict_findings)
    both("blocking_finding_texts", lambda: fidelity.blocking_finding_texts)
    out["v1.verdict_file_usable"], _ = _call(lambda: fidelity.verdict_file_usable, v1)
    out["v1.read_shadow"], read = _call(lambda: fidelity.read_shadow, v1, source="verdict-1",
                                        reviewed_tree=tree1, plan_blind=rec.get("plan_blind"))
    files = rec.get("changed_files") or []
    diff_text = rec.get("diff_text") or ""
    ri = rec.get("review_inputs")
    ri = tuple(ri) if isinstance(ri, list) else ri
    out["holds.read_shadow"], _ = _call(
        lambda: fidelity.reviewer_arming_holds, copy.deepcopy(read), copy.deepcopy(files),
        diff_text, _null_log, review_inputs=copy.deepcopy(ri))
    stored, s_files, s_ri = copy.deepcopy(rec.get("shadow")), copy.deepcopy(files), copy.deepcopy(ri)
    out["holds.stored_shadow"], _ = _call(
        lambda: fidelity.reviewer_arming_holds, stored, s_files, diff_text, _null_log,
        review_inputs=s_ri)
    out["holds.args_after"] = {"ok": _norm({"shadow": stored, "changed_files": s_files,
                                            "review_inputs": s_ri})}
    out["sticky_terminal"], terminal = _call(lambda: fidelity.sticky_terminal, copy.deepcopy(fid))
    out["sticky_prior.synthetic"], _ = _call(lambda: fidelity._sticky_prior_verdict,
                                             f"{terminal or ''}\n{marker}")
    out["sticky_prior.stored"], _ = _call(lambda: fidelity._sticky_prior_verdict,
                                          rec.get("sticky_body") or "")
    fid_with_paths = copy.deepcopy(fid)
    if v2 is not None:
        fid_with_paths["verdict_path"] = v2
    out["selected_gate_edit_only"], _ = _call(lambda: second_review.selected_gate_edit_only,
                                              copy.deepcopy(fid_with_paths), pr, current_tree)
    out["selected_blocking_findings"], _ = _call(lambda: second_review.selected_blocking_findings,
                                                 copy.deepcopy(fid_with_paths), pr, current_tree)
    out["select_fidelity_verdict"], _ = _call(lambda: armable.select_fidelity_verdict,
                                              word1, word2, tree2, current_tree)
    return {k: out[k] for k in OUTPUT_KEYS}


if __name__ == "__main__":
    _rc = main(sys.argv)
    sys.stdout.flush()
    os._exit(_rc)  # no atexit, finalizer or leftover thread runs after the output
