"""Static read-only scan for `sql-equivalent` evidence (rewrite and new-query kinds).

`sql_equivalence.evaluate_evidence` calls `scan_sources` with the changed file set and,
per changed PHP source, the base text, head text and added head line numbers. The scan
returns exactly "ok", or a note that keeps the owner hold:

    new-query: path outside Repository/tests: <path>
    scan: write keyword <KW> at <path>:<line>
    scan: actor identity <token> at <path>:<line>
    scan: unbound interpolation <expr> at <path>:<line>
    new-query: no Repository source in diff
    error: <reason>

The invariant: every operand a SQL-bearing statement concatenates, embeds, or passes to a
string builder is fragment-safe. Safe means a literal, a constant, a local built only from
safe values (F2), a private-method param that every in-file call site feeds a safe value
(F3), or a name the base file already interpolated into SQL (F1). Values travel as `?`
binds, which the scan never judges. Any lexing or parsing doubt raises ScanError, and the
verdict becomes "error: ...", which holds. Pure functions: no I/O, no subprocess.
"""
from __future__ import annotations

import re
from dataclasses import dataclass, field

ALLOWED_NEW_QUERY_PATH = re.compile(r"^ibl5/(classes/.+Repository\.php|tests/.+)$")
SQL_KEYWORD_PATTERN = re.compile(r"\b(SELECT|FROM|WHERE|JOIN|UNION|AND|OR|ORDER\s+BY|GROUP\s+BY|HAVING|LIMIT|OFFSET|WITH|INSERT|UPDATE|DELETE|INTO|VALUES|SET)\b", re.IGNORECASE)
SQL_KEYWORD_PATTERN_STRICT = re.compile(r"\b(SELECT|FROM|WHERE|JOIN|UNION|AND|ORDER BY|GROUP BY|LIMIT|WITH)\b")
WRITE_KEYWORD_PATTERN = re.compile(r"\b(INSERT|UPDATE|DELETE|REPLACE\s*(?:/\*.*?\*/\s*)*(?:INTO|[`\w]+)|TRUNCATE|ALTER|DROP|CREATE|RENAME|GRANT|REVOKE|LOAD\s+DATA|CALL|LOCK|HANDLER|INTO\s+(?:OUTFILE|DUMPFILE)|SET\s+(?:GLOBAL|SESSION|PERSIST|@)|PREPARE|EXECUTE|KILL|FLUSH|GET_LOCK|RELEASE_LOCK|DO\s+\w+\s*\()\b", re.IGNORECASE)
# a second statement after `;` (multi-statement) is never read-only evidence
MULTI_STATEMENT_PATTERN = re.compile(r";(?=\s*\S)")
ACTOR_IDENTITY_PATTERN = re.compile(r"\$_(SESSION|COOKIE|POST|GET|REQUEST|SERVER|FILES)\b|\$loggedInTeamID|\bsession_\w+\s*\(|\bloggedIn\w*|\bcurrentUser\w*|\bgetCurrentUser\b|\bAuthService\b|\bCsrfGuard\b|\bAuth::", re.IGNORECASE)
STRING_BUILDER_ARGS = {"sprintf": None, "vsprintf": None, "implode": None, "join": None, "str_replace": None, "strtr": None, "str_repeat": (0,), "trim": (0, 1), "rtrim": (0, 1), "ltrim": (0, 1)}

MAX_DEPTH = 8
TEST_PATH = re.compile(r"^ibl5/tests/")
REPOSITORY_PATH = re.compile(r"^ibl5/classes/.+Repository\.php$")


class ScanError(Exception):
    """The scanner could not lex or parse a file it must judge."""


@dataclass(frozen=True)
class SourcePair:
    base_text: str | None      # None when the file is new on the branch
    head_text: str
    added_lines: frozenset[int]  # 1-indexed head line numbers


# ---------------------------------------------------------------- lexer

@dataclass
class Token:
    kind: str          # STRING DQSTRING VAR IDENT NUM OP CAST
    text: str
    line: int
    end_line: int
    parts: tuple = ()  # DQSTRING literal parts
    refs: tuple = ()   # DQSTRING embedded refs: (source text, line)


_IDENT = re.compile(r"[A-Za-z_\\][A-Za-z0-9_\\]*")
_VARNAME = re.compile(r"\$[A-Za-z_][A-Za-z0-9_]*")
_NUM = re.compile(r"0[xX][0-9a-fA-F_]+|0[bB][01_]+|\d[\d_]*(?:\.\d+)?(?:[eE][+-]?\d+)?|\.\d+")
_CAST = re.compile(r"\(\s*(int|integer|float|double|string|bool|boolean|array|object)\s*\)", re.IGNORECASE)
_OPS = sorted(["===", "!==", "<=>", "**=", "...", "??=", "<<=", ">>=", "?->",
               "==", "!=", "<>", "<=", ">=", "&&", "||", "??", "::", "->", "=>", "++", "--",
               ".=", "+=", "-=", "*=", "/=", "%=", "|=", "&=", "^=", "<<", ">>", "**",
               ".", "=", "?", ":", ",", "(", ")", "[", "]", ";", "{", "}", "+", "-", "*",
               "/", "%", "<", ">", "!", "&", "|", "^", "~", "@"], key=len, reverse=True)
_HEREDOC = re.compile(r"<<<[ \t]*(['\"]?)([A-Za-z_][A-Za-z0-9_]*)\1\r?\n")


def _parse_dq(body: str, line: int) -> tuple[tuple, tuple]:
    """Split a double-quoted or heredoc body into literal parts and embedded refs."""
    parts, refs, buf = [], [], []
    i, cur = 0, line
    n = len(body)
    while i < n:
        ch = body[i]
        if ch == "\\" and i + 1 < n:
            buf.append(body[i:i + 2])
            cur += body[i + 1] == "\n"
            i += 2
            continue
        if ch == "{" and i + 1 < n and body[i + 1] == "$":
            depth, j = 1, i + 1
            while j < n and depth:
                if body[j] == "{":
                    depth += 1
                elif body[j] == "}":
                    depth -= 1
                j += 1
            if depth:
                raise ScanError(f"unterminated {{$ in string at line {cur}")
            parts.append("".join(buf))
            buf = []
            refs.append((body[i + 1:j - 1], cur))
            i = j
            continue
        if ch == "$" and i + 1 < n and body[i + 1] == "{":
            raise ScanError(f"${{...}} interpolation at line {cur}")
        if ch == "$":
            m = _VARNAME.match(body, i)
            if m:
                j = m.end()
                if j < n and body[j] == "[":
                    k = body.find("]", j)
                    if k < 0:
                        raise ScanError(f"unterminated [ in string at line {cur}")
                    j = k + 1
                elif body.startswith("->", j) and _IDENT.match(body, j + 2):
                    j = _IDENT.match(body, j + 2).end()
                parts.append("".join(buf))
                buf = []
                refs.append((body[i:j], cur))
                i = j
                continue
        buf.append(ch)
        cur += ch == "\n"
        i += 1
    parts.append("".join(buf))
    return tuple(parts), tuple(refs)


