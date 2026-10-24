"""Off-limits choke point: every tree-editing call_tooled carries the owner-approved
deviation block and the posting denials, enforced at the LLM adapter boundary."""
import ast
import glob
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import cifix, fidelity, owner_approvals as oa, prosefix, thread_ingestion
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger
from test_runner_replay import CANNED, _actions, _fixture, _verdict_doc

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PROXY_FILES = {"reviewer_eval.py", "verifier_replay.py", "owner_approvals.py"}

# Purposes that edit the tree (or write files), so they receive the off-limits block.
EDITING_PURPOSES = {
    "fidelity-remediation", "thread-remediation", "gate-fix", "ci-fix", "prose-fix",
    "adr-draft", "conflict-fix-session", "conflict-ladder-session",
}
# Editing purposes whose names carry a runtime suffix (the AST resolves them to a prefix).
EDITING_PREFIXES = ("conflict-resolve:",)

HEADER = "## Owner-approved deviations (off-limits)"


# --- AST enumeration ---------------------------------------------------------

def _py_files():
    return sorted(glob.glob(os.path.join(ROOT, "harness", "*.py"))) + [os.path.join(ROOT, "runner.py")]


def _module_env(tree):
    env = {}
    for node in tree.body:
        if isinstance(node, ast.Assign):
            for tgt in node.targets:
                if isinstance(tgt, ast.Name):
                    env[tgt.id] = node.value
                elif isinstance(tgt, ast.Tuple) and isinstance(node.value, ast.Tuple) \
                        and len(tgt.elts) == len(node.value.elts):
                    for t, v in zip(tgt.elts, node.value.elts):
                        if isinstance(t, ast.Name):
                            env[t.id] = v
    return env


def _load():
    trees, envs, global_env = {}, {}, {}
    for path in _py_files():
        tree = ast.parse(open(path).read())
        trees[path] = tree
        envs[path] = _module_env(tree)
        for name, val in envs[path].items():
            global_env.setdefault(name, val)
    return trees, envs, global_env


def _lookup(name, env, global_env):
    return env.get(name) or global_env.get(name)


def _resolve_str(node, env, global_env):
    """A literal, or a pattern with "{}" where a runtime value is interpolated."""
    if isinstance(node, ast.Constant) and isinstance(node.value, str):
        return node.value
    if isinstance(node, (ast.Name, ast.Attribute)):
        name = node.id if isinstance(node, ast.Name) else node.attr
        target = _lookup(name, env, global_env)
        return None if target is None else _resolve_str(target, env, global_env)
    if isinstance(node, ast.JoinedStr):
        parts = []
        for v in node.values:
            if isinstance(v, ast.Constant):
                parts.append(v.value)
            else:
                inner = _resolve_str(v.value, env, global_env) if isinstance(v, ast.FormattedValue) else None
                parts.append(inner if (inner is not None and isinstance(v.value, (ast.Name, ast.Attribute))
                                       and not _is_loop_var(v.value)) else "{}")
        return "".join(parts)
    if isinstance(node, ast.Subscript):          # VERIFIER_PURPOSES[seat]
        return _resolve_str(node.value, env, global_env)
    if isinstance(node, ast.Call) and node.args and isinstance(node.args[0], ast.GeneratorExp):
        return _resolve_str(node.args[0].elt, env, global_env)
    return None


def _is_loop_var(node):
    return isinstance(node, ast.Name) and node.id in {"i", "n", "path", "seat", "round_num"}


def _resolve_tuple(node, env, global_env):
    if isinstance(node, (ast.Tuple, ast.List)):
        out = []
        for e in node.elts:
            if isinstance(e, ast.Starred):
                sub = _resolve_tuple(e.value, env, global_env)
                if sub is None:
                    return None
                out.extend(sub)
            elif isinstance(e, ast.Constant):
                out.append(e.value)
            else:
                return None
        return tuple(out)
    if isinstance(node, (ast.Name, ast.Attribute)):
        name = node.id if isinstance(node, ast.Name) else node.attr
        target = _lookup(name, env, global_env)
        return None if target is None else _resolve_tuple(target, env, global_env)
    if isinstance(node, ast.BinOp) and isinstance(node.op, ast.Add):
        l = _resolve_tuple(node.left, env, global_env)
        r = _resolve_tuple(node.right, env, global_env)
        return None if l is None or r is None else l + r
    return None


