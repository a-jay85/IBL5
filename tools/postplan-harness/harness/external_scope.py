"""Changed-hunk test for the non-workflow external paths of merge-danger trigger 2.

`bin/db-sync-prod` fires only when a changed line names a prod connection or a variable that
shapes the table lists the dump reads. `bin/discord-dm` fires only when a changed line can change
who gets a message or what is sent: an assignment to a send-shaping variable, a send command or
token, a command outside a local allowlist, a call to or redefinition of an existing send
function, an output redirect to anything but `/dev/null`, a standard stream or `$log`
(the sent log), or a removed test or exit that the same hunk does not re-add. `.github/actions/notify-discord/`
and `ibl5/classes/Mail/` fire on any changed code line. Comment, blank and indentation-only
edits, and the pipefail-safe rewrite of `echo "$v" | grep ...` into `grep ... <<< "$v"`, stay
quiet. Any shape the parser cannot read returns a reason, so the trigger fires (fail closed),
the same contract as `workflow_scope.workflow_send_change`.
"""
from __future__ import annotations

import re

from harness.workflow_scope import _HUNK_RE, _R_BAD_HUNK, _R_BAD_SECTION, _R_TRUNCATED, _SECTION_META

SYNC_PATH = "bin/db-sync-prod"
_R_MISSING = "missing-section"
_R_CONTINUATION = "truncated-continuation"
_R_SYNC = "sync-change"
_R_SEND = "send-change"
DM_PATH = "bin/discord-dm"
_R_DM_TOKEN = "send-token"
_R_DM_FLOW = "flow-change"
_R_DM_REDIRECT = "redirect-target"
_R_DM_UNREADABLE = "unreadable-shell"

# Every variable bin/discord-dm reads to pick the recipient, target, route, body, chunks, retries,
# spool or fallback, plus the locals of its send functions and the env names that steer lookup.
_DM_SEND_VARS = frozenset((
    "TO", "SSH_HOST", "BOT_PORT", "ATTEMPTS", "QUIET", "FALLBACK", "CHUNK", "ROUTE", "RAW",
    "SSH_BIN", "OSASCRIPT_BIN", "CURL_BIN", "WEBHOOK_FILE", "ERRORS_WEBHOOK_FILE", "OWNER_ID",
    "ERROR", "SPOOL", "ID_FILE", "BACKOFF", "MSG", "USE_WEBHOOK", "WEBHOOK_URL", "FULL_MSG",
    "CAP", "CHUNKS", "REMOTE_CMD", "http", "LAST_ATTEMPTS", "WH_PAYLOAD", "TOTAL", "DELIVERED",
    "IDX", "rc", "NOTE", "chunk", "msg", "payload", "content", "am", "out", "text", "piece",
    "cut", "u", "PATH", "IFS", "HOME", "BASH_ENV", "ENV", "PS4",
    # The chunk prefix counters, the retry counter, and the exit codes the retry loop reads.
    "i", "total", "n", "ra", "curl_rc", "ssh_rc",
))
# The only variables a redirect may write through. `log_sent` appends to `$log`.
_DM_LOG_TARGETS = frozenset(("log",))
# An assignment bash makes while it evaluates arithmetic: `$((TO=5))`, `x[TO=5]`, `n++`.
_DM_ARITH_SET_RE = re.compile(
    r"(?:\(\(|\w\[)[^\]]*?(?:(?<![=!<>])=(?!=)|<<=|>>=|\+\+|--)"
    r"|-(?:eq|ne|lt|le|gt|ge)\s+[^\s\]]*(?<![=!<>])=(?!=)"
)
# `sed` runs only as `sed [-n|-E|-r] '<script>' [file...]`. The script is one `p`/`d` with an
# optional line address, or one `s` command whose flags cannot write a file or run a command.
_DM_SED_RE = re.compile(
    r"""\bsed((?:\s+-[nEr]+)*)\s+'([^']*)'(?:\s+(?:"\$\{?\w+\}?"|\$\{?\w+\}?))*\s*"""
)
_DM_SED_SCRIPT_RE = re.compile(
    r"(?:(?:\d+|\$)(?:,(?:\d+|\$))?[pd])"
    r"|s(?P<d>[/|#,])(?:\\.|(?!(?P=d)).)*(?P=d)(?:\\.|(?!(?P=d)).)*(?P=d)[gpI0-9]*"
)
_DM_EXISTING_FUNCS = frozenset(("webhook_url_from", "say", "chunk_message", "send_one", "send_webhook_one"))
# Commands that run locally and cannot deliver a message. `say` prints to stderr only.
_DM_SAFE_COMMANDS = frozenset((
    "[", "[[", "test", "printf", "echo", "tr", "cut", "head", "tail", "sed", "wc",
    "date", "ps", "dirname", "basename", "mkdir", "cat", "grep", "true", "false", ":",
    "local", "say",
))
_DM_CONTROL = frozenset(("exit", "return", "break", "continue", "shift"))
_DM_SAFE_TARGETS = ("/dev/null", "1", "2", "-")
# Scanned over the whole joined command, quoted text included, before any parsing.
_DM_DENY_RE = re.compile(
    r"\b(?:curl|wget|nc|ncat|socat|ssh|scp|sftp|rsync|osascript|telnet|jq|eval|exec|source|xargs"
    r"|env|command|builtin|nohup|sudo|bash|sh|zsh|python3?|perl|ruby|node|php|awk)\b"
    r"|/dev/(?:tcp|udp)\b|https?://|`|[<>]\(|\bprintf\s+-v|\$\{\w+:?="
)
# Same-length mask characters for quoted text, `${...}`, and `$(...)` / `$((...))`.
_DM_Q, _DM_V, _DM_S = "\x01", "\x02", "\x03"
_DM_TESTS = frozenset(("[", "[[", "test"))
_DM_RESERVED = frozenset((
    "if", "then", "elif", "else", "fi", "for", "in", "do", "done", "while", "until", "case",
    "esac", "{", "}", "(", ")", "!",
))
_DM_OPENERS = {"if": "if", "for": "loop", "while": "loop", "until": "loop", "case": "case",
               "{": "{", "(": "("}