def lex(src: str) -> list[Token]:
    """PHP source to tokens. Comments are dropped; unterminated constructs raise ScanError."""
    toks: list[Token] = []
    i, line, n = 0, 1, len(src)
    while i < n:
        ch = src[i]
        if ch == "\n":
            line += 1
            i += 1
            continue
        if ch in " \t\r\f\v":
            i += 1
            continue
        if src.startswith("<?php", i):
            i += 5
            continue
        if src.startswith("?>", i):
            i += 2
            continue
        if src.startswith("#[", i):
            depth, j = 0, i + 1
            while j < n:
                if src[j] == "[":
                    depth += 1
                elif src[j] == "]":
                    depth -= 1
                    if depth == 0:
                        break
                j += 1
            if j >= n:
                raise ScanError(f"unterminated attribute at line {line}")
            line += src.count("\n", i, j)
            i = j + 1
            continue
        if src.startswith("//", i) or ch == "#":
            j = src.find("\n", i)
            i = n if j < 0 else j
            continue
        if src.startswith("/*", i):
            j = src.find("*/", i + 2)
            if j < 0:
                raise ScanError(f"unterminated comment at line {line}")
            line += src.count("\n", i, j)
            i = j + 2
            continue
        if ch == "'":
            j, buf = i + 1, []
            while j < n and src[j] != "'":
                if src[j] == "\\" and j + 1 < n and src[j + 1] in "\\'":
                    buf.append(src[j + 1])
                    j += 2
                    continue
                buf.append(src[j])
                j += 1
            if j >= n:
                raise ScanError(f"unterminated string at line {line}")
            text = "".join(buf)
            toks.append(Token("STRING", text, line, line + src.count("\n", i, j)))
            line = toks[-1].end_line
            i = j + 1
            continue
        if ch == '"':
            j = i + 1
            while j < n and src[j] != '"':
                j += 2 if src[j] == "\\" else 1
            if j >= n:
                raise ScanError(f"unterminated string at line {line}")
            body = src[i + 1:j]
            parts, refs = _parse_dq(body, line)
            end = line + body.count("\n")
            toks.append(Token("DQSTRING", body, line, end, parts, refs))
            line = end
            i = j + 1
            continue
        if src.startswith("<<<", i):
            m = _HEREDOC.match(src, i)
            if not m:
                raise ScanError(f"bad heredoc at line {line}")
            ident = m.group(2)
            start = m.end()
            close = re.compile(rf"^[ \t]*{ident}\b", re.MULTILINE)
            cm = close.search(src, start)
            if not cm:
                raise ScanError(f"unterminated heredoc {ident} at line {line}")
            body = src[start:cm.start()]
            end = line + src.count("\n", i, cm.start())
            if m.group(1) == "'":
                toks.append(Token("STRING", body, line, end))
            else:
                parts, refs = _parse_dq(body, line + 1)
                toks.append(Token("DQSTRING", body, line, end, parts, refs))
            line = end
            i = cm.end()
            continue
        if ch == "$":
            m = _VARNAME.match(src, i)
            if not m:
                raise ScanError(f"bad variable at line {line}")
            toks.append(Token("VAR", m.group(0), line, line))
            i = m.end()
            continue
        if ch == "(":
            m = _CAST.match(src, i)
            if m:
                toks.append(Token("CAST", m.group(1).lower(), line, line))
                i = m.end()
                continue
        m = _NUM.match(src, i) if (ch.isdigit() or (ch == "." and i + 1 < n and src[i + 1].isdigit())) else None
        if m:
            toks.append(Token("NUM", m.group(0), line, line))
            i = m.end()
            continue
        m = _IDENT.match(src, i)
        if m:
            toks.append(Token("IDENT", m.group(0), line, line))
            i = m.end()
            continue
        for op in _OPS:
            if src.startswith(op, i):
                toks.append(Token("OP", op, line, line))
                i += len(op)
                break
        else:
            raise ScanError(f"unexpected character {ch!r} at line {line}")
    return toks


# ---------------------------------------------------------------- expression parser

class _ParseFail(Exception):
    pass


@dataclass
class Node:
    kind: str                 # lit dq var prop sprop index const call mcall array bin cmp tern
    #                           coalesce unary cast assign new opaque
    text: str = ""
    kids: list = field(default_factory=list)
    name: str = ""            # call/method name, operator, or array keys
    keys: list = field(default_factory=list)   # array item keys (str or None)
    line: int = 0


_BIN_PREC = [
    ({"or"}, 1), ({"xor"}, 2), ({"and"}, 3),
    ({"??"}, 6), ({"||"}, 7), ({"&&"}, 8), ({"|"}, 9), ({"^"}, 10), ({"&"}, 11),
    ({"==", "!=", "===", "!==", "<>", "<=>"}, 12), ({"<", ">", "<=", ">=", "instanceof"}, 13),
    ({"<<", ">>"}, 14), ({"."}, 15), ({"+", "-"}, 16), ({"*", "/", "%"}, 17), ({"**"}, 19),
]
_ASSIGN_OPS = {"=", ".=", "+=", "-=", "*=", "/=", "%=", "|=", "&=", "^=", "??=", "**=", "<<=", ">>="}
_LITERAL_IDENTS = {"null", "true", "false"}
_CMP_OPS = {"==", "!=", "===", "!==", "<>", "<=>", "<", ">", "<=", ">=", "&&", "||", "and", "or",
            "xor", "instanceof"}


def _prec(tok: Token) -> int | None:
    if tok.kind == "OP" or (tok.kind == "IDENT" and tok.text.lower() in {"or", "xor", "and", "instanceof"}):
        t = tok.text.lower() if tok.kind == "IDENT" else tok.text
        for ops, p in _BIN_PREC:
            if t in ops:
                return p
    return None


