#!/usr/bin/env python3
"""GitHub Actions `if:` expression evaluator (restricted subset).

Usage: python3 -I bin/lib/gha-if-eval.py <event.json> <<< "<expression>"

The expression arrives on stdin (without the `${{ }}` wrapper); the argument is
the path of an event-payload JSON file. Prints exactly `true` or `false` and
exits 0. Anything outside the supported subset exits 2 with
`gha-if-eval: unsupported: <token>` on stderr.

Supported grammar (lowest precedence first):
    or      := and ('||' and)*
    and     := eq ('&&' eq)*
    eq      := unary (('==' | '!=') unary)*
    unary   := '!' unary | primary
    primary := '(' or ')' | string | number | 'null' | 'true' | 'false'
             | call | context
    call    := ident '(' [or (',' or)*] ')'   # contains/format/join/toJSON/always
    context := dotted path starting at `github` or `needs`; `*` allowed as a segment

Only the stdlib modules json, re, and sys are used.

The event file is the `github.event` payload, plus two optional extra top-level
keys read by this tool only: `event_name` (becomes `github.event_name`, default
`pull_request`) and `needs` (becomes the `needs` context).
"""

import json
import re
import sys

FUNCS = {"contains", "format", "join", "toJSON", "always"}

TOKEN_RE = re.compile(
    r"""\s*(?:
        (?P<op>&&|\|\||==|!=|!|\(|\)|,)
      | (?P<str>'(?:[^']|'')*')
      | (?P<num>-?\d+(?:\.\d+)?(?![A-Za-z0-9_.\-*]))
      | (?P<name>[A-Za-z_][A-Za-z0-9_\-]*(?:\.(?:[A-Za-z0-9_\-]+|\*))*)
    )""",
    re.VERBOSE,
)


class Unsupported(Exception):
    pass


def tokenize(src):
    toks = []
    pos = 0
    src = src.strip()
    while pos < len(src):
        m = TOKEN_RE.match(src, pos)
        if not m or m.end() == pos:
            raise Unsupported(src[pos:].strip().split()[0] if src[pos:].strip() else "<end>")
        pos = m.end()
        kind = m.lastgroup
        text = m.group(kind)
        toks.append((kind, text))
    return toks


class Parser:
    """Builds a small AST of tuples; evaluation happens after the whole
    expression parses, so an unsupported token anywhere exits 2 even in a
    branch that short-circuiting would never reach."""

    def __init__(self, toks):
        self.toks = toks
        self.i = 0

    def peek(self):
        return self.toks[self.i] if self.i < len(self.toks) else (None, None)

    def take(self):
        tok = self.peek()
        if tok[0] is None:
            raise Unsupported("<end>")
        self.i += 1
        return tok

    def expect_op(self, op):
        kind, text = self.peek()
        if kind != "op" or text != op:
            raise Unsupported(text if text is not None else "<end>")
        self.i += 1

    def parse(self):
        node = self.p_or()
        kind, text = self.peek()
        if kind is not None:
            raise Unsupported(text)
        return node

    def p_or(self):
        node = self.p_and()
        while self.peek() == ("op", "||"):
            self.i += 1
            node = ("or", node, self.p_and())
        return node

    def p_and(self):
        node = self.p_eq()
        while self.peek() == ("op", "&&"):
            self.i += 1
            node = ("and", node, self.p_eq())
        return node

    def p_eq(self):
        node = self.p_unary()
        while self.peek() in (("op", "=="), ("op", "!=")):
            op = self.take()[1]
            node = ("eq" if op == "==" else "ne", node, self.p_unary())
        return node

    def p_unary(self):
        if self.peek() == ("op", "!"):
            self.i += 1
            return ("not", self.p_unary())
        return self.p_primary()

    def p_primary(self):
        kind, text = self.take()
        if kind == "op":
            if text == "(":
                node = self.p_or()
                self.expect_op(")")
                return node
            raise Unsupported(text)
        if kind == "str":
            return ("lit", text[1:-1].replace("''", "'"))
        if kind == "num":
            return ("lit", float(text) if "." in text else int(text))
        # name
        if self.peek() == ("op", "("):
            return self.p_call(text)
        if text == "null":
            return ("lit", None)
        if text == "true":
            return ("lit", True)
        if text == "false":
            return ("lit", False)
        segs = text.split(".")
        if segs[0] not in ("github", "needs"):
            raise Unsupported(text)
        if segs[0] == "github":
            if segs[1:2] == ["event"]:
                pass
            elif segs[1:] in (["event_name"], ["run_id"]):
                pass
            else:
                raise Unsupported(text)
        elif len(segs) < 2:
            raise Unsupported(text)
        return ("ctx", segs)

    def p_call(self, name):
        if name not in FUNCS:
            raise Unsupported(name + "()")
        self.expect_op("(")
        args = []
        if self.peek() != ("op", ")"):
            args.append(self.p_or())
            while self.peek() == ("op", ","):
                self.i += 1
                args.append(self.p_or())
        self.expect_op(")")
        arity = {
            "contains": (2, 2),
            "format": (1, 99),
            "join": (1, 2),
            "toJSON": (1, 1),
            "always": (0, 0),
        }[name]
        if not arity[0] <= len(args) <= arity[1]:
            raise Unsupported(name + "(" + str(len(args)) + " args)")
        return ("call", name, args)


