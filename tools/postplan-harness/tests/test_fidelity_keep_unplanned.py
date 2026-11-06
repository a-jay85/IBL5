"""Phase 5.5 keep, remove and hold branches for `[unplanned-scope]` findings."""
from __future__ import annotations

import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity, llm_calls, removed_guards, unplanned_scope as us
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.classify import strip_change_blocks
from harness.state import UsageLedger

import runner

GRID = "ibl5/design/components/negotiate.css"
OTHER = "ibl5/classes/Other.php"
FEAT = "feat: confine quick offer presets to three in a row"
KEEP_ITEM = f"- [BLOCKING] [unplanned-scope] Remove or plan the button grid in {GRID}. Next action: move it."
UNTAGGED_ITEM = "- [BLOCKING] Rename the helper in the plan's terms."
KEPT_BODY = (f"intro\n\n{us.KEPT_BEGIN}\n## Kept unplanned changes\n"
             f"- Remove or plan the button grid in {GRID} (`{GRID}`)\n{us.KEPT_END}\n")

GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


def _ctx(*, subjects=None, preserve=(), removed=(), changed=(GRID,)):
    return {
        "changed_paths": list(changed),
        "subjects_by_path": {GRID: [FEAT]} if subjects is None else subjects,
        "must_preserve": set(preserve),
        "removed_guard_paths": set(removed),
    }


def _item(text=KEEP_ITEM, hold="12"):
    return {"hold": hold, "text": text}


def _verdict(tmp_path, word, *items, name="verdict.md"):
    path = tmp_path / name
    path.write_text("## FINDINGS\n" + "\n".join(items) + f"\n\n{word}\n", encoding="utf-8")
    return str(path)


def test_partition_keep_branch():
    work, kept, held = us.partition([_item()], _ctx())
    assert (work, kept, held) == ([], [_item()], [])


def test_partition_remove_branch_reaches_fixer():
    ctx = _ctx(subjects={GRID: [fidelity.REMEDIATION_COMMIT_MSG]})
    work, kept, held = us.partition([_item()], ctx)
    assert (work, kept, held) == ([_item()], [], [])
    assert work[0]["text"] == KEEP_ITEM


def test_partition_hold_branch_never_reaches_fixer():
    work, kept, held = us.partition([_item()], _ctx(preserve=(GRID,), removed=(GRID,)))
    assert (work, kept, held) == ([], [], [_item()])


def test_partition_ctx_none_is_identity():
    items = [_item(), _item(UNTAGGED_ITEM)]
    assert us.partition(items, None) == (items, [], [])


def test_partition_untagged_and_other_holds_untouched():
    items = [_item(UNTAGGED_ITEM), _item(KEEP_ITEM, hold="16")]
    assert us.partition(items, _ctx()) == (items, [], [])


def test_downgrade_all_kept(tmp_path):
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM)
    assert us.downgrade_applies(path, _ctx(), KEPT_BODY) is True


def test_downgrade_refuses_untagged_blocker(tmp_path):
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM, UNTAGGED_ITEM)
    assert us.downgrade_applies(path, _ctx(), KEPT_BODY) is False


def test_downgrade_refuses_unmarked_blocker(tmp_path):
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM, "- A bullet with no severity marker.")
    assert us.downgrade_applies(path, _ctx(), KEPT_BODY) is False


def test_downgrade_refuses_hold(tmp_path):
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM)
    ctx = _ctx(preserve=(GRID,), removed=(GRID,))
    assert us.downgrade_applies(path, ctx, KEPT_BODY) is False


def test_downgrade_refuses_leftover_remove(tmp_path):
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM)
    ctx = _ctx(subjects={GRID: [fidelity.REMEDIATION_COMMIT_MSG]})
    assert us.downgrade_applies(path, ctx, KEPT_BODY) is False


def test_downgrade_refuses_missing_declaration(tmp_path):
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM)
    assert us.downgrade_applies(path, _ctx(), "a body with no block") is False