class _Parser:
    def __init__(self, toks: list[Token]):
        self.t = toks
        self.i = 0

    def peek(self, off=0):
        j = self.i + off
        return self.t[j] if j < len(self.t) else None

    def at(self, text, off=0):
        tok = self.peek(off)
        return tok is not None and tok.kind in ("OP", "IDENT") and tok.text == text

    def take(self):
        tok = self.peek()
        if tok is None:
            raise _ParseFail("unexpected end")
        self.i += 1
        return tok

    def expect(self, text):
        tok = self.take()
        if tok.text != text or tok.kind not in ("OP", "IDENT"):
            raise _ParseFail(f"expected {text}, got {tok.text}")
        return tok

    def done(self):
        return self.i >= len(self.t)

    # expr := assignment | ternary
    def expr(self, min_prec=0) -> Node:
        left = self.unary()
        while True:
            tok = self.peek()
            if tok is None:
                return left
            if tok.kind == "OP" and tok.text in _ASSIGN_OPS and min_prec <= 4:
                self.take()
                right = self.expr(4)
                left = Node("assign", name=tok.text, kids=[left, right], line=tok.line)
                continue
            if tok.kind == "OP" and tok.text == "?" and min_prec <= 5:
                self.take()
                if self.at(":"):
                    self.take()
                    other = self.expr(6)
                    left = Node("tern", kids=[left, left, other], line=tok.line)
                    continue
                yes = self.expr(0)
                self.expect(":")
                no = self.expr(6)
                left = Node("tern", kids=[left, yes, no], line=tok.line)
                continue
            p = _prec(tok)
            if p is None or p < min_prec or p <= 5 and min_prec > p:
                return left
            if p < min_prec:
                return left
            self.take()
            op = tok.text.lower() if tok.kind == "IDENT" else tok.text
            if op == "instanceof":
                self.unary()
                left = Node("cmp", name=op, kids=[left], line=tok.line)
                continue
            right = self.expr(p if op in ("??", "**") else p + 1)
            if op == "??":
                left = Node("coalesce", kids=[left, right], line=tok.line)
            elif op in _CMP_OPS:
                left = Node("cmp", name=op, kids=[left, right], line=tok.line)
            else:
                left = Node("bin", name=op, kids=[left, right], line=tok.line)

    def unary(self) -> Node:
        tok = self.peek()
        if tok is None:
            raise _ParseFail("unexpected end")
        if tok.kind == "CAST":
            self.take()
            return Node("cast", name=tok.text, kids=[self.unary()], line=tok.line)
        if tok.kind == "OP" and tok.text == "!":
            self.take()
            return Node("cmp", name=tok.text, kids=[self.unary()], line=tok.line)
        if tok.kind == "OP" and tok.text == "~":
            # bitwise not on a string returns inverted caller bytes, so it keeps its operand's safety
            self.take()
            return Node("unary", name=tok.text, kids=[self.unary()], line=tok.line)
        if tok.kind == "OP" and tok.text in ("-", "+"):
            self.take()
            return Node("unary", name=tok.text, kids=[self.unary()], line=tok.line)
        if tok.kind == "OP" and tok.text in ("@", "&"):
            self.take()
            return self.unary()
        if tok.kind == "OP" and tok.text in ("++", "--"):
            self.take()
            return Node("opaque", text="++", kids=[self.unary()], line=tok.line)
        if tok.kind == "IDENT" and tok.text.lower() in ("new", "clone"):
            self.take()
            return Node("new", kids=self._new_operand(), line=tok.line)
        if tok.kind == "IDENT" and tok.text.lower() in ("fn", "function", "static") and self._is_closure():
            return Node("opaque", text="closure", kids=self._skip_closure(), line=tok.line)
        return self.postfix(self.primary())

    def _is_closure(self):
        j = 0
        if self.at("static"):
            j = 1
        nxt = self.peek(j)
        return nxt is not None and nxt.text.lower() in ("fn", "function")

    def _skip_balanced(self, open_, close):
        """Consume a balanced group; return the tokens between the delimiters."""
        self.expect(open_)
        depth = 1
        inner: list[Token] = []
        while depth:
            tok = self.take()
            if tok.kind == "OP" and tok.text == open_:
                depth += 1
            elif tok.kind == "OP" and tok.text == close:
                depth -= 1
                if not depth:
                    break
            inner.append(tok)
        return inner

    @staticmethod
    def _body_kids(inner: list[Token]) -> list[Node]:
        """Expression nodes for a skipped `{...}` body, so a `.` concat inside it is judged.

        The body splits into segments at `;`, `{` and `}` outside parens/brackets. A segment
        that carries a `.` operator and does not parse fails closed."""
        kids: list[Node] = []
        seg: list[Token] = []
        depth = 0

        def flush():
            if not seg:
                return
            toks = list(seg)
            seg.clear()
            if toks[0].kind == "IDENT" and toks[0].text.lower() in ("return", "echo", "throw", "yield", "print"):
                toks = toks[1:]
            if not toks:
                return
            try:
                kids.append(parse_expr(toks))
            except _ParseFail:
                if any(t.kind == "OP" and t.text in (".", ".=") for t in toks):
                    raise
                if any(t.kind in ("STRING", "DQSTRING") for t in toks):
                    kids.append(Node("opaque", text="closure body", line=toks[0].line))

        for tok in inner:
            if tok.kind == "OP" and tok.text in ("(", "["):
                depth += 1
            elif tok.kind == "OP" and tok.text in (")", "]"):
                depth -= 1
            if depth <= 0 and tok.kind == "OP" and tok.text in (";", "{", "}"):
                flush()
            else:
                seg.append(tok)
        flush()
        return kids

    def _new_operand(self) -> list[Node]:
        # new Foo(...), new class(...) {...}: consume the name, parse the args so a
        # string built inside `new X('...' . $v)` is still scanned
        while self.peek() is not None and self.peek().kind in ("IDENT", "VAR") or self.at("::"):
            self.take()
        kids = self.args() if self.at("(") else []
        if self.at("{"):
            kids = kids + self._body_kids(self._skip_balanced("{", "}"))
        return kids

    def _skip_closure(self):
        if self.at("static"):
            self.take()
        kw = self.take().text.lower()
        if self.at("&"):
            self.take()
        self._skip_balanced("(", ")")
        if self.at("use"):
            self.take()
            self._skip_balanced("(", ")")
        if self.at(":"):
            self.take()
            while self.peek() is not None and not (self.at("{") or self.at("=>")):
                self.take()
        if kw == "fn":
            self.expect("=>")
            return [self.expr(4)]
        return self._body_kids(self._skip_balanced("{", "}"))

    def args(self) -> list[Node]:
        self.expect("(")
        out: list[Node] = []
        while not self.at(")"):
            if self.at("..."):
                tok = self.take()
                if self.at(")"):  # first-class callable syntax foo(...)
                    out.append(Node("fcc", line=tok.line))
                    break
                out.append(Node("spread", kids=[self.expr(0)], line=tok.line))
            elif self.peek() is not None and self.peek().kind == "IDENT" and self.at(":", 1) and not self.at("::", 1):
                self.take()
                self.take()
                out.append(Node("named", kids=[self.expr(0)]))
            else:
                out.append(self.expr(0))
            if self.at(","):
                self.take()
            elif not self.at(")"):
                raise _ParseFail("bad argument list")
        self.expect(")")
        return out

    def array(self, close) -> Node:
        line = self.take().line
        keys, vals = [], []
        while not self.at(close):
            if self.at(","):
                self.take()
                continue
            if self.at("..."):
                self.take()
                keys.append(None)
                vals.append(Node("spread", kids=[self.expr(0)]))
            else:
                v = self.expr(0)
                if self.at("=>"):
                    self.take()
                    key = v.text if v.kind == "lit" else None
                    v = self.expr(0)
                    keys.append(key if key is not None else Ellipsis)
                else:
                    keys.append(None)
                vals.append(v)
            if self.at(","):
                self.take()
            elif not self.at(close):
                raise _ParseFail("bad array literal")
        self.expect(close)
        return Node("array", kids=vals, keys=keys, line=line)

    def primary(self) -> Node:
        tok = self.peek()
        if tok.kind == "STRING":
            self.take()
            return Node("lit", text=tok.text, line=tok.line)
        if tok.kind == "NUM":
            self.take()
            return Node("lit", text=tok.text, line=tok.line)
        if tok.kind == "DQSTRING":
            self.take()
            return Node("dq", text=tok.text, kids=[_ref_node(r, ln) for r, ln in tok.refs], line=tok.line)
        if tok.kind == "VAR":
            self.take()
            return Node("var", text=tok.text, line=tok.line)
        if tok.kind == "OP" and tok.text == "(":
            self.take()
            inner = self.expr(0)
            self.expect(")")
            return inner
        if tok.kind == "OP" and tok.text == "[":
            return self.array("]")
        if tok.kind == "IDENT":
            low = tok.text.lower()
            if low == "array" and self.at("(", 1):
                self.take()
                return self.array(")")
            if low in ("match", "list", "include", "require", "include_once", "require_once",
                        "yield", "throw", "print", "eval"):
                raise _ParseFail(f"unsupported {low}")
            self.take()
            if self.at("::"):
                self.take()
                member = self.take()
                if member.kind == "VAR":
                    return Node("sprop", text=f"{tok.text}::{member.text}", line=tok.line)
                if self.at("("):
                    return Node("mcall", name=member.text, text=tok.text,
                                kids=self.args(), line=tok.line)
                return Node("const", text=f"{tok.text}::{member.text}", line=tok.line)
            if self.at("("):
                return Node("call", name=tok.text.lstrip("\\").lower(), kids=self.args(), line=tok.line)
            if low in _LITERAL_IDENTS:
                return Node("lit", text=low, line=tok.line)
            return Node("const", text=tok.text, line=tok.line)
        raise _ParseFail(f"unexpected token {tok.text}")

    def postfix(self, node: Node) -> Node:
        while True:
            if self.at("["):
                line = self.take().line
                if self.at("]"):
                    self.take()
                    node = Node("index", kids=[node], name="[]", line=line)
                    continue
                idx = self.expr(0)
                self.expect("]")
                node = Node("index", kids=[node, idx], line=line)
            elif self.at("->") or self.at("?->"):
                self.take()
                member = self.take()
                if member.kind not in ("IDENT", "VAR"):
                    raise _ParseFail("bad member access")
                if self.at("("):
                    node = Node("mcall", name=member.text, text=_root_text(node),
                                kids=self.args(), line=member.line)
                else:
                    node = Node("prop", text=f"{_root_text(node)}->{member.text}",
                                kids=[node], line=member.line)
            elif self.at("::"):
                self.take()
                member = self.take()
                if self.at("("):
                    node = Node("mcall", name=member.text, text="?", kids=self.args(), line=member.line)
                else:
                    node = Node("opaque", text=f"::{member.text}", line=member.line)
            elif self.at("(") and node.kind in ("var", "index", "prop", "call", "mcall"):
                node = Node("opaque", text="dyncall", kids=self.args(), line=node.line)
            elif self.at("++") or self.at("--"):
                self.take()
                node = Node("opaque", text="++", kids=[node], line=node.line)
            else:
                return node


def _root_text(node: Node) -> str:
    if node.kind in ("var", "prop", "sprop"):
        return node.text
    if node.kind == "index":
        return _root_text(node.kids[0])
    return "?"


def parse_expr(toks: list[Token]) -> Node:
    p = _Parser(toks)
    node = p.expr(0)
    if not p.done():
        raise _ParseFail(f"trailing tokens at {p.peek().text}")
    return node


def _ref_node(src: str, line: int) -> Node:
    try:
        toks = lex("<?php " + src)
        for tok in toks:
            tok.line = tok.end_line = line
        return parse_expr(toks)
    except (_ParseFail, ScanError) as exc:
        raise ScanError(f"cannot parse embedded {src!r} at line {line}: {exc}") from exc


# ---------------------------------------------------------------- file structure

_MODIFIERS = {"public", "private", "protected", "static", "final", "abstract"}


@dataclass
class Stmt:
    toks: list[Token]
    line: int
    end_line: int
    _node: object = None
    _parsed: bool = False
    refs_sql_const: bool = False   # names a class constant whose value holds SQL text

    def overlaps(self, lines) -> bool:
        return any(self.line <= ln <= self.end_line for ln in lines)

    def literal_text(self) -> str:
        out = []
        for tok in self.toks:
            if tok.kind == "STRING":
                out.append(tok.text)
            elif tok.kind == "DQSTRING":
                out.extend(tok.parts)
        return " ".join(out)

    def src(self) -> str:
        return " ".join(t.text if t.kind not in ("STRING", "DQSTRING") else '"…"' for t in self.toks)


