Fixtures for tests/test_conflict_report_sticky.py.

Each set holds four versions of one file: base (the merge base), master (the
target side), branch (this branch's side) and resolved (what Claude committed).
The tests build a throwaway git repo from them and run the real lost-line check.

pr3705/  Route: real. Cut from the live PR #3705 conflict in bin/lib/wt-guards.sh.
         Claude's resolution dropped nine master lines inside kill_infra_processes().
         The test pins those nine lines.

pr3726/  Route: hand-built. PR #3726 was already rebased onto a single commit
         (a254f6e705), so its original conflict stops being reachable. These files
         are an eight-to-nine-line window of the README at that commit. The branch added one
         bullet ("Phase 5.5 note filing") and master did not touch the window. The
         resolved file keeps that bullet, so the test pins an empty loss list.
