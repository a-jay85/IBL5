<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Characterization pins for the CLI scripts whose PHPStan baseline entries are cleared with
 * typing-only edits. Reads SOURCE and never runs a script (they bootstrap the DB).
 * Pins every `exit` argument in source order and every `--flag` string literal.
 */
final class ScriptsBaselineArgvExitPinTest extends TestCase
{
    /** @return array<string, array{string, list<string>, list<string>}> */
    public static function pinnedScriptProvider(): array
    {
        return [
            'generate_api_key' => ['generate_api_key.php', ['1', '1', '1', '1', '1', '1'], []],
            'simRecapContext' => [
                'simRecapContext.php',
                ["'ThisscriptisCLI-only.'", '1', '0'],
                ['--sim must be >= 1', '--sim=', '--sim=N (positive integer) is required'],
            ],
            'simRecapQueue' => [
                'simRecapQueue.php',
                ["'ThisscriptisCLI-only.'", '1', '0'],
                [
                    '--cause=',
                    '--cause=flaky|usage_limit|auth|transport|prompt is required for verb park',
                    '--event=',
                    '--event=done is required for verb notify',
                    '--host=',
                    '--reset is accepted only with --cause=usage_limit',
                    '--reset must be a positive epoch',
                    '--reset=',
                    '--sim and --host are required for verb notify',
                    '--sim must be >= 1',
                    '--sim=',
                ],
            ],
            'storeSimRecap' => [
                'storeSimRecap.php',
                ['1', '1', '1', '0'],
                ['--sim must be >= 1', '--sim=', '--sim=N is required'],
            ],
            'jsbExport' => ['jsbExport.php', [''], []],
            'reconstructPlr' => [
                'reconstructPlr.php',
                ['1', '2', '2', '2', '$exitCode', '2', '$ok?0:1', '2', '$result->hasErrors()?1:0'],
                [
                    '--all',
                    '--all requires --out-dir=PATH\n',
                    '--base=',
                    '--out-dir=',
                    '--out=',
                    '--season-year=',
                    '--snapshot requires --out=PATH\n',
                    '--snapshot=',
                    '--target-end-date=',
                ],
            ],
            'bulkJsbImport' => [
                'bulkJsbImport.php',
                ['1', '1', '1', '1', '$summary->hasErrors()?1:0'],
                ['--dry-run', '--file-type=', '--season=', '--verify'],
            ],
            'build-engine-bundle' => ['build-engine-bundle.php', ['1', '1', '1', '1'], []],
            'runEngineShadow' => ['runEngineShadow.php', ['1', '1', '0', '0', '0', '1'], []],
        ];
    }

    /**
     * @param list<string> $exits
     * @param list<string> $flags
     */
    #[DataProvider('pinnedScriptProvider')]
    public function testExitArgumentsArePinned(string $file, array $exits, array $flags): void
    {
        self::assertSame($exits, self::exitArguments(self::source($file)));
    }

    /**
     * @param list<string> $exits
     * @param list<string> $flags
     */
    #[DataProvider('pinnedScriptProvider')]
    public function testCliFlagLiteralsArePinned(string $file, array $exits, array $flags): void
    {
        self::assertSame($flags, self::flagLiterals(self::source($file)));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function rejectedArgumentProvider(): array
    {
        return [
            'runEngineShadow unknown option' => ['runEngineShadow.php', ['--bogus=1']],
            'runEngineShadow space form' => ['runEngineShadow.php', ['--year', '2026']],
            'build-engine-bundle typo' => ['build-engine-bundle.php', ['--yaer=2026']],
            'build-engine-bundle space form' => ['build-engine-bundle.php', ['--year', '2026']],
        ];
    }

    /** @return array<string, array{string}> */
    public static function engineScriptProvider(): array
    {
        return [
            'runEngineShadow' => ['runEngineShadow.php'],
            'build-engine-bundle' => ['build-engine-bundle.php'],
        ];
    }

    /** @param list<string> $args */
    #[DataProvider('rejectedArgumentProvider')]
    public function testEngineScriptsRejectUnknownOrSpaceFormArguments(string $file, array $args): void
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/scripts/' . $file)
            . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        $out = [];
        $code = 0;
        exec($command, $out, $code);

        self::assertSame(1, $code);
        self::assertStringContainsString('unknown or malformed argument', implode("\n", $out));
    }

