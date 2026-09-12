import os, pathlib, re, shlex, shutil, subprocess

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


def _fixture_repo(tmp_path):
    """A dirty linked worktree on a non-master branch — enough to clear post-plan-now's guards.

    Creates a main checkout at tmp_path/wt (on some-feature) with bin/lib/git-helpers.sh
    committed, then adds a linked worktree at tmp_path/wt-base (on wt-feature).
    Returns tmp_path/wt-base so is_in_worktree() passes in callers.
    CI has no global git identity, so the fixture sets its own.
    """
    repo = tmp_path / "wt"
    repo.mkdir()
    run = lambda *a: subprocess.run(a, cwd=str(repo), check=True, capture_output=True)
    run("git", "init", "-q", "-b", "master")
    run("git", "config", "user.email", "test@example.com")
    run("git", "config", "user.name", "Test")
    (repo / "bin" / "lib").mkdir(parents=True)
    shutil.copy(os.path.join(REPO, "bin", "lib", "git-helpers.sh"),
                str(repo / "bin" / "lib" / "git-helpers.sh"))
    (repo / "f.txt").write_text("one\n")
    run("git", "add", "-A")
    run("git", "commit", "-qm", "base")
    run("git", "checkout", "-qb", "some-feature")
    linked = tmp_path / "wt-base"
    run("git", "worktree", "add", str(linked), "-b", "wt-feature")
    (linked / "f.txt").write_text("two\n")      # dirty tree ⇒ "nothing to ship" guard passes
    return linked


def _fixture_worktree(tmp_path, branch):
    """Return (main_root, wt_root) where wt_root is a linked worktree on `branch`.

    Uses _fixture_repo to build the main checkout, then adds another linked worktree.
    """
    feature_wt = _fixture_repo(tmp_path)
    # Derive main_root from the linked worktree's git-common-dir
    main_root = tmp_path / "wt"          # _fixture_repo always puts main here
    wt_root = tmp_path / f"wt-{branch}"
    subprocess.run(
        ["git", "worktree", "add", str(wt_root), "-b", branch],
        cwd=str(main_root), check=True, capture_output=True)
    (wt_root / "f.txt").write_text("wt-dirty\n")
    return main_root, wt_root


def _gh_stub(bin_dir, branch, rc=0):
    """Write a gh stub that answers headRefName queries with `branch`."""
    stub = pathlib.Path(bin_dir) / "gh"
    stub.write_text(
        "#!/bin/sh\n"
        f"case \"$*\" in\n"
        f"  *headRefName*) printf '%s\\n' {shlex.quote(branch)} ; exit 0 ;;\n"
        f"  *) exit {rc} ;;\n"
        f"esac\n"
    )
    stub.chmod(0o755)


def _guard_block():
    """Extract the Phase 0 guard block verbatim from SKILL.md."""
    skill_path = os.path.join(REPO, ".claude", "skills", "post-plan", "SKILL.md")
    src = open(skill_path).read()
    START = "# --- post-plan worktree guard (Phase 0) ---"
    END   = "# --- end post-plan worktree guard ---"
    parts = src.split(START)
    assert len(parts) == 2, f"expected exactly one guard block start, got {len(parts) - 1}"
    inner = parts[1].split(END)
    assert len(inner) == 2, f"expected exactly one guard block end, got {len(inner) - 1}"
    return (START + inner[0] + END).strip()

def _generate_cmd(tmp_path, extra_env=None):
    """Run bin/post-plan-now with launchctl stubbed and HOME redirected; return $CMD."""
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    harness = tmp_path / "fake-harness"; harness.mkdir()
    (harness / "run").write_text("#!/bin/sh\nexit 0\n")
    (harness / "run").chmod(0o755)

    env = dict(os.environ, HOME=str(home), HARNESS=str(harness),
               PATH=f"{shim}:{os.environ['PATH']}")
    env.pop("POST_PLAN_SKILL", None)
    env.update(extra_env or {})
    repo = _fixture_repo(tmp_path)
    r = subprocess.run(["bash", PPN], cwd=repo, env=env, capture_output=True, text=True)
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

def test_bare_invocation_cmd_has_no_plan_slug_export(tmp_path):
    """Bare invocation (no --pr) must not inject PLAN_SLUG into $CMD."""
    cmd = _generate_cmd(tmp_path)
    assert "PLAN_SLUG" not in cmd, f"bare path must not export PLAN_SLUG; got {cmd!r}"

    # Boundary: PLAN_SLUG explicitly empty in caller env — still must not appear in CMD
    tmp2 = tmp_path / "b2"
    tmp2.mkdir()
    cmd2 = _generate_cmd(tmp2, extra_env={"PLAN_SLUG": ""})
    assert "PLAN_SLUG" not in cmd2, f"PLAN_SLUG='' must not be injected; got {cmd2!r}"


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
# --pr flag tests
# ---------------------------------------------------------------------------

