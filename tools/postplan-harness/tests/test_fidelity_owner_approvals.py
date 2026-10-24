"""Phase 5.5 honors a trusted owner-approval record: the harness, never the reviewer,
downgrades a cited [BLOCKING] item, and only after checking the record against HEAD."""
import ast
import glob
import json
import os
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity, owner_approvals as oa
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger
from test_runner_replay import CANNED, _fixture

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

RID = "oad-0123abcd"
PATH = "ibl5/x.php"
KEEP = "if ($teamId === 0) { return; }"
GONE = "legacy_call($teamId);"
ITEM_A = f"- [BLOCKING] The guard in {PATH} deviates from the plan."
CITE = f"  approved-by-owner: {RID}"
DIGEST = "## DIGEST\n\n**What changed:** a synthetic change\napproved-by-owner: " + RID + "\n"


def _snap(rid=RID, path=PATH, keep=(KEEP,), gone=(GONE,)):
    payload = {"schema": 1, "id": rid, "summary": "owner approved deviation",
               "files": [{"path": path, "keep": list(keep), "gone": list(gone)}]}
    body = f"{oa.APPROVAL_MARKER}\n```json\n{json.dumps(payload)}\n```\n"
    comment = {"id": 11, "body": body, "login": "a-jay85", "type": "User"}

    class _Gh:
        def pr_issue_comments(self, pr):
            return [comment]

    return oa.snapshot(_Gh(), 5)


def _git(files=None, error=False):
    fx = {"files_at": {f"HEAD:{p}": t for p, t in (files if files is not None
                                                   else {PATH: f"<?php\n  {KEEP}\n"}).items()}}
    if error:
        fx["files_at_error"] = True
    return ReplayGit(fx)


def _verdict(tmp_path, items, word="NOT READY", digest=DIGEST, name="v.md"):
    text = "## 6d checks\n\nCheck 3 deviates.\n\n## FINDINGS\n\n" + "\n".join(items) + \
        f"\n\n{word}\n\n{digest}" if word else \
        "## 6d checks\n\n## FINDINGS\n\n" + "\n".join(items) + f"\n\n{digest}"
    p = tmp_path / name
    p.write_text(text)
    return str(p)


def _blocking_count(path):
    body = open(path).read().split(fidelity.DIGEST_CUT)[0]
    return body.count("[BLOCKING]")


def test_cited_trusted_record_with_anchors_at_head_downgrades_to_ready_with_notes(tmp_path):
    p = _verdict(tmp_path, [ITEM_A, CITE])
    out = fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert out["downgraded"] == [RID]
    assert (out["verdict_before"], out["verdict_after"]) == ("NOT READY", "READY WITH NOTES")
    assert fidelity.parse_verdict(p) == "READY WITH NOTES"
    body = open(p).read()
    assert f"- [NOTE] The guard in {PATH} deviates from the plan. " \
           f"(owner-approved {RID}, downgraded by the harness)" in body
    assert "[BLOCKING]" not in body


def test_same_deviation_without_citation_stays_not_ready(tmp_path):
    p = _verdict(tmp_path, [ITEM_A])
    before = open(p).read()
    out = fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert out["downgraded"] == []
    assert fidelity.parse_verdict(p) == "NOT READY"
    assert open(p).read() == before


def test_citation_of_id_not_in_snapshot_stays_not_ready(tmp_path):
    p = _verdict(tmp_path, [ITEM_A, "  approved-by-owner: oad-ffffffff"])
    out = fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert out["downgraded"] == []
    assert out["kept"] == [("oad-ffffffff", "untrusted-id")]
    assert fidelity.parse_verdict(p) == "NOT READY"
    assert _blocking_count(p) == 1


def test_cited_record_whose_keep_line_is_missing_at_head_stays_not_ready(tmp_path):
    p = _verdict(tmp_path, [ITEM_A, CITE])
    out = fidelity.apply_owner_approvals(p, (_snap(), _git({PATH: "<?php\nother();\n"})))
    assert out["kept"] == [(RID, "anchors-not-at-head")]
    assert fidelity.parse_verdict(p) == "NOT READY"
    assert _blocking_count(p) == 1


def test_cited_record_whose_gone_line_is_back_at_head_stays_not_ready(tmp_path):
    p = _verdict(tmp_path, [ITEM_A, CITE])
    head = {PATH: f"<?php\n{KEEP}\n   {GONE}\n"}
    out = fidelity.apply_owner_approvals(p, (_snap(), _git(head)))
    assert out["kept"] == [(RID, "anchors-not-at-head")]
    assert fidelity.parse_verdict(p) == "NOT READY"


