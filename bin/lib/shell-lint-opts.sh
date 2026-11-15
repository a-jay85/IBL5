# shellcheck shell=bash
# bin/lib/shell-lint-opts.sh: the repo's shellcheck flag policy for workflow run: blocks.
# Sourced, never executed. It defines two variables and does nothing else, so it
# is safe to source under `set -euo pipefail`.
# Readers: bin/lint-workflows (actionlint -shellcheck), bin/lib/prefire-verify
# (SHELLCHECK_OPTS for matrix rows that run actionlint or shellcheck), the
# ShellCheck job in .github/workflows/tests.yml, and bin/pre-commit-hook. Each
# reads IBL5_SC_OPTS unquoted so the shell splits it into separate flags.
# A reader never spells the excludes out itself. test_sc_opts_single_source in
# bin/test-prefire-verify fails when a reader carries its own copy.
IBL5_SC_EXCLUDE='--exclude=SC2034,SC1090,SC2207'
IBL5_SC_OPTS="--severity=warning $IBL5_SC_EXCLUDE"