def test_downgrade_refuses_keep_item_the_block_does_not_name(tmp_path):
    other = f"- [BLOCKING] [unplanned-scope] Remove the second grid in {GRID}."
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM, other)
    assert us.downgrade_applies(path, _ctx(), KEPT_BODY) is False


def test_downgrade_refuses_ctx_none(tmp_path):
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM)
    assert us.downgrade_applies(path, None, KEPT_BODY) is False


def test_downgrade_refuses_ready_verdict(tmp_path):
    path = _verdict(tmp_path, "READY", KEEP_ITEM)
    assert us.downgrade_applies(path, _ctx(), KEPT_BODY) is False


def test_render_kept_block_shape():
    text = f"- [BLOCKING] [unplanned-scope] Remove the grid in {GRID} and {OTHER}. Next action: move."
    ctx = _ctx(changed=(GRID, OTHER))
    assert us.render_kept_block([_item(text)], ctx) == (
        "<!-- kept-unplanned:begin -->\n"
        "## Kept unplanned changes\n"
        "Phase 5.5 kept these changes. No plan phase covers them, and they were not authored "
        "by a post-plan fix commit. Veto any of them before merging.\n"
        f"- Remove the grid in {GRID} and {OTHER} (`{GRID}`, `{OTHER}`)\n"
        "<!-- kept-unplanned:end -->"
    )
    assert us.render_kept_block([], ctx) == ""


def test_render_kept_block_no_path_cited():
    text = "- [BLOCKING] [unplanned-scope] Remove the grid."
    assert "- Remove the grid. (no path cited)\n" in us.render_kept_block([_item(text)], _ctx())


def test_title_of_strips_markers():
    text = ("- [BLOCKING] [unplanned-scope] Remove or plan the quick-offer button grid. "
            "Next action: move it.")
    assert us.title_of(text) == "Remove or plan the quick-offer button grid"


DIGEST = [
    "**What changed:** a",
    "**Why:** b",
    "**Watch:** none",
    "**Touches:** c",
    "**Machine-authored fixes:** none",
]


def test_watch_line_carries_kept_change():
    rows = fidelity.digest_rows_for_display(DIGEST, {}, kept_titles=("the grid",))
    assert rows[2] == "**Watch:** kept unplanned change: the grid"
    assert sum(row.startswith("**") for row in rows) == 5

    busy = [*DIGEST[:2], "**Watch:** the seam", *DIGEST[3:]]
    rows = fidelity.digest_rows_for_display(busy, {}, kept_titles=("x", "y"))
    assert rows[2] == "**Watch:** the seam; kept unplanned change: x; kept unplanned change: y"

    # Captured from one run of the code before this change.
    assert fidelity.digest_rows_for_display(DIGEST, {"remediation_sha": "abc"}) == [
        "**What changed:** a",
        "**Why:** b",
        "**Watch:** none",
        "**Touches:** c",
        "**Machine-authored fixes:** none (post-plan remediation: abc)",
    ]


def test_kept_block_survives_body_rewrite():
    block = us.render_kept_block([_item()], _ctx())
    guards = f"{removed_guards.BEGIN}\n## Removed guards\n{removed_guards.END}"
    body = f"intro\n\n{guards}\n\n{block}\n\ntail\n"
    assert block in strip_change_blocks(body)
    assert block in removed_guards.upsert_block(body, "")
    assert block in removed_guards.upsert_block(body, f"{removed_guards.BEGIN}\nnew\n{removed_guards.END}")


def test_upsert_kept_block_inserts_after_scope_notes_and_replaces_in_place():
    scoped = f"intro\n\n{removed_guards.SCOPE_NOTES_END}\n\ntail\n"
    first = us.upsert_kept_block(scoped, f"{us.KEPT_BEGIN}\none\n{us.KEPT_END}")
    assert first == (f"intro\n\n{removed_guards.SCOPE_NOTES_END}\n\n"
                     f"{us.KEPT_BEGIN}\none\n{us.KEPT_END}\n\ntail\n")
    second = us.upsert_kept_block(first, f"{us.KEPT_BEGIN}\ntwo\n{us.KEPT_END}")
    assert second == first.replace("\none\n", "\ntwo\n")