@dataclass
class Param:
    name: str
    default: list[Token] | None
    by_ref: bool = False   # declared `&$name`, so a call writes the caller's variable


@dataclass
class Method:
    name: str
    private: bool
    params: list[Param]
    stmts: list[Stmt]
    line: int
    siblings: dict = field(default_factory=dict, repr=False)   # every method in the file, by name


def _match(toks, i, open_, close) -> int:
    depth = 0
    for j in range(i, len(toks)):
        tok = toks[j]
        if tok.kind == "OP" and tok.text == open_:
            depth += 1
        elif tok.kind == "OP" and tok.text == close:
            depth -= 1
            if depth == 0:
                return j
    raise ScanError(f"unbalanced {open_} at line {toks[i].line}")


def _split_stmts(toks: list[Token]) -> list[Stmt]:
    out, cur, depth = [], [], 0
    for tok in toks:
        if tok.kind == "OP" and tok.text in ("(", "["):
            depth += 1
        elif tok.kind == "OP" and tok.text in (")", "]"):
            depth -= 1
        if depth == 0 and tok.kind == "OP" and tok.text in (";", "{", "}"):
            if cur:
                out.append(Stmt(cur, cur[0].line, max(t.end_line for t in cur)))
            cur = []
            continue
        cur.append(tok)
    if cur:
        out.append(Stmt(cur, cur[0].line, max(t.end_line for t in cur)))
    return out


def _params(toks: list[Token]) -> list[Param]:
    out, cur, depth = [], [], 0
    groups = []
    for tok in toks:
        if tok.kind == "OP" and tok.text in ("(", "["):
            depth += 1
        elif tok.kind == "OP" and tok.text in (")", "]"):
            depth -= 1
        if depth == 0 and tok.kind == "OP" and tok.text == ",":
            groups.append(cur)
            cur = []
            continue
        cur.append(tok)
    if cur:
        groups.append(cur)
    for grp in groups:
        names = [k for k, t in enumerate(grp) if t.kind == "VAR"]
        if not names:
            continue
        k = names[0]
        default = None
        if k + 1 < len(grp) and grp[k + 1].text == "=":
            default = grp[k + 2:]
        by_ref = any(t.kind == "OP" and t.text == "&" for t in grp[:k])
        out.append(Param(grp[k].text, default, by_ref))
    return out


def structure(toks: list[Token]) -> list[Method]:
    methods, i = [], 0
    while i < len(toks):
        tok = toks[i]
        if (tok.kind == "IDENT" and tok.text.lower() == "function" and i + 2 < len(toks)
                and toks[i + 1].kind == "IDENT" and toks[i + 2].text == "("):
            mods, j = set(), i - 1
            while j >= 0 and toks[j].kind == "IDENT" and toks[j].text.lower() in _MODIFIERS | {"readonly"}:
                mods.add(toks[j].text.lower())
                j -= 1
            close = _match(toks, i + 2, "(", ")")
            params = _params(toks[i + 3:close])
            k = close + 1
            while k < len(toks) and not (toks[k].kind == "OP" and toks[k].text in ("{", ";")):
                k += 1
            if k >= len(toks) or toks[k].text == ";":
                i = k + 1
                continue
            end = _match(toks, k, "{", "}")
            methods.append(Method(toks[i + 1].text, "private" in mods, params,
                                  _split_stmts(toks[k + 1:end]), tok.line))
            i = end + 1
            continue
        i += 1
    by_name = {m.name: m for m in methods}
    for m in methods:
        m.siblings = by_name
    return methods


_DECLARATION_FIRST = {"class", "abstract", "final", "interface", "trait", "enum", "namespace", "use",
                      "const", "public", "private", "protected", "static", "readonly", "declare",
                      "function", "require", "require_once", "include", "include_once", "<?php"}


def top_level_method(toks: list[Token], methods: list[Method]) -> Method | None:
    """Pseudo-method of the script-style statements that sit outside every function body.

    A rewrite can touch a script file whose top-level code runs SQL. Class headers, member
    declarations and imports are left out, since they carry no query call."""
    kept: list[Token] = []
    i = 0
    while i < len(toks):
        tok = toks[i]
        if (tok.kind == "IDENT" and tok.text.lower() == "function" and i + 2 < len(toks)
                and toks[i + 1].kind == "IDENT" and toks[i + 2].text == "("):
            k = _match(toks, i + 2, "(", ")") + 1
            while k < len(toks) and not (toks[k].kind == "OP" and toks[k].text in ("{", ";")):
                k += 1
            if k < len(toks) and toks[k].text == "{":
                while kept and kept[-1].kind == "IDENT" and kept[-1].text.lower() in _MODIFIERS | {"readonly"}:
                    kept.pop()
                kept.append(Token("OP", ";", tok.line, tok.line))
                i = _match(toks, k, "{", "}") + 1
                continue
        kept.append(tok)
        i += 1
    stmts = []
    for stmt in _split_stmts(kept):
        first = stmt.toks[0]
        if first.kind == "IDENT" and first.text.lower() in _DECLARATION_FIRST:
            continue
        if first.kind == "OP":
            continue
        stmts.append(stmt)
    if not stmts:
        return None
    top = Method("<top-level>", False, [], stmts, stmts[0].line)
    top.siblings = {m.name: m for m in methods}
    return top


def _stmt_node(stmt: Stmt) -> Node | None:
    """The statement's expression tree; None for control headers that carry no value."""
    if stmt._parsed:
        return stmt._node
    stmt._parsed = True
    toks = stmt.toks
    first = toks[0].text.lower() if toks[0].kind == "IDENT" else ""
    if first == "else" and len(toks) > 2 and toks[1].kind == "IDENT" and toks[1].text.lower() == "if":
        toks, first = toks[1:], "if"   # `else if (...)` is an elseif header
    try:
        if first in ("return", "echo", "throw", "yield", "print"):
            stmt._node = parse_expr(toks[1:]) if len(toks) > 1 else None
        elif first in ("if", "elseif", "while", "switch", "foreach") and len(toks) > 1 and toks[1].text == "(":
            close = _match(toks, 1, "(", ")")
            if close + 1 < len(toks):
                # a braceless body shares the header's statement and would go unjudged
                raise _ParseFail(f"braceless {first} body")
            stmt._node = _foreach_parts(stmt)[0] if first == "foreach" else parse_expr(toks[2:close])
        elif first in ("else", "try", "finally", "do", "default", "break", "continue", "catch",
                       "for", "case", "unset", "global", "static", "const", "use"):
            stmt._node = None
            if first in ("case", "for", "static", "unset", "global"):
                raise _ParseFail(f"unsupported statement {first}")
            if first in ("else", "try", "finally", "do") and len(toks) > 1:
                raise _ParseFail(f"braceless {first} body")
        else:
            stmt._node = parse_expr(toks)
    except _ParseFail as exc:
        raise ScanError(f"cannot parse statement at line {stmt.line}: {exc}") from exc
    return stmt._node


def _foreach_parts(stmt: Stmt):
    """(source node, [bound variable names]) for a `foreach (... as ...)` header."""
    toks = stmt.toks
    if len(toks) < 2 or toks[1].text != "(":
        raise _ParseFail("bad foreach")
    close = _match(toks, 1, "(", ")")
    inner = toks[2:close]
    depth, as_at = 0, None
    for k, tok in enumerate(inner):
        if tok.kind == "OP" and tok.text in ("(", "["):
            depth += 1
        elif tok.kind == "OP" and tok.text in (")", "]"):
            depth -= 1
        elif depth == 0 and tok.kind == "IDENT" and tok.text.lower() == "as":
            as_at = k
    if as_at is None:
        raise _ParseFail("foreach without as")
    source = parse_expr(inner[:as_at])
    binds = [t.text for t in inner[as_at + 1:] if t.kind == "VAR"]
    if any(t.kind == "OP" and t.text in ("[", "(") for t in inner[as_at + 1:]):
        binds = binds + ["<destructure>"]
    return source, binds


def _walk(node):
    if node is None:
        return
    yield node
    for kid in node.kids:
        yield from _walk(kid)


# ---------------------------------------------------------------- judged expressions

def _concat_leaves(node: Node) -> list[Node]:
    if node.kind == "bin" and node.name == ".":
        return _concat_leaves(node.kids[0]) + _concat_leaves(node.kids[1])
    return [node]


