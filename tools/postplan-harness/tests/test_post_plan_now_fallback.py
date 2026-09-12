import os, re, shutil, subprocess

REPO = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
PPN = os.path.join(REPO, "bin", "post-plan-now")

def _fb(code):
    r = subprocess.run(
        ["bash", "-c", f'source "{PPN}" >/dev/null 2>&1; should_fallback {code}; echo $?'],
        capture_output=True, text=True)
    return r.stdout.strip().splitlines()[-1]

def test_success_and_sentinel_do_not_fall_back():
    assert _fb(0) == "1"   # success → should_fallback returns 1 (false) → no skill fallback
    assert _fb(3) == "1"   # rebase-conflict sentinel → NO fallback (the fix)

def test_generic_failures_fall_back():            # negative path: real failures still degrade
    assert _fb(1) == "0"
    assert _fb(2) == "0"
    assert _fb(130) == "0"

def _bash(script):
    return subprocess.run(["bash", "-c", script], capture_output=True, text=True)


def test_shq_survives_a_second_shell_parse():
    """shq() output must reproduce its input byte-for-byte when a SECOND shell parses it.

    $CMD is re-parsed by `/bin/bash -lc` inside the launchd plist, so anything the
    prompt carries gets a second round of shell evaluation. Grepping the source for
    backticks would not catch this — only a real round-trip does.
    """
    hostile = [
        "plain",
        "has `backticks` here",
        "has $(cmd) and $VAR",
        "it's got a single quote",
        r"a\b backslash",
        "<slug>-N.md",
        "mix: `x` $y 'z' \\w",
    ]
    for s in hostile:
        script = f'source "{PPN}" >/dev/null 2>&1; q=$(shq "$1"); bash -c "printf %s $q"'
        r = subprocess.run(["bash", "-c", script, "_", s], capture_output=True, text=True)
        assert r.stderr == "", f"{s!r}: second parse wrote to stderr: {r.stderr!r}"
        assert r.stdout == s, f"{s!r}: round-tripped to {r.stdout!r}"


def test_plan_blind_prompt_reaches_the_model_intact():
    """The real plan-blind prompt literal, round-tripped through the far-side shell.

    Regression: it carried literal backticks around <slug>-N.md, was embedded as
    `claude -p \\"$PROMPT\\"`, and `/bin/bash -lc` ran the backticks as command
    substitution — logging `slug: No such file or directory` and silently deleting the
    plan-disambiguation clause from the prompt the model received.
    """
    import re
    src = open(PPN).read()
    prompts = re.findall(r'^\s*(PROMPT="(?:[^"\\]|\\.)*")$', src, re.M)
    assert len(prompts) == 2, f"expected the --plan and plan-blind prompts, got {len(prompts)}"
    plan_blind = prompts[1]
    assert "<slug>-N.md" in plan_blind, "plan-blind prompt no longer names the variant rule"

    script = (f'source "{PPN}" >/dev/null 2>&1\n'
              f'{plan_blind}\n'
              'q=$(shq "$PROMPT")\n'
              'bash -c "printf %s $q"\n')
    r = _bash(script)
    assert r.stderr == "", f"far-side shell wrote to stderr: {r.stderr!r}"
    assert "`<slug>-N.md`" in r.stdout, f"backticked span did not survive: {r.stdout!r}"
    assert "never ask questions" in r.stdout


def test_embed_sites_are_shell_quoted():
    src = open(PPN).read()
    assert 'claude -p $(shq "$PROMPT")' in src      # not \"$PROMPT\" — see shq() header
    assert r'claude -p \"$PROMPT\"' not in src
    assert '--plan $(shq "$PLAN_OVERRIDE")' in src


def test_plist_wiring_regression():
    src = open(PPN).read()
    assert "--live || " not in src               # old unconditional fallback chain removed
    assert "should_fallback" in src              # fallback now gated by the tested function
    assert r"rc=\$?" in src                       # harness exit code captured for the gate
    assert r'\"\$rc\" = 3' in src                 # fail-closed notice is rc=3-only (elif)
    # New wiring assertions
    assert "PLAN_ARG" in src
    assert "--live${PLAN_ARG}" in src
    assert "${PLAN_OVERRIDE:-$HOME/claude-plans/$SLUG.md}" in src
    assert "do NOT derive the plan from the branch slug" in src


def test_plan_override_missing_file_aborts(tmp_path):
    r = subprocess.run(
        ["bash", "bin/post-plan-now", "--plan", "/nonexistent-plan.md"],
        capture_output=True, text=True, cwd=REPO)
    assert r.returncode == 1
    assert "does not exist — aborting" in r.stderr