def test_pr_flag_requires_an_argument():
    r = subprocess.run(["bash", PPN, "--pr"],
                       capture_output=True, text=True, cwd=REPO)
    assert r.returncode == 1
    assert "--pr requires a PR number argument" in r.stderr


def test_pr_flag_rejects_non_numeric():
    for bad in ("abc", "-3", "12x"):
        r = subprocess.run(["bash", PPN, "--pr", bad],
                           capture_output=True, text=True, cwd=REPO)
        assert r.returncode == 1, f"--pr {bad!r}: expected rc=1, got {r.returncode}"
        assert "--pr needs a positive integer PR number" in r.stderr, \
            f"--pr {bad!r}: {r.stderr!r}"
        assert bad in r.stderr, f"--pr {bad!r}: bad value not echoed in {r.stderr!r}"


def test_unknown_argument_message_lists_pr():
    r = subprocess.run(["bash", PPN, "--prr", "5"],
                       capture_output=True, text=True, cwd=REPO)
    assert r.returncode == 1
    assert "unknown argument" in r.stderr
    assert "--pr" in r.stderr


def test_pr_flag_unresolvable_branch_errors(tmp_path):
    """gh returns empty/failure → no worktree found error."""
    stub_dir = tmp_path / "stub-bin"
    stub_dir.mkdir()
    gh = stub_dir / "gh"
    gh.write_text("#!/bin/sh\nexit 1\n")
    gh.chmod(0o755)
    env = dict(os.environ, PATH=f"{stub_dir}:{os.environ['PATH']}")
    r = subprocess.run(["bash", PPN, "--pr", "99"],
                       capture_output=True, text=True, cwd=REPO, env=env)
    assert r.returncode == 1
    assert "could not resolve a head branch" in r.stderr


def test_pr_flag_rejects_a_branch_with_no_worktree(tmp_path):
    """gh resolves branch, but no worktree has it checked out."""
    main_root, _ = _fixture_worktree(tmp_path, "some-branch")
    stub_dir = tmp_path / "stub-bin"
    stub_dir.mkdir()
    _gh_stub(stub_dir, "orphan-branch")
    env = dict(os.environ,
               PATH=f"{stub_dir}:{os.environ['PATH']}",
               POST_PLAN_MAIN_ROOT=str(main_root))
    r = subprocess.run(["bash", PPN, "--pr", "7"],
                       capture_output=True, text=True,
                       cwd=str(main_root), env=env)
    assert r.returncode == 1
    assert "no worktree has it checked out" in r.stderr


def test_pr_flag_targets_the_matching_worktree_and_exports_plan_slug(tmp_path):
    """--pr resolves the worktree, writes one plist, and injects PLAN_SLUG before cd."""
    main_root, wt_root = _fixture_worktree(tmp_path, "pr-branch")
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    harness = tmp_path / "fake-harness"; harness.mkdir()
    (harness / "run").write_text("#!/bin/sh\nexit 0\n")
    (harness / "run").chmod(0o755)
    stub_dir = tmp_path / "stub-bin"; stub_dir.mkdir()
    _gh_stub(stub_dir, "pr-branch")
    env = dict(os.environ,
               HOME=str(home),
               HARNESS=str(harness),
               PATH=f"{shim}:{stub_dir}:{os.environ['PATH']}",
               POST_PLAN_MAIN_ROOT=str(main_root))
    env.pop("POST_PLAN_SKILL", None)
    r = subprocess.run(["bash", PPN, "--pr", "42"],
                       capture_output=True, text=True,
                       cwd=str(wt_root), env=env)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, f"expected one plist, got {plists}"
    body = plists[0].read_text()
    cmd = re.search(r"<string>(export PATH=.*?)</string>", body, re.S).group(1)
    cmd = cmd.replace("&lt;", "<").replace("&gt;", ">").replace("&amp;", "&")
    assert "export PLAN_SLUG=" in cmd
    assert "pr-branch" in cmd
    assert f'cd "{wt_root}"' in cmd
    # PLAN_SLUG export must precede the cd to the worktree root
    assert cmd.index("export PLAN_SLUG=") < cmd.index(f'cd "')


def test_pr_flag_plan_blind_when_no_plan_file_exists(tmp_path):
    """--pr succeeds even when no plan file exists for the branch (plan-blind mode)."""
    main_root, wt_root = _fixture_worktree(tmp_path, "pr-branch")
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    (home / "claude-plans").mkdir(parents=True)   # plans dir exists but empty
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    harness = tmp_path / "fake-harness"; harness.mkdir()
    (harness / "run").write_text("#!/bin/sh\nexit 0\n")
    (harness / "run").chmod(0o755)
    stub_dir = tmp_path / "stub-bin"; stub_dir.mkdir()
    _gh_stub(stub_dir, "pr-branch")
    env = dict(os.environ,
               HOME=str(home),
               HARNESS=str(harness),
               PATH=f"{shim}:{stub_dir}:{os.environ['PATH']}",
               POST_PLAN_MAIN_ROOT=str(main_root))
    env.pop("POST_PLAN_SKILL", None)
    r = subprocess.run(["bash", PPN, "--pr", "42"],
                       capture_output=True, text=True,
                       cwd=str(wt_root), env=env)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert len(plists) == 1, f"expected one plist, got {plists}"
    assert "plan-blind" in r.stderr or "no plan at" in r.stderr, \
        f"expected plan-missing warning in stderr: {r.stderr!r}"
    body = plists[0].read_text()
    cmd = re.search(r"<string>(export PATH=.*?)</string>", body, re.S).group(1)
    cmd = cmd.replace("&lt;", "<").replace("&gt;", ">").replace("&amp;", "&")
    assert "export PLAN_SLUG=" in cmd, "PLAN_SLUG must be in CMD even in plan-blind mode"
    assert "pr-branch" in cmd


