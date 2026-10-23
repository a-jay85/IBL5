<!-- vr-new-screens:begin -->

_VR screens for head `c56e582` at 2026-10-08 05:18 UTC._

## 🔍 Changed VR screens

### cap-space · cap-space · top, y 232-440 px

**Before**

![cap-space before](https://a-jay85.github.io/IBL5/0cf7a90f7941b09374db77da0e13afe1be2f2ac2/visual-review/changed/cap-space.1.before.png)

<!-- vr-new-screens:end -->

<!-- merge-digest:begin -->
## Merge digest

**What changed:** CI now crops each changed VR screen into before/after pairs and puts them at the top of the PR body.

**Why:** A small change inside a full-page screenshot is hard to see on a phone.

**Watch:** The PR body holds a stale second `vr-new-screens` block that `spliceBody` never replaces.
<!-- merge-digest:end -->

<!-- vr-new-screens:begin -->

_VR screens for head `721513f` at 2026-10-07 15:24 UTC._

## 🔍 Changed VR screens

### cap-space · cap-space · top, y 232-440 px

**Before**

![cap-space before](https://a-jay85.github.io/IBL5/d1230d3c0f72c3e18bc698c4fa07941980d39463/visual-review/changed/cap-space.1.before.png)

<!-- vr-new-screens:end -->

## Summary
- Adds a pure, I/O-free crop module.
- Adds `--crop-changed=DEST` mode to `bin/vr-review-comment`.
- Workflow adds a "Crop changed-screen pairs" step.