def _sites():
    """[(file, line, purpose-or-None, denied_tools-or-None, denied_kw_present)] for every call_tooled call."""
    trees, envs, global_env = _load()
    out = []
    for path, tree in trees.items():
        for node in ast.walk(tree):
            if not (isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute)
                    and node.func.attr == "call_tooled"):
                continue
            base = os.path.basename(path)
            first = node.args[0] if node.args else None
            if first is None or isinstance(first, ast.Starred):
                purpose = None
            else:
                purpose = _resolve_str(first, envs[path], global_env)
            denied = None
            for kw in node.keywords:
                if kw.arg == "denied_tools":
                    denied = _resolve_tuple(kw.value, envs[path], global_env)
            out.append((base, node.lineno, purpose, denied))
    return out


def _is_editing(purpose):
    if "{}" in purpose:
        prefix = purpose.split("{}")[0]
        return prefix in EDITING_PREFIXES or prefix.startswith(EDITING_PREFIXES)
    return purpose in EDITING_PURPOSES or purpose.startswith(EDITING_PREFIXES)


def _is_read_only(purpose):
    return oa.is_read_only_purpose(purpose.split("{}")[0] if "{}" in purpose else purpose)


def test_every_call_tooled_purpose_is_classified():
    unclassified, unresolved = [], []
    for base, line, purpose, _denied in _sites():
        if base in PROXY_FILES:
            continue
        if purpose is None:
            unresolved.append(f"{base}:{line}")
        elif not (_is_editing(purpose) or _is_read_only(purpose)):
            unclassified.append(f"{base}:{line} {purpose!r}")
    assert not unresolved, f"unresolvable call_tooled purpose: {unresolved}"
    assert not unclassified, f"classify as EDITING or READ_ONLY: {unclassified}"


def test_read_only_purposes_deny_edit_and_write():
    seen = set()
    for base, line, purpose, denied in _sites():
        if base in PROXY_FILES or purpose is None or not _is_read_only(purpose):
            continue
        seen.add(purpose.split("{}")[0])
        assert denied is not None, f"{base}:{line} {purpose!r}: denied_tools unresolvable"
        assert "Edit" in denied and "Write" in denied, f"{base}:{line} {purpose!r} lets a reviewer edit"
    listed = set(oa.READ_ONLY_PURPOSES) | set(oa.READ_ONLY_PURPOSE_PREFIXES)
    missing = {p for p in listed if not any(s.startswith(p) for s in seen)}
    assert not missing, f"read-only entries with no call site: {sorted(missing)}"


# --- the guard ---------------------------------------------------------------

class _Rec:
    def __init__(self):
        self.calls = []
        self.ledger = "ledger-sentinel"

    def call(self, *a, **k):
        return ("call", a, k)

    def call_tooled(self, purpose, model, prompt, **kw):
        self.calls.append((purpose, prompt, kw))
        return "ok"


def _snap(keep=("if ($teamId === 0) { return; }",), gone=("legacy_call();",)):
    rec = oa.Record("oad-0123abcd", "owner approved deviation", 1,
                    (oa.ApprovedFile("ibl5/x.php", tuple(keep), tuple(gone)),))
    return oa.ApprovalSnapshot((rec,), "fp", 0)


def test_guard_appends_off_limits_to_editing_purpose():
    inner = _Rec()
    oa.OwnerGuardLlm(inner, _snap()).call_tooled("gate-fix", "opus", "do it", cwd=".")
    purpose, prompt, _ = inner.calls[0]
    assert prompt.startswith("do it\n\n" + HEADER)
    assert "if ($teamId === 0) { return; }" in prompt


def test_guard_skips_read_only_purpose():
    inner = _Rec()
    g = oa.OwnerGuardLlm(inner, _snap())
    g.call_tooled("plan-fidelity-review", "opus", "review it", cwd=".",
                  denied_tools=("Bash", "Write", "Edit"))
    _, prompt, kw = inner.calls[0]
    assert prompt == "review it"
    assert kw["denied_tools"] == ("Bash", "Write", "Edit")
    g.call_tooled("plan-fidelity-re-review-3", "opus", "again", cwd=".")
    assert inner.calls[1][1] == "again"


def test_guard_unions_posting_denials_and_keeps_caller_denials():
    inner = _Rec()
    oa.OwnerGuardLlm(inner, _snap()).call_tooled(
        "ci-fix", "opus", "p", cwd=".", denied_tools=("Agent", "Bash(git push:*)", "Bash(gh api:*)"))
    denied = inner.calls[0][2]["denied_tools"]
    assert "Agent" in denied and "Bash(git push:*)" in denied
    assert set(oa.FIXER_DENIED_TOOLS) <= set(denied)
    assert len(denied) == len(set(denied))


def test_guard_with_empty_denied_tools_still_denies_posting():
    inner = _Rec()
    oa.OwnerGuardLlm(inner, _snap()).call_tooled("prose-fix", "sonnet", "p", cwd=".", denied_tools=())
    assert inner.calls[0][2]["denied_tools"] == oa.FIXER_DENIED_TOOLS


