import os, subprocess

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
    import glob
    before = set(glob.glob(str(tmp_path / "com.ibl5.postplan-now-*.plist")))
    r = subprocess.run(
        ["bash", "bin/post-plan-now", "--plan", "/nonexistent-plan.md"],
        capture_output=True, text=True, cwd=REPO)
    assert r.returncode == 1
    assert "does not exist — aborting" in r.stderr
    after = set(glob.glob(
        os.path.expanduser("~/Library/LaunchAgents/com.ibl5.postplan-now-*.plist")))
    assert not (after - set(glob.glob(
        os.path.expanduser("~/Library/LaunchAgents/com.ibl5.postplan-now-*.plist"))))


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
        ["bash", "bin/post-plan-now", "--plan", str(plan)],
        capture_output=True, text=True, cwd=REPO)
    assert "does not exist — aborting" not in r.stderr


def test_plist_wiring_regression_new():
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

    env = os.environ.copy()
    env["HOME"] = str(tmp_path.parent)
    env["SLUG"] = "s"
    script = (
        f"cd {REPO!r}\n"
        f'mkdir -p "{tmp_path!s}"\n'
        + shell_block
        + '\necho "PLAN_FILE=$PLAN_FILE"\n'
        + '\necho "BEST=$BEST"\n'
    )
    # Override claude-plans to our tmp dir
    script = script.replace(
        '"$HOME/claude-plans/$SLUG.md"',
        f'"{tmp_path!s}/$SLUG.md"'
    ).replace(
        '"$HOME/claude-plans/$SLUG"-*.md',
        f'"{tmp_path!s}/$SLUG"-*.md'
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
    script = (
        "SLUG=no-such-slug\n"
        + shell_block.replace(
            '"$HOME/claude-plans/$SLUG.md"',
            f'"{plans_dir}/$SLUG.md"'
        ).replace(
            '"$HOME/claude-plans/$SLUG"-*.md',
            f'"{plans_dir}/$SLUG"-*.md'
        )
        + '\necho "PLAN_FILE=${PLAN_FILE:-}"\n'
        + 'if [ -z "${PLAN_FILE:-}" ]; then echo "PLAN_FOUND=none"; fi\n'
    )
    r = subprocess.run(["bash", "-c", script], capture_output=True, text=True)
    assert r.returncode == 0, r.stderr
    assert "PLAN_VARIANT_SELECTED" not in r.stdout
    assert "PLAN_FOUND=none" in r.stdout
