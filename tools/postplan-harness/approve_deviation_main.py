#!/usr/bin/env python3
"""Entry shim for bin/approve-deviation (runs under `python3 -I`)."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness.approve_deviation import main  # noqa: E402

if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
