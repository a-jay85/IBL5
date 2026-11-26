"""Owner-card evidence for a PR that edits harness/fidelity.py. Display only.

Local post-plan compares the verdict-parsing functions of the base and head `fidelity.py`
over the stored verdict corpus, then posts the result as its own marker comment.
`bin/pr-triage --card` prints that line. Nothing that arms or holds a PR reads it.

Standard library only, and no environment variable is read. The same file runs under
`python -I` as the per-side worker, so it must load without the harness package on
`sys.path`.

Trust: the worker runs PR-head harness code in a separate process with a stripped
environment, a temp cwd and HOME, and a deadline. Network and filesystem access as the
user are not isolated.
"""
import argparse, ast, copy, hashlib, importlib, io, json, shutil, subprocess, sys, tarfile, tempfile, time
from dataclasses import dataclass, field, replace
from pathlib import Path

FIDELITY_PATH = "tools/postplan-harness/harness/fidelity.py"
COMPARED = ("parse_verdict", "verdict_items", "blocking_finding_texts", "gate_edit_only", "marked_finding_texts")
CALL_ARGS = {"marked_finding_texts": (("BLOCKING",), ("NOTE",))}
VERDICT_GLOB = "raw-plan-fidelity-review-attempt*.txt"
MARKER_PREFIX = "<!-- fidelity-evidence head="
STATUS_PREFIX = "fidelity-evidence: "
SIDE_TIMEOUT_S = 90
GIT_TIMEOUT_S = 30
GH_TIMEOUT_S = 30
MAX_FILES = 6000
_UNSAFE_CHARS = "<>|" + chr(10) + chr(13)
FOOTER = "Posted by post-plan for the owner card. Nothing that arms or holds a PR reads this comment."
SAME_T = "same output on {prs} past PRs ({files} stored verdict files) for {fns}."
CHANGED_T = "OUTPUT CHANGED on {bad} of {prs} past PRs ({diff} of {files} files differ; first PRs: {first}) for {fns}."
UNCOVERED_T = " Changed top-level symbols outside these functions' static call tree: {names}."
NOT_COMPARABLE_T = " Not comparable: {names} (missing, signature changed, or raised on most files)."
CAPPED_T = " Capped at the {cap} newest files."
TAIL = " This compares verdict parsing only and does not clear the hold."
ZERO_T = "NOT RUN: 0 past PRs found (no stored verdict files with a PR number under {root}). No verdict claim."
TIMEOUT_T = "NOT RUN: the {side} comparison timed out after {secs} s. No verdict claim."
ERROR_T = "NOT RUN: comparison failed ({reason}). No verdict claim."
NONE_COMPARABLE_T = "NOT RUN: no compared function is comparable ({names}). No verdict claim."

_OTHER = "<other top-level code>"
_TRUSTED_ASSOCIATIONS = ("OWNER", "MEMBER", "COLLABORATOR")


def should_run(changed_files):
    return FIDELITY_PATH in changed_files


def _strip_docstring(body):
    if body and isinstance(body[0], ast.Expr) and isinstance(body[0].value, ast.Constant) and isinstance(body[0].value.value, str):
        return body[1:]
    return body


# ---- symbol diff and static call tree --------------------------------------

def _without_docstrings(node):
    node = copy.deepcopy(node)
    for sub in ast.walk(node):
        if isinstance(sub, (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef)):
            sub.body = _strip_docstring(sub.body) or [ast.Pass()]
    return node


def _target_names(node) -> list:
    targets = node.targets if isinstance(node, ast.Assign) else [node.target]
    return [n.id for t in targets for n in ast.walk(t)
            if isinstance(n, ast.Name) and isinstance(n.ctx, ast.Store)]


def _entries(src):
    """(name, dump, node) for every top-level binding of `src`, docstrings stripped.

    `ast.dump` omits line numbers, so a comment-only or line-shift edit changes nothing.
    A statement that binds no name joins the `<other top-level code>` pseudo-symbol.
    """
    for raw in _strip_docstring(ast.parse(src).body):
        node = _without_docstrings(raw)
        if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef, ast.ClassDef)):
            yield node.name, ast.dump(node), node
        elif isinstance(node, (ast.Assign, ast.AnnAssign)):
            names = _target_names(node) or [_OTHER]
            for name in names:
                yield name, ast.dump(node), node
        elif isinstance(node, (ast.Import, ast.ImportFrom)):
            module = getattr(node, "module", None)
            level = getattr(node, "level", 0)
            for alias in node.names:
                name = alias.asname or alias.name.split(".")[0]
                yield name, f"import {level} {module} {alias.name} {alias.asname}", node
        else:
            yield _OTHER, ast.dump(node), node