# ---------------------------------------------------------------------------
# Value semantics (copied from the GitHub Actions expressions docs)
# ---------------------------------------------------------------------------

NAN = float("nan")
NUM_RE = re.compile(r"^[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?$")


def truthy(v):
    # Docs, "Operators > Logical operators": "false, 0, -0, '', null" are
    # falsy; "all other values are truthy" -- arrays and objects included.
    if v is None or v is False or v == "":
        return False
    if isinstance(v, bool):
        return True
    if isinstance(v, (int, float)):
        return not (v == 0 or v != v)
    return True


def to_number(v):
    # Docs, "Type casting" table: null -> 0, true -> 1, false -> 0, string ->
    # parsed number ('' -> 0), otherwise NaN; array/object -> NaN.
    if v is None:
        return 0.0
    if isinstance(v, bool):
        return 1.0 if v else 0.0
    if isinstance(v, (int, float)):
        return float(v)
    if isinstance(v, str):
        s = v.strip()
        if s == "":
            return 0.0
        return float(s) if NUM_RE.match(s) else NAN
    return NAN


def loose_eq(a, b):
    # Docs: "GitHub ignores case when comparing strings."  "If the types do
    # not match, GitHub coerces the type to a number."  "NaN is never equal."
    # Objects and arrays are equal only when they are the same instance, so
    # against null (the only non-coerced pairing) they are false.
    if isinstance(a, str) and isinstance(b, str):
        return a.casefold() == b.casefold()
    if a is None and b is None:
        return True
    if isinstance(a, (list, dict)) or isinstance(b, (list, dict)):
        return False
    x, y = to_number(a), to_number(b)
    return x == y


def stringify(v):
    if v is None:
        return ""
    if v is True:
        return "true"
    if v is False:
        return "false"
    if isinstance(v, float) and v == int(v):
        return str(int(v))
    if isinstance(v, (list, dict)):
        return json.dumps(v)
    return str(v)


def get_key(obj, key):
    # Docs: property names in contexts are case-insensitive.
    if not isinstance(obj, dict):
        return None
    if key in obj:
        return obj[key]
    low = key.casefold()
    for k, v in obj.items():
        if k.casefold() == low:
            return v
    return None


