#!/usr/bin/env bash
# bin/lib/launchd-job.sh — sourced library; defines launchd-job helpers only.
#
# Bash 3.2 / macOS compatible.  No set options, no traps — lib must not alter
# the caller's shell state.  Sourced by bin/docfix-run, bin/launchd-health-check,
# bin/lib/pr-cycle-watch.sh, bin/plan-now, bin/post-plan-fleet, bin/post-plan-now,
# bin/pr-cycle, bin/pr-review-now, bin/usage-gate-coordinator,
# bin/usage-gate-cron-setup, bin/wt-new.
#
# Callers: . "$SCRIPT_DIR/lib/launchd-job.sh"
#          . "$ROOT/bin/lib/launchd-job.sh"

[[ -n "${_LJOB_SOURCED:-}" ]] && return 0
_LJOB_SOURCED=1

# ---------------------------------------------------------------------------
# ljob_agents_dir — resolved at call time from $HOME so a test that points
# HOME at a scratch dir gets the right path without any extra seam.
# ---------------------------------------------------------------------------
ljob_agents_dir() { printf '%s\n' "$HOME/Library/LaunchAgents"; }

# PATH every detached job gets (plist EnvironmentVariables and ljob_submit alike).
ljob_job_path() { printf '%s' "/usr/local/bin:/opt/homebrew/bin:${HOME}/.bun/bin:${HOME}/.local/bin:/usr/bin:/bin:/usr/sbin:/sbin"; }

# ---------------------------------------------------------------------------
# ljob_snapshot — one-shot job-table snapshot; callers keep the result:
#   LAUNCHCTL_LIST="$(ljob_snapshot)"
# Never `launchctl list | grep -q` (pipefail + SIGPIPE false negative).
# `|| true` covers Linux CI, which has no launchctl.
# ---------------------------------------------------------------------------
ljob_snapshot() { launchctl list 2>/dev/null || true; }

# Branch name -> the SAFE_SLUG bin/post-plan-now derives for its launchd label.
# Must stay byte-identical to bin/post-plan-now's `tr -cs 'A-Za-z0-9._-' '-'`.
ljob_safe_slug() { printf '%s' "$1" | tr -cs 'A-Za-z0-9._-' '-'; }

# ljob_postplan_now_live <safe_slug> <snapshot>: prints the label of a live worktree-fired
# post-plan-now run for that slug and returns 0, or returns 1. Same anchored glob as
# postplan_inflight_label in bin/post-plan-now: the "-" after the slug must be followed by
# YYYYmmdd-HHMMSS-<pid>, so slug "foo" never matches a job for slug "foo-bar". The quoted
# "$want" is matched literally, so dots in a slug need no escaping. Reads a here-string,
# never a pipe, so an early return cannot SIGPIPE a writer under pipefail.
ljob_postplan_now_live() {
    local want="$1" snap="${2-}" _pid _status label
    [[ -n "$want" ]] || return 1
    while IFS=$'\t' read -r _pid _status label; do
        case "$label" in
            com.ibl5.postplan-now-"$want"-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9]-[0-9]*)
                printf '%s\n' "$label"; return 0 ;;
        esac
    done <<< "$snap"
    return 1
}

# ---------------------------------------------------------------------------
# ljob_listed <ERE> <snapshot> — herestring grep; anchoring is the caller's.
# ---------------------------------------------------------------------------
ljob_listed() { grep -qE -- "$1" <<< "$2"; }

# ---------------------------------------------------------------------------
# ljob_label_loaded <label> — point lookup used by the reaper.
# ---------------------------------------------------------------------------
ljob_label_loaded() { launchctl list "$1" >/dev/null 2>&1; }

# ---------------------------------------------------------------------------
# ljob_pgrep_alive <pattern>... — 0 = alive OR undecidable, 1 = provably dead.
# Missing pgrep => alive.  Any pattern matching => alive.  Otherwise the exit
# status of the LAST pgrep decides: 1 => dead, anything else => alive.
# ---------------------------------------------------------------------------
ljob_pgrep_alive() {
    local pat rc=1
    command -v pgrep >/dev/null 2>&1 || return 0
    for pat in "$@"; do
        if pgrep -f "$pat" >/dev/null 2>&1; then return 0; else rc=$?; fi
    done
    [[ $rc -eq 1 ]] && return 1
    return 0
}