def test_body_recheck_ignores_kept_block():
    old = "intro\n"
    new = f"intro\n{us.render_kept_block([_item()], _ctx())}\n"
    assert "Kept unplanned" not in str(llm_calls.body_hunks(old, new))


def _bare_inputs(body, v1="NOT READY", v2=None, tree2=None):
    return types.SimpleNamespace(pr_body=body, fidelity_verdict=v1, fidelity_verdict_2=v2,
                                 fidelity_tree_2=tree2, current_tree="t" * 40)


def _stub_ctx(monkeypatch, value):
    monkeypatch.setattr(runner, "_unplanned_ctx", lambda *a: value)


def _downgrade(inputs, fid, pr=99, log=None):
    res = types.SimpleNamespace(fidelity=fid)
    git = types.SimpleNamespace(diff_vs_base=lambda: "diff")
    runner._apply_kept_downgrade(inputs, res, git, ".", None, "base", pr, log or (lambda m: None))
    return res


def test_apply_downgrade_moves_verdict_2_slot_only(tmp_path, monkeypatch):
    _stub_ctx(monkeypatch, _ctx())
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM)
    inputs = _bare_inputs(KEPT_BODY, v1="NOT READY", v2="NOT READY", tree2="t" * 40)
    logs: list[str] = []
    res = _downgrade(inputs, {"verdict_path": path, "verdict_2": "NOT READY"}, log=logs.append)
    assert (inputs.fidelity_verdict, inputs.fidelity_verdict_2) == ("NOT READY", "READY WITH NOTES")
    assert res.fidelity["verdict_2"] == "NOT READY"
    assert res.fidelity["downgraded_unplanned"] is True
    assert "phase5.5: blocking findings are all kept unplanned scope; declared in PR body" in logs
    with open(path, encoding="utf-8") as fh:
        assert "NOT READY" in fh.read()


def test_apply_downgrade_reads_verdict_1_when_verdict_2_is_stale(tmp_path, monkeypatch):
    _stub_ctx(monkeypatch, _ctx())
    monkeypatch.setattr(fidelity, "verdict_path",
                        lambda pr: _verdict(tmp_path, "NOT READY", KEEP_ITEM, name="v1.md"))
    stale = _verdict(tmp_path, "NOT READY", UNTAGGED_ITEM, name="v2.md")
    inputs = _bare_inputs(KEPT_BODY, v1="NOT READY", v2="NOT READY", tree2="s" * 40)
    _downgrade(inputs, {"verdict_path": stale})
    assert (inputs.fidelity_verdict, inputs.fidelity_verdict_2) == ("READY WITH NOTES", "NOT READY")


def test_apply_downgrade_prefers_repin_verdict_file(tmp_path, monkeypatch):
    _stub_ctx(monkeypatch, _ctx())
    old = _verdict(tmp_path, "NOT READY", KEEP_ITEM, name="old.md")
    repin = _verdict(tmp_path, "NOT READY", UNTAGGED_ITEM, name="repin.md")
    inputs = _bare_inputs(KEPT_BODY, v2="NOT READY", tree2="t" * 40)
    _downgrade(inputs, {"verdict_path": old, "same_run_repin": {"verdict_path": repin}})
    assert inputs.fidelity_verdict_2 == "NOT READY"


def test_apply_downgrade_leaves_mixed_blockers_alone(tmp_path, monkeypatch):
    _stub_ctx(monkeypatch, _ctx())
    path = _verdict(tmp_path, "NOT READY", KEEP_ITEM, UNTAGGED_ITEM)
    inputs = _bare_inputs(KEPT_BODY, v2="NOT READY", tree2="t" * 40)
    res = _downgrade(inputs, {"verdict_path": path})
    assert inputs.fidelity_verdict_2 == "NOT READY"
    assert "downgraded_unplanned" not in res.fidelity


