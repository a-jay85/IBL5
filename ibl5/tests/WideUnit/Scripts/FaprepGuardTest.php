<?php

declare(strict_types=1);

namespace Tests\WideUnit\Scripts;

use PHPUnit\Framework\TestCase;

final class FaprepGuardTest extends TestCase
{
    private string $src;

    protected function setUp(): void
    {
        parent::setUp();
        $source = file_get_contents(__DIR__ . '/../../../faprep.php');
        self::assertIsString($source, 'faprep.php must exist and be readable');
        $this->src = $source;
    }

    /** @param array<int, array<string, string>> $rows */
    private function render(array $rows): string
    {
        $boundary = strpos($this->src, "\n?>\n");
        self::assertIsInt($boundary, 'faprep.php must have a PHP-close boundary before the template');

        $template = substr($this->src, $boundary + strlen("\n?>\n"));

        $tmp = tempnam(sys_get_temp_dir(), 'faprep');
        self::assertIsString($tmp);
        file_put_contents($tmp, "<?php use Security\\HtmlSanitizer; ?>" . $template);

        ob_start();
        try {
            include $tmp;
            $html = ob_get_clean();
        } finally {
            unlink($tmp);
        }

        return (string) $html;
    }

    /** @return array<string, string> */
    private function row(string $name): array
    {
        return [
            'ordinal'      => '1',
            'name'         => $name,
            'age'          => '27',
            'teamname'     => 'Test Team',
            'pos'          => 'PG',
            'coach'        => '3',
            'loyalty'      => '3',
            'playing_time' => '3',
            'winner'       => '3',
            'tradition'    => '3',
            'security'     => '3',
            'exp'          => '3',
            'stamina'      => '3',
        ];
    }

    public function testAdminGuardIsPresentAndReturns403(): void
    {
        self::assertStringContainsString('if (!is_admin())', $this->src);
        self::assertStringContainsString('http_response_code(403)', $this->src);
        self::assertStringContainsString("die('Forbidden')", $this->src);
    }

    public function testAdminGuardPrecedesQueryAndTemplate(): void
    {
        $guard = strpos($this->src, 'is_admin()');
        $query = strpos($this->src, '$mysqli_db->query(');
        $tpl   = strpos($this->src, "\n?>\n");

        self::assertIsInt($guard);
        self::assertIsInt($query);
        self::assertIsInt($tpl);
        self::assertLessThan($query, $guard, 'Guard must precede the DB query');
        self::assertLessThan($tpl, $guard, 'Guard must precede the template boundary');
    }

    public function testEveryEchoIsWrappedInHtmlSanitizer(): void
    {
        preg_match_all('/<\?=\s*(.*?)\s*\?>/', $this->src, $m);
        self::assertCount(13, $m[1], 'Expected exactly 13 <?= ?> expressions');
        foreach ($m[1] as $capture) {
            self::assertStringStartsWith('HtmlSanitizer::e(', $capture, "Echo not wrapped: $capture");
        }
        self::assertSame(13, substr_count($this->src, 'HtmlSanitizer::e('));
    }

    public function testHtmlTagCarriesLangEn(): void
    {
        $html = $this->render([$this->row('Test Player')]);
        self::assertStringContainsString('<html lang="en">', $html);
        self::assertStringNotContainsString("<html>\n", $html);
    }

    public function testScriptTagInPlayerNameIsEscaped(): void
    {
        $xss  = '<script>alert(1)</script>';
        $html = $this->render([$this->row($xss)]);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString($xss, $html);
    }

    public function testEmptyRowsRendersHeaderOnly(): void
    {
        $html = $this->render([]);
        self::assertSame(1, substr_count($html, '<tr>'));
        self::assertStringContainsString('<th>Sta</th>', $html);
    }
}