def test_plan_arg_errors():
    r1 = subprocess.run(
        ["bash", "bin/post-plan-now", "--plan"],
        capture_output=True, text=True, cwd=REPO)
    assert r1.returncode == 1
    assert "--plan requires a path argument" in r1.stderr

    r2 = subprocess.run(
        ["bash", "bin/post-plan-now", "--pln", "/tmp/x.md"],
        capture_output=True, text=True, cwd=REPO)
    assert r2.returncode == 1
    assert "unknown argument" in r2.stderr


def test_arg_order_independent(tmp_path):
    for args in (
        ["--auto", "--plan", "/nonexistent.md"],
        ["--plan", "/nonexistent.md", "--auto"],
        ["--plan=/nonexistent.md"],
    ):
        r = subprocess.run(["bash", "bin/post-plan-now"] + args,
                           capture_output=True, text=True, cwd=REPO)
        assert r.returncode == 1
        assert "does not exist — aborting" in r.stderr, \
            f"args {args}: got {r.stderr!r}"


def test_plan_override_valid_path_passes_validation(tmp_path):
    plan = tmp_path / "myplan.md"
    plan.write_text("---\nauto_merge: false\n---\n# My Plan\n")
    r = subprocess.run(
        ["bash", PPN, "--plan", str(plan)],
        capture_output=True, text=True, cwd=str(tmp_path))
    assert r.returncode == 1
    assert "does not exist — aborting" not in r.stderr
    assert "not inside a git repo" in r.stderr



def test_skill_block_matches_resolver(tmp_path):
    """SKILL.md Phase 1 shell block and locate_plan agree on same corpus."""
    import sys as _sys
    _sys.path.insert(0, os.path.join(REPO, "tools", "postplan-harness"))
    from harness.planfile import locate_plan

    plans = tmp_path
    for name in ("s.md", "s-shared-context.md", "s-2.md"):
        (plans / name).write_text("---\nauto_merge: false\n---\n# " + name + "\n")

    skill_md = os.path.join(REPO, ".claude", "skills", "post-plan", "SKILL.md")
    skill_src = open(skill_md).read()
    # Extract the resolution shell block between the two bash fences in Phase 1
    import re
    m = re.search(r"```bash\n(# Authoritative.*?PLAN_VARIANT_SELECTED.*?\n)```", skill_src, re.S)
    assert m, "SKILL.md Phase 1 shell block not found"
    shell_block = m.group(1)

    # Use plan-resolve.sh test seams: PLAN_DIR and PLAN_SLUG override slug derivation.
    env = os.environ.copy()
    env["PLAN_DIR"] = str(plans)
    env["PLAN_SLUG"] = "s"
    script = (
        f"cd {REPO!r}\n"
        f'mkdir -p "{tmp_path!s}"\n'
        + shell_block
        + '\necho "PLAN_FILE=$PLAN_FILE"\n'
        + '\necho "BEST=$BEST"\n'
    )
    r = subprocess.run(["bash", "-c", script], capture_output=True, text=True, env=env)
    assert r.returncode == 0, r.stderr
    # Skill should pick s-2.md (highest)
    assert "s-2.md" in r.stdout, f"skill output: {r.stdout!r}"

    # Resolver should also pick s-2.md
    info = locate_plan("s", plans_dir=str(tmp_path))
    assert info.path.endswith("s-2.md"), f"resolver picked: {info.path}"


def test_skill_block_plan_blind(tmp_path):
    """SKILL.md shell block on empty plans dir prints nothing, leaves PLAN_FILE empty."""
    skill_md = os.path.join(REPO, ".claude", "skills", "post-plan", "SKILL.md")
    skill_src = open(skill_md).read()
    import re
    m = re.search(r"```bash\n(# Authoritative.*?PLAN_VARIANT_SELECTED.*?\n)```", skill_src, re.S)
    assert m, "SKILL.md Phase 1 shell block not found"
    shell_block = m.group(1)

    plans_dir = str(tmp_path / "empty-plans")
    os.makedirs(plans_dir, exist_ok=True)
    # Use plan-resolve.sh test seams: PLAN_DIR and PLAN_SLUG override slug derivation.
    env = os.environ.copy()
    env["PLAN_DIR"] = plans_dir
    env["PLAN_SLUG"] = "no-such-slug"
    script = (
        f"cd {REPO!r}\n"
        + shell_block
        + '\necho "PLAN_FILE=${PLAN_FILE:-}"\n'
        + 'if [ -z "${PLAN_FILE:-}" ]; then echo "PLAN_FOUND=none"; fi\n'
    )
    r = subprocess.run(["bash", "-c", script], capture_output=True, text=True, env=env)
    assert r.returncode == 0, r.stderr
    assert "PLAN_VARIANT_SELECTED" not in r.stdout
    assert "PLAN_FOUND=none" in r.stdout