def test_apply_downgrade_builds_no_ctx_for_untagged_verdict(tmp_path, monkeypatch):
    def _boom(*a):
        raise AssertionError("no tagged finding, so no context read")
    monkeypatch.setattr(runner, "_unplanned_ctx", _boom)
    path = _verdict(tmp_path, "NOT READY", UNTAGGED_ITEM)
    _downgrade(_bare_inputs(KEPT_BODY, v2="NOT READY", tree2="t" * 40), {"verdict_path": path})


def test_unplanned_ctx_none_on_detector_failure(monkeypatch):
    monkeypatch.setattr(runner.removed_guards, "run_detector", lambda *a: (False, []))
    logs: list[str] = []
    git = types.SimpleNamespace(changed_files=lambda: [GRID])
    assert runner._unplanned_ctx(".", git, None, "base", "diff", logs.append) is None
    assert "phase5.5: unplanned-scope context unavailable" in logs


def test_unplanned_ctx_none_when_plan_unreadable(monkeypatch, tmp_path):
    monkeypatch.setattr(runner.removed_guards, "run_detector", lambda *a: (True, []))
    plan = types.SimpleNamespace(found=True, path=str(tmp_path / "missing.md"))
    logs: list[str] = []
    git = types.SimpleNamespace(changed_files=lambda: [GRID])
    assert runner._unplanned_ctx(".", git, plan, "base", "diff", logs.append) is None
    assert "phase5.5: unplanned-scope context unavailable" in logs


# ---- The round loop, driven end to end through _run_fidelity -----------------------------

class _PromptLlm(FixtureLlm):
    def __init__(self, canned):
        super().__init__(UsageLedger(), canned)
        self.tooled_prompts: dict[str, str] = {}

    def call_tooled(self, purpose, model, prompt, **kw):
        self.tooled_prompts[purpose] = prompt
        return super().call_tooled(purpose, model, prompt, **kw)