def test_citation_naming_wrong_path_stays_not_ready(tmp_path):
    p = _verdict(tmp_path, ["- [BLOCKING] The guard in ibl5/other.php deviates.", CITE])
    out = fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert out["kept"] == [(RID, "path-mismatch")]
    assert fidelity.parse_verdict(p) == "NOT READY"
    assert _blocking_count(p) == 1


def test_one_cited_one_uncited_keeps_not_ready_but_downgrades_the_cited_item(tmp_path):
    uncited = "- [BLOCKING] An unrelated missing file in ibl5/y.php."
    p = _verdict(tmp_path, [ITEM_A, CITE, uncited])
    out = fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert out["downgraded"] == [RID]
    assert out["verdict_after"] == "NOT READY"
    assert fidelity.parse_verdict(p) == "NOT READY"
    body = open(p).read()
    assert "- [NOTE] The guard" in body
    assert uncited in body
    assert _blocking_count(p) == 1


def test_unreadable_head_file_writes_nothing(tmp_path):
    uncited = "- [BLOCKING] Another finding in ibl5/y.php."
    p = _verdict(tmp_path, [ITEM_A, CITE, uncited])
    before = open(p).read()
    logs = []
    out = fidelity.apply_owner_approvals(p, (_snap(), _git(error=True)), logs.append)
    assert out["downgraded"] == []
    assert out["kept"] == [(RID, "unreadable")]
    assert open(p).read() == before
    assert not os.path.exists(p + ".pre-approval")
    assert logs


def test_approvals_none_leaves_file_byte_identical(tmp_path):
    p = _verdict(tmp_path, [ITEM_A, CITE])
    before = open(p, "rb").read()
    out = fidelity.apply_owner_approvals(p, None)
    assert out["downgraded"] == []
    assert open(p, "rb").read() == before
    assert not os.path.exists(p + ".pre-approval")
    empty = fidelity.apply_owner_approvals(p, (oa.empty_snapshot(), _git()))
    assert empty["downgraded"] == []
    assert open(p, "rb").read() == before


def test_ready_verdict_untouched_and_indeterminate_stays_none(tmp_path):
    ready = _verdict(tmp_path, [ITEM_A, CITE], word="READY", name="ready.md")
    before = open(ready, "rb").read()
    fidelity.apply_owner_approvals(ready, (_snap(), _git()))
    assert open(ready, "rb").read() == before
    assert fidelity.parse_verdict(ready) == "READY"
    none = _verdict(tmp_path, [ITEM_A, CITE], word="", name="none.md")
    before = open(none, "rb").read()
    out = fidelity.apply_owner_approvals(none, (_snap(), _git()))
    assert fidelity.parse_verdict(none) is None
    assert out["verdict_before"] is None and out["downgraded"] == []
    assert open(none, "rb").read() == before


def test_citation_below_digest_cut_is_ignored(tmp_path):
    p = _verdict(tmp_path, [ITEM_A])      # DIGEST carries a citation line of its own
    assert RID in open(p).read().split(fidelity.DIGEST_CUT)[1]
    before = open(p).read()
    out = fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert out["downgraded"] == []
    assert open(p).read() == before
    assert fidelity.parse_verdict(p) == "NOT READY"


def test_malformed_id_in_citation_is_ignored(tmp_path):
    p = _verdict(tmp_path, [ITEM_A, "  approved-by-owner: oad-ZZZ"])
    before = open(p).read()
    out = fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert out["downgraded"] == [] and out["kept"] == []
    assert open(p).read() == before


def test_pre_approval_copy_written_before_rewrite(tmp_path):
    p = _verdict(tmp_path, [ITEM_A, CITE])
    before = open(p).read()
    fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert open(p + ".pre-approval").read() == before
    assert open(p).read() != before


def test_digest_is_reattached_byte_for_byte(tmp_path):
    p = _verdict(tmp_path, [ITEM_A, CITE])
    tail = open(p).read().split(fidelity.DIGEST_CUT, 1)[1]
    fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert open(p).read().split(fidelity.DIGEST_CUT, 1)[1] == tail


def test_unmarked_cited_item_is_downgraded(tmp_path):
    p = _verdict(tmp_path, [f"- The guard in {PATH} deviates.", CITE])
    out = fidelity.apply_owner_approvals(p, (_snap(), _git()))
    assert out["downgraded"] == [RID]
    assert f"- [NOTE] The guard in {PATH} deviates. (owner-approved" in open(p).read()
    assert fidelity.parse_verdict(p) == "READY WITH NOTES"


