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
                ['--sim must be >= 1', '--sim='],
            ],
            'storeSimRecap' => [
                'storeSimRecap.php',
                ['1', '1', '0'],
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
            'build-engine-bundle' => ['build-engine-bundle.php', ['1', '1', '1'], []],
            'runEngineShadow' => ['runEngineShadow.php', ['1', '0', '0', '0', '1'], []],
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