def resolve(root, segs):
    # Docs, "Contexts": a missing property evaluates to null (empty string);
    # the `*` segment filters an array/object into a list of the next property.
    cur = [root]
    starred = False
    for seg in segs:
        nxt = []
        if seg == "*":
            starred = True
            for c in cur:
                if isinstance(c, list):
                    nxt.extend(c)
                elif isinstance(c, dict):
                    nxt.extend(c.values())
            cur = nxt
            continue
        for c in cur:
            if isinstance(c, list):
                continue
            v = get_key(c, seg)
            if v is not None or not starred:
                nxt.append(v)
        cur = nxt
        if not starred:
            cur = cur[:1] if cur else [None]
    if starred:
        return [c for c in cur if c is not None]
    return cur[0] if cur else None


class Evaluator:
    def __init__(self, load_event):
        self.load_event = load_event
        self._event = None

    @property
    def event(self):
        if self._event is None:
            self._event = self.load_event()
        return self._event

    def ev(self, node):
        t = node[0]
        if t == "lit":
            return node[1]
        if t == "ctx":
            return self.ctx(node[1])
        if t == "not":
            return not truthy(self.ev(node[1]))
        if t == "and":
            # Docs: `&&` / `||` return the deciding operand (ternary idiom).
            left = self.ev(node[1])
            return self.ev(node[2]) if truthy(left) else left
        if t == "or":
            left = self.ev(node[1])
            return left if truthy(left) else self.ev(node[2])
        if t == "eq":
            return loose_eq(self.ev(node[1]), self.ev(node[2]))
        if t == "ne":
            return not loose_eq(self.ev(node[1]), self.ev(node[2]))
        if t == "call":
            return self.call(node[1], [self.ev(a) for a in node[2]])
        raise Unsupported(str(t))

    def ctx(self, segs):
        if segs[0] == "needs":
            return resolve(get_key(self.event, "needs") or {}, segs[1:])
        if segs[1:] == ["event_name"]:
            name = get_key(self.event, "event_name")
            return name if name is not None else "pull_request"
        if segs[1:] == ["run_id"]:
            return "1"
        return resolve(self.event, segs[2:])

    def call(self, name, args):
        if name == "always":
            return True
        if name == "contains":
            a, b = args
            # Docs: "contains(search, item)... If search is an array, true if
            # item is an element; otherwise item is a case-insensitive
            # substring of search."
            if isinstance(a, list):
                return any(loose_eq(x, b) for x in a)
            return stringify(b).casefold() in stringify(a).casefold()
        if name == "format":
            fmt = stringify(args[0])
            rest = args[1:]

            def sub(m):
                if m.group(0) in ("{{", "}}"):
                    return m.group(0)[0]
                n = int(m.group(1))
                if n >= len(rest):
                    raise Unsupported("format({" + str(n) + "})")
                return stringify(rest[n])

            return re.sub(r"\{\{|\}\}|\{(\d+)\}", sub, fmt)
        if name == "join":
            sep = stringify(args[1]) if len(args) > 1 else ","
            seq = args[0] if isinstance(args[0], list) else [args[0]]
            return sep.join(stringify(x) for x in seq)
        if name == "toJSON":
            return json.dumps(args[0])
        raise Unsupported(name + "()")


def main(argv):
    if len(argv) != 2:
        print("gha-if-eval: usage: gha-if-eval.py <event.json> < expr", file=sys.stderr)
        return 2
    path = argv[1]
    expr = sys.stdin.read()

    def load_event():
        try:
            with open(path, encoding="utf-8") as fh:
                text = fh.read()
        except OSError as exc:
            raise Unsupported("event file: " + str(exc))
        if text.strip() == "":
            return {}
        try:
            data = json.loads(text)
        except ValueError as exc:
            raise Unsupported("event json: " + str(exc))
        return data if isinstance(data, dict) else {}

    try:
        tree = Parser(tokenize(expr)).parse()
        result = Evaluator(load_event).ev(tree)
    except Unsupported as exc:
        print("gha-if-eval: unsupported: " + str(exc), file=sys.stderr)
        return 2
    print("true" if truthy(result) else "false")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