@pytest.fixture()
def loop_env(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    shim = bindir / "git"
    shim.write_text(GIT_SHIM)
    shim.chmod(shim.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    monkeypatch.setattr(runner.removed_guards, "run_detector", lambda *a: (True, []))
    yield
    for suffix in (99, "99-2", "99-3", "99-4"):
        path = fidelity.verdict_path(suffix)
        if os.path.exists(path):
            os.unlink(path)


def _drive(tmp_path, canned):
    llm = _PromptLlm(canned)
    git = ReplayGit({"slug": "demo", "worktree_diff": "",
                     "diff": f"diff --git a/{GRID} b/{GRID}\n",
                     "head_trees": ["a" * 40, "b" * 40]})
    gh = RecordingGh(str(tmp_path))
    res = types.SimpleNamespace(fidelity={}, reused_from={})
    plan = types.SimpleNamespace(found=False, path="", auto_merge_false=False)
    logs: list[str] = []
    runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, plan, "diff", "body", 99,
                         "dead" * 10, "a" * 40, False, logs.append, res)
    return llm, gh, res, logs


def _review(*items):
    return "## FINDINGS\n" + "\n".join(items) + "\n\nNOT READY\n"


def test_kept_only_verdict_spawns_no_fixer_and_declares(tmp_path, loop_env):
    llm, gh, res, logs = _drive(tmp_path, {"plan-fidelity-review": _review(KEEP_ITEM)})
    assert "fidelity-remediation" not in llm.tooled_prompts
    assert res.fidelity["rounds"][0]["outcome"] == "empty-work-list"
    assert res.fidelity["kept_unplanned"] == [f"Remove or plan the button grid in {GRID}"]
    assert res.fidelity["held_unplanned"] == []
    assert res.fidelity["verdict_1"] == "NOT READY"
    assert us.KEPT_BEGIN in gh.pr_body()
    assert "phase5.5: unplanned-scope kept=1 held=0" in logs


def test_mixed_verdict_cuts_kept_finding_from_fixer_prompt(tmp_path, loop_env):
    llm, gh, res, _ = _drive(tmp_path, {
        "plan-fidelity-review": _review(KEEP_ITEM, UNTAGGED_ITEM),
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "READY\n",
    })
    prompt = llm.tooled_prompts["fidelity-remediation"]
    assert UNTAGGED_ITEM in prompt
    assert "[unplanned-scope]" not in prompt
    assert "Delete no code the work list does not name." in prompt
    assert res.fidelity["rounds"][0]["work_list_sizes"]["12"] == 1
    assert us.KEPT_BEGIN in gh.pr_body()


def test_kept_block_restored_when_fixer_drops_it(tmp_path, loop_env):
    """A round's rewrite dropped the block, and the last re-review still names the change."""
    _, gh, res, _ = _drive(tmp_path, {
        "plan-fidelity-review": _review(KEEP_ITEM, UNTAGGED_ITEM),
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": _review(KEEP_ITEM),
    })
    assert us.KEPT_BEGIN in gh.pr_body()
    assert res.fidelity["verdict_2"] == "NOT READY"


def test_untagged_verdict_adds_no_fidelity_keys_and_no_note(tmp_path, loop_env):
    llm, _, res, _ = _drive(tmp_path, {
        "plan-fidelity-review": _review(UNTAGGED_ITEM),
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "READY\n",
    })
    assert "kept_unplanned" not in res.fidelity
    assert "held_unplanned" not in res.fidelity
    assert "cut them from this verdict" not in llm.tooled_prompts["fidelity-remediation"]


# ---- Review-fix coverage: stale block, git failure, path boundaries ----------------------

class _BodyGh:
    def __init__(self, body):
        self.body, self.edits = body, []

    def pr_body_fresh(self):
        return self.body

    def pr_edit_body(self, pr, new):
        self.edits.append(new)
        self.body = new


def test_stale_kept_block_dropped_when_nothing_kept():
    gh = _BodyGh(KEPT_BODY)
    work = [{"hold": "12", "text": UNTAGGED_ITEM}]
    res = types.SimpleNamespace(fidelity={})
    out, withheld = runner._partition_unplanned(
        work, [], ".", None, gh, None, "base", KEPT_BODY, 7, res, lambda m: None)
    assert out == work and withheld == []
    assert gh.edits and us.KEPT_BEGIN not in gh.edits[-1]


def test_stale_kept_block_dropped_when_tagged_item_goes_to_fixer(monkeypatch):
    # Tagged, but classified `remove`: nothing is kept, so the old block must go.
    _stub_ctx(monkeypatch, _ctx(subjects={GRID: [fidelity.REMEDIATION_COMMIT_MSG]}))
    gh = _BodyGh(KEPT_BODY)
    work = [{"hold": "12", "text": KEEP_ITEM}]
    res = types.SimpleNamespace(fidelity={})
    git = types.SimpleNamespace(diff_vs_base=lambda: "diff")
    runner._partition_unplanned(work, [], ".", git, gh, None, "base", KEPT_BODY, 7, res,
                                lambda m: None)
    assert gh.edits and us.KEPT_BEGIN not in gh.edits[-1]


def test_diff_vs_base_failure_yields_no_ctx(monkeypatch):
    def _raise():
        raise runner.HarnessError("git-down", "boom")
    git = types.SimpleNamespace(diff_vs_base=_raise, changed_files=lambda: [GRID])
    logs: list[str] = []
    assert runner._unplanned_ctx(".", git, None, "base", None, logs.append) is None
    assert "phase5.5: unplanned-scope context unavailable" in logs


def test_partition_survives_diff_vs_base_failure():
    def _raise():
        raise runner.HarnessError("git-down", "boom")
    git = types.SimpleNamespace(diff_vs_base=_raise, changed_files=lambda: [GRID])
    work = [{"hold": "12", "text": KEEP_ITEM}]
    res = types.SimpleNamespace(fidelity={})
    out, withheld = runner._partition_unplanned(
        work, [], ".", git, _BodyGh(""), None, "base", "", 7, res, lambda m: None)
    assert out == work and withheld == []


def test_cited_paths_respects_path_boundaries():
    short = "classes/Other.php"
    text = f"- [BLOCKING] [unplanned-scope] Change in {OTHER}."
    assert us.cited_paths(text, [OTHER, short]) == [OTHER]