# --- runner wiring ------------------------------------------------------------

def test_every_fidelity_verdict_call_in_runner_passes_approvals():
    tree = ast.parse(open(os.path.join(ROOT, "runner.py")).read())
    names = {"review", "re_review", "body_recheck"}
    calls = [n for n in ast.walk(tree)
             if isinstance(n, ast.Call) and isinstance(n.func, ast.Attribute)
             and isinstance(n.func.value, ast.Name) and n.func.value.id == "fidelity"
             and n.func.attr in names]
    assert len(calls) >= 4
    missing = [c.lineno for c in calls if "approvals" not in {k.arg for k in c.keywords}]
    assert missing == []


def test_review_context_present_in_packet_when_records_exist(tmp_path, monkeypatch):
    monkeypatch.setattr(fidelity, "_find_procedure", lambda *a, **k: "PROCEDURE")
    out = tmp_path / "out"
    out.mkdir()
    ctx = oa.review_context(_snap())
    assert ctx.startswith("## Owner-approved deviations (trusted, read from the PR by the harness)")
    assert "approved-by-owner: <id>" in ctx and RID in ctx
    packet = fidelity.build_packet(str(out), "deadbeef", "a" * 40,
                                   types.SimpleNamespace(found=False, path=""),
                                   "diff", "body", 42, False, worktree=str(tmp_path),
                                   extra_context="\n\n" + ctx)
    joined = "".join(open(f).read() for f in glob.glob(os.path.join(packet, "*")))
    assert "Owner-approved deviations (trusted" in joined
    assert oa.review_context(oa.empty_snapshot()) == ""


# --- run() replay -------------------------------------------------------------

def _owner_comment():
    payload = {"schema": 1, "id": RID, "summary": "owner approved deviation",
               "files": [{"path": PATH, "keep": [KEEP], "gone": []}]}
    body = f"{oa.APPROVAL_MARKER}\n```json\n{json.dumps(payload)}\n```\n"
    return {"id": 11, "body": body, "login": "a-jay85", "type": "User"}


class _ScriptedLlm(FixtureLlm):
    def __init__(self, ledger, canned, scripts):
        super().__init__(ledger, dict(canned))
        self.scripts = {k: list(v) for k, v in scripts.items()}

    def call_tooled(self, purpose, model, prompt, **kw):
        queue = self.scripts.get(purpose)
        if queue:
            self.canned[purpose] = queue.pop(0)
        return super().call_tooled(purpose, model, prompt, **kw)


def _doc(items):
    return ("## 6d checks\n\nCheck 3 deviates.\n\n## FINDINGS\n\n" + "\n".join(items)
            + "\n\nNOT READY\n\n## DIGEST\n\n**What changed:** a synthetic replay change\n")


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


def _replay(tmp_path, pr, items):
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""
    llm = _ScriptedLlm(UsageLedger(), canned, {
        "plan-fidelity-review": [_doc(items)],
        "fidelity-remediation": ["edits made"],
    })
    fx = _fixture(pr_number=pr, pr_issue_comments=[_owner_comment()],
                  files_at={f"HEAD:{PATH}": f"<?php\n{KEEP}\n"})
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    res = runner.run(fx, str(tmp_path / "out"), llm, mode="replay")
    return res, fidelity.verdict_path(pr)


def test_run_replay_downgrades_only_cited_trusted_finding(tmp_path, verdict_cleanup):
    pr = 7611
    verdict_cleanup(pr)
    uncited = "- [BLOCKING] An unrelated missing file in ibl5/y.php."
    res, path = _replay(tmp_path, pr, [ITEM_A, CITE, uncited])
    assert res.fidelity["verdict_1"] == "NOT READY"
    assert fidelity.parse_verdict(path) == "NOT READY"
    assert _blocking_count(path) == 1
    assert "- [NOTE] The guard" in open(path).read()
    assert res.fidelity["owner_downgrades"][0]["downgraded"] == [RID]

    pr2 = 7612
    verdict_cleanup(pr2)
    res2, path2 = _replay(tmp_path / "second", pr2, [ITEM_A, CITE])
    assert res2.fidelity["verdict_1"] == "READY WITH NOTES"
    assert fidelity.parse_verdict(path2) == "READY WITH NOTES"
    assert 12 not in {c.number for c in res2.arm.holds}