# ---------------------------------------------------------------------------
# ljob_kill_label <label> <alive_fn> <pattern>...
# bootout, TERM every pattern in order, sleep 2, KILL every pattern in order,
# then wait up to 5 s for <alive_fn label> to report dead.
# Returns 1 if still alive after the wait (caller prints its own ERROR lines).
# ---------------------------------------------------------------------------
ljob_kill_label() {
    local label="$1" alive_fn="$2"
    shift 2
    local pat waited=0
    launchctl bootout "gui/$(id -u)/${label}" 2>/dev/null || true
    # A submitted job may survive bootout; remove is the documented teardown for it.
    if launchctl list "$label" >/dev/null 2>&1; then
        launchctl remove "$label" 2>/dev/null || true
    fi
    for pat in "$@"; do
        pkill -TERM -f "$pat" 2>/dev/null || true
    done
    sleep 2
    for pat in "$@"; do
        pkill -KILL -f "$pat" 2>/dev/null || true
    done
    while "$alive_fn" "$label"; do
        if [[ $waited -ge 5 ]]; then
            return 1
        fi
        sleep 1; waited=$((waited + 1))
    done
    return 0
}

# ---------------------------------------------------------------------------
# ljob_release_slot <plist> <log> <scratch_glob>
# mktemp-keep the log, rm plist, rm every compgen -G match of <scratch_glob>,
# move the keep to <log>.aborted.  The .aborted file is created AFTER the
# scratch sweep, so it survives even when the glob would otherwise match it.
# ---------------------------------------------------------------------------
ljob_release_slot() {
    local plist="$1" log="$2" scratch_glob="$3"
    local keep="" _s
    if [[ -f "$log" ]]; then
        keep="$(mktemp /tmp/prn-keep.XXXXXX)"
        mv -f "$log" "$keep"
    fi
    rm -f "$plist"
    for _s in $(compgen -G "$scratch_glob" 2>/dev/null); do
        rm -f "$_s"
    done
    if [[ -n "$keep" ]]; then
        mv -f "$keep" "${log}.aborted"
    fi
}

# ---------------------------------------------------------------------------
# ljob_count_plists <label_prefix>
# Count of $(ljob_agents_dir)/<prefix>*.plist; -e check so unmatched glob = 0.
# ---------------------------------------------------------------------------
ljob_count_plists() {
    local prefix="$1" f n=0
    for f in "$(ljob_agents_dir)/${prefix}"*.plist; do
        [[ -e "$f" ]] && n=$((n + 1))
    done
    printf '%s\n' "$n"
}

# ---------------------------------------------------------------------------
# ljob_reap_stale <label_prefix> <alive_fn>
# For each plist: keep if ljob_label_loaded; keep if <alive_fn> <label>;
# else rm -f and echo "reaped stale slot <label>".  Always return 0.
# ---------------------------------------------------------------------------
ljob_reap_stale() {
    local prefix="$1" alive_fn="$2"
    local f label
    for f in "$(ljob_agents_dir)/${prefix}"*.plist; do
        [[ -e "$f" ]] || continue
        label="$(basename "$f" .plist)"
        ljob_label_loaded "$label" && continue
        "$alive_fn" "$label" && continue
        rm -f "$f"; echo "reaped stale slot $label"
    done
    return 0
}

# ---------------------------------------------------------------------------
# ljob_wait_for_slot <count_fn> <reap_fn> <jobs> <ceiling_secs> <dry_seam_name>
# Verbatim fleet loop; reads the dry seam indirectly via ${!5:-}.
# ---------------------------------------------------------------------------
ljob_wait_for_slot() {
    local count_fn="$1" reap_fn="$2" jobs="$3" ceiling_secs="$4" dry_seam_name="$5"
    local waited=0
    while [[ "$("$count_fn")" -ge "$jobs" ]]; do
        echo "waiting for slot ($("$count_fn")/$jobs in flight)…"
        "$reap_fn"
        [[ "$("$count_fn")" -lt "$jobs" ]] && break
        if [[ -n "${!dry_seam_name:-}" ]]; then
            echo "cap reached under ${dry_seam_name} — not sleeping; stopping here." >&2
            exit 0
        fi
        sleep 5; waited=$((waited + 5))
        if [[ $waited -ge $ceiling_secs ]]; then
            echo "ERROR: waited 2h for a slot; aborting" >&2; exit 1
        fi
    done
}

