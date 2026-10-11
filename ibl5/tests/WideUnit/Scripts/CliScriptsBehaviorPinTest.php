<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Behavior pins for four CLI scripts whose PHPStan baseline entries are being cleared
 * with native types, casts and docblocks only. Reads SOURCE and never runs a script.
 */
final class CliScriptsBehaviorPinTest extends TestCase
{
    private const ASG_GUARD = "if (PHP_SAPI !== 'cli') {\n    echo 'This script must be run from the command line.';\n    exit(1);\n}";
    private const IMPORT_GUARD = "if (PHP_SAPI !== 'cli') {\n    http_response_code(403);\n    echo 'This script must be run from the command line.';\n    exit(1);\n}";

    /** @return array<string, array{string, array<string, int|string>}> */
    public static function pinnedScriptProvider(): array
    {
        return [
            'engParser' => ['engParser.php', ['outputs' => 3, 'exits' => 2, 'constOutputs' => 1, 'constSha' => '577096c5feb0', 'sqlCount' => 0, 'sqlSha' => 'da39a3ee5e6b', 'prepares' => 0]],
            'reconstruct' => ['reconstruct_2007_asg_boxscores.php', ['outputs' => 11, 'exits' => 2, 'constOutputs' => 6, 'constSha' => 'a1f1b663f66c', 'sqlCount' => 1, 'sqlSha' => '55934a595b79', 'prepares' => 1]],
            'patch' => ['patch_2007_asg_sco.php', ['outputs' => 26, 'exits' => 3, 'constOutputs' => 13, 'constSha' => 'b8475cfa5a34', 'sqlCount' => 1, 'sqlSha' => '55934a595b79', 'prepares' => 1]],
            'import' => ['archive/importBoxscoresFromHtml.php', ['outputs' => 28, 'exits' => 2, 'constOutputs' => 14, 'constSha' => 'ab4ce4d34638', 'sqlCount' => 3, 'sqlSha' => 'd83ea0e8936b', 'prepares' => 3]],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function guardedScriptProvider(): array
    {
        return [
            'reconstruct' => ['reconstruct_2007_asg_boxscores.php', self::ASG_GUARD],
            'patch' => ['patch_2007_asg_sco.php', self::ASG_GUARD],
            'import' => ['archive/importBoxscoresFromHtml.php', self::IMPORT_GUARD],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function flagScriptProvider(): array
    {
        return [
            'reconstruct' => ['reconstruct_2007_asg_boxscores.php', '--apply'],
            'patch' => ['patch_2007_asg_sco.php', '--apply'],
            'import' => ['archive/importBoxscoresFromHtml.php', '--dry-run'],
        ];
    }

    /** @param array<string, int|string> $expected */
    #[DataProvider('pinnedScriptProvider')]
    public function testOutputExitAndSqlStatementsArePinned(string $file, array $expected): void
    {
        self::assertSame($expected, self::pinStats(self::source($file)));
    }

    #[DataProvider('guardedScriptProvider')]
    public function testCliGuardIsFirstExecutableStatement(string $file, string $guardBlock): void
    {
        $src = self::source($file);
        $guardPos = strpos($src, $guardBlock);
        self::assertNotFalse($guardPos, 'CLI guard block changed or missing in ' . $file);
        self::assertSame(1, preg_match('/^\s*(require|include)(_once)?\b/m', $src, $m, PREG_OFFSET_CAPTURE));
        self::assertLessThan($m[0][1], $guardPos, 'CLI guard must precede the first require/include in ' . $file);
    }

    public function testEngParserKeepsDieMessagesAndOutputGuard(): void
    {
        $src = self::source('engParser.php');
        foreach ([
            'die("Unable to find League File Name setting");',
            'die("Energy file not found: $engFilePath");',
            'fopen($engFilePath, "rb")',
            '$engArray[$key] = $value;',
            '(int) $matches[2]',
            'var_dump($engArray);',
            "'REQUEST_URI'",
            "'engParser.php'",
        ] as $needle) {
            self::assertStringContainsString($needle, $src);
        }
    }

    #[DataProvider('flagScriptProvider')]
    public function testFlagCheckKeepsLiteral(string $file, string $flag): void
    {
        self::assertSame(1, preg_match('/in_array\(\'' . preg_quote($flag, '/') . '\',[^;]*true\)/', self::source($file)));
    }

    public function testPinStatsDetectsAddedOutputExitAndSql(): void
    {
        $base = "<?php\necho 'a';\n";
        $stats = self::pinStats($base);
        self::assertNotSame($stats, self::pinStats($base . "echo 'b';\n"));
        self::assertNotSame($stats, self::pinStats($base . "exit(1);\n"));
        self::assertNotSame($stats, self::pinStats($base . "die('x');\n"));
        self::assertNotSame($stats, self::pinStats($base . "\$s = 'SELECT 1';\n"));
        self::assertSame($stats, self::pinStats($base . "throw new \\RuntimeException('x');\n"));
        self::assertSame($stats, self::pinStats($base . "/** @var \\mysqli \$db */\n\$x = (int) \$y;\n"));
    }

    private static function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3) . '/scripts/' . $file);
    }

    /** @return array<string, int|string> */
    private static function pinStats(string $src): array
    {
        $t = [];
        foreach (token_get_all($src) as $tok) {
            if (is_array($tok) && in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $t[] = $tok;
        }
        $heads = ['printf', 'fprintf', 'fwrite', 'var_dump', 'print_r'];
        $outputs = 0;
        $exits = 0;
        $prepares = 0;
        $const = [];
        $sql = [];
        $n = count($t);
        for ($i = 0; $i < $n; $i++) {
            $tok = $t[$i];
            $id = is_array($tok) ? $tok[0] : null;
            $text = is_array($tok) ? $tok[1] : $tok;
            $prev = $i > 0 ? $t[$i - 1] : null;
            $prevId = is_array($prev) ? $prev[0] : null;
            $isHead = $id === T_ECHO || $id === T_PRINT || $id === T_EXIT
                || ($id === T_STRING && in_array($text, $heads, true) && ($t[$i + 1] ?? null) === '('
                    && !in_array($prevId, [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true));
            if ($isHead) {
                $outputs++;
                $exits += $id === T_EXIT ? 1 : 0;
                $parts = [$text];
                $constOnly = true;
                for ($j = $i + 1; $j < $n && $t[$j] !== ';'; $j++) {
                    $p = $t[$j];
                    $pid = is_array($p) ? $p[0] : null;
                    $ptext = is_array($p) ? $p[1] : $p;
                    if (!($pid === T_CONSTANT_ENCAPSED_STRING || $pid === T_LNUMBER || in_array($ptext, ['(', ')', ',', '.'], true))) {
                        $constOnly = false;
                    }
                    $parts[] = $ptext;
                }
                if ($constOnly) {
                    $const[] = implode(' ', $parts);
                }
            }
            if ($id === T_CONSTANT_ENCAPSED_STRING && preg_match('/^[\'"]\s*(SELECT|INSERT|UPDATE|DELETE|REPLACE)\b/i', $text) === 1) {
                $sql[] = $text;
            }
            if ($id === T_STRING && $text === 'prepare' && $prevId === T_OBJECT_OPERATOR) {
                $prepares++;
            }
        }

        return [
            'outputs' => $outputs,
            'exits' => $exits,
            'constOutputs' => count($const),
            'constSha' => substr(sha1(implode("\n", $const)), 0, 12),
            'sqlCount' => count($sql),
            'sqlSha' => substr(sha1(implode("\n", $sql)), 0, 12),
            'prepares' => $prepares,
        ];
    }
}