def _concat_operands(stmt: Stmt) -> list[Node]:
    """Operands of every `.` chain and `.=` in the statement."""
    root = _stmt_node(stmt)
    out: list[Node] = []
    seen: set[int] = set()

    def visit(node, in_chain=False):
        if node is None:
            return
        if node.kind == "bin" and node.name == "." and not in_chain:
            for leaf in _concat_leaves(node):
                out.append(leaf)
                seen.add(id(leaf))
            for leaf in _concat_leaves(node):
                visit(leaf)
            return
        if node.kind == "assign" and node.name == ".=":
            target, value = node.kids
            leaves = _concat_leaves(value)
            out.extend(leaves)
            for leaf in leaves:
                visit(leaf)
            return
        for kid in node.kids:
            visit(kid)

    visit(root)
    return out


def _embedded_refs(stmt: Stmt) -> list[Node]:
    return [_ref_node(src, line) for tok in stmt.toks if tok.kind == "DQSTRING" for src, line in tok.refs]


def _builder_judged(node: Node) -> list[Node]:
    positions = STRING_BUILDER_ARGS[node.name]
    args = node.kids
    if positions is None:
        return list(args)
    return [args[k] for k in positions if k < len(args)]


def _builder_args(stmt: Stmt) -> list[Node]:
    out: list[Node] = []
    for node in _walk(_stmt_node(stmt)):
        if node.kind == "call" and node.name in STRING_BUILDER_ARGS:
            out.extend(_builder_judged(node))
    return out


def _sql_bearing(stmt: Stmt, strict=False) -> bool:
    pattern = SQL_KEYWORD_PATTERN_STRICT if strict else SQL_KEYWORD_PATTERN
    if not strict and stmt.refs_sql_const:
        return True
    return pattern.search(stmt.literal_text()) is not None


def _sql_const_names(toks: list[Token]) -> set[str]:
    """Names of class constants (`const BASE = 'SELECT ...';`) whose value holds SQL text."""
    names: set[str] = set()
    for i, tok in enumerate(toks):
        if tok.kind != "IDENT" or tok.text.lower() != "const":
            continue
        j = i + 1
        while j < len(toks) and not (toks[j].kind == "OP" and toks[j].text in ("=", ";")):
            j += 1
        if j >= len(toks) or toks[j].text != "=" or j < 1 or toks[j - 1].kind != "IDENT":
            continue
        name = toks[j - 1].text
        k = j + 1
        parts: list[str] = []
        while k < len(toks) and not (toks[k].kind == "OP" and toks[k].text == ";"):
            if toks[k].kind == "STRING":
                parts.append(toks[k].text)
            elif toks[k].kind == "DQSTRING":
                parts.extend(toks[k].parts)
            k += 1
        if SQL_KEYWORD_PATTERN.search(" ".join(parts)):
            names.add(name)
    return names


def _mark_sql_const_refs(stmts: list[Stmt], const_names: set[str]) -> None:
    """Flag each statement that reads `self::NAME` / `static::NAME` / `Cls::NAME` for a SQL constant."""
    if not const_names:
        return
    for stmt in stmts:
        toks = stmt.toks
        for k in range(1, len(toks)):
            if (toks[k].kind == "IDENT" and toks[k].text in const_names and toks[k - 1].text == "::"
                    and not (k + 1 < len(toks) and toks[k + 1].text == "(")):
                stmt.refs_sql_const = True
                break


def _names(node: Node) -> set[str]:
    """Variable and `$this->prop` names a judged expression interpolates."""
    out: set[str] = set()
    if node is None:
        return out
    if node.kind == "var":
        out.add(node.text)
    elif node.kind in ("prop", "sprop"):
        out.add(node.text)
    elif node.kind == "index":
        out |= _names(node.kids[0])
    elif node.kind == "call":
        if node.name in STRING_BUILDER_ARGS:
            for arg in _builder_judged(node):
                out |= _names(arg)
    elif node.kind == "mcall":
        pass
    else:
        for kid in node.kids:
            out |= _names(kid)
    return out


def _callee_param_keys(callee: Method, pname: str) -> set:
    """The keys under which `callee` interpolates its param; {None} when it uses the whole value."""
    keys = set()
    for stmt in callee.stmts:
        if _sql_bearing(stmt):
            keys |= {k for n, k in _interpolated_keys(stmt, nested=False) if n == pname}
    return keys


def _name_keys(node: Node, siblings=None) -> set[tuple[str, str | None]]:
    """Like `_names`, but `$x['k']` reports (`$x`, 'k') so J2 judges only that key.

    A plain `$x` passed to a same-file method inherits the keys that method reads off
    its param, one level deep; any other shape falls back to the whole value."""
    if node is not None and node.kind == "mcall" and siblings and node.name in siblings \
            and node.text in ("$this", "self", "static"):
        callee = siblings[node.name]
        out = set()
        for i, arg in enumerate(node.kids):
            keys = _callee_param_keys(callee, callee.params[i].name) if (
                arg.kind == "var" and i < len(callee.params)) else {None}
            if keys and None not in keys:
                out |= {(arg.text, k) for k in keys}
            else:
                out |= _name_keys(arg)
        return out
    if node is not None and node.kind == "mcall" and all(a.kind not in ("spread", "named") for a in node.kids):
        out = set()
        for arg in node.kids:
            out |= _name_keys(arg)
        return out
    if node is not None and node.kind == "index" and len(node.kids) > 1 and node.kids[1].kind == "lit":
        root = _root_text(node)
        if node.kids[0].kind == "var":
            return {(root, node.kids[1].text)}
    if node is not None and node.kind in ("bin", "tern", "coalesce", "dq", "cast", "unary"):
        out: set[tuple[str, str | None]] = set()
        for kid in node.kids[1:] if node.kind == "tern" else node.kids:
            out |= _name_keys(kid, siblings)
        return out
    if node is not None and node.kind == "call" and node.name in STRING_BUILDER_ARGS:
        out = set()
        for arg in _builder_judged(node):
            out |= _name_keys(arg, siblings)
        return out
    return {(name, None) for name in _names(node)}


def _interpolated_keys(stmt: Stmt, siblings=None, nested=True) -> set[tuple[str, str | None]]:
    out: set[tuple[str, str | None]] = set()
    for node in _concat_operands(stmt) + _embedded_refs(stmt) + _builder_args(stmt):
        out |= _name_keys(node, siblings if nested else None)
    return out


def _interpolated_names(stmt: Stmt) -> set[str]:
    out: set[str] = set()
    for node in _concat_operands(stmt) + _embedded_refs(stmt) + _builder_args(stmt):
        out |= _names(node)
    return out


