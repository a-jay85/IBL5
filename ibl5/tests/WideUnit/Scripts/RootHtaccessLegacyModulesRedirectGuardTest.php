<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Source-content guard for the legacy `/modules.php` redirect in the repo-root
 * .htaccess. Old PHP-Nuke links still hit `/modules.php` at the web root; the
 * rule sends them to `/ibl5/modules.php` with the query string kept. This test
 * parses the file and models mod_rewrite's first-matching-rule evaluation. It
 * never runs Apache.
 *
 * What this test cannot see: a mod_rewrite PARSE failure. That is covered by
 * the ephemeral AllowOverride-All Apache container check in the plan, because
 * the Docker stack's `AllowOverride None` (Dockerfile:40-47) never reads the
 * root .htaccess.
 */
final class RootHtaccessLegacyModulesRedirectGuardTest extends TestCase
{
    private const TARGET = '/ibl5/modules.php';

    /** @var list<string> */
    private array $lines;

    /** @var list<array{pattern: string, target: string, flags: list<string>, line: int}> */
    private array $rules;

    protected function setUp(): void
    {
        // From tests/WideUnit/Scripts/ the repo root is one level above ibl5/.
        $htaccess = (string) file_get_contents(__DIR__ . '/../../../../.htaccess');

        // A wrong __DIR__ depth must fail loudly instead of passing vacuously.
        self::assertStringContainsString('RewriteEngine On', $htaccess, 'Root .htaccess was not read');

        $this->lines = preg_split('/\R/', $htaccess) ?: [];
        $this->rules = [];

        foreach ($this->lines as $index => $line) {
            if (preg_match('/^RewriteRule\s+(\S+)\s+(\S+)(?:\s+\[([^\]]*)\])?\s*$/', $line, $m) !== 1) {
                continue;
            }

            $this->rules[] = [
                'pattern' => $m[1],
                'target' => $m[2],
                'flags' => array_map('trim', explode(',', $m[3] ?? '')),
                'line' => $index,
            ];
        }
    }

    /**
     * First rule whose pattern matches the per-directory path (no leading
     * slash), mirroring mod_rewrite's first-L-wins evaluation.
     *
     * @return array{pattern: string, target: string, flags: list<string>, line: int}|null
     */
    private function firstRuleMatching(string $path): ?array
    {
        foreach ($this->rules as $rule) {
            if (preg_match('#' . $rule['pattern'] . '#', $path) === 1) {
                return $rule;
            }
        }

        return null;
    }

    public function testExistingRootRulesUnchanged(): void
    {
        $htaccess = implode("\n", $this->lines);

        $expected = [
            'ErrorDocument 404 /ibl5/error-pages/404.html',
            'ErrorDocument 500 /ibl5/error-pages/500.html',
            'RewriteRule ^(setup\.cgi|geoserver/|rdweb/) - [R=410,L]',
            'RewriteRule ^(wp|wordpress|blog|backup|old|new)/? - [R=410,L]',
            'RewriteRule ^(app-ads\.txt|sellers\.json)$ - [R=410,L]',
            'RewriteCond %{REQUEST_URI} ^/?$',
            'RewriteRule ^$ /ibl5/index.php [R=301,L]',
        ];

        foreach ($expected as $line) {
            self::assertStringContainsString($line, $htaccess, "Existing root rule changed or missing: $line");
        }
    }

    public function testLegacyModulesPathRedirectsToIbl5ModulesPhp(): void
    {
        $rule = $this->firstRuleMatching('modules.php');

        self::assertNotNull($rule, 'no rule matches modules.php');
        self::assertSame(self::TARGET, $rule['target']);
        self::assertContains('R=301', $rule['flags']);
        self::assertContains('L', $rule['flags']);
        // No `?` in the target: Apache appends the original query string.
        self::assertStringNotContainsString('?', $rule['target']);
    }

    public function testLegacyModulesRuleTargetIsSameOrigin(): void
    {
        $rule = $this->firstRuleMatching('modules.php');

        self::assertNotNull($rule, 'no rule matches modules.php');
        self::assertStringStartsWith('/ibl5/', $rule['target']);
        self::assertSame(0, preg_match('#^[a-z][a-z0-9+.-]*://#i', $rule['target']), 'Target must not be absolute');
        self::assertSame(0, preg_match('#^//#', $rule['target']), 'Target must not be protocol-relative');
    }

    public function testLegacyModulesRuleHasNoAttachedRewriteCond(): void
    {
        $rule = $this->firstRuleMatching('modules.php');

        self::assertNotNull($rule, 'no rule matches modules.php');

        // mod_rewrite ignores blank and comment lines, so a cond above a
        // comment block still binds the rule.
        for ($i = $rule['line'] - 1; $i >= 0; $i--) {
            $trimmed = trim($this->lines[$i]);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            self::assertStringStartsNotWith(
                'RewriteCond',
                $trimmed,
                'A RewriteCond binds the modules.php rule; a stale server file would shadow the redirect'
            );

            return;
        }
    }

    #[DataProvider('nonMatchingPathProvider')]
    public function testRulePatternScopesToRootModulesPhpOnly(string $path): void
    {
        $rule = $this->firstRuleMatching($path);

        self::assertTrue(
            $rule === null || $rule['target'] !== self::TARGET,
            "$path must not be redirected to " . self::TARGET
        );
    }

    /** @return array<string, array{string}> */
    public static function nonMatchingPathProvider(): array
    {
        return [
            'real target (no loop)' => ['ibl5/modules.php'],
            'suffix spoof'          => ['modules.phpx'],
            'prefix spoof'          => ['xmodules.php'],
            'nested path'           => ['foo/modules.php'],
            'wrong case'            => ['Modules.php'],
            'trailing path'         => ['modules.php/extra'],
        ];
    }
}
