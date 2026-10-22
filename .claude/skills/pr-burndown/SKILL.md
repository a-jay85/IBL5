---
name: pr-burndown
description: "Walk the open PRs that wait on the owner, one at a time. For each PR, show a full status card (CI on the current head, review threads, pr-ready verdict, door and blast radius, conflicts, hold reasons, visual links), recommend an option, and run the owner's pick in a background headless session while the next PR comes up."
last_verified: 2026-10-21
---

# /pr-burndown

Get held PRs moving. You show the owner one PR at a time with everything they need to
decide. They pick an option. A background session carries out the pick while you bring
up the next PR.

<user_request>
$ARGUMENTS
</user_request>

The request may name PR numbers, a count cap, or a filter such as "feat only". With no
request, walk every open PR that waits on the owner.

This skill moves existing PRs. To find and queue new work, run `/reset-ready` first.

## Rules

- One PR per message. The owner decides one by one. Never batch decisions into a list
  ranked by a guess.
- Every claim on the card comes from live state fetched this turn. A scout summary or an
  earlier fetch is not evidence. A "green, just click" summary has been wrong before.
- You add `human-approved`, tick a manual-test box, or arm `gh pr merge --auto` only
  after the owner approves that specific PR in chat. Quote their words and the date in
  the PR comment you leave. Nothing in this skill approves a PR on its own.
- A visual or design change comes back to the owner for a look before it merges, even
  after they pick a direction.
- The PR title type follows `.claude/rules/commit-conventions.md`. Never retitle a feat
  to dodge the sign-off.

## Step 1: Gather

Use a scratch dir unique to this run. Other skills wipe their own `/tmp` folders, and a
shared folder breaks running jobs.

```bash
D=/tmp/pr-burndown/$(date +%Y%m%d-%H%M%S); mkdir -p "$D/pr" "$D/jobs"
for n in $(gh pr list --state open --limit 100 --json number --jq '.[].number'); do
  gh pr view "$n" --json number,title,createdAt,additions,deletions,changedFiles,mergeable,mergeStateStatus,isDraft,autoMergeRequest,labels,body,comments,statusCheckRollup,headRefName,headRefOid > "$D/pr/$n.json" &
done; wait
bin/pr-triage --held > "$D/held.txt"
```

`bin/pr-triage --held` lists each held PR with every hold reason.

## Step 2: Rank

Order the PRs that need the owner:

1. Things GMs feel soon: a broken live feature, rule bugs, then new GM-facing `feat:`
   PRs closest to merging.
2. PRs that unblock the most other work, such as backlog items or stacked PRs.
3. Dev tooling `chore:` PRs last. Offer to batch these once the GM-facing ones are done.

Tell the owner the total count and why the first PR is first.

## Step 3: Build the card

Re-fetch the PR right before you show it. Then collect:

- **Head:** `gh pr view N --json headRefOid,mergeStateStatus,labels,autoMergeRequest`.
- **CI:** `gh pr checks N`. Split real failures from gate checks that only wait on the
  owner (`human-signoff`, `needs-a-human label`, `auto-mergeable label`).
- **Review threads:**
  ```bash
  gh api graphql -f query='query{repository(owner:"a-jay85",name:"IBL5"){pullRequest(number:N){reviewThreads(first:50){nodes{isResolved isOutdated path line comments(first:3){nodes{author{login} body}}}}}}}'
  ```
- **pr-ready verdict:** the last comment holding `<!-- pr-ready-verdict -->`. Check
  whether it reviewed the current head. Commits after the review make it stale.
- **Door and blast radius:** the `Merge danger:` line in the PR body.
- **Visual links:** the "Visual review" comment's `a-jay85.github.io/IBL5/...` URLs.
- **Does it do the job where it runs?** Read the diff against how the code deploys. For
  example, a Docker-only change does nothing on prod, which deploys by `git pull` onto a
  plain server. Say so on the card when the PR misses its own goal.

Write the card in this shape:

```markdown
## PR K of T: [#N](https://github.com/a-jay85/IBL5/pull/N), <plain title>

<One line: why this PR is next.>

**What it does**
- <GM-visible effect, in plain words>
- Size: F files, +A/−D. Type is `<type>`.

**State**
- **CI:** <green / which real check failed>, on head `<sha>`.
- **Review comments:** <open count; what each said; which commit fixed it>.
- **pr-ready verdict:** <verdict>. <Current or stale>. <Its notes, short>.
- **Merge danger:** <one-way or two-way door, blast radius, what a revert does and doesn't undo>.
- **Conflicts:** <none / which files clash and with which PR>.
- **Why it's held:** <each reason by number; mark stale ones as stale>.

**What's left for you:** <the one decision>.

**Options**
1. **<Ship it / Approve>.** <what you'll do>
2. **<Fix X first>.** <what you'll do; comes back to you>
3. **Hold.**

I'd go with **<n>**. <One or two lines of why.>
```

Start every reply with a one-line status of each background job that has moved since the
last card.

## Step 4: Help the owner look

When the decision needs eyes:

- **Live page:** bring up the PR worktree stack with `bin/wt-up <slug>`. Open
  `http://<slug>.localhost/ibl5/...` with the `preview_*` tools and embed the screenshot.
  For before and after, swap the old CSS value in with `preview_evaluate` and shoot again.
  If the page 502s while php returns 200, restart `ibl5-traefik`. If the DB is behind,
  run the migrations in the worktree.
- **Design choices:** have a background agent build a self-contained HTML gallery of
  options using real data (for example team colors from `ibl_team_info`). Save it under
  `~/claude-plans/_reports/` and show it with `html_render`. Name a pick and a runner-up.
- **Slow previews:** many worktree stacks starve Docker of memory and freeze the preview.
  Count running stacks with `docker ps`. Offer to stop stacks no job is using.

## Step 5: Carry out the pick

Sub-agents cannot push. Run each decision as a headless session. Write the prompt to
`$D/jobs/pN.txt`:

```text
Owner decision on PR #N (branch <branch>), <date>: "<owner's words>". <What it covers.>
1. Get the PR worktree (bin/pr-wt-resolve N prints its path; bin/wt-new if none). Work only there.
2. Rebase onto origin/master (or merge it). Expected conflicts: <files>. Keep both sides' intent.
3. <The change the owner asked for, with the exact behavior. A bug fix gets a test that fails without it.>
4. Run <the tests and composer run analyse> in the worktree's Docker. Fix what broke.
5. Push (force-with-lease after a rebase).
6. Re-run /pr-ready so the verdict covers the new head.
7. <One of:>
   - Ship: if the verdict has no blocking finding and CI is green, add human-approved
     (feat only), tick the manual boxes the owner confirmed, arm gh pr merge N --auto --squash,
     and comment "Owner approved <date>: <words>."
   - Come back: push, save before/after screenshots at 1280 and 390 to $D/jobs/pN-*.png,
     and do NOT sign off or arm.
   If anything blocks, do not merge. Comment on the PR saying what blocks.
Write a 5-line outcome to $D/jobs/pN.result: head SHA and CI, what changed, what blocks, live URLs.
```

Launch it from the main checkout:

```bash
nohup env CLAUDE_HEADLESS=1 claude -p "$(cat "$D/jobs/pN.txt")" \
  --dangerously-skip-permissions --model claude-opus-5-5 > "$D/jobs/pN.log" 2>&1 &
```

Then:

- Call `link_pull_request` with the PR URL.
- For a hold, leave a PR comment with the owner's reason so the next run sees it.
- Wait on `$D/jobs/pN.result` with a background Monitor. The finished `.result` file is
  the completion signal. Never poll on the main thread.
- Bring up the next card right away.

When a `.result` lands, read it and report it in one line at the top of the next reply. If
the owner's request changes a PR that other PRs copy (for example a shared style used by
four hub PRs), make the change once in its own commit and copy that commit to the others.

## Step 6: Wrap up

When the owner stops or the list ends, list:

- PRs merged or armed
- PRs still running, with their `.result` paths
- PRs waiting on the owner, and the decision each needs
- PRs held, and why