_DM_CLOSERS = {"fi": ("if",), "done": ("loop",), "esac": ("case",), "}": ("{", "func"), ")": ("(",)}
_DM_MIDDLES = frozenset(("then", "elif", "else", "do"))
_DM_ARM = r"[^\s()|;&<>]+(?:\|[^\s()|;&<>]+)*"
# A line-start case arm takes no leading `(`, so a `(cmd)` subshell is never read as an arm.
_DM_ARM_LEAD_RE = re.compile(r"^\s*" + _DM_ARM + r"\)")
_DM_ARM_CASE_RE = re.compile(r"\bcase\s+\S+\s+in\s+\(?" + _DM_ARM + r"\)")
_DM_ARM_NEXT_RE = re.compile(r";;\s*\(?" + _DM_ARM + r"\)")
_DM_FUNC_RE = re.compile(r"^\s*([A-Za-z_]\w*)\s*\(\)\s*")
_DM_ASSIGN_RE = re.compile(r"^([A-Za-z_]\w*)(?:\[[^\]]*\])?\+?=")
_DM_REDIR_RE = re.compile(r"(?:\d+|&)?(?:>>|>\||>&|>)|\d*<<<|\d*<&|\d*<")
_DM_VAR_TARGET_RE = re.compile(r'\$(\w+)|\$\{(\w+)\}|"\$(\w+)"|"\$\{(\w+)\}"')

# Reading PRESERVE_TABLES (e.g. passing it to a local replay) is quiet; assigning it fires.
_SYNC_TOKEN_RE = re.compile(
    r"REMOTE_|\bremote_(?:query|dump)\b|mariadb-dump|mysqldump|\b(?:ssh|scp|rsync|curl|wget)\b"
    r"|--ignore-table|--host\b|(?:^|\s)-h\s|db-sync-preserve-tables"
    r"|\b(?:GEN_RE|PRESERVE_RE|ALL_TABLES|REGULAR_TABLES|GENERATED_TABLES|IGNORE_ARGS"
    r"|PRESERVE_EXISTING|_kept_gen|_preserve_name_ok)\b|\bPRESERVE_TABLES\+?="
)
_ECHO_GREP_RE = re.compile(r'echo ("\$\{?\w+\}?") \| (grep(?: -[A-Za-z]+)*) ("[^"]*")')
_HERESTRING_RE = re.compile(r"<<<\s*")
_DQUOTE_RE = re.compile(r'(?<!\\)"')
_SHELL_COMMENT_RE = re.compile(r"#(?!!)")
_PHP_COMMENT_RE = re.compile(r"(?://|#(?!\[)|/\*|\*/|\*(?:\s|$))")
_YAML_COMMENT_RE = re.compile(r"#")