def _top_level_symbols(src) -> dict:
    symbols: dict = {}
    for name, dump, _node in _entries(src):
        symbols[name] = symbols.get(name, "") + dump
    return symbols


def _global_refs(node) -> set:
    """`ast.Name` ids under `node`, minus a function's own parameters and assigned names.

    A local that shares a top-level name would otherwise pull that symbol into the call tree
    and hide its edit. Dropping too much only lists more symbols as outside the tree.
    """
    ids = {n.id for n in ast.walk(node) if isinstance(n, ast.Name)}
    if not isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)):
        return ids
    local = {n.arg for n in ast.walk(node) if isinstance(n, ast.arg)}
    local |= {n.id for n in ast.walk(node)
              if isinstance(n, ast.Name) and isinstance(n.ctx, ast.Store)}
    declared = {name for n in ast.walk(node) if isinstance(n, ast.Global) for name in n.names}
    return ids - (local - declared)


def static_closure(src, roots) -> set:
    """The roots plus every top-level name reached through `ast.Name` ids, transitively."""
    refs: dict = {}
    for name, _dump, node in _entries(src):
        ids = refs.setdefault(name, set())
        if not isinstance(node, (ast.Import, ast.ImportFrom)):
            ids.update(_global_refs(node))
    seen: set = set()
    todo = list(roots)
    while todo:
        name = todo.pop()
        if name in seen:
            continue
        seen.add(name)
        todo.extend(r for r in refs.get(name, ()) if r in refs)
    return seen


def uncovered(base_src, head_src) -> list:
    """Changed, added and removed top-level names outside the compared functions' call tree."""
    base = _top_level_symbols(base_src)
    head = _top_level_symbols(head_src)
    changed = {n for n in base.keys() | head.keys() if base.get(n) != head.get(n)}
    closure = static_closure(base_src, COMPARED) | static_closure(head_src, COMPARED)
    return sorted(changed - closure)


def signatures(src) -> dict:
    return {node.name: ast.dump(node.args) for node in ast.parse(src).body
            if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)) and node.name in COMPARED}


# ---- wording ---------------------------------------------------------------

@dataclass
class Result:
    kind: str  # same | changed | zero | timeout | error | none_comparable
    prs: int = 0
    files: int = 0
    bad_prs: list = field(default_factory=list)
    diff_files: int = 0
    fns: list = field(default_factory=list)
    uncovered: list = field(default_factory=list)
    not_comparable: list = field(default_factory=list)
    capped: bool = False
    side: str = ""
    secs: float = 0
    reason: str = ""
    root: str = ""


def _sanitize(text) -> str:
    text = str(text)
    for char in _UNSAFE_CHARS:
        text = text.replace(char, " ")
    return " ".join(part for part in text.split(" ") if part)[:120]


def _names(names) -> str:
    return ", ".join(_sanitize(n) for n in names)


def render(result) -> str:
    if result.kind == "timeout":
        return TIMEOUT_T.format(side=_sanitize(result.side), secs=f"{result.secs:g}")
    if result.kind == "error":
        return ERROR_T.format(reason=_sanitize(result.reason))
    if result.kind == "none_comparable":
        return NONE_COMPARABLE_T.format(names=_names(result.not_comparable))
    if result.prs == 0:
        return ZERO_T.format(root=_sanitize(result.root))
    result = replace(result, uncovered=[_sanitize(n) for n in result.uncovered])
    if result.kind == "changed":
        bad_prs = result.bad_prs
        text = CHANGED_T.format(
            bad=len(bad_prs), prs=result.prs, diff=result.diff_files, files=result.files,
            first=", ".join(f"PR {p}" for p in bad_prs[:3]), fns=_names(result.fns))
    else:
        text = SAME_T.format(prs=result.prs, files=result.files, fns=_names(result.fns))
    if result.not_comparable:
        text += NOT_COMPARABLE_T.format(names=_names(result.not_comparable))
    text += UNCOVERED_T.format(names=", ".join(result.uncovered) or "none")
    if result.capped:
        text += CAPPED_T.format(cap=MAX_FILES)
    return text + TAIL


