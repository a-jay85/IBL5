"""The static read-only scan behind `sql-equivalent` new-query evidence.

Each test builds one small Repository file and checks the exact note. A fixture that a
hold test uses reaches the hold through one construct only, so the mutation for that
construct is the only one that turns it green."""
import difflib

from harness.sql_readonly_scan import SourcePair, scan_sources

REPO = "ibl5/classes/Foo/FooRepository.php"


def _php(body: str) -> str:
    return ("<?php\n\ndeclare(strict_types=1);\n\nnamespace Foo;\n\n"
            "final class FooRepository extends \\Database\\BaseMysqliRepository\n{\n"
            + body + "}\n")


def _added(base, head) -> frozenset[int]:
    head_lines = head.splitlines()
    if base is None:
        return frozenset(range(1, len(head_lines) + 1))
    out: set[int] = set()
    matcher = difflib.SequenceMatcher(None, base.splitlines(), head_lines, autojunk=False)
    for tag, _i1, _i2, j1, j2 in matcher.get_opcodes():
        if tag in ("replace", "insert"):
            out.update(range(j1 + 1, j2 + 1))
    return frozenset(out)


def _scan(head, base=None, path=REPO, extra_changed=(), kind="new-query"):
    pair = SourcePair(base, head, _added(base, head))
    return scan_sources(kind, [path, *extra_changed], {path: pair})


def _line(text: str, needle: str) -> int:
    return next(n for n, line in enumerate(text.splitlines(), 1) if needle in line)


def test_bound_select_passes():
    head = _php("""    private const TABLE = 'ibl_foo';

    public function byId(int $id): array
    {
        return $this->fetchAll('SELECT * FROM ' . self::TABLE . ' WHERE id = ?', 'i', $id);
    }
""")
    assert _scan(head) == "ok"


def test_concat_interpolation_holds():
    head = _php("""    public function byTeam(int $teamId): array
    {
        $sql = 'SELECT * FROM t WHERE tid = ' . $teamId;
        return $this->fetchAll($sql);
    }
""")
    assert _scan(head) == f"scan: unbound interpolation $teamId at {REPO}:{_line(head, 'tid = ')}"


def test_bitwise_not_interpolation_holds():
    head = _php("""    public function byName(string $name): array
    {
        $sql = "SELECT * FROM t WHERE x = '" . ~$name . "'";
        return $this->fetchAll($sql);
    }
""")
    assert _scan(head) == f"scan: unbound interpolation $name at {REPO}:{_line(head, 'WHERE x')}"


def test_embedded_var_interpolation_holds():
    head = _php("""    public function byTeam(int $teamId): array
    {
        $sql = "SELECT * FROM t WHERE tid = {$teamId}";
        return $this->fetchAll($sql);
    }
""")
    assert _scan(head) == f"scan: unbound interpolation $teamId at {REPO}:{_line(head, 'tid = ')}"


def test_sprintf_value_interpolation_holds():
    head = _php("""    public function byTeam(int $teamId): array
    {
        $sql = sprintf('SELECT * FROM t WHERE tid = %d', $teamId);
        return $this->fetchAll($sql);
    }
""")
    assert _scan(head) == f"scan: unbound interpolation $teamId at {REPO}:{_line(head, 'sprintf')}"


def test_implode_user_values_hold():
    head = _php("""    public function byIds(array $ids): array
    {
        $sql = sprintf('SELECT * FROM t WHERE id IN (%s)', implode(',', $ids));
        return $this->fetchAll($sql);
    }
""")
    assert _scan(head).startswith(f"scan: unbound interpolation implode(...) at {REPO}:")


def test_implode_literal_placeholders_pass():
    head = _php("""    public function byPairs(array $pairs): array
    {
        $tuples = [];
        $params = [];
        foreach ($pairs as $pair) {
            $tuples[] = '(a = ? AND b = ?)';
            array_push($params, $pair[0], $pair[1]);
        }
        return $this->fetchAll('SELECT * FROM t WHERE ' . implode(' OR ', $tuples), str_repeat('ii', count($pairs)), ...$params);
    }
""")
    assert _scan(head) == "ok"