def _comment_re(path: str) -> re.Pattern[str] | None:
    """The comment opener for the file type; None means every non-blank line is code."""
    name = path.rsplit("/", 1)[-1]
    if name.endswith(".php"):
        return _PHP_COMMENT_RE
    if name.endswith((".yml", ".yaml")):
        return _YAML_COMMENT_RE
    if name.endswith(".sh") or "." not in name:
        return _SHELL_COMMENT_RE
    return None


def _norm(line: str, keep_indent: bool = False) -> str:
    """Indentation-free form. The here-string rewrite of `echo "$v" | grep` maps to one form.
    YAML keeps its leading whitespace, because indentation is structure there."""
    line = line.rstrip() if keep_indent else line.strip()
    line = _HERESTRING_RE.sub("<<< ", line)
    line = _ECHO_GREP_RE.sub(r"\2 \3 <<< \1", line)
    return line


def _hunks(section: str) -> list[tuple[int, int, list[str]]] | str:
    """(old_start, new_start, tagged body lines) per hunk, or a fail-closed reason."""
    lines = section.split("\n")
    if lines and lines[-1] == "":
        lines.pop()
    start = next((k for k, ln in enumerate(lines) if ln.startswith("@@")), None)
    if start is None or not all(ln.startswith(_SECTION_META) for ln in lines[:start]):
        return _R_BAD_SECTION
    hunks: list[tuple[int, int, list[str]]] = []
    i = start
    while i < len(lines):
        m = _HUNK_RE.match(lines[i])
        if m is None:
            return _R_BAD_HUNK
        old_left, new_left = int(m.group(2) or "1"), int(m.group(4) or "1")
        body: list[str] = []
        i += 1
        while old_left > 0 or new_left > 0:
            if i == len(lines):
                return _R_TRUNCATED
            tag = lines[i][:1]
            if tag != "\\":
                if tag not in (" ", "-", "+"):
                    return _R_BAD_HUNK
                old_left -= 0 if tag == "+" else 1
                new_left -= 0 if tag == "-" else 1
                if old_left < 0 or new_left < 0:
                    return _R_BAD_HUNK
                body.append(lines[i])
            i += 1
        while i < len(lines) and lines[i].startswith("\\"):
            i += 1
        hunks.append((int(m.group(1)), int(m.group(3)), body))
    return hunks


def _live_changes(body: list[str], keep_indent: bool = False) -> list[int]:
    """Changed-line indexes left after aligned cancellation. In a run of removed lines followed by
    a run of added lines of the same length, the i-th pair cancels when both normalize equal.
    A reorder or a length change cancels nothing."""
    live: list[int] = []
    i = 0
    while i < len(body):
        if body[i][:1] == " ":
            i += 1
            continue
        minus: list[int] = []
        while i < len(body) and body[i][:1] == "-":
            minus.append(i)
            i += 1
        plus: list[int] = []
        while i < len(body) and body[i][:1] == "+":
            plus.append(i)
            i += 1
        if len(minus) == len(plus):
            for a, b in zip(minus, plus):
                if _norm(body[a][1:], keep_indent) != _norm(body[b][1:], keep_indent):
                    live += [a, b]
        else:
            live += minus + plus
    return live


def _sync_reason(body: list[str], idx: int, start: int) -> str | None:
    """Join the backslash-continued command holding body[idx] over its own side of the hunk."""
    tag = body[idx][:1]
    side = [k for k, ln in enumerate(body) if ln[:1] in (" ", tag)]
    pos = side.index(idx)
    lo = pos
    while lo > 0 and body[side[lo - 1]].rstrip().endswith("\\"):
        if _dm_comment_cont(body[side[lo - 1]][1:]):
            return _R_CONTINUATION
        lo -= 1
    if lo == 0 and start > 1:
        return _R_CONTINUATION
    hi = pos
    while body[side[hi]].rstrip().endswith("\\"):
        if _dm_comment_cont(body[side[hi]][1:]):
            return _R_CONTINUATION
        hi += 1
        if hi == len(side):
            return _R_CONTINUATION
    joined = " ".join(body[side[k]][1:] for k in range(lo, hi + 1))
    if _SYNC_TOKEN_RE.search(joined):
        return _R_SYNC
    return _string_reason(body, idx)