def _fixture_repo(tmp_path, dirty_default=True, dirty=None):
    """A dirty worktree on a non-master branch — enough to clear post-plan-now's guards.

    CI has no global git identity, so the fixture sets its own.

    `dirty_default=False` leaves `f.txt` clean so a caller can choose exactly which paths
    are dirty; `f.txt` is a production path as far as the exit-5 guard is concerned, so the
    default dirtying would mask a test-only working set.

    `dirty` maps repo-relative paths to their post-branch content. Those paths are COMMITTED
    into the base commit first and only then rewritten, so they show up as tracked
    modifications: the "nothing to ship" guard above tests `git diff --quiet`, which an
    untracked file does not trip, and zero commits ahead of origin/master is exactly the
    shape the exit-5 guard must handle.
    """
    repo = tmp_path / "wt"
    repo.mkdir()
    run = lambda *a: subprocess.run(a, cwd=repo, check=True, capture_output=True)
    run("git", "init", "-q", "-b", "master")
    run("git", "config", "user.email", "test@example.com")
    run("git", "config", "user.name", "Test")
    (repo / "f.txt").write_text("one\n")
    for rel in (dirty or {}):
        p = repo / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text("base\n")
    run("git", "add", "-A")
    run("git", "commit", "-qm", "base")
    run("git", "checkout", "-qb", "some-feature")
    # Without an origin/master ref the `origin/master...HEAD` leg of the guard's changed-set
    # union errors out, and every guard test below would pass for the wrong reason.
    run("git", "update-ref", "refs/remotes/origin/master", "master")
    if dirty_default:
        (repo / "f.txt").write_text("two\n")  # dirty tree ⇒ "nothing to ship" guard passes
    for rel, text in (dirty or {}).items():
        (repo / rel).write_text(text)         # tracked-and-modified, so `git diff` sees it
    return repo

def _run_ppn(tmp_path, args=(), extra_env=None, dirty=None):
    """Run bin/post-plan-now with launchctl + gh stubbed and HOME redirected.

    Returns the raw CompletedProcess — the guard tests need the return code, which
    _generate_cmd asserts away.

    The `gh` stub prints $FAKE_PR_NUMBER when it is non-empty and nothing otherwise, so a
    test selects the PR-exists / no-PR case purely through the environment.
    """
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    (shim / "gh").write_text(
        '#!/bin/sh\nif [ -n "${FAKE_PR_NUMBER:-}" ]; then echo "$FAKE_PR_NUMBER"; fi\nexit 0\n')
    (shim / "gh").chmod(0o755)
    harness = tmp_path / "fake-harness"; harness.mkdir()
    (harness / "run").write_text("#!/bin/sh\nexit 0\n")
    (harness / "run").chmod(0o755)

    env = dict(os.environ, HOME=str(home), HARNESS=str(harness),
               PATH=f"{shim}:{os.environ['PATH']}")
    env.pop("POST_PLAN_SKILL", None)
    env.pop("FAKE_PR_NUMBER", None)
    env.update(extra_env or {})
    repo = _fixture_repo(tmp_path, dirty_default=dirty is None, dirty=dirty)
    return subprocess.run(["bash", PPN, *args], cwd=repo, env=env,
                          capture_output=True, text=True)

def _generate_cmd(tmp_path, extra_env=None):
    """Run bin/post-plan-now with launchctl stubbed and HOME redirected; return $CMD."""
    home = tmp_path / "home"
    r = _run_ppn(tmp_path, extra_env=extra_env)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"

    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, f"expected one generated plist, got {plists}"
    body = plists[0].read_text()
    cmd = re.search(r"<string>(export PATH=.*?)</string>", body, re.S).group(1)
    return cmd.replace("&lt;", "<").replace("&gt;", ">").replace("&amp;", "&")

