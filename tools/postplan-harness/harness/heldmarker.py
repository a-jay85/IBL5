"""Held-unfixable marker: stop re-launching a post-plan run the harness already declined.

When the harness declines a run as a hold-repeat, it writes this marker next to the
`.holdrepeat.json` record. Every scheduler (`bin/post-plan-now`, `bin/pr-cycle`) asks
`python3 -m harness.heldmarker check` before spending a launch. Exit 10 means suppressed.

The marker is advisory and fails open. It is active only while the live hold record
still carries the same structural key and condition-3 MISSING set, and the plan resolves
to the same path with the same mtime. Anything unsure means "proceed": a bug here costs
one launch at most and can never suppress a run whose hold or plan changed.

This module only reads and writes files under `state_dir`. It never touches a PR, a
label, a matrix row or an arming condition.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import sys

from harness import holdrepeat
from harness.statefile import safe_slug

SCHEMA_VERSION = 1
SUPPRESS_EXIT = 10


def marker_path(state_dir: str, slug: str) -> str:
    return os.path.join(state_dir, f"{safe_slug(slug)}.held-unfixable.json")


def _condition3_parts(rec) -> frozenset:
    for entry in (rec or {}).get("structural") or []:
        if isinstance(entry, list) and len(entry) == 3 and entry[0] == 3:
            return holdrepeat.missing_parts(entry[2] or "")
    return frozenset()


def record_hold(rec) -> tuple[str, str]:
    """(structural_key, hold_hash) of a hold record; ("", "") on any shape problem."""
    try:
        if not isinstance(rec, dict):
            return "", ""
        key = rec.get("structural_key") or ""
        parts = _condition3_parts(rec)
        if not key or not parts:
            return "", ""
        digest = hashlib.sha256("\n".join(sorted(parts)).encode()).hexdigest()
        return key, digest
    except Exception:  # noqa: BLE001 - fail open by design
        return "", ""


def _read_marker(state_dir, slug):
    try:
        with open(marker_path(state_dir, slug)) as fh:
            doc = json.load(fh)
    except (OSError, ValueError):
        return None
    if not isinstance(doc, dict) or doc.get("schema_version") != SCHEMA_VERSION:
        return None
    return doc


def write(state_dir, slug, *, plan_path: str, now: str):
    """Write the marker. Returns (path, is_new), or None when refused or failed."""
    try:
        rec = holdrepeat.load_record(state_dir, slug)
        key, digest = record_hold(rec)
        if rec is None or not key or not digest or not plan_path:
            return None
        mtime_ns = os.stat(plan_path).st_mtime_ns
        real_plan = os.path.realpath(plan_path)
        doc = {
            "schema_version": SCHEMA_VERSION,
            "slug": slug,
            "structural_key": key,
            "hold_hash": digest,
            "reason": "; ".join(sorted(_condition3_parts(rec))),
            "plan_path": real_plan,
            "plan_mtime_ns": mtime_ns,
            "written_at": now,
        }
        prior = _read_marker(state_dir, slug)
        is_new = prior is None or any(
            prior.get(f) != doc[f]
            for f in ("structural_key", "hold_hash", "plan_path", "plan_mtime_ns")
        )
        path = marker_path(state_dir, slug)
        tmp = f"{path}.{os.getpid()}.tmp"
        try:
            os.makedirs(os.path.dirname(path), exist_ok=True)
            with open(tmp, "w") as fh:
                fh.write(json.dumps(doc, indent=1))
            os.replace(tmp, path)
        except OSError:
            try:
                os.remove(tmp)
            except OSError:
                pass
            raise
        return path, is_new
    except Exception as e:  # noqa: BLE001 - the marker is advisory
        print(f"heldmarker: write failed ({e!r}); continuing", file=sys.stderr)
        return None


def active(state_dir, slug, plan_path: str) -> tuple[bool, str]:
    """Read-only. True only when the marker still describes the live hold and plan."""
    try:
        marker = _read_marker(state_dir, slug)
        if marker is None or not plan_path:
            return False, ""
        rec = holdrepeat.load_record(state_dir, slug)
        if rec is None:
            return False, ""
        key, digest = record_hold(rec)
        if not digest or (key, digest) != (marker.get("structural_key"), marker.get("hold_hash")):
            return False, ""
        if os.path.realpath(plan_path) != marker.get("plan_path"):
            return False, ""
        if os.stat(plan_path).st_mtime_ns != marker.get("plan_mtime_ns"):
            return False, ""
        reason = (f"held-unfixable: same hold ({marker.get('reason')}), plan unchanged; "
                  f"marker {marker_path(state_dir, slug)}")
        return True, reason
    except Exception:  # noqa: BLE001 - fail open by design
        return False, ""


def clear(state_dir, slug) -> None:
    try:
        os.remove(marker_path(state_dir, slug))
    except OSError:
        pass


def _build_parser() -> argparse.ArgumentParser:
    ap = argparse.ArgumentParser(prog="harness.heldmarker")
    sub = ap.add_subparsers(dest="cmd", required=True)
    chk = sub.add_parser("check", help="suppress a run whose hold was already declined as unfixable")
    chk.add_argument("--state-dir", required=True)
    chk.add_argument("--slug", required=True)
    chk.add_argument("--plan", default="")  # explicit override path only; "" = resolve via locate_plan
    return ap


def main(argv=None) -> int:
    args = _build_parser().parse_args(argv)
    try:
        from harness.planfile import locate_plan
        plan = locate_plan(args.slug, explicit_path=args.plan or None)
        plan_path = plan.path if plan.found else ""
        is_active, reason = active(args.state_dir, args.slug, plan_path)
    except Exception as e:  # noqa: BLE001 - fail open by design
        print(f"heldmarker: check failed ({e}); proceeding", file=sys.stderr)
        return 0
    if is_active:
        print(f"heldmarker: SUPPRESSED {reason}")
        return SUPPRESS_EXIT
    return 0


if __name__ == "__main__":
    sys.exit(main())