def test_write_keyword_holds():
    head = _php("""    public function touch(int $x): void
    {
        $this->execute('UPDATE t SET x = ?', 'i', $x);
    }
""")
    assert _scan(head) == f"scan: write keyword UPDATE at {REPO}:{_line(head, 'UPDATE')}"
    # the write string sits on an unchanged line; only the statement's bind line changed
    base = _php("""    public function touch(int $x): void
    {
        $this->execute('UPDATE t SET x = ?',
            'i', $x);
    }
""")
    edited = base.replace("'i', $x);", "'i', $x + 1);")
    assert _scan(edited, base) == f"scan: write keyword UPDATE at {REPO}:{_line(edited, 'UPDATE')}"


def test_actor_identity_holds():
    session = _php("""    public function mine(): array
    {
        $tid = (int) $_SESSION['tid'];
        return $this->fetchAll('SELECT * FROM t WHERE tid = ?', 'i', $tid);
    }
""")
    assert _scan(session).startswith("scan: actor identity ")
    logged_in = _php("""    public function mine(int $loggedInTeamID): array
    {
        return $this->fetchAll('SELECT * FROM t WHERE tid = ?', 'i', $loggedInTeamID);
    }
""")
    assert _scan(logged_in).startswith("scan: actor identity ")
    in_string = _php("""    public function mine(): array
    {
        $params = ['https://iblhoops.net/' . $_COOKIE['team']];
        return $this->fetchAll('SELECT * FROM t WHERE tid = ?', 's', $params[0]);
    }
""")
    assert _scan(in_string).startswith("scan: actor identity ")


def test_path_outside_repository_holds():
    head = _php("""    public function byId(int $id): array
    {
        return $this->fetchAll('SELECT * FROM t WHERE id = ?', 'i', $id);
    }
""")
    controller = "ibl5/classes/Api/Controller/FooController.php"
    assert _scan(head, extra_changed=[controller]) == (
        f"new-query: path outside Repository/tests: {controller}")


_TABLE_BASE = _php("""    private string $table = 'ibl_foo';

    public function byId(int $id): array
    {
        return $this->fetchAll("SELECT * FROM {$this->table} WHERE id = ?", 'i', $id);
    }
""")


def test_grandfathered_name_passes():
    head = _TABLE_BASE.replace("""    public function byId""", """    public function byTeam(int $teamId): array
    {
        return $this->fetchAll("SELECT * FROM {$this->table} WHERE tid = ?", 'i', $teamId);
    }

    public function byId""")
    assert _scan(head, base=_TABLE_BASE) == "ok"


def test_added_property_write_feeding_grandfathered_sql_holds():
    head = _TABLE_BASE.replace("""    public function byId""", """    public function retarget(string $publicArg): void
    {
        $this->table = $publicArg;
    }

    public function byId""")
    assert _scan(head, base=_TABLE_BASE) == (
        f"scan: unbound interpolation $publicArg at {REPO}:{_line(head, 'table = $publicArg')}")


def test_added_property_write_of_literal_passes():
    head = _TABLE_BASE.replace("""    public function byId""", """    public function retarget(): void
    {
        $this->table = 'ibl_bar';
    }

    public function byId""")
    assert _scan(head, base=_TABLE_BASE) == "ok"


def test_new_property_interpolation_holds():
    base = _php("""    private string $table = 'ibl_foo';
""")
    head = _TABLE_BASE
    assert _scan(head, base=base) == (
        f"scan: unbound interpolation $this->table at {REPO}:{_line(head, 'WHERE id')}")


_HINT_METHOD = """    private function hinted(int $id, string $indexHint = ''): array
    {
        return $this->fetchAll('SELECT * FROM t' . $indexHint . ' WHERE id = ?', 'i', $id);
    }
"""