def _string_reason(body: list[str], idx: int) -> str | None:
    """A multi-line double-quoted string, such as a `remote_query "...")` table-list query, hides
    its command head from the backslash join. Walk the changed line's side of the hunk, toggling
    on each line with an odd count of unescaped quotes. When the first such line is a closer (it
    opens with `"` and does not end with one), the hunk starts inside a string whose head is
    unseen, so a changed line there fires. A changed line inside a string whose opening line is in
    view is joined from that line and searched for a sync token."""
    tag = body[idx][:1]
    side = [k for k, ln in enumerate(body) if ln[:1] in (" ", tag)]
    odd = [pos for pos, k in enumerate(side) if len(_DQUOTE_RE.findall(body[k][1:])) % 2]
    first = body[side[odd[0]]][1:].strip() if odd else ""
    opener: int | None = None
    inside = first.startswith('"') and not first.endswith('"')
    for pos, k in enumerate(side):
        if k == idx:
            break
        if pos in odd:
            inside = not inside
            opener = pos if inside else None
    if not inside:
        return None
    if opener is None:
        return _R_CONTINUATION
    through = " ".join(body[side[j]][1:] for j in range(opener, side.index(idx) + 1))
    return _R_SYNC if _SYNC_TOKEN_RE.search(through) else None


_DmStmt = tuple[str, str, str]  # (raw, masked, connector after it)
_DmWords = tuple[list[str], "str | None", list[str], "str | None", list[str], str]


def _dm_comment_cont(line: str) -> bool:
    """True when a line ends in `\\` that sits inside an unquoted `#` comment. Bash does not
    continue such a line."""
    if not line.rstrip().endswith("\\"):
        return False
    masked = _dm_mask(line)
    if masked is None:
        return False
    return any(c == "#" and o == " " and (i == 0 or line[i - 1] in " \t;&|(")
               for i, (c, o) in enumerate(zip(line, masked[0])))


def _dm_join(body: list[str], idx: int, start: int) -> str | tuple[str, list[int]]:
    """The backslash-continued command holding body[idx] and the body indexes it spans, joined
    over its own side of the hunk. Same walk as `_sync_reason`, so it returns `_R_CONTINUATION`
    where that code does."""
    tag = body[idx][:1]
    side = [k for k, ln in enumerate(body) if ln[:1] in (" ", tag)]
    pos = side.index(idx)
    lo = pos
    while lo > 0 and body[side[lo - 1]].rstrip().endswith("\\"):
        if _dm_comment_cont(body[side[lo - 1]][1:]):
            return _R_CONTINUATION
        lo -= 1
    if lo == 0 and start > 1:
        return _R_CONTINUATION
    hi = pos
    while body[side[hi]].rstrip().endswith("\\"):
        if _dm_comment_cont(body[side[hi]][1:]):
            return _R_CONTINUATION
        hi += 1
        if hi == len(side):
            return _R_CONTINUATION
    members = [side[k] for k in range(lo, hi + 1)]
    return " ".join(body[k][1:] for k in members), members


