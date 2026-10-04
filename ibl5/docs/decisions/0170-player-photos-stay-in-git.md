---
description: Player photos under ibl5/images/player stay tracked in git; records the measurements, the deploy coupling, the rejected offload options, and the thresholds that would reopen the question.
last_verified: 2026-10-04
---

# ADR-0170: Player photos stay in git

**Status:** Accepted
**Date:** 2026-10-04
**Deciders:** ajaynicolas

## Context

Backlog issue a-jay85/IBL5-backlog#206 proposed moving the player photos in `ibl5/images/player/` out of git, to S3 or a CDN or Git LFS, with admin tooling for uploads. The issue argued that clone size grows with each new player and that binary diffs are useless.

Measurements taken on 2026-10-04 at `a3ce7e053`:

| Measure | Value | Command |
|---------|-------|---------|
| Tracked photos | 1980 (1966 jpg, 7 png, 4 jpeg, 2 bmp, 1 JPG) | `git ls-files ibl5/images/player \| wc -l` |
| Working-tree size | 20M | `du -sh ibl5/images/player` |
| History blobs under the path | 2011 blobs, 14.15 MB on disk | `git rev-list --objects --all -- ibl5/images/player \| git cat-file --batch-check='%(objecttype) %(objectname) %(objectsize:disk) %(rest)'`, summed over blobs |
| Whole pack | 1.98 GiB | `git count-objects -vH` (size-pack) |
| Share of history | about 0.7% | blob total divided by pack size |
| Commits touching the path | 19 since 2020; per year 2020: 9, 2021: 3, 2022: 1, 2023: 1, 2024: 1, 2025: 2, 2026: 2 | `git log --format=%cd --date=format:%Y -- ibl5/images/player \| sort \| uniq -c` |

The photos add one or two commits a year. The growth the issue describes is not happening at a rate that matters. The heavy history lives elsewhere: `visual-review/playwright-report` at 1637.3 MB, `ibl5/tests` at 88.9 MB, and `ibl5/images` at 24.1 MB (photos plus other images). These sizes sum blob bytes on disk by the first two path components over every ref.

The serving path depends on the files being in the checkout. `PlayerImageHelper::getImageUrl()` in `ibl5/classes/Player/PlayerImageHelper.php` builds the relative URL `./images/player/<pid>.jpg`, and `ibl5/jslib/depth-chart-lineup-preview.js` hardcodes the same path. Production is a git checkout: the deploy job in `.github/workflows/main.yml` runs `git fetch origin` and then `git reset --hard origin/production`. The dev and CI Docker stacks bind-mount `./ibl5`, so they serve photos from the checkout too. The repo has no object store, no CDN configuration, no upload endpoint, and no storage credentials.

## Decision

Keep every player photo tracked in git at its current path. New photos keep arriving as ordinary commits. No offload, no LFS filter, and no upload tooling are added.

Reopen this decision when any one of these thresholds is crossed. The numbers are judgment values chosen to sit well above today's figures:

- Photo blobs in history exceed 5% of the pack size (today about 0.7%).
- More than 6 commits touch `ibl5/images/player/` in one calendar year (today 1 to 3).
- The working-tree size of `ibl5/images/player/` passes 100 MB (today 20M).
- A bulk-import or per-signing upload workflow is introduced that would commit photos routinely.

Re-run the commands in the Context table to check.

## Alternatives Considered

**Untrack the folder and add it to `.gitignore`.** Rejected. When the deploy runs `git reset --hard` onto a commit where a previously tracked file is no longer tracked, git deletes that file from the working tree. Every production photo would vanish on the first deploy after the change. A throwaway repo reproduces it: commit a file, `git rm --cached` it, ignore it, commit, check out the first commit, then `git reset --hard` to the second; the file is gone. Untracking also reclaims nothing from existing clones without a history rewrite and a force-push.

**Git LFS.** Rejected. The repo tried LFS for the JSB simulation files and backed it out in commit `f069c22fd` (2026-04-08), which removed the LFS filter rules from `.gitattributes` and stopped tracking those files because the binaries bloated history. Moving the photos to LFS would need git-lfs installed on the production host, in the CI checkout, and in every `bin/wt-new` worktree. Converting the existing blobs would need `git lfs migrate`, which rewrites history.

**S3 or a CDN with admin upload tooling.** Rejected for now. It needs storage infrastructure and credentials the project does not have, a rewrite of every caller that builds the photo URL, and a new authenticated upload endpoint, which is a new security surface. This becomes the path to take if a revisit threshold above is crossed.

## Consequences

- Clones keep carrying about 14.15 MB of photo history, under 1% of the pack.
- Production, CI, and local stacks keep serving photos from the checkout with no extra moving parts.
- Adding a photo stays a normal commit; reviewers see binary diffs, which is acceptable at one or two commits a year.
- Anyone revisiting clone size should start with the larger history groups named in Context.

## Supersedes

None.

## References

- a-jay85/IBL5-backlog#206
- Commit `f069c22fd` (stop tracking JSB sim files in git/LFS)
- `ibl5/classes/Player/PlayerImageHelper.php`
- `.github/workflows/main.yml`