def test_private_param_literal_call_sites_pass():
    head = _php(_HINT_METHOD + """
    public function fast(int $id): array
    {
        return $this->hinted($id, ' FORCE INDEX (`idx_id`)');
    }

    public function plain(int $id): array
    {
        return $this->hinted($id);
    }
""")
    assert _scan(head) == "ok"


def test_private_param_unsafe_call_site_holds():
    head = _php(_HINT_METHOD + """
    public function custom(int $id, string $hint): array
    {
        return $this->hinted($id, $hint);
    }
""")
    assert _scan(head) == f"scan: unbound interpolation $indexHint at {REPO}:{_line(head, 'indexHint .')}"


def test_new_assignment_feeding_unchanged_sql_holds():
    base = _php("""    public function filtered(string $userValue): array
    {
        $filter = 'a = 1';
        return $this->fetchAll('SELECT * FROM t WHERE ' . $filter);
    }
""")
    head = base.replace("""        $filter = 'a = 1';
""", """        $filter = 'a = 1';
        $filter = $userValue;
""")
    assert _scan(head, base=base) == (
        f"scan: unbound interpolation $userValue at {REPO}:{_line(head, '$filter = $userValue')}")


def test_prose_from_message_holds_exactly():
    head = _php("""    public function cached(string $key): array
    {
        throw new \\RuntimeException('Missing rows from cache: ' . $key);
    }
""")
    assert _scan(head) == f"scan: unbound interpolation $key at {REPO}:{_line(head, 'Missing rows')}"


def test_php_comment_variable_ignored():
    head = _php("""    public function byTeam(int $teamId): array
    {
        // uses $teamId
        /* and $teamId again */
        return $this->fetchAll('SELECT * FROM t WHERE tid = ?', 'i', $teamId);
    }
""")
    assert _scan(head) == "ok"


def test_unterminated_heredoc_is_error():
    head = _php("""    public function broken(): array
    {
        $sql = <<<SQL
            SELECT * FROM t
""")
    assert _scan(head).startswith("error:")


def test_replace_function_passes():
    head = _php("""    public function names(int $id): array
    {
        return $this->fetchAll("SELECT REPLACE(name, 'a', 'b') FROM t WHERE id = ?", 'i', $id);
    }
""")
    assert _scan(head) == "ok"


def test_braceless_control_body_is_error():
    """A braceless body shares its header's statement, so the scan refuses it."""
    for body in ("if ($c) return $this->fetchAll('SELECT * FROM t ORDER BY ' . $col);",
                 "while ($c) $this->fetchAll('SELECT * FROM t ORDER BY ' . $col);",
                 "if ($c) { return []; } else return $this->fetchAll('SELECT * FROM t ORDER BY ' . $col);",
                 "$sql = 'SELECT * FROM t ORDER BY id'; if ($c) $sql = $col; return $this->fetchAll('SELECT * FROM (' . $sql . ') x');"):
        head = _php(f"""    public function f(string $col, bool $c): array
    {{
        {body}
        return [];
    }}
""")
        assert _scan(head) != "ok", body


def test_by_reference_write_holds():
    head = _php("""    public function byInput(string $input): array
    {
        $m = [];
        preg_match('/(\\d+)/', $input, $m);
        return $this->fetchAll('SELECT * FROM t WHERE x = ' . $m[1]);
    }
""")
    note = _scan(head)
    assert note.startswith("scan: unbound interpolation")


def test_same_file_by_reference_param_holds():
    """A same-file method that fills its `&$out` param writes the caller's local."""
    head = _php("""    private function fill(array &$out, string $in): void
    {
        $out = [$in];
    }

    public function byInput(string $input): array
    {
        $m = [];
        $this->fill($m, $input);
        return $this->fetchAll('SELECT * FROM t WHERE x = ' . $m[0]);
    }
""")
    assert _scan(head) == f"scan: unbound interpolation $m['0'] at {REPO}:{_line(head, 'WHERE x = ')}"