def test_generated_cmd_tests_rc4_before_should_fallback(tmp_path):
    cmd = _generate_cmd(tmp_path)
    i4 = cmd.index('if [ "$rc" = 4 ]; then')
    ifb = cmd.index('elif should_fallback "$rc"; then')
    i3 = cmd.index('elif [ "$rc" = 3 ]; then')
    assert i4 < ifb < i3, "rc=4 must be tested before should_fallback, and rc=3 last"

def test_generated_cmd_carries_two_distinct_claude_invocations(tmp_path):
    cmd = _generate_cmd(tmp_path)
    assert cmd.count("caffeinate -s claude -p ") == 2
    assert "RESUMING at Phase 5.5" in cmd            # the rc=4 prompt
    assert "then execute every phase" in cmd         # the untouched fallback prompt
    assert cmd.index("RESUMING at Phase 5.5") < cmd.index("then execute every phase")

def test_generated_cmd_resume_prompt_is_single_quoted(tmp_path):
    """Free-form text must cross the /bin/bash -lc boundary inside single quotes."""
    cmd = _generate_cmd(tmp_path)
    m = re.search(r"caffeinate -s claude -p ('.*?RESUMING at Phase 5\.5.*?') --dangerously", cmd, re.S)
    assert m, "resume prompt is not single-quoted at the embed site"
    assert '\\"' not in m.group(1)

def test_gate_selects_the_right_arm_per_rc(tmp_path):
    """Branch selection, exercised on the GENERATED chain with the claude calls stubbed.

    Only the two `caffeinate -s claude … --name "…"` spans are replaced (with echo
    markers) and the harness segment with a literal rc; every condition under test —
    `[ "$rc" = 4 ]`, `should_fallback "$rc"`, `[ "$rc" = 3 ]` — is the generated text.
    """
    cmd = _generate_cmd(tmp_path)
    gate = cmd.split("rc=$?; ", 1)[1].split("; }; pp_rc=", 1)[0]
    seen = []
    def _stub(_m):
        seen.append(1)
        return f'echo "RAN-{len(seen)}"'
    gate = re.sub(r'CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS=0.*?--name "[^"]*"', _stub, gate)
    assert len(seen) == 2

    for rc, expect in ((4, "RAN-1"), (1, "RAN-2"), (2, "RAN-2"),
                       (0, None), (3, None)):
        r = subprocess.run(
            ["bash", "-c", f'source "{PPN}" >/dev/null 2>&1; rc={rc}; {gate}'],
            capture_output=True, text=True)
        assert r.returncode == 0, f"rc={rc}: {r.stderr!r}"
        if expect:
            assert expect in r.stdout, f"rc={rc}: expected {expect}, got {r.stdout!r}"
        else:
            assert "RAN-" not in r.stdout, f"rc={rc} must launch no session: {r.stdout!r}"
        if rc == 3:
            assert "fail-closed sentinel" in r.stdout, "rc=3 lost its notice"

def test_resume_prompt_reaches_the_model_intact():
    """Round-trip the real literal: it carries backticks AND a single-quoted --jq arg."""
    src = open(PPN).read()
    m = re.search(r'^\s*(RESUME_PROMPT="(?:[^"\\]|\\.)*")$', src, re.M)
    assert m, "RESUME_PROMPT assignment not found (or no longer a single line)"
    script = (f'source "{PPN}" >/dev/null 2>&1\n'
              'RESUME_PLAN_CLAUSE="CLAUSE"\n'
              f'{m.group(1)}\n'
              'q=$(shq "$RESUME_PROMPT")\nbash -c "printf %s $q"\n')
    r = _bash(script)
    assert r.stderr == "", f"far-side shell wrote to stderr: {r.stderr!r}"
    assert "`gh pr view --json number --jq '.number'`" in r.stdout
    assert "RESUMING at Phase 5.5" in r.stdout
    assert "never ask questions" in r.stdout

def test_prompt_assignment_count_is_still_two():
    """RESUME_PROMPT must not be mistaken for a third PROMPT site, and the two real
    PROMPT sites must keep their order (index 1 is the plan-blind one)."""
    src = open(PPN).read()
    prompts = re.findall(r'^\s*(PROMPT="(?:[^"\\]|\\.)*")$', src, re.M)
    assert len(prompts) == 2, f"expected exactly two PROMPT= sites, got {len(prompts)}"
    assert "<slug>-N.md" in prompts[1]
    assert len(re.findall(r'^\s*RESUME_PROMPT="', src, re.M)) == 1