def _dm_mask(text: str) -> tuple[str, list[str]] | None:
    """A same-length copy of `text` with quoted spans, `${...}` and `$(...)` masked, plus the
    body of each outermost `$(...)`. None when a quote or an expansion never closes, or on a
    heredoc."""
    out = list(text)
    bodies: list[str] = []
    quote = ""
    stack: list[list] = []  # [kind, start, saved quote, paren depth]
    i, n = 0, len(text)
    while i < n:
        c = text[i]
        nxt = text[i + 1] if i + 1 < n else ""
        if quote == "'":
            out[i] = _DM_Q
            if c == "'":
                quote = ""
            i += 1
            continue
        if c == "\\":
            if not quote and (nxt == "" or nxt.isspace()):
                out[i] = " "
                i += 1
                continue
            out[i] = _DM_Q
            if nxt:
                out[i + 1] = _DM_Q
            i += 2
            continue
        if c == "$" and nxt == "(":
            kind = "((" if text.startswith("((", i + 1) else "("
            stack.append([kind, i, quote, 0])
            quote = ""
            i += 1 + len(kind)
            continue
        if c == "$" and nxt == "{":
            stack.append(["{", i, quote, 0])
            quote = ""
            i += 2
            continue
        if quote == '"':
            out[i] = _DM_Q
            if c == '"':
                quote = ""
            i += 1
            continue
        if c in "'\"":
            quote = c
            out[i] = _DM_Q
            i += 1
            continue
        if c == "#" and (i == 0 or text[i - 1] in " \t\n;&|(") and (not stack or stack[-1][0] == "("):
            j = text.find("\n", i)
            j = n if j < 0 else j
            out[i:j] = " " * (j - i)
            i = j
            continue
        if text.startswith("<<", i):
            if not text.startswith("<<<", i):
                return None
            i += 3
            continue
        if stack:
            frame = stack[-1]
            if frame[0] == "{" and c == "}":
                stack.pop()
                out[frame[1]:i + 1] = _DM_V * (i + 1 - frame[1])
                quote = frame[2]
            elif frame[0] != "{" and c == "(":
                frame[3] += 1
            elif frame[0] != "{" and c == ")":
                if frame[3]:
                    frame[3] -= 1
                elif frame[0] == "(":
                    stack.pop()
                    if not any(f[0] == "(" for f in stack):
                        bodies.append(text[frame[1] + 2:i])
                    out[frame[1]:i + 1] = _DM_S * (i + 1 - frame[1])
                    quote = frame[2]
                elif nxt == ")":
                    stack.pop()
                    out[frame[1]:i + 2] = _DM_S * (i + 2 - frame[1])
                    quote = frame[2]
                    i += 2
                    continue
                else:
                    return None
        i += 1
    if quote or stack:
        return None
    return "".join(out), bodies


def _dm_blank(match: re.Match[str], keep: str = "") -> str:
    """A same-length replacement for a stripped case-arm pattern, keeping `keep`'s first word
    at the start and its last character at the end."""
    width = len(match.group(0))
    if not keep:
        return " " * width
    head, tail = keep[:-1], keep[-1]
    return head + " " * (width - len(keep)) + tail


def _dm_split(text: str) -> list[_DmStmt] | None:
    """(raw, masked, connector after it) per statement: each `$(...)` body first, then the top
    level. None when the scanner cannot read the text."""
    masked_bodies = _dm_mask(text)
    if masked_bodies is None:
        return None
    masked, bodies = masked_bodies
    stmts: list[_DmStmt] = []
    for inner_text in bodies:
        inner = _dm_split(inner_text)
        if inner is None:
            return None
        stmts += inner
    masked = _DM_ARM_LEAD_RE.sub(_dm_blank, masked, count=1)
    masked = _DM_ARM_CASE_RE.sub(lambda m: _dm_blank(m, "case;"), masked)
    masked = _DM_ARM_NEXT_RE.sub(lambda m: _dm_blank(m, ";"), masked)
    n = len(masked)
    start = i = 0
    while i <= n:
        op = ""
        if i == n:
            op = "end"
        elif masked[i] == "\n":
            op = ";"
        elif masked.startswith(";;", i):
            op = ";;"
        elif masked[i] == ";":
            op = ";"
        elif masked.startswith("&&", i):
            op = "&&"
        elif masked.startswith("||", i):
            op = "||"
        elif masked[i] == "|" and not (i and masked[i - 1] == ">"):
            op = "|"
        elif masked[i] == "&" and not (i and masked[i - 1] in "<>") and masked[i + 1:i + 2] != ">":
            op = "&"
        if not op:
            i += 1
            continue
        if masked[start:i].strip():
            stmts.append((text[start:i], masked[start:i], "" if op == "end" else op))
        i += 1 if op == "end" else len(op)
        start = i
    return stmts