def test_same_file_by_value_param_passes():
    """The same call into a by-value param leaves the caller's local alone."""
    head = _php("""    private function fill(array $out, string $in): void
    {
        $out = [$in];
    }

    public function byInput(string $input): array
    {
        $m = ['id'];
        $this->fill($m, $input);
        return $this->fetchAll('SELECT ' . $m[0] . ' FROM t');
    }
""")
    assert _scan(head) == "ok"


def test_foreach_by_reference_holds():
    head = _php("""    public function byInput(string $input): array
    {
        $cols = ['id'];
        foreach ($cols as &$col) {
            $col = $input;
        }
        return $this->fetchAll('SELECT ' . $cols[0] . ' FROM t');
    }
""")
    assert _scan(head).startswith("scan: unbound interpolation $cols")


def test_extract_holds():
    head = _php("""    public function byRow(array $row): array
    {
        $col = 'id';
        extract($row);
        return $this->fetchAll('SELECT ' . $col . ' FROM t');
    }
""")
    assert _scan(head).startswith("scan: unbound interpolation $col")


def test_two_step_sql_build_holds():
    """`.=` and a concat onto a SQL string build SQL with no keyword on that line."""
    for body in ("$sql = 'SELECT * FROM t WHERE name = ';\n        $sql .= $name;",
                 "$prefix = 'SELECT * FROM t WHERE name = ';\n        $sql = $prefix . $name;"):
        head = _php(f"""    public function byName(string $name): array
    {{
        {body}
        return $this->fetchAll($sql);
    }}
""")
        assert _scan(head) == f"scan: unbound interpolation $name at {REPO}:{_line(head, '$name;')}", body


def test_write_keyword_in_class_constant_holds():
    head = _php("""    private const PURGE = 'DELETE FROM ibl_plr';

    public function purge(): array
    {
        return $this->fetchAll(self::PURGE);
    }
""")
    assert _scan(head) == f"scan: write keyword DELETE at {REPO}:{_line(head, 'PURGE =')}"


def test_added_method_running_unchanged_write_constant_holds():
    base = _php("""    private const PURGE_SQL = 'DELETE FROM ibl_x WHERE id = ?';

    public function noop(): void
    {
        return;
    }
""")
    head = _php("""    private const PURGE_SQL = 'DELETE FROM ibl_x WHERE id = ?';

    public function noop(): void
    {
        return;
    }

    public function purge(int $id): void
    {
        $this->execute(self::PURGE_SQL, 'i', $id);
    }
""")
    assert _scan(head, base) == f"scan: write keyword DELETE at {REPO}:{_line(head, 'self::PURGE_SQL')}"


_FILTER_BASE = _php("""    public function byDate(): array
    {
        $dateFilter = 'game_date > 0';
        return $this->fetchAll('SELECT * FROM t WHERE ' . $dateFilter);
    }
""")


def test_grandfathered_name_in_new_method_param_holds():
    """A base name does not vouch for a new method's public param of the same name."""
    head = _FILTER_BASE[:-2] + """
    public function findByFilter(string $dateFilter): array
    {
        return $this->fetchAll('SELECT * FROM t WHERE ' . $dateFilter);
    }
}
"""
    line = len(head.splitlines()) - 2
    assert _scan(head, base=_FILTER_BASE) == f"scan: unbound interpolation $dateFilter at {REPO}:{line}"


def test_grandfathered_name_in_other_base_method_param_holds():
    """A name another base method interpolated does not vouch for an existing method's param."""
    base = """<?php
class R
{
    public function a(): array
    {
        $dateFilter = 'game_date > 0';
        return $this->fetchAll('SELECT * FROM t WHERE ' . $dateFilter);
    }

    public function b(string $dateFilter): array
    {
        return $this->fetchAll('SELECT * FROM t WHERE d = ?', [$dateFilter]);
    }
}
"""
    head = base.replace("'SELECT * FROM t WHERE d = ?', [$dateFilter]", "'SELECT * FROM t WHERE ' . $dateFilter")
    line = [i for i, ln in enumerate(head.splitlines(), 1) if "WHERE '" in ln][-1]
    assert _scan(head, base=base) == f"scan: unbound interpolation $dateFilter at {REPO}:{line}"