def _is_sha(value) -> bool:
    return len(value) == 40 and all(c in "0123456789abcdef" for c in value)


def comment_body(head, base, status) -> str:
    if not (_is_sha(head) and _is_sha(base)):
        raise ValueError("comment_body needs 40-hex head and base")
    return f"{MARKER_PREFIX}{head} base={base} -->\n{STATUS_PREFIX}{status}\n\n{FOOTER}\n"


# ---- isolated base/head comparison -----------------------------------------

def _git(worktree, *args) -> str:
    proc = subprocess.run(["git", "-C", str(worktree), *args], capture_output=True, text=True,
                          timeout=GIT_TIMEOUT_S, check=True)
    return proc.stdout.strip()


def _corpus(out_dir) -> tuple:
    """({absolute verdict path: PR number}, capped) over `out_dir`, newest run first.

    `capped` is true only when the MAX_FILES limit left a verdict file out.
    """
    root = Path(out_dir).resolve()
    if not root.is_dir():
        return {}, False
    runs = []
    for child in root.iterdir():
        if child.name == "state" or not child.is_dir():
            continue
        try:
            mtime = (child / "result.json").stat().st_mtime
        except OSError:
            continue
        runs.append((mtime, child))
    found: dict = {}
    for _mtime, child in sorted(runs, reverse=True):
        try:
            data = json.loads((child / "result.json").read_text(encoding="utf-8"))
        except (OSError, ValueError):
            continue
        pr = data.get("pr_number") if isinstance(data, dict) else None
        if not isinstance(pr, int) or pr <= 0:
            continue
        for verdict in sorted(child.glob(VERDICT_GLOB)):
            if len(found) >= MAX_FILES:
                return found, True
            found[str(verdict)] = pr
    return found, False


def _archive(worktree, sha, dest) -> None:
    """The whole harness dir at `sha`, so sibling imports and import-time reads resolve."""
    out = subprocess.run(
        ["git", "-C", str(worktree), "archive", "--format=tar", sha, "tools/postplan-harness"],
        capture_output=True, timeout=GIT_TIMEOUT_S, check=True).stdout
    with tarfile.open(fileobj=io.BytesIO(out)) as tar:
        tar.extractall(dest, filter="data")


def _worker_env(tmp) -> dict:
    return {"PATH": f"{Path(sys.executable).parent}:/usr/bin:/bin", "HOME": tmp, "TMPDIR": tmp,
            "LANG": "C.UTF-8", "PYTHONDONTWRITEBYTECODE": "1"}


def _spawn(side, root, list_path, out_path, tmp):
    err_path = str(Path(tmp) / f"{side}.stderr")
    argv = [sys.executable, "-I", str(Path(__file__).resolve()), "--worker",
            "--root", root, "--files", list_path, "--out", out_path]
    err = open(err_path, "wb")  # the child keeps its own descriptor
    proc = subprocess.Popen(
        argv,
        cwd=tmp,
        stdin=subprocess.DEVNULL,
        stdout=subprocess.DEVNULL,
        stderr=err,
        env=_worker_env(tmp),
    )
    err.close()
    return proc, err_path