    #[DataProvider('engineScriptProvider')]
    public function testUnknownOptionGuardAcceptsEveryGetoptOption(string $file): void
    {
        $src = self::source($file);
        self::assertSame(1, preg_match('/getopt\(\'\', \[([^\]]*)\]\)/', $src, $getopt));
        self::assertSame(1, preg_match('/\$acceptedOptions = \[([^\]]*)\];/', $src, $accepted));

        self::assertSame(self::optionNames($getopt[1]), self::optionNames($accepted[1]));
    }

    /** @return array<string, array{string, string}> */
    public static function adminGateProvider(): array
    {
        $allStarRenameGate = <<<'GATE'
// SECURITY: Admin-only endpoint
if (!function_exists('is_admin') || !is_admin($admin)) {
    header('Content-Type: application/json');
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}
GATE;
        $updateAllTheThingsGate = <<<'GATE'
// SECURITY: Redirect logged-out users to login
if (!function_exists('is_user') || !is_user($user ?? '')) {
    $_SESSION['redirect_after_login'] = 'name=LeagueControlPanel'
        . (isset($_GET['league']) && $_GET['league'] === League\LeagueContext::LEAGUE_OLYMPICS ? '&league=olympics' : '');
    header('Location: ../modules.php?name=YourAccount');
    exit;
}

// SECURITY: Admin-only — logged-in non-admins get 403
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. This script requires administrator privileges.');
}

// SECURITY: This is a destructive full-league mutation — POST-only closes the
// GET-based CSRF vector (e.g. <img src=".../updateAllTheThings.php">). A GET
// from a stale link/bookmark bounces back to the control panel.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../modules.php?name=LeagueControlPanel');
    exit;
}

// SECURITY: Validate CSRF token (form name must match the LCP View's generateToken call)
$csrfToken = isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : '';
if (!\Security\CsrfGuard::validateToken($csrfToken, 'lcp_update_all')) {
    http_response_code(403);
    die('Invalid CSRF token.');
}
GATE;

        return [
            'allStarRename' => ['allStarRename.php', $allStarRenameGate],
            'updateAllTheThings' => ['updateAllTheThings.php', $updateAllTheThingsGate],
        ];
    }

    #[DataProvider('adminGateProvider')]
    public function testAdminScriptAuthGatesAreByteIdentical(string $file, string $gateBlock): void
    {
        $src = self::source($file);
        $gatePos = strpos($src, $gateBlock);

        self::assertNotFalse($gatePos, 'Auth gate block changed in scripts/' . $file);
        $dbPos = strpos($src, '$mysqli_db');
        self::assertNotFalse($dbPos);
        self::assertLessThan($dbPos, $gatePos, 'Auth gate must precede the first $mysqli_db use');
    }

    /** @return list<string> */
    private static function optionNames(string $list): array
    {
        preg_match_all('/\'([a-z-]+):*\'/', $list, $matches);
        $names = $matches[1];
        sort($names);

        return $names;
    }

    /** @return list<string> */
    private static function exitArguments(string $src): array
    {
        $tokens = token_get_all($src);
        $count = count($tokens);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_EXIT) {
                continue;
            }
            $buf = '';
            $depth = 0;
            $opened = false;
            for ($j = $i + 1; $j < $count; $j++) {
                $token = $tokens[$j];
                if (is_array($token) && $token[0] === T_WHITESPACE) {
                    continue;
                }
                $text = is_array($token) ? $token[1] : $token;
                if ($text === ';' && $depth === 0) {
                    break;
                }
                if ($text === '(') {
                    $depth++;
                    if (!$opened) {
                        $opened = true;
                        continue;
                    }
                } elseif ($text === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
                $buf .= $text;
            }
            $out[] = preg_replace('/\s+/', '', $buf) ?? $buf;
        }

        return $out;
    }

    /** @return list<string> */
    private static function flagLiterals(string $src): array
    {
        $flags = [];
        foreach (token_get_all($src) as $token) {
            if (
                is_array($token)
                && $token[0] === T_CONSTANT_ENCAPSED_STRING
                && preg_match('/^[\'"]--[a-z0-9]/', $token[1]) === 1
            ) {
                $flags[] = substr($token[1], 1, -1);
            }
        }
        $flags = array_values(array_unique($flags));
        sort($flags);

        return $flags;
    }

    private static function source(string $file): string
    {
        // phpunit-hygiene-allow: top-level CLI scripts bootstrap the DB; exit codes and argv flags have no seam
        $src = file_get_contents(dirname(__DIR__, 3) . '/scripts/' . $file);
        self::assertNotFalse($src, 'Unable to read scripts/' . $file);

        return $src;
    }
}