def test_rewrite_outside_classes_is_scanned():
    """A rewrite in `ibl5/api.php` gets the same interpolation scan as a Repository."""
    path = "ibl5/api.php"
    head = """<?php
function lookup(string $name): array
{
    return $db->fetchAll('SELECT * FROM t WHERE name = ' . $name);
}
"""
    assert _scan(head, path=path, kind="rewrite") == (
        f"scan: unbound interpolation $name at {path}:{_line(head, 'WHERE name')}")


def test_rewrite_top_level_script_code_is_scanned():
    """Top-level script code in a rewritten file gets the interpolation scan too."""
    path = "ibl5/script.php"
    head = """<?php
$rows = $db->query('SELECT * FROM t WHERE name = ' . $name);
"""
    assert _scan(head, path=path, kind="rewrite") == (
        f"scan: unbound interpolation $name at {path}:{_line(head, 'WHERE name')}")


def test_rewrite_top_level_bound_query_passes():
    path = "ibl5/script.php"
    head = """<?php
$rows = $db->fetchAll('SELECT * FROM t WHERE name = ?', [$name]);
"""
    assert _scan(head, path=path, kind="rewrite") == "ok"


def test_closure_by_reference_use_holds():
    """A closure that captures `&$col` may write it inside its skipped body."""
    head = _php("""    public function run(string $input): array
    {
        $col = 'id';
        $r = array_map(function () use (&$col, $input) { $col = $input; return 1; }, [1]);
        return $this->fetchAll('SELECT ' . $col . ' FROM t');
    }
""")
    note = _scan(head)
    assert note.startswith("scan: unbound interpolation")


import pytest


@pytest.mark.parametrize("body", [
    "return array_map(function ($c) { return $this->db->query('SELECT 1 FROM t ORDER BY ' . $c); }, $cols);",
    "return array_map(static function ($c) { return $this->db->query('SELECT 1 FROM t ORDER BY ' . $c); }, $cols);",
    "return $this->run(fn($c) => 'SELECT 1 FROM t ORDER BY ' . $c);",
    "$o = new class($cols) { function q($c) { return 'SELECT 1 FROM t ORDER BY ' . $c; } }; return $o;",
])
def test_concat_inside_closure_or_anon_class_body_is_judged(body):
    head = _php(f"""    public function run(array $cols): array
    {{
        {body}
    }}
""")
    assert _scan(head).startswith("scan: unbound interpolation")


def test_closure_body_with_literal_only_concat_passes():
    head = _php("""    public function run(array $cols): array
    {
        return array_map(function ($c) { return $this->db->query('SELECT 1 ' . 'FROM t'); }, $cols);
    }
""")
    assert _scan(head) == "ok"


@pytest.mark.parametrize("sql, kw", [
    ("SET GLOBAL max_connections = 1", "SET GLOBAL"),
    ("SET @a = (SELECT 1)", "SET @"),
    ("PREPARE s FROM @q", "PREPARE"),
    ("KILL 1", "KILL"),
    ("REPLACE ibl_plr VALUES (?)", "REPLACE ibl_plr"),
    ("REPLACE/**/INTO ibl_plr VALUES (?)", "REPLACE/**/INTO"),
    ("SELECT GET_LOCK('a', 1)", "GET_LOCK"),
    ("FLUSH TABLES", "FLUSH"),
    ("SELECT 1; SELECT 2", ";"),
])
def test_unlisted_write_shapes_hold(sql, kw):
    head = _php(f"""    public function go(): array
    {{
        return $this->fetchAll('{sql}');
    }}
""")
    assert _scan(head).startswith("scan: write keyword ")


def test_multi_query_holds():
    head = _php("""    public function go(): bool
    {
        return $this->db->multi_query($sql);
    }
""")
    assert _scan(head) == f"scan: write keyword MULTI_QUERY at {REPO}:{_line(head, 'multi_query')}"