def _worker(root, files_path, out_path) -> int:
    """Runs inside the isolated child: hash every compared function's output per file."""
    sys.dont_write_bytecode = True
    sys.path.insert(0, root)
    try:
        module = importlib.import_module("harness.fidelity")
    except BaseException as exc:  # a SystemExit at import time is an import failure too
        Path(out_path).write_text(
            json.dumps({"import_error": f"{type(exc).__name__}: {exc}"[:200]}), encoding="utf-8")
        return 0
    paths = [ln for ln in Path(files_path).read_text(encoding="utf-8").split("\n") if ln]
    present = [n for n in COMPARED if callable(getattr(module, n, None))]
    hashes: dict = {n: {} for n in present}
    raises: dict = {n: 0 for n in present}
    unstable: dict = {n: 0 for n in present}
    for path in paths:
        for name in present:
            fn = getattr(module, name)
            try:
                value = tuple(fn(path, *a) for a in CALL_ARGS.get(name, ((),)))
                text = repr(value)
            except Exception as exc:
                raises[name] += 1
                text = "raise:" + type(exc).__name__
            if " at 0x" in text:
                unstable[name] += 1
            hashes[name][path] = hashlib.sha256(text.encode("utf-8", "backslashreplace")).hexdigest()
    Path(out_path).write_text(
        json.dumps({"hashes": hashes, "raises": raises, "unstable": unstable}), encoding="utf-8")
    return 0


def _load_side(side, returncode, out_path, err_path):
    """(data, "") for a usable worker result, else (None, reason)."""
    try:
        data = json.loads(Path(out_path).read_text(encoding="utf-8"))
    except (OSError, ValueError):
        tail = _sanitize(Path(err_path).read_text(encoding="utf-8", errors="replace")[-80:])
        return None, f"{side} worker exit {returncode} with no result: {tail}"
    if data.get("import_error"):
        return None, f"{side} fidelity.py failed to import: {data['import_error']}"
    return data, ""


def _score(file_pr, base, head, base_sigs, head_sigs):
    """The `same`, `changed` or `none_comparable` Result from two worker outputs."""
    not_comparable: set = set()
    for name in COMPARED:
        tables = (base_sigs, head_sigs, base["hashes"], head["hashes"])
        missing = any(name not in table for table in tables)
        raised = base["raises"].get(name, 0) * 2 > len(file_pr)
        unstable = base["unstable"].get(name, 0) + head["unstable"].get(name, 0) > 0
        if missing or base_sigs[name] != head_sigs[name] or raised or unstable:
            not_comparable.add(name)
    fns = [n for n in COMPARED if n not in not_comparable]
    result = Result(kind="same", files=len(file_pr), fns=fns,
                    not_comparable=[n for n in COMPARED if n in not_comparable])
    if not fns:
        result.kind = "none_comparable"
        return result
    differing = [p for p in file_pr
                 if any(base["hashes"][n].get(p) != head["hashes"][n].get(p) for n in fns)]
    result.prs = len(set(file_pr.values()))
    result.diff_files = len(differing)
    result.bad_prs = sorted({file_pr[p] for p in differing})
    if differing:
        result.kind = "changed"
    return result


def _kill(procs) -> None:
    for proc, _err_path, _out_path in procs.values():
        if proc.poll() is None:
            proc.kill()
            proc.wait()


def _compare_sides(worktree, shas, file_pr, tmp, side_timeout, procs):
    """Archive both sides, run both workers under one deadline, and score them.

    `procs` is filled in place so the caller can kill the workers on any exit path.
    """
    roots, sources = {}, {}
    for side, sha in shas:
        dest = Path(tmp) / side
        _archive(worktree, sha, str(dest))
        roots[side] = str(dest / "tools" / "postplan-harness")
        sources[side] = (dest / FIDELITY_PATH).read_text(encoding="utf-8")
    outside = uncovered(sources["base"], sources["head"])
    base_sigs, head_sigs = signatures(sources["base"]), signatures(sources["head"])
    list_path = Path(tmp) / "files.txt"
    list_path.write_text("\n".join(file_pr) + "\n", encoding="utf-8")
    for side, _sha in shas:
        out_path = str(Path(tmp) / f"{side}.json")
        proc, err_path = _spawn(side, roots[side], str(list_path), out_path, tmp)
        procs[side] = (proc, err_path, out_path)
    deadline = time.monotonic() + side_timeout
    for side, (proc, _err_path, _out_path) in procs.items():
        try:
            proc.wait(timeout=max(0.0, deadline - time.monotonic()))
        except subprocess.TimeoutExpired:
            _kill(procs)
            return Result(kind="timeout", side=side, secs=side_timeout)
    data = {}
    for side, (proc, err_path, out_path) in procs.items():
        data[side], reason = _load_side(side, proc.returncode, out_path, err_path)
        if reason:
            return Result(kind="error", reason=reason)
    result = _score(file_pr, data["base"], data["head"], base_sigs, head_sigs)
    result.uncovered = outside
    return result