# ---------------------------------------------------------------------------
# ljob_write_runner_plist <plist> <label> <runner> <root> <log>
# Writes the compact plist layout byte-for-byte (same key order, same PATH
# string, unquoted heredoc so variables expand at write time).
# Does NOT mkdir.
# ---------------------------------------------------------------------------
ljob_write_runner_plist() {
    local plist="$1" label="$2" runner="$3" root="$4" log="$5"
    cat > "$plist" <<PLIST_EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0"><dict>
  <key>Label</key><string>${label}</string>
  <key>ProgramArguments</key><array>
    <string>/bin/bash</string><string>-lc</string><string>exec ${runner}</string>
  </array>
  <key>RunAtLoad</key><true/>
  <key>KeepAlive</key><false/>
  <key>WorkingDirectory</key><string>${root}</string>
  <key>StandardOutPath</key><string>${log}</string>
  <key>StandardErrorPath</key><string>${log}</string>
  <key>EnvironmentVariables</key><dict>
    <key>PATH</key><string>$(ljob_job_path)</string>
  </dict>
</dict></plist>
PLIST_EOF
}

# ---------------------------------------------------------------------------
# ljob_bootstrap <label> <plist>
# bootout (errors ignored) then bootstrap; returns bootstrap's exit status.
# ---------------------------------------------------------------------------
ljob_bootstrap() {
    local label="$1" plist="$2"
    launchctl bootout "gui/$(id -u)/${label}" 2>/dev/null || true
    launchctl bootstrap "gui/$(id -u)" "$plist"
}

# ---------------------------------------------------------------------------
# Per-run detached jobs with NO file in ~/Library/LaunchAgents.
# Every per-run launcher goes through ljob_submit, so swapping the mechanism
# later edits only this block. launchd restarts a submitted job after it exits
# until the label is removed. The wrapper's once-guard (atomic mkdir of
# "<script>.once") turns any respawn into "remove the label, exit 0" so the
# script body runs at most once. The normal tail ignores TERM, removes the
# label, then removes the marker, and exits with the script's status.
# ---------------------------------------------------------------------------
_ljob_valid_label() { [[ "$1" =~ ^com\.ibl5\.[A-Za-z0-9._-]+$ ]]; }

# ljob_submit_wrapper_text <label> <script>: prints the bash -c text the job runs.
ljob_submit_wrapper_text() {
    local label="$1" script="$2" ctl l s m c
    ctl="$(command -v "${LAUNCHCTL_CMD:-launchctl}" 2>/dev/null || printf '%s' "${LAUNCHCTL_CMD:-launchctl}")"
    l="$(shq "$label")"; s="$(shq "$script")"; m="$(shq "${script}.once")"; c="$(shq "$ctl")"
    printf '%s' "if ! mkdir $m 2>/dev/null; then trap '' TERM; $c remove $l; rmdir $m; exit 0; fi; $s; rc=\$?; trap '' TERM; $c remove $l; rmdir $m; exit \$rc"
}