def test_should_fallback_body_unchanged():
    src = open(PPN).read()
    assert 'should_fallback() { case "$1" in 0|3) return 1 ;; *) return 0 ;; esac; }' in src
    assert _fb(4) == "0"     # unchanged: 4 still "escalates" — the rc=4 arm intercepts first

def test_harness_default_is_the_main_checkout(tmp_path):
    """ADR-0092: the seam must not become $ROOT/tools/postplan-harness."""
    src = open(PPN).read()
    assert 'HARNESS="${HARNESS:-/Users/ajaynicolas/GitHub/IBL5/tools/postplan-harness}"' in src
    assert '$ROOT/tools/postplan-harness' not in src


def test_plan_override_reaches_the_python_harness_in_live_mode():
    """--plan must land in the HARNESS invocation, not only the skill-fallback prompt.

    plan_source (harness/state.py) is recorded by harness/planfile.py::locate_plan, which
    only sees an explicit path via runner.py's --plan. runner.py rejects --plan outside
    --mode isolated, and --live is itself isolated-only, so the INSTALLED live mode is the
    one place both apply. If PLAN_ARG were interpolated into $PROMPT instead, a live
    override would resolve through bin/lib/plan-resolve.sh, which writes no result.json —
    and the audit trail this field exists for would not cover the live run class.
    """
    src = open(PPN).read()
    seg = next(l for l in src.splitlines() if l.startswith("    HARNESS_SEG="))
    assert "--live${PLAN_ARG}" in seg


# ---------------------------------------------------------------------------
# Exit-5 guard: a PR already exists and the working set touches no production files.
# The failure mode is a SILENTLY DEAD guard, so each test below names the mutation it
# catches; a guard that never fires still leaves the whole rest of this file green.
# ---------------------------------------------------------------------------

_TEST_ONLY_DIRTY = {"ibl5/tests/Foo/BarTest.php": "<?php\n// test-only change\n"}


def test_guard_exits_5_when_pr_exists_and_no_production_files(tmp_path):
    """Catches: the guard block deleted, the exit code changed, or the message no longer
    naming the cheaper command."""
    r = _run_ppn(tmp_path, extra_env={"FAKE_PR_NUMBER": "2186"}, dirty=_TEST_ONLY_DIRTY)
    assert r.returncode == 5, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "/commit-commands:commit-push-pr" in r.stderr
    assert "PR #2186" in r.stderr


def test_guard_proceeds_when_pr_exists_and_uncommitted_production_files(tmp_path):
    """The false-block case: zero commits ahead of origin/master, one UNCOMMITTED
    production file. Catches narrowing the changed set back to
    `git diff --name-only origin/master...HEAD` alone, which sees nothing here."""
    dirty = dict(_TEST_ONLY_DIRTY)
    dirty["ibl5/modules/Player/index.php"] = "<?php\n// production change\n"
    r = _run_ppn(tmp_path, extra_env={"FAKE_PR_NUMBER": "2186"}, dirty=dirty)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "no production files" not in r.stderr


def test_guard_force_flag_overrides_exit_5(tmp_path):
    """Catches: the --force arm dropped, or the guard reading FORCE before the arg loop
    assigns it (which under `set -u` is exit 2, not 0)."""
    r = _run_ppn(tmp_path, args=("--force",),
                 extra_env={"FAKE_PR_NUMBER": "2186"}, dirty=_TEST_ONLY_DIRTY)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"


def test_guard_proceeds_when_no_pr_exists(tmp_path):
    """The hard stop: the guard must NOT widen to the no-PR case. Catches removing or
    inverting `[ -n "$PR_NUM" ]`."""
    r = _run_ppn(tmp_path, dirty=_TEST_ONLY_DIRTY)      # FAKE_PR_NUMBER unset ⇒ gh prints nothing
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "no production files" not in r.stderr


def test_force_flag_rejected_forms_fail_loudly(tmp_path):
    """--force is a BARE flag only. Catches a permissive `--force=*)` arm, under which a
    typo'd `--force=0` would silently ENABLE the override."""
    # index, not a slug of `args` — both forms slugify to "force1" and would collide on
    # the same tmp dir, making the second iteration blow up in setup instead of asserting.
    for i, args in enumerate((("--force=1",), ("--force", "1"))):
        r = _run_ppn(tmp_path / f"case{i}",
                     args=args, extra_env={"FAKE_PR_NUMBER": "2186"},
                     dirty=_TEST_ONLY_DIRTY)
        assert r.returncode != 0, f"{args}: expected failure, got 0"
        assert "unknown argument" in r.stderr, f"{args}: got {r.stderr!r}"