def compare(worktree, base_ref, out_dir, side_timeout=SIDE_TIMEOUT_S) -> tuple:
    """(head_sha, base_sha, status). Never raises; a failure renders as `NOT RUN`."""
    head = base = ""
    tmp = None
    procs: dict = {}
    try:
        head = _git(worktree, "rev-parse", "HEAD")
        base = _git(worktree, "merge-base", base_ref, "HEAD")
        file_pr, capped = _corpus(out_dir)
        if not file_pr:
            result = Result(kind="zero", root=str(out_dir))
        else:
            tmp = tempfile.mkdtemp(prefix="fid-evidence-")
            result = _compare_sides(worktree, (("base", base), ("head", head)), file_pr, tmp,
                                    side_timeout, procs)
            result.capped = capped
        return head, base, render(result)
    except Exception as exc:  # compare never raises
        reason = _sanitize(f"{type(exc).__name__}: {exc}")
        return head, base, render(Result(kind="error", reason=reason))
    finally:
        _kill(procs)
        if tmp:
            shutil.rmtree(tmp, ignore_errors=True)


# ---- the evidence comment --------------------------------------------------

def _gh_api(worktree, gh_cmd, step, args) -> str:
    proc = subprocess.run([*gh_cmd, "api", *args], cwd=str(worktree), capture_output=True,
                          text=True, stdin=subprocess.DEVNULL, timeout=GH_TIMEOUT_S)
    if proc.returncode != 0:
        raise RuntimeError(f"gh {step} exit {proc.returncode}")
    return proc.stdout


def _find_comment(worktree, pr, gh_cmd):
    """Id of the last trusted evidence comment on `pr`, or None."""
    trusted = " or ".join(f'.author_association == "{a}"' for a in _TRUSTED_ASSOCIATIONS)
    query = (f'.[] | select(({trusted}) and ((.body // "") '
             f'| startswith({json.dumps(MARKER_PREFIX)}))) | .id')
    out = _gh_api(worktree, gh_cmd, "list",
                  ["--paginate", "--jq", query, f"repos/{{owner}}/{{repo}}/issues/{int(pr)}/comments"])
    ids = out.split()
    return ids[-1] if ids else None


def _upsert(worktree, pr, body, gh_cmd) -> None:
    found = _find_comment(worktree, pr, gh_cmd)
    if found:
        step = "update"
        target = ["-X", "PATCH", f"repos/{{owner}}/{{repo}}/issues/comments/{found}"]
    else:
        step = "create"
        target = [f"repos/{{owner}}/{{repo}}/issues/{int(pr)}/comments"]
    _gh_api(worktree, gh_cmd, step, [*target, "-f", f"body={body}"])


def post_for_pr(worktree, base_ref, pr, out_dir, gh_cmd=("gh",)) -> str:
    """Compare, then upsert the one evidence comment. Returns a log line."""
    try:
        head, base, status = compare(worktree, base_ref, out_dir)
        if not head:
            return "fidelity evidence skipped (head unresolved)"
        _upsert(worktree, pr, comment_body(head, base, status), gh_cmd)
        return f"fidelity evidence posted: {status}"
    except Exception as exc:  # post_for_pr never raises
        return f"fidelity evidence post failed ({_sanitize(f'{type(exc).__name__}: {exc}')})"


def main(argv) -> int:
    parser = argparse.ArgumentParser(prog="fidelity_evidence")
    parser.add_argument("--worker", action="store_true")
    for flag in ("--root", "--files", "--out", "--worktree", "--base", "--out-dir"):
        parser.add_argument(flag)
    args = parser.parse_args(argv)
    needed = ("root", "files", "out") if args.worker else ("worktree", "base", "out_dir")
    missing = ["--" + n.replace("_", "-") for n in needed if not getattr(args, n)]
    if missing:
        parser.error("missing " + ", ".join(missing))
    if args.worker:
        return _worker(args.root, args.files, args.out)
    started = time.monotonic()
    _head, _base, status = compare(args.worktree, args.base, args.out_dir)
    print(status)
    print(f"elapsed: {time.monotonic() - started:.1f} s", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