def _assignments(method: Method):
    """(stmt, var name, value node, op) for each recognized write to a local in the method."""
    out = []
    for stmt in method.stmts:
        first = stmt.toks[0]
        if first.kind == "IDENT" and first.text.lower() == "foreach":
            try:
                source, binds = _foreach_parts(stmt)
            except _ParseFail as exc:
                raise ScanError(f"cannot parse foreach at line {stmt.line}: {exc}") from exc
            for name in binds:
                out.append((stmt, name, source, "foreach"))
            as_at = next((k for k, t in enumerate(stmt.toks) if t.kind == "IDENT" and t.text.lower() == "as"), None)
            if as_at is not None and any(t.kind == "OP" and t.text == "&" for t in stmt.toks[as_at + 1:]):
                # `foreach ($rows as &$row)` writes each `$row` change back into `$rows`
                out.append((stmt, _root_text(source), Node("opaque", text="foreach by reference"), "="))
            continue
        if first.kind == "IDENT" and first.text.lower() == "catch":
            for tok in stmt.toks:
                if tok.kind == "VAR":
                    out.append((stmt, tok.text, Node("opaque", text="catch"), "catch"))
            continue
        node = _stmt_node(stmt) if first.kind == "VAR" or first.text in ("[", "self", "static") else None
        for sub in _walk(node) if node is not None else ():
            if sub.kind == "assign":
                target, value = sub.kids
                base = target
                while base.kind == "index":
                    base = base.kids[0]
                if base.kind in ("var", "prop", "sprop"):
                    # a `$this->prop` / `self::$prop` write counts too: a grandfathered property
                    # is trusted only because its writes are judged where they are added
                    out.append((stmt, base.text, value, sub.name))
                elif base.kind == "array":
                    for kid in _walk(base):
                        if kid.kind == "var":
                            out.append((stmt, kid.text, Node("opaque", text="destructure"), "="))
        # by-reference writes and assignments buried in other statements make the var unknown
        if node is None:
            toks = stmt.toks
            for k, tok in enumerate(toks):
                if tok.kind != "VAR" or (k > 0 and toks[k - 1].text in ("->", "::")):
                    continue
                # step over `[..]` index groups: `if ($x) $o['k'] = $v;` writes `$o` too
                j = k + 1
                while j < len(toks) and toks[j].text == "[":
                    depth = 0
                    while j < len(toks):
                        if toks[j].text == "[":
                            depth += 1
                        elif toks[j].text == "]":
                            depth -= 1
                            if depth == 0:
                                break
                        j += 1
                    j += 1
                if j < len(toks) and toks[j].kind == "OP" and toks[j].text in _ASSIGN_OPS:
                    out.append((stmt, tok.text, Node("opaque", text="nested assignment"), "="))
            # a property write buried in `if (($this->p = $x) !== '')` / `return $this->p = $x;`
            for k, tok in enumerate(toks):
                name = None
                j = k + 1
                if tok.kind == "VAR" and not (k > 0 and toks[k - 1].text in ("->", "::")):
                    name = tok.text
                    while j + 1 < len(toks) and toks[j].text == "->" and toks[j + 1].kind == "IDENT" \
                            and not (j + 2 < len(toks) and toks[j + 2].text == "("):
                        name += f"->{toks[j + 1].text}"
                        j += 2
                    if name == tok.text:
                        continue  # bare var: handled by the loop above
                elif tok.kind == "IDENT" and j + 1 < len(toks) and toks[j].text == "::" \
                        and toks[j + 1].kind == "VAR":
                    name = f"{tok.text}::{toks[j + 1].text}"
                    j += 2
                if name is None:
                    continue
                while j < len(toks) and toks[j].text == "[":
                    depth = 0
                    while j < len(toks):
                        if toks[j].text == "[":
                            depth += 1
                        elif toks[j].text == "]":
                            depth -= 1
                            if depth == 0:
                                break
                        j += 1
                    j += 1
                if j < len(toks) and toks[j].kind == "OP" and toks[j].text in _ASSIGN_OPS:
                    out.append((stmt, name, Node("opaque", text="nested assignment"), "="))
            # `list($a, [$b]) = ...` writes every var inside the parens, like a `[...] =` destructure
            if first.kind == "IDENT" and first.text.lower() == "list" and len(toks) > 1 and toks[1].text == "(":
                depth = 0
                for k in range(1, len(toks)):
                    if toks[k].text == "(":
                        depth += 1
                    elif toks[k].text == ")":
                        depth -= 1
                        if depth == 0:
                            if k + 1 < len(toks) and toks[k + 1].kind == "OP" and toks[k + 1].text == "=":
                                for inner in toks[2:k]:
                                    if inner.kind == "VAR":
                                        out.append((stmt, inner.text, Node("opaque", text="destructure"), "="))
                            break
        # a bare local passed as a call argument may be written by reference (`preg_match($re, $s, $m)`)
        out.extend(_by_reference_writes(stmt, method.siblings))
    return out


_BY_VALUE_CALLS = frozenset({"count", "sizeof", "strlen", "intval", "floatval", "strval", "in_array", "array_keys",
                             "array_values", "array_key_exists", "array_merge", "array_map", "array_filter",
                             "array_sum", "array_unique", "array_slice", "array_flip", "min", "max", "abs",
                             "is_array", "is_string", "is_int", "is_null", "is_numeric", "ctype_digit",
                             "json_encode", "number_format", "str_contains", "str_starts_with", "strtoupper",
                             "strtolower", "ucfirst", "htmlspecialchars", "array_key_last", "array_key_first"})
_NON_CALL_WORDS = frozenset({"if", "elseif", "while", "for", "foreach", "switch", "catch", "array",
                             "list", "isset", "empty", "unset", "return", "match", "fn", "function"})


def _own_param_by_ref(callee: Method, index: int) -> bool:
    if index < len(callee.params):
        return callee.params[index].by_ref
    # extra args land in a trailing variadic param
    return bool(callee.params) and callee.params[-1].by_ref


def _by_reference_writes(stmt: Stmt, siblings=None):
    """An opaque write for each `$var` a call may fill by reference.

    The scan cannot see an outside function's signature, so any bare local handed to one is
    treated as written. String builders never write their args, so they are skipped. A
    same-file `$this->m()` / `self::m()` call writes only the args whose matching param is
    declared `&$param`. A `$this->m()` the file does not define (an inherited helper) is skipped."""
    siblings = siblings or {}
    out = []
    toks = stmt.toks
    # per open bracket: [call name or None, same-file callee or None, argument index]
    stack: list[list] = []
    for k, tok in enumerate(toks):
        if tok.kind == "OP" and tok.text in ("(", "[", "{"):
            if tok.text == "(" and k > 0 and toks[k - 1].kind == "IDENT" \
                    and toks[k - 1].text.lower() not in _NON_CALL_WORDS:
                own = k > 2 and toks[k - 2].text in ("->", "::") and toks[k - 3].text in ("$this", "self", "static")
                if own:
                    stack.append([None, siblings.get(toks[k - 1].text), 0])
                else:
                    stack.append([toks[k - 1].text, None, 0])
            else:
                stack.append([None, None, 0])
            continue
        if tok.kind == "OP" and tok.text in (")", "]", "}"):
            if stack:
                stack.pop()
            continue
        if tok.kind == "OP" and tok.text == "," and stack:
            stack[-1][2] += 1
            continue
        if (tok.kind == "VAR" and stack and stack[-1][0] is not None and stack[-1][0].lower() == "use"
                and k > 1 and toks[k - 1].text == "&" and toks[k - 2].text in ("(", ",")):
            # `function () use (&$col) {...}`: the skipped closure body may write `$col`
            out.append((stmt, tok.text, Node("opaque", text="closure by-reference use"), "="))
            continue
        if tok.kind != "VAR" or not stack or not 0 < k < len(toks) - 1 or toks[k - 1].text not in ("(", ","):
            continue
        name, callee, index = stack[-1]
        if callee is not None:
            # `$this->fill($m)` into `fill(array &$out)` writes `$m`, whatever follows it
            if _own_param_by_ref(callee, index):
                out.append((stmt, tok.text, Node("opaque", text="by-reference param"), "="))
            continue
        if name is not None and name not in STRING_BUILDER_ARGS and name.lower() not in _BY_VALUE_CALLS \
                and toks[k + 1].text in (",", ")") \
                and not (name.lower() == "array_push" and toks[k - 1].text == ","):
            out.append((stmt, tok.text, Node("opaque", text="call argument"), "="))
    return out


_SQL_STRING_VALUES = ("lit", "dq", "bin", "var", "index")


def _sql_stmts(method: Method) -> set[int]:
    """ids of the statements that build SQL: literal SQL text, or a write that extends a SQL string.

    `$sql = 'SELECT ...'` makes `$sql` a SQL string. A later `$sql .= $name` or
    `$q = $sql . $name` then builds SQL too, even though that line has no SQL literal."""
    sql = {id(s) for s in method.stmts if _sql_bearing(s)}
    sql_vars: set[str] = set()
    assigns = [a for a in _assignments(method) if a[3] in ("=", ".=")]
    changed = True
    while changed:
        changed = False
        for stmt, name, value, op in assigns:
            reads_sql = any(leaf.kind in ("var", "index") and _root_text(leaf) in sql_vars
                            for leaf in _concat_leaves(value))
            builds = id(stmt) in sql or reads_sql or (op == ".=" and name in sql_vars)
            if builds and id(stmt) not in sql:
                sql.add(id(stmt))
                changed = True
            stringy = value.kind in _SQL_STRING_VALUES or (value.kind == "call" and value.name in STRING_BUILDER_ARGS)
            if builds and stringy and name not in sql_vars:
                sql_vars.add(name)
                changed = True
    # a non-assignment statement (`return $this->fetchAll($sql . $n)`) that concatenates onto a SQL string
    for stmt in method.stmts:
        if id(stmt) in sql:
            continue
        toks = stmt.toks
        if any(t.kind == "OP" and t.text == "." for t in toks) and any(t.kind == "VAR" and t.text in sql_vars for t in toks):
            sql.add(id(stmt))
    return sql