def test_refuses_to_run_in_the_main_checkout(tmp_path):
    """post-plan-now exits 1 with ADR-0062 message when run from main checkout."""
    main_root, _ = _fixture_worktree(tmp_path, "some-branch")
    home = tmp_path / "home"
    (home / "Library" / "LaunchAgents").mkdir(parents=True)
    shim = tmp_path / "shim"; shim.mkdir()
    (shim / "launchctl").write_text("#!/bin/sh\nexit 0\n")
    (shim / "launchctl").chmod(0o755)
    env = dict(os.environ, HOME=str(home),
               PATH=f"{shim}:{os.environ['PATH']}")
    env.pop("POST_PLAN_SKILL", None)
    # Run from the main checkout itself (which is on some-feature, not master/main/HEAD)
    r = subprocess.run(["bash", PPN],
                       capture_output=True, text=True,
                       cwd=str(main_root), env=env)
    assert r.returncode == 1
    assert "refusing to run in the main checkout" in r.stderr
    plists = list((home / "Library" / "LaunchAgents").glob("*.plist"))
    assert plists == [], "no plist should be written when refusing"


# ---------------------------------------------------------------------------
# SKILL.md Phase 0 guard block tests
# ---------------------------------------------------------------------------

def test_phase0_guard_extracts_cleanly():
    """_guard_block() must return a non-empty string containing both markers."""
    block = _guard_block()
    assert "# --- post-plan worktree guard (Phase 0) ---" in block
    assert "# --- end post-plan worktree guard ---" in block
    assert len(block) > 100


def test_phase0_guard_already_in_target_arm_is_a_noop(tmp_path):
    """ALREADY-IN-TARGET arm: linked worktree on the right branch → exit 0."""
    _, wt_root = _fixture_worktree(tmp_path, "target-branch")
    block = _guard_block()
    env = dict(os.environ, PLAN_SLUG="target-branch")
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(wt_root), env=env)
    assert r.returncode == 0, f"stdout={r.stdout!r} stderr={r.stderr!r}"
    assert "ALREADY-IN-TARGET" in r.stdout


def test_phase0_guard_main_checkout_arm_exits_nonzero(tmp_path):
    """MAIN-CHECKOUT arm: running from the main checkout → exit 1, STOP in output."""
    main_root, _ = _fixture_worktree(tmp_path, "some-branch")
    block = _guard_block()
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(main_root))
    assert r.returncode == 1
    assert "STOP" in r.stdout
    assert "MAIN-CHECKOUT" in r.stdout


def test_phase0_guard_wrong_worktree_arm_exits_nonzero(tmp_path):
    """WRONG-WORKTREE arm: linked worktree on wrong branch → exit 1."""
    _, wt_root = _fixture_worktree(tmp_path, "wrong-branch")
    block = _guard_block()
    env = dict(os.environ, PLAN_SLUG="different-branch")
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(wt_root), env=env)
    assert r.returncode == 1
    assert "STOP" in r.stdout
    assert "WRONG-WORKTREE" in r.stdout


def test_phase0_guard_main_checkout_arm_wins_over_a_branch_match(tmp_path):
    """MAIN-CHECKOUT must win even when PLAN_SLUG matches the main checkout's branch.

    If the guard checked branch equality first, a main-checkout run on the correct
    branch would slip through to ALREADY-IN-TARGET and exit 0. The is_in_worktree
    check must be the outer gate.
    """
    main_root, _ = _fixture_worktree(tmp_path, "some-branch")
    # The main checkout is on 'some-feature' (set by _fixture_repo).
    main_branch_r = subprocess.run(
        ["git", "rev-parse", "--abbrev-ref", "HEAD"],
        capture_output=True, text=True, cwd=str(main_root), check=True)
    main_branch = main_branch_r.stdout.strip()
    block = _guard_block()
    # Set PLAN_SLUG to the main checkout's own branch — branch matches, but it's still main.
    env = dict(os.environ, PLAN_SLUG=main_branch)
    r = subprocess.run(["bash", "-c", block],
                       capture_output=True, text=True,
                       cwd=str(main_root), env=env)
    assert r.returncode == 1, "main checkout must be refused even when branch matches PLAN_SLUG"
    assert "MAIN-CHECKOUT" in r.stdout