def test_guard_empty_snapshot_leaves_prompt_unchanged():
    inner = _Rec()
    g = oa.OwnerGuardLlm(inner, oa.empty_snapshot())
    g.call_tooled("gate-fix", "opus", "unchanged", cwd=".")
    assert inner.calls[0][1] == "unchanged"
    assert set(oa.FIXER_DENIED_TOOLS) <= set(inner.calls[0][2]["denied_tools"])


def test_guard_delegates_other_attributes_without_recursing():
    inner = _Rec()
    g = oa.OwnerGuardLlm(inner, oa.empty_snapshot())
    assert g.ledger == "ledger-sentinel"
    assert g.call("x")[0] == "call"
    bare = oa.OwnerGuardLlm.__new__(oa.OwnerGuardLlm)
    with pytest.raises(AttributeError):
        bare.anything


def test_off_limits_text_lists_keep_and_gone_with_ids():
    text = oa.off_limits_text(_snap())
    assert text.startswith(HEADER)
    assert "- oad-0123abcd: owner approved deviation" in text
    assert "  - ibl5/x.php" in text
    assert "    keep (must remain present): if ($teamId === 0) { return; }" in text
    assert "    gone (must remain absent): legacy_call();" in text
    assert text.endswith("say so in your reply.")
    assert oa.off_limits_text(oa.empty_snapshot()) == ""


def test_remediation_denied_tools_include_posting_denials():
    posting = set(oa.FIXER_DENIED_TOOLS)
    assert posting <= set(fidelity.REMEDIATION_DENIED_TOOLS)
    assert posting <= set(thread_ingestion.REMEDIATION_DENIED_TOOLS)
    assert posting <= set(cifix.CI_FIX_DENIED_TOOLS)
    assert posting <= set(prosefix.PROSE_FIX_DENIED_TOOLS)


# --- run() wiring ------------------------------------------------------------

class _RecordingLlm(FixtureLlm):
    """FixtureLlm that records the prompt and denied_tools of each tooled call, and serves
    scripted replies per purpose, in order."""

    def __init__(self, ledger, canned, scripts):
        super().__init__(ledger, dict(canned))
        self.scripts = {k: list(v) for k, v in scripts.items()}
        self.tooled = []

    def call_tooled(self, purpose, model, prompt, **kw):
        self.tooled.append((purpose, prompt, tuple(kw.get("denied_tools", ()))))
        queue = self.scripts.get(purpose)
        if queue:
            self.canned[purpose] = queue.pop(0)
        return super().call_tooled(purpose, model, prompt, **kw)


def _owner_comment():
    payload = {"schema": 1, "id": "oad-0123abcd", "summary": "owner approved deviation",
               "files": [{"path": "ibl5/x.php", "keep": ["if ($teamId === 0) { return; }"], "gone": []}]}
    body = f"{oa.APPROVAL_MARKER}\n```json\n{json.dumps(payload)}\n```\n"
    return {"id": 11, "body": body, "login": "a-jay85", "type": "User"}


@pytest.fixture
def verdict_cleanup():
    used = []
    yield used.append
    for n in used:
        for path in glob.glob(f"/tmp/post-plan-fidelity-*-{n}*"):
            try:
                os.unlink(path)
            except OSError:
                pass


def test_run_wraps_llm_before_first_fixer(tmp_path, verdict_cleanup):
    pr = 7301
    verdict_cleanup(pr)
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""
    llm = _RecordingLlm(UsageLedger(), canned, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [_verdict_doc("READY")],
    })
    fx = _fixture(pr_number=pr, pr_issue_comments=[_owner_comment()])
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    res = runner.run(fx, str(tmp_path / "out"), llm, mode="replay")
    remediation = [t for t in llm.tooled if t[0] == "fidelity-remediation"]
    assert remediation, [t[0] for t in llm.tooled]
    _, prompt, denied = remediation[0]
    assert HEADER in prompt and "oad-0123abcd" in prompt
    assert "Bash(gh pr comment:*)" in denied
    assert res.owner_approvals == {"ids": ["oad-0123abcd"], "ignored_untrusted": 0}
    review = [t for t in llm.tooled if t[0] == "plan-fidelity-review"]
    assert HEADER not in review[0][1]


def test_run_stops_before_any_fixer_when_snapshot_read_fails(tmp_path):
    pr = 7302
    llm = _RecordingLlm(UsageLedger(), dict(CANNED), {})
    fx = _fixture(pr_number=pr, pr_issue_comments_error=True)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    out = str(tmp_path / "out")
    res = runner.run(fx, out, llm, mode="replay")
    assert res.error_kind == "gh"
    assert llm.tooled == []
    assert not [a for a in _actions(out) if a["action"] in ("push", "pr_push")]