def _dm_words(raw: str, masked: str) -> _DmWords:
    """(leading reserved words, function name, assigned names, command word, output-redirect
    targets, core) for one statement. `core` is the whitespace-collapsed text after the reserved
    words. A command word that holds masked text comes back masked, so it is never allowed."""
    func = None
    pos = 0
    m = _DM_FUNC_RE.match(masked)
    if m is not None:
        func = m.group(1)
        pos = m.end()
    work = [" "] * pos + list(masked[pos:])
    targets: list[str] = []
    for r in _DM_REDIR_RE.finditer(masked, pos):
        end = r.end()
        while end < len(masked) and masked[end] in " \t":
            end += 1
        tend = end
        while tend < len(masked) and not masked[tend].isspace():
            tend += 1
        if r.group(0).endswith((">", ">|", ">&")):
            targets.append(raw[end:tend])
        work[r.start():tend] = " " * (tend - r.start())
    words = [(w.start(), w.end()) for w in re.finditer(r"\S+", "".join(work))]
    reserved: list[str] = []
    assigns: list[str] = []
    command = None
    k = 0
    if func is not None:
        if words and masked[words[0][0]:words[0][1]] == "{":
            reserved.append("func")
            k = 1
        else:
            reserved.append("func-open")
    bang: int | None = None  # start of a trailing run of `!` words: a negation changes the test
    while k < len(words) and masked[words[k][0]:words[k][1]] in _DM_RESERVED:
        word = masked[words[k][0]:words[k][1]]
        reserved.append(word)
        bang = (k if bang is None else bang) if word == "!" else None
        k += 1
        if word == "for":
            assigns += [masked[s:e] for s, e in words[k:k + 1]]
            k = len(words)
        elif word == "case":
            k = len(words)
    core_k = k if bang is None else bang
    core = " ".join(raw[words[core_k][0]:].split()) if k < len(words) else ""
    while k < len(words):
        am = _DM_ASSIGN_RE.match(masked[words[k][0]:words[k][1]])
        if am is None:
            break
        assigns.append(am.group(1))
        k += 1
    if k < len(words):
        s, e = words[k]
        command = raw[s:e] if raw[s:e] == masked[s:e] else masked[s:e]
        if command == "local":
            lwords = [masked[s2:e2] for s2, e2 in words[k + 1:]]
            assigns += [w.split("=", 1)[0] for w in lwords]
            if any(w.startswith("-") and "n" in w for w in lwords):
                # `local -n r=TO` makes `r` a nameref: a later `r=...` writes `TO`.
                assigns += [w.split("=", 1)[1] for w in lwords if "=" in w]
    return reserved, func, assigns, command, targets, core


def _dm_target_ok(target: str) -> bool:
    """True for `/dev/null`, a standard stream, or a variable that shapes no send."""
    target = target.lstrip("&")
    if target in _DM_SAFE_TARGETS:
        return True
    m = _DM_VAR_TARGET_RE.fullmatch(target)
    if m is None:
        return False
    return next(g for g in m.groups() if g is not None) in _DM_LOG_TARGETS


def _dm_sed_ok(raw: str) -> bool:
    """True when a `sed` statement only reads input and prints."""
    first = re.search(r"\bsed\b", raw)
    m = _DM_SED_RE.match(raw, first.start()) if first is not None else None
    return m is not None and m.end() == len(raw) and _DM_SED_SCRIPT_RE.fullmatch(m.group(2)) is not None


def _dm_walk(stmts: list[_DmStmt]) -> list[bool] | None:
    """Per statement, whether it sits inside a function opened in `stmts`. None when a middle or
    closer has no matching opener, or an opener is left open."""
    stack: list[str] = []
    in_func: list[bool] = []
    for raw, masked, _conn in stmts:
        for word in _dm_words(raw, masked)[0]:
            if word in ("func", "func-open"):
                stack.append(word)
            elif word in _DM_OPENERS:
                stack.append(_DM_OPENERS[word])
            elif word in _DM_CLOSERS:
                if not stack or stack.pop() not in _DM_CLOSERS[word]:
                    return None
            elif word in _DM_MIDDLES and not stack:
                return None
        in_func.append("func" in stack)
    return None if stack else in_func


