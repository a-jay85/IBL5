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
import copy
import dataclasses
import inspect
import json
import os
import tempfile
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
                 "builtins.breakpoint")
READ_ONLY_EVENTS = frozenset({"os.listdir", "os.scandir", "os.walk", "os.fwalk", "os.getxattr",
                              "os.listxattr", "os.listdrives", "os.listmounts", "os.listvolumes"})
CALL_ONLY_EVENTS = frozenset({"object.__setattr__", "object.__delattr__", "sys.settrace",
                              "sys.setprofile", "sys.addaudithook"})
_WRITE_FLAGS = os.O_WRONLY | os.O_RDWR | os.O_APPEND | os.O_CREAT | os.O_TRUNC
_WATCH = {"phase": None, "record": "-", "where": "import", "hits": []}
MAX_HITS = 50
_UNCOPYABLE = object()


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
    if phase is None:
        return
    try:
        if event == "open":
            if _write_open(args):
                _hit("audit:open-write")
        elif _denied(event, phase):
            _hit(f"audit:{event}")
    except Exception:  # noqa: BLE001 — a raising hook would abort the replayed call
        _hit("audit:hook-error")


def _untraced(fn, *args):
    prior = sys.gettrace()
    sys.settrace(None)  # a side __eq__/__deepcopy__ run by the watch must not count as executed
    try:
        return fn(*args)
    finally:
        sys.settrace(prior)


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
    _WATCH["phase"] = "call"
    try:
        value = fn(*args, **kw)
    except Exception as exc:  # noqa: BLE001 — a raise is an output, compared as looser
        value, raised = None, {"raise": type(exc).__name__}
    else:
        raised = None
    finally:
        _WATCH["phase"] = None
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
        _WATCH["phase"] = None

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

    def tracer(frame, event, arg):
        name = frame.f_code.co_filename
        if name.startswith("<"):   # <string>, <frozen os>: no file, and realpath would root it at cwd
            return None
        path = real.setdefault(name, os.path.realpath(name))
        if not path.startswith(root + os.sep):
            return None
        if event == "line":
            hits.setdefault(os.path.relpath(path, root), set()).add(frame.f_lineno)
        return tracer

    results = []
    sys.settrace(tracer)
    try:
        for rec in records:
            with tempfile.TemporaryDirectory(prefix="fid-replay-") as tmp:
                v1 = os.path.join(tmp, "v1.md")
                with open(v1, "w", encoding="utf-8") as fh:
                    fh.write(rec["verdict_1"])
                v2 = None
                if rec.get("verdict_2"):
                    v2 = os.path.join(tmp, "v2.md")
                    with open(v2, "w", encoding="utf-8") as fh:
                        fh.write(rec["verdict_2"])
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
        sys.settrace(None)
    json.dump({"schema": "fidelity-replay/1", "results": results,
               "executed": {k: sorted(v) for k, v in hits.items()},
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
    sys.exit(main(sys.argv))
