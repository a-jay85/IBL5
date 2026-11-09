<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Source-content guard for the bare-origin Referer deny in ibl5/.htaccess.
 * Bots send Referer exactly "https://iblhoops.net" (no trailing slash);
 * browsers always send "https://iblhoops.net/". The test extracts the live
 * %{HTTP_REFERER} cond and models mod_rewrite's match in PHP. It cannot see
 * a mod_rewrite parse failure; the plan's Phase 3 container covers that,
 * since the worktree stack runs AllowOverride None and never reads the file.
 */
final class HtaccessBareOriginRefererGuardTest extends TestCase
{
    private string $htaccess;

    /** @var list<string> */
    private array $patterns;

    protected function setUp(): void
    {
        $this->htaccess = (string) file_get_contents(__DIR__ . '/../../../.htaccess');
        preg_match_all(
            '/^RewriteCond\s+%\{HTTP_REFERER\}\s+(\S+)\s+\[NC\]$/m',
            $this->htaccess,
            $matches
        );
        $this->patterns = array_map(
            static fn (string $raw): string => '#' . $raw . '#i',
            $matches[1]
        );
    }

    /** mod_rewrite tests an absent Referer header as the empty string. */
    private function isBlocked(string $referer): bool
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $referer) === 1) {
                return true;
            }
        }
        return false;
    }

    public function testExactlyOneRefererCondIsExtracted(): void
    {
        $this->assertCount(
            1,
            $this->patterns,
            'ibl5/.htaccess must hold exactly one "RewriteCond %{HTTP_REFERER} <pattern> [NC]" line'
        );
    }

    public function testRefererCondIsFollowedByForbiddenRule(): void
    {
        $this->assertSame(
            1,
            preg_match(
                '/^RewriteCond\s+%\{HTTP_REFERER\}\s+\S+\s+\[NC\]\nRewriteRule\s+\^\s+-\s+\[F,L\]$/m',
                $this->htaccess
            ),
            'The HTTP_REFERER cond must be immediately followed by "RewriteRule ^ - [F,L]" so it binds to a 403 deny'
        );
    }

    #[DataProvider('bareOriginRefererProvider')]
    public function testBareOriginReferersAreBlocked(string $referer): void
    {
        $this->assertTrue($this->isBlocked($referer), "Referer '{$referer}' must be blocked");
    }

    #[DataProvider('passingRefererProvider')]
    public function testNonBareReferersPass(string $referer): void
    {
        $this->assertFalse($this->isBlocked($referer), "Referer '{$referer}' must pass");
    }

    public function testRefererBlockSitsAfterCanonicalRedirectAndBeforeOrderAllowDeny(): void
    {
        $markers = [
            'RewriteEngine On',
            'RewriteRule ^ https://iblhoops.net%{REQUEST_URI} [R=301,L]',
            'RewriteCond %{HTTP_USER_AGENT} meta-externalagent [NC]',
            'RewriteCond %{HTTP_REFERER}',
            'Order Allow,Deny',
        ];

        $offsets = [];
        foreach ($markers as $marker) {
            $offset = strpos($this->htaccess, $marker);
            $this->assertIsInt($offset, "Marker '{$marker}' missing from ibl5/.htaccess");
            $offsets[$marker] = $offset;
        }

        for ($i = 1, $n = count($markers); $i < $n; $i++) {
            $this->assertLessThan(
                $offsets[$markers[$i]],
                $offsets[$markers[$i - 1]],
                "'{$markers[$i - 1]}' must come before '{$markers[$i]}'"
            );
        }
    }

    /** @return array<string, array{string}> */
    public static function bareOriginRefererProvider(): array
    {
        return [
            'https apex' => ['https://iblhoops.net'],
            'https www' => ['https://www.iblhoops.net'],
            'http apex' => ['http://iblhoops.net'],
            'http www' => ['http://www.iblhoops.net'],
            'uppercase apex' => ['HTTPS://IBLHOOPS.NET'],
            'mixed-case www' => ['https://WWW.IblHoops.Net'],
            'http uppercase www' => ['http://WWW.iblhoops.net'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function passingRefererProvider(): array
    {
        return [
            'apex slash' => ['https://iblhoops.net/'],
            'www slash' => ['https://www.iblhoops.net/'],
            'module path' => ['https://iblhoops.net/ibl5/modules.php?name=Player&pa=showpage&pid=1'],
            'absent header' => [''],
            'dash literal' => ['-'],
            'lookalike suffix domain' => ['https://iblhoops.net.evil.com'],
            'origin inside query' => ['https://evil.com/?r=https://iblhoops.net'],
            'prefixed host' => ['https://notiblhoops.net'],
            'four-w host' => ['https://wwww.iblhoops.net'],
            'explicit port' => ['https://iblhoops.net:443'],
            'other scheme' => ['ftp://iblhoops.net'],
            'local dev origin' => ['http://127.0.0.1/'],
        ];
    }
}