def _dm_balanced(run: list[list[_DmStmt]]) -> bool:
    """Every opener in the run closes inside it, and no joined command ends in `&&`, `||` or `|`."""
    if any(group and group[-1][2] in ("&&", "||", "|") for group in run):
        return False
    return _dm_walk([s for group in run for s in group]) is not None


def _dm_if_holds(plus: list[_DmWords], test: str, ctl: str) -> bool:
    """True when an added `if <test>; then` holds `<ctl>` at its own level before its `fi`."""
    for j, w in enumerate(plus):
        if not w[0] or w[0][-1] != "if" or w[5] != test:
            continue
        depth = 1
        for later in plus[j + 1:]:
            for word in later[0]:
                depth += 1 if word == "if" else -1 if word == "fi" else 0
            if depth <= 0:
                break
            if depth == 1 and later[5] == ctl:
                return True
    return False


def _dm_conserved(minus_stmts: list[_DmStmt], plus_stmts: list[_DmStmt]) -> bool:
    """Every removed test, exit or guarded exit is re-added by the same block, and every added
    exit that replaces none sits inside a function body the block adds."""
    minus = [_dm_words(r, m) for r, m, _c in minus_stmts]
    plus = [_dm_words(r, m) for r, m, _c in plus_stmts]
    plus_cores: dict[str, int] = {}
    for w in plus:
        plus_cores[w[5]] = plus_cores.get(w[5], 0) + 1
    removed_control: dict[str, int] = {}
    for w in minus:
        if w[3] in _DM_CONTROL or w[3] in _DM_TESTS:
            if plus_cores.get(w[5], 0) <= 0:
                return False
            plus_cores[w[5]] -= 1
        if w[3] in _DM_CONTROL:
            removed_control[w[5]] = removed_control.get(w[5], 0) + 1
    for k in range(len(minus) - 1):
        conn = minus_stmts[k][2]
        if conn not in ("&&", "||") or minus[k + 1][3] not in _DM_CONTROL:
            continue
        test, ctl = minus[k][5], minus[k + 1][5]
        pair = any(plus_stmts[j][2] == conn and plus[j][5] == test and plus[j + 1][5] == ctl
                   for j in range(len(plus) - 1))
        if not pair and not (conn == "&&" and _dm_if_holds(plus, test, ctl)):
            return False
    in_func = _dm_walk(plus_stmts)
    if in_func is None:
        return False
    for w, inside in zip(plus, in_func):
        if w[3] in _DM_CONTROL and not inside:
            if removed_control.get(w[5], 0) <= 0:
                return False
            removed_control[w[5]] -= 1
    return True


def _dm_partner(body: list[str], idx: int, start: int) -> set[tuple[str, str]]:
    """The whitespace-collapsed statements of a paired block's partner line, each with the
    connector that precedes it, so a new `&&`/`||`/pipe guard in front of one never matches."""
    joined = _dm_join(body, idx, start)
    if isinstance(joined, str):
        return set()
    stmts = _dm_split(joined[0])
    return {(" ".join(raw.split()), stmts[k - 1][2] if k else "") for k, (raw, _m, _c) in enumerate(stmts or [])}


_DM_LOG_VALUE_RE = re.compile(r'"\$\{DISCORD_DM_SENT_LOG:-[^}"$`\\]*(?:\$HOME[^}"$`\\]*)?\}"')


def _dm_log_assign_ok(raw: str, word: str | None) -> bool:
    """True for `local log` or an assignment of `log` from the DISCORD_DM_SENT_LOG default, so
    `$log` stays the sent-log path and never aliases the spool or another send file."""
    m = re.search(r"(?<![\w$])log=(\S*)", raw)
    if m is None:
        return word == "local"
    return _DM_LOG_VALUE_RE.fullmatch(m.group(1)) is not None


def _dm_stmt_reason(stmts: list[_DmStmt], allowed: frozenset[str], partner: set[tuple[str, str]]) -> str | None:
    """Rules R4 to R7 over one joined command's statements."""
    for k, (raw, masked, _conn) in enumerate(stmts):
        _reserved, name, assigns, word, targets, _core = _dm_words(raw, masked)
        if name is not None:
            if name in _DM_EXISTING_FUNCS:
                return _R_SEND
        for name in assigns:
            if name in _DM_LOG_TARGETS and not _dm_log_assign_ok(raw, word):
                return _R_SEND
            if name in _DM_SEND_VARS or name.startswith("DISCORD_DM_"):
                if (" ".join(raw.split()), stmts[k - 1][2] if k else "") not in partner:
                    return _R_SEND
        if word is not None:
            if word not in allowed:
                return _R_SEND
            if word == "sed" and not _dm_sed_ok(raw.rstrip()):
                return _R_SEND
        for target in targets:
            if not _dm_target_ok(target):
                return _R_DM_REDIRECT
    return None