# Builds LJOB_SUBMIT_ARGV. Returns 2 (nothing built) on a usage error.
_ljob_submit_argv() {
    local label="${1:-}" log="${2:-}" script="${3:-}"
    if [[ $# -ne 3 ]] || ! _ljob_valid_label "$label" || [[ "$log" != /* || "$script" != /* ]]; then
        echo "ljob_submit: usage: ljob_submit <com.ibl5.label> </abs/log> </abs/script>" >&2
        return 2
    fi
    [[ -x "$script" ]] || { echo "ljob_submit: script not executable: $script" >&2; return 2; }
    [[ -e "${script}.once" ]] && { echo "ljob_submit: once-marker already exists: ${script}.once" >&2; return 2; }
    LJOB_SUBMIT_ARGV=(submit -l "$label" -o "$log" -e "$log" -- \
        /usr/bin/env "HOME=$HOME" "PATH=$(ljob_job_path)" \
        /bin/bash -lc "$(ljob_submit_wrapper_text "$label" "$script")")
}

# ljob_submit <label> <log> <script>: launch <script> once, detached, under <label>.
# Returns 2 on a usage error (launchctl never called), else launchctl's status.
ljob_submit() {
    _ljob_submit_argv "$@" || return 2
    "${LAUNCHCTL_CMD:-launchctl}" "${LJOB_SUBMIT_ARGV[@]}"
}

# ljob_submit_print <label> <log> <script>: the exact argv ljob_submit would run,
# shell-quoted on one line. Dry-run callers print it and launch nothing.
ljob_submit_print() {
    _ljob_submit_argv "$@" || return 2
    printf '%s' "${LAUNCHCTL_CMD:-launchctl}"; printf ' %q' "${LJOB_SUBMIT_ARGV[@]}"; printf '\n'
}

# ---------------------------------------------------------------------------
# Runner-embeddable helpers — must not call any other ljob_ function because
# callers inject them via `declare -f`.
#
# ljob_dm_ready <tool>
# Reads runner globals NOTIFY, DISCORD_DM.  Returns 1 silently when NOTIFY!=1;
# prints "<tool>: no executable discord-dm at $DISCORD_DM — not notifying."
# and returns 1 when DISCORD_DM is not executable; else 0.
# ---------------------------------------------------------------------------
ljob_dm_ready() {
    local tool="$1"
    [ "${NOTIFY:-}" = 1 ] || return 1
    if [ ! -x "${DISCORD_DM:-}" ]; then
        printf '%s\n' "${tool}: no executable discord-dm at ${DISCORD_DM:-} — not notifying."
        return 1
    fi
    return 0
}

# ---------------------------------------------------------------------------
# ljob_dm_send <tool> <msg>
# printf '%s' "$msg" | "$DISCORD_DM" -; on failure prints the UNDELIVERED line.
# ---------------------------------------------------------------------------
ljob_dm_send() {
    local tool="$1" msg="$2"
    if ! printf '%s' "$msg" | "$DISCORD_DM" -; then
        printf '%s\n' "${tool}: DM UNDELIVERED (spooled by discord-dm) — the verdict above stands."
    fi
}

# ---------------------------------------------------------------------------
# xml_escape / shq — keep UNPREFIXED names: bin/post-plan-now embeds them via
# `declare -f` and its runner command text uses these exact names.
# Bodies copied byte-for-byte from bin/post-plan-now.
# ---------------------------------------------------------------------------
xml_escape() { printf '%s' "$1" | sed -e 's/&/\&amp;/g' -e 's/</\&lt;/g' -e 's/>/\&gt;/g'; }
shq() { local q="'\\''"; printf "'%s'" "${1//\'/$q}"; }

# ---------------------------------------------------------------------------
# ljob_reap_orphan_scripts <dir> <file_prefix> <label_prefix> <min_age_min> <snapshot>
# Deletes <dir>/<file_prefix>*.sh (and its .once marker) when the file is older
# than <min_age_min> minutes AND the label <label_prefix><stem after file_prefix>
# is absent from <snapshot>. A finished ljob_submit run deletes its own script,
# so such a file belongs to a run killed by SIGKILL, reboot or logout.
# An empty snapshot reaps nothing: a failed `launchctl list` must never read as
# "nothing is running". LJOB_REAP_DRY=1 prints "would reap <file>" and changes
# nothing. Always returns 0.
# ---------------------------------------------------------------------------
ljob_reap_orphan_scripts() {
    local dir="$1" fpre="$2" lpre="$3" age="$4" snap="$5" f stem label
    [[ -n "$snap" ]] || return 0
    for f in "$dir/$fpre"*.sh; do
        [[ -e "$f" ]] || continue
        [[ -n "$(find "$f" -mmin "-${age}" 2>/dev/null)" ]] && continue   # too young
        stem="$(basename "$f" .sh)"
        label="${lpre}${stem#"$fpre"}"
        awk -F'\t' -v l="$label" '$3 == l {f=1} END {exit !f}' <<< "$snap" && continue   # still loaded
        if [[ -n "${LJOB_REAP_DRY:-}" ]]; then echo "would reap $f"; continue; fi
        rm -f "$f"; rmdir "$f.once" 2>/dev/null || true
        echo "reaped orphan script $f"
    done
    return 0
}