def test_list_destructure_write_is_opaque():
    head = _php("""    public function byCol(string $userArg): array
    {
        $col = 'id';
        list($col) = [$userArg];
        return $this->fetchAll('SELECT ' . $col . ' FROM t');
    }
""")
    assert _scan(head) != "ok"


def test_added_buried_property_write_feeding_grandfathered_sql_holds():
    for stmt in ("return $this->table = $publicArg;",
                 "if (($this->table = $publicArg) !== '') { return 1; }"):
        head = _TABLE_BASE.replace("""    public function byId""", f"""    public function retarget(string $publicArg): int
    {{
        {stmt}
        return 0;
    }}

    public function byId""")
        assert _scan(head, base=_TABLE_BASE) != "ok", stmt


def test_concat_in_non_assignment_statement_holds():
    head = _php("""    public function a(string $n): array
    {
        $sql = 'SELECT * FROM t WHERE 1=1';
        return $this->fetchAll($sql . $n);
    }
""")
    assert _scan(head) == f"scan: unbound interpolation $n at {REPO}:{_line(head, 'fetchAll')}"


def test_buried_indexed_write_feeding_sql_holds():
    head = _php("""    public function a(array $n): array
    {
        $o = ['k' => 'id'];
        $sql = 'SELECT * FROM t ORDER BY ' . $o['k'];
        if ($n) $o['k'] = $n[0];
        return $this->fetchAll($sql);
    }
""")
    assert _scan(head).startswith("scan: unbound interpolation $o['k']")


_HELPER_BASE = _php("""    public function f(string $u): array
    {
        return $this->fetchAll('SELECT ' . $this->col($u) . ' FROM t');
    }

    private function col(string $c): string
    {
        $x = 'id';
        return $x;
    }
""")


def test_added_write_in_helper_feeding_unchanged_sql_holds():
    head = _HELPER_BASE.replace("""        $x = 'id';
""", """        $x = 'id';
        $x = $c;
""")
    assert _scan(head, base=_HELPER_BASE) == (
        f"scan: unbound interpolation $this->col(...) at {REPO}:{_line(head, 'SELECT')}")


def test_changed_helper_return_feeding_unchanged_sql_holds():
    head = _HELPER_BASE.replace("        return $x;\n", "        return $c;\n")
    assert _scan(head, base=_HELPER_BASE) == (
        f"scan: unbound interpolation $this->col(...) at {REPO}:{_line(head, 'SELECT')}")


def test_two_hop_helper_feeding_unchanged_sql_holds():
    base = _php("""    public function f(string $u): array
    {
        $c = $this->outer($u);
        return $this->fetchAll("SELECT $c FROM t");
    }

    private function outer(string $o): string
    {
        return $this->inner($o);
    }

    private function inner(string $i): string
    {
        return 'id';
    }
""")
    head = base.replace("        return 'id';\n", "        return $i;\n")
    assert _scan(head, base=base) == (
        f"scan: unbound interpolation $this->outer(...) at {REPO}:{_line(head, 'outer($u)')}")


def test_safe_write_in_helper_feeding_unchanged_sql_passes():
    head = _HELPER_BASE.replace("""        $x = 'id';
""", """        $x = 'id';
        $x = 'name';
""")
    assert _scan(head, base=_HELPER_BASE) == "ok"


_CONST_SQL = """    private const BASE_SQL = 'SELECT * FROM t ORDER BY ';

    public function sorted(string $col): array
    {
        return $this->fetchAll(self::BASE_SQL . $col);
    }
"""


def test_public_param_concatenated_onto_sql_constant_holds():
    assert _scan(_php(_CONST_SQL)) != "ok"


def test_sql_constant_alone_passes():
    head = _php("""    private const BASE_SQL = 'SELECT * FROM t';

    public function all(): array
    {
        return $this->fetchAll(self::BASE_SQL);
    }
""")
    assert _scan(head) == "ok"