def _feeding_assignments(method: Method, added, class_props=None, changed_calls=frozenset()) -> list[Node]:
    """J2: added writes to a variable some SQL-bearing statement in the method interpolates.

    `class_props` holds property names interpolated anywhere in the class or its base file. A
    property write in another method feeds that SQL too, so it is judged here. A write whose
    value calls a method in `changed_calls` is judged even when unchanged, because an added
    line in that helper changes what the write stores."""
    interpolated: dict[str, set] = {}
    for prop in class_props or ():
        interpolated.setdefault(prop, set()).add(None)
    sql = _sql_stmts(method)
    for stmt in method.stmts:
        if id(stmt) in sql:
            for name, key in _interpolated_keys(stmt, method.siblings):
                interpolated.setdefault(name, set()).add(key)
    out = []
    for stmt, name, value, _op in _assignments(method):
        if name in interpolated and (stmt.overlaps(added) or _calls_any(value, changed_calls)):
            for key in sorted(interpolated[name], key=lambda k: (k is not None, k or "")):
                out.append(value if key is None else Node("keyed", name=key, kids=[value], line=value.line))
    return out


def _calls_any(node: Node, names) -> bool:
    """True when the expression makes a same-file call to one of `names`."""
    return bool(names) and any(n.kind == "mcall" and n.name in names and n.text in ("$this", "self", "static")
                               for n in _walk(node))


def _calls_in(method: Method, name: str):
    """(stmt, mcall node) for each same-file call `$this->name(` / `self::name(` / `static::name(`."""
    out = []
    for stmt in method.stmts:
        if not any(t.kind == "IDENT" and t.text == name for t in stmt.toks):
            continue
        root = _stmt_node(stmt)
        if root is None and stmt.toks[0].text.lower() == "foreach":
            try:
                root = _foreach_parts(stmt)[0]
            except _ParseFail as exc:
                raise ScanError(str(exc)) from exc
        for node in _walk(root):
            if node.kind == "mcall" and node.name == name and node.text in ("$this", "self", "static"):
                out.append((stmt, node))
    return out


def _param_interpolated(callee: Method, param: str) -> bool:
    return any(_sql_bearing(s) and param in _interpolated_names(s) for s in callee.stmts)


def _feeding_call_args(method: Method, added, ctx) -> list[Node]:
    """J3: added same-file calls whose argument lands in a param the callee interpolates."""
    out = []
    for callee in ctx.methods.values():
        for stmt, call in _calls_in(method, callee.name):
            if not stmt.overlaps(added):
                continue
            for k, arg in enumerate(call.kids):
                if arg.kind in ("spread", "named"):
                    out.append(arg)
                    continue
                if k < len(callee.params) and _param_interpolated(callee, callee.params[k].name):
                    keys = _callee_param_keys(callee, callee.params[k].name)
                    if None in keys or not keys:
                        out.append(arg)
                    else:
                        out.extend(Node("keyed", name=key, kids=[arg], line=arg.line) for key in sorted(keys))
    return out


# ---------------------------------------------------------------- fragment safety

@dataclass(frozen=True)
class _Ctx:
    methods: dict
    base_names: frozenset
    depth: int = 0
    visiting: frozenset = frozenset()
    key: str | None = None    # the array key the SQL reads off a param (F3 carries it to call sites)
    base_params: dict = field(default_factory=dict)   # base file: method name -> its param names
    base_method_names: dict = field(default_factory=dict)   # base file: method name -> names it interpolated

    def at(self, depth, visiting, key=None) -> "_Ctx":
        return _Ctx(self.methods, self.base_names, depth, visiting, key, self.base_params, self.base_method_names)

    def grandfathered(self, name: str, method: Method) -> bool:
        """F1: the base already interpolated `name`, in a method the base had, from no new param."""
        if name not in self.base_names:
            return False
        if "->" in name or "::" in name:
            return True   # a property is class-wide; its writes are judged where they are added
        if method.name not in self.base_params:
            return False
        if name not in self.base_method_names.get(method.name, ()):
            return False   # another base method interpolated it; that does not vouch for this one
        is_param = any(p.name == name for p in method.params)
        return not is_param or name in self.base_params[method.name]


def _is_constant_expr(node: Node) -> bool:
    """A class or global constant, or an index into one (`self::STAT_INDEXES[$x]`)."""
    if node.kind == "const":
        return True
    return node.kind == "index" and bool(node.kids) and node.kids[0].kind == "const"


def _is_literal_tokens(toks) -> bool:
    if not toks:
        return False
    try:
        node = parse_expr(list(toks))
    except _ParseFail:
        return False
    return node.kind in ("lit", "const") or (node.kind == "array" and not node.kids)


def _is_safe(expr: Node, method: Method, ctx: _Ctx, key=None, depth=0, visiting=frozenset()) -> bool:
    """True when the expression can only produce text the file itself wrote.

    `depth` counts provenance hops (a variable's writes, a callee's returns, a call
    site), not syntax nesting, so a long concat chain never hits the cap."""
    if depth > MAX_DEPTH:
        return False
    nxt = depth + 1
    kind = expr.kind
    if kind == "lit":
        return True
    if kind == "keyed":
        return _is_safe(expr.kids[0], method, ctx, expr.name, depth, visiting)
    if _is_constant_expr(expr):
        return True
    if kind == "dq":
        return all(_is_safe(k, method, ctx, None, depth, visiting) for k in expr.kids)
    if kind in ("bin", "unary", "cast"):
        return all(_is_safe(k, method, ctx, None, depth, visiting) for k in expr.kids)
    if kind == "cmp":
        return True  # yields a bool, never caller text
    if kind == "tern":
        return all(_is_safe(k, method, ctx, key, depth, visiting) for k in expr.kids[1:])
    if kind == "coalesce":
        return all(_is_safe(k, method, ctx, key, depth, visiting) for k in expr.kids)
    if kind == "array":
        items = list(zip(expr.keys, expr.kids))
        if key is not None and all(k is not None and k is not Ellipsis for k, _ in items):
            items = [(k, v) for k, v in items if k == key]
        return all(_is_safe(v, method, ctx, None, depth, visiting) for _, v in items)
    if kind == "call":
        if expr.name not in STRING_BUILDER_ARGS:
            return False
        return all(_is_safe(a, method, ctx, None, depth, visiting) for a in _builder_judged(expr))
    if kind == "mcall":
        return _returns_safe(expr, ctx, key, nxt, visiting)
    if kind in ("var", "prop", "sprop", "index"):
        ref_name = _root_text(expr)
        if ctx.grandfathered(ref_name, method):
            return True
        if kind == "index":
            sub = expr.kids[1] if len(expr.kids) > 1 else None
            sub_key = sub.text if sub is not None and sub.kind == "lit" else None
            return _is_safe(expr.kids[0], method, ctx, sub_key, depth, visiting)
        if kind != "var" or ref_name == "$this":
            return False  # `$this->prop` is safe only by F1
        return _var_safe(ref_name, method, ctx, key, nxt, visiting)
    return False


def _returns_safe(call: Node, ctx: _Ctx, key, depth, visiting) -> bool:
    """A same-file `$this->m()` / `self::m()` call is safe when every `return` is safe."""
    callee = ctx.methods.get(call.name)
    if call.text not in ("$this", "self", "static") or callee is None:
        return False
    returns = [s for s in callee.stmts if s.toks[0].kind == "IDENT" and s.toks[0].text.lower() == "return"]
    marker = ("ret", callee.name, key)
    if not returns or marker in visiting:
        return False
    for stmt in returns:
        node = _stmt_node(stmt)
        if node is not None and not _is_safe(node, callee, ctx, key, depth, visiting | {marker}):
            return False
    return True


def _var_safe(name, method, ctx, key, depth, visiting) -> bool:
    """F2 (every local write is safe) and F3 (a private param fed only safe values)."""
    marker = ("var", method.name, name, key)
    if marker in visiting:
        return False  # a cycle counts as unsafe
    visiting = visiting | {marker}
    if any(t.kind == "IDENT" and t.text.lower() == "extract"
           for s in method.stmts for t in s.toks):
        return False  # `extract($row)` can write any local
    writes = [(v, op) for _s, n, v, op in _assignments(method) if n == name]
    for value, op in writes:
        if not _is_safe(value, method, ctx, None if op == "foreach" else key, depth, visiting):
            return False
    param_index = next((k for k, p in enumerate(method.params) if p.name == name), None)
    if param_index is None:
        return bool(writes)
    if not method.private:
        return False
    ctx = ctx.at(depth, visiting, key)
    return _call_sites_safe(method, param_index, ctx)


def _call_sites_safe(method: Method, param_index: int, ctx: _Ctx) -> bool:
    """F3: every in-file call site passes a safe arg, or omits it under a literal default."""
    param = method.params[param_index]
    sites = []
    for caller in ctx.methods.values():
        sites.extend((caller, call) for _s, call in _calls_in(caller, method.name))
    if not sites:
        return False
    for caller, call in sites:
        args = call.kids
        if any(a.kind == "fcc" for a in args):
            return False  # `$this->m(...)`: the real args arrive later, through the callable
        if any(a.kind in ("spread", "named") for a in args[:param_index + 1]):
            return False
        if param_index < len(args):
            if not _is_safe(args[param_index], caller, ctx, ctx.key, ctx.depth + 1, ctx.visiting):
                return False
        elif not _is_literal_tokens(param.default):
            return False
    return True


