"""Shared reader for what bin/post-plan-now launches (ljob_submit + per-run script)."""
import json
import pathlib
import re

# A launchctl stand-in that records every call as one JSON argv line in
# $LCTL_ARGV_LOG, prints nothing, and exits 0 (or $LCTL_SUBMIT_RC for submit).
# Unset and empty are both legal for each var: no log written, rc 0.
# test_recording_stub_env_unset_and_empty pins all four cases.
RECORDING_LAUNCHCTL = """#!/usr/bin/env python3
import json, os, sys
p = os.environ.get("LCTL_ARGV_LOG")
if p:
    with open(p, "a") as fh:
        fh.write(json.dumps(sys.argv[1:]) + "\\n")
if sys.argv[1:2] == ["submit"]:
    sys.exit(int(os.environ.get("LCTL_SUBMIT_RC") or "0"))
"""

def launched_cmd(stdout: str, home: pathlib.Path) -> str:
    """Return the $CMD text post-plan-now wrote to its per-run script."""
    agents = home / "Library" / "LaunchAgents"
    files = list(agents.iterdir()) if agents.is_dir() else []
    assert not files, f"post-plan-now wrote into LaunchAgents: {files}"
    paths = re.findall(r"^  script: (\S+)", stdout, re.M)
    assert len(paths) == 1, f"expected one '  script:' line, got {paths}: {stdout!r}"
    text = pathlib.Path(paths[0]).read_text()
    first, _, body = text.partition("\n")
    assert first == "#!/bin/bash", f"unexpected shebang {first!r}"
    assert body.startswith("export PATH="), body[:80]
    return body.rstrip("\n")

def submit_calls(argv_log: pathlib.Path) -> list:
    """Every recorded `launchctl submit ...` argv, in order."""
    if not argv_log.exists():
        return []
    calls = [json.loads(line) for line in argv_log.read_text().splitlines() if line]
    return [c for c in calls if c[:1] == ["submit"]]
