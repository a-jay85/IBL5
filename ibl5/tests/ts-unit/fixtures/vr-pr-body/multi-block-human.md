<!-- merge-digest:begin -->
## Merge digest

**What changed:** CI now crops each changed VR screen into before/after pairs and puts them at the top of the PR body.

**Why:** A small change inside a full-page screenshot is hard to see on a phone.

**Watch:** The PR body holds a stale second `vr-new-screens` block that `spliceBody` never replaces.
<!-- merge-digest:end -->

## Summary
- Adds a pure, I/O-free crop module.
- Adds `--crop-changed=DEST` mode to `bin/vr-review-comment`.
- Workflow adds a "Crop changed-screen pairs" step.