def _dm_reason(hunks: list[tuple[int, int, list[str]]]) -> str | None:
    """A reason when a `bin/discord-dm` change can reach a recipient or change what is sent."""
    new_funcs: set[str] = set()
    for _old, _new, body in hunks:
        for ln in body:
            m = _DM_FUNC_RE.match(ln[1:]) if ln[:1] == "+" else None
            if m is not None and m.group(1) not in _DM_EXISTING_FUNCS:
                if m.group(1) in _DM_SAFE_COMMANDS | _DM_CONTROL | _DM_TESTS:
                    return _R_SEND  # a function named like a builtin changes what that builtin does
                new_funcs.add(m.group(1))
    allowed = _DM_SAFE_COMMANDS | _DM_CONTROL | new_funcs
    for old_start, new_start, body in hunks:
        live = set(_live_changes(body))
        i = 0
        while i < len(body):
            if body[i][:1] == " ":
                i += 1
                continue
            minus: list[int] = []
            while i < len(body) and body[i][:1] == "-":
                minus.append(i)
                i += 1
            plus: list[int] = []
            while i < len(body) and body[i][:1] == "+":
                plus.append(i)
                i += 1
            if not live.intersection(minus + plus):
                continue
            groups: dict[str, list[list[_DmStmt]]] = {"-": [], "+": []}
            for tag, run, other, start, other_start in (
                ("-", minus, plus, old_start, new_start),
                ("+", plus, minus, new_start, old_start),
            ):
                covered: set[int] = set()
                for pos, idx in enumerate(run):
                    line = body[idx][1:].strip()
                    if idx in covered or not line or _SHELL_COMMENT_RE.match(line):
                        continue
                    if line.startswith("#!"):
                        return _R_SEND  # a new interpreter re-reads every line of the script
                    joined = _dm_join(body, idx, start)
                    if isinstance(joined, str):
                        return _R_CONTINUATION
                    text, members = joined
                    covered.update(members)
                    if _DM_DENY_RE.search(text):
                        return _R_DM_TOKEN
                    if _DM_ARITH_SET_RE.search(text):
                        return _R_SEND
                    stmts = _dm_split(text)
                    if stmts is None:
                        return _R_DM_UNREADABLE
                    paired = len(run) == len(other)
                    partner = _dm_partner(body, other[pos], other_start) if paired else set()
                    reason = _dm_stmt_reason(stmts, allowed, partner)
                    if reason is not None:
                        return reason
                    groups[tag].append(stmts)
            for run in (groups["-"], groups["+"]):
                if not _dm_balanced(run):
                    return _R_DM_FLOW
            minus_stmts = [s for g in groups["-"] for s in g]
            plus_stmts = [s for g in groups["+"] for s in g]
            if not _dm_conserved(minus_stmts, plus_stmts):
                return _R_DM_FLOW
    return None


def external_send_change(path: str, section: str | None) -> str | None:
    """A reason when `path`'s changed lines can change what is synced or sent, else None."""
    if path.endswith(".md"):
        return None
    if section is None:
        return _R_MISSING
    hunks = _hunks(section)
    if isinstance(hunks, str):
        return hunks
    if path == DM_PATH:
        return _dm_reason(hunks)
    comment_re = _comment_re(path)
    keep_indent = comment_re is _YAML_COMMENT_RE
    for old_start, new_start, body in hunks:
        for idx in _live_changes(body, keep_indent):
            text = body[idx][1:].strip()
            if not text or (comment_re is not None and comment_re.match(text)):
                continue
            if path != SYNC_PATH:
                return _R_SEND
            reason = _sync_reason(body, idx, old_start if body[idx][:1] == "-" else new_start)
            if reason is not None:
                return reason
    return None