# ---------------------------------------------------------------- verdicts

def _expr_text(node: Node) -> str:
    if node.kind in ("var", "prop", "sprop", "const", "lit"):
        return node.text
    if node.kind == "index":
        sub = node.kids[1] if len(node.kids) > 1 else None
        inner = _expr_text(sub) if sub is not None else ""
        if sub is not None and sub.kind == "lit":
            inner = f"'{sub.text}'"
        return f"{_expr_text(node.kids[0])}[{inner}]"
    if node.kind == "call":
        return f"{node.name}(...)"
    if node.kind == "mcall":
        return f"{node.text}->{node.name}(...)" if node.text == "$this" else f"{node.text}::{node.name}(...)"
    if node.kind == "keyed":
        return _expr_text(node.kids[0])
    names = sorted(_names(node))
    return names[0] if names else node.kind


def _base_params(base_text: str | None) -> dict:
    if base_text is None:
        return {}
    try:
        return {m.name: frozenset(p.name for p in m.params) for m in structure(lex(base_text))}
    except ScanError:
        return {}


def _base_method_names(base_text: str | None) -> dict:
    if base_text is None:
        return {}
    out: dict[str, set[str]] = {}
    try:
        methods = structure(lex(base_text))
    except ScanError:
        return out
    for method in methods:
        names = out.setdefault(method.name, set())
        for stmt in method.stmts:
            try:
                if _sql_bearing(stmt, strict=True):
                    names |= _interpolated_names(stmt)
            except ScanError:
                continue
    return {k: frozenset(v) for k, v in out.items()}


def _base_names(base_text: str | None) -> set[str]:
    names: set[str] = set()
    for v in _base_method_names(base_text).values():
        names |= v
    return names


def _write_hit(text: str) -> str | None:
    m = WRITE_KEYWORD_PATTERN.search(text)
    if m:
        return re.sub(r"\s+", " ", m.group(1)).upper()
    if MULTI_STATEMENT_PATTERN.search(text):
        return ";"
    return None


def _method_touched(method: Method, added) -> bool:
    end = max([method.line, *(s.end_line for s in method.stmts)])
    return any(method.line <= ln <= end for ln in added)


def _reaches_changed(methods: list[Method], ctx: "_Ctx", added) -> tuple[set[str], set[str]]:
    """(methods with an added line, methods that reach one through same-file calls).

    An added line in a helper changes what the helper returns, so an unchanged SQL
    statement in any caller must be judged again."""
    changed = {m.name for m in ctx.methods.values() if _method_touched(m, added)}
    callees = {id(m): {c for c in ctx.methods if c != m.name and _calls_in(m, c)} for m in methods}
    out: set[str] = set()
    grew = True
    while grew:
        grew = False
        for m in methods:
            if m.name not in out and callees[id(m)] & (changed | out):
                out.add(m.name)
                grew = True
    return changed, out


def _scan_file(path: str, pair: SourcePair) -> str:
    toks = lex(pair.head_text)
    methods = structure(toks)
    const_names = _sql_const_names(toks)
    for m in methods:
        _mark_sql_const_refs(m.stmts, const_names)
    added = pair.added_lines
    for method in methods:
        for stmt in method.stmts:
            if stmt.overlaps(added):
                literal_text = stmt.literal_text()
                hit = None
                hit = _write_hit(literal_text)
                if hit:
                    return f"scan: write keyword {hit} at {path}:{stmt.line}"
    # a class constant or property sits outside every method, so check each added string too
    for tok in toks:
        if tok.kind not in ("STRING", "DQSTRING") or not any(tok.line <= ln <= tok.end_line for ln in added):
            continue
        text = tok.text if tok.kind == "STRING" else " ".join(tok.parts)
        hit = _write_hit(text)
        if hit:
            return f"scan: write keyword {hit} at {path}:{tok.line}"
    for tok in toks:
        if tok.kind == "IDENT" and tok.text.lower().endswith("multi_query") and any(tok.line <= ln <= tok.end_line for ln in added):
            return f"scan: write keyword MULTI_QUERY at {path}:{tok.line}"
    ctx = _Ctx({m.name: m for m in methods}, frozenset(_base_names(pair.base_text)),
               base_params=_base_params(pair.base_text),
               base_method_names=_base_method_names(pair.base_text))
    class_props: set[str] = {n for n in ctx.base_names if "->" in n or "::" in n}
    for m in methods:
        m_sql = _sql_stmts(m)
        for st in m.stmts:
            if id(st) in m_sql:
                class_props |= {n for n, _k in _interpolated_keys(st, m.siblings) if "->" in n or "::" in n}
    to_judge = list(methods)
    top = top_level_method(toks, methods)
    if top is not None:
        _mark_sql_const_refs(top.stmts, const_names)
    if top is not None and any(_sql_bearing(s) for s in top.stmts):
        to_judge.append(top)
    changed_methods, reaching = _reaches_changed(to_judge, ctx, added)
    changed_calls = frozenset(changed_methods | reaching)
    for method in to_judge:
        judged: list[tuple[Node, int]] = []
        sql = _sql_stmts(method)
        rejudge = method.name in reaching
        for stmt in method.stmts:
            if id(stmt) in sql and (rejudge or stmt.overlaps(added)):
                exprs: list[Node] = []
                exprs.extend(_concat_operands(stmt))
                exprs.extend(_embedded_refs(stmt))
                exprs.extend(_builder_args(stmt))
                judged.extend((e, e.line or stmt.line) for e in exprs)
        exprs = []
        exprs.extend(_feeding_assignments(method, added, class_props, changed_calls))
        exprs.extend(_feeding_call_args(method, added, ctx))
        judged.extend((e, e.line or method.line) for e in exprs)
        for expr, line in judged:
            if expr.kind in ("spread", "named") or not _is_safe(expr, method, ctx):
                return f"scan: unbound interpolation {_expr_text(expr)} at {path}:{line}"
    return "ok"


def _strip_line_comment(line: str) -> str:
    """Cut a trailing // or # comment, ignoring those marks inside a quoted string."""
    quote = ""
    i = 0
    while i < len(line):
        c = line[i]
        if quote:
            if c == "\\":
                i += 1
            elif c == quote:
                quote = ""
        elif c in ("'", '"'):
            quote = c
        elif line.startswith("//", i) or (c == "#" and not line.startswith("#[", i)):
            return line[:i]
        i += 1
    return line


def _actor_identity(path: str, pair: SourcePair) -> str | None:
    lines = pair.head_text.splitlines()
    for ln in sorted(pair.added_lines):
        if not 1 <= ln <= len(lines):
            continue
        code_text = _strip_line_comment(lines[ln - 1].strip())
        if code_text.startswith(("*", "/*")):
            continue
        m = ACTOR_IDENTITY_PATTERN.search(code_text)
        if ACTOR_IDENTITY_PATTERN.search(code_text):
            return f"scan: actor identity {m.group(0)} at {path}:{ln}"
    return None


def scan_sources(kind: str, changed_files: list[str], files: dict[str, SourcePair]) -> str:
    """Return "ok" or a note starting "new-query:" / "scan:" / "error:"."""
    try:
        if kind not in ("rewrite", "new-query"):
            return f"error: unknown kind {kind!r}"
        changed = sorted(set(changed_files or ()))
        if kind == "new-query":
            for path in changed:
                if path.startswith("ibl5/tests/sql-equivalence/"):
                    continue
                if not ALLOWED_NEW_QUERY_PATH.match(path):
                    return f"new-query: path outside Repository/tests: {path}"
        for path in changed:
            if TEST_PATH.match(path) or path not in files:
                continue
            note = _actor_identity(path, files[path])
            if note:
                return note
        repo_sources = [p for p in changed if p.endswith(".php") and not TEST_PATH.match(p)]
        for path in repo_sources:
            if path not in files:
                return f"error: no source text for {path}"
            note = _scan_file(path, files[path])
            if note != "ok":
                return note
        if kind == "new-query" and not any(REPOSITORY_PATH.match(p) for p in repo_sources):
            return ("new-query: no Repository source "
                    "in diff")
        return "ok"
    except Exception as exc:  # noqa: BLE001 - every scan failure holds
        return "error: " + str(exc)
