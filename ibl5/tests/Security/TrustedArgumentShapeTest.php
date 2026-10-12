<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Scans every ibl5/classes/**\/*View.php and fails on any HtmlSanitizer::trusted()
 * argument that RequireTrustedAnnotationRule would reject. Nothing is allowed.
 *
 * The PHPStan baseline is a regenerable allowance (bin/regen-baselines rewrites
 * its counts to whatever the tree holds). This test is not: a new bare-variable
 * trusted($x) in any view fails CI even after a baseline regen.
 *
 * The CI baseline-drift gate (.github/workflows/tests.yml:505) only compares
 * counts, so it passes after a regen. This test is the independent check.
 */
final class TrustedArgumentShapeTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function viewProvider(): array
    {
        $root = dirname(__DIR__, 2) . '/classes';
        $sets = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || !str_ends_with($file->getFilename(), 'View.php')) {
                continue;
            }
            $relPath = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $sets[$relPath] = [$relPath];
        }
        ksort($sets);

        self::assertGreaterThanOrEqual(20, count($sets), 'View scan found too few files; did classes/ move?');

        return $sets;
    }

    #[DataProvider('viewProvider')]
    public function testEveryTrustedArgumentInEveryViewIsRuleShaped(string $relPath): void
    {
        $this->assertSame(
            [],
            array_values(array_unique(self::nonRuleShapedTrustedArguments(self::readView($relPath)))),
            "Non-rule-shaped trusted() argument in {$relPath}; escape at the echo with HtmlSanitizer::e() or build the HTML in a \$this->render…() method"
        );
    }

    public function testClassifierRejectsBareVariableArgument(): void
    {
        $src = '<?php echo HtmlSanitizer::trusted($safeTitle); echo \Security\HtmlSanitizer::trusted($rows[\'html\']);';

        $this->assertSame(['$safeTitle', "\$rows['html']"], self::nonRuleShapedTrustedArguments($src));
    }

    /**
     * Accepted: literals, numeric casts, $this->method() and $this->prop->method()
     * calls. safeHtmlOutput( is ignored because it is not a `::trusted(` call.
     */
    public function testClassifierAcceptsRuleShapes(): void
    {
        $src = '<?php echo HtmlSanitizer::trusted(\'lit\'), HtmlSanitizer::trusted(42), HtmlSanitizer::trusted((int) $n),'
            . ' HtmlSanitizer::trusted((bool) $b), HtmlSanitizer::trusted($this->renderX($a, [1])),'
            . ' HtmlSanitizer::trusted($this->helper->render()), HtmlSanitizer::trusted($this->formView->renderCsrfInput()),'
            . ' HtmlSanitizer::trusted((int) $x), HtmlSanitizer::safeHtmlOutput($raw);';

        $this->assertSame([], self::nonRuleShapedTrustedArguments($src));
    }

    public function testClassifierRejectsStaticCallArgument(): void
    {
        $src = '<?= HtmlSanitizer::trusted(PageLayout::renderFontPreconnectLinks()) ?>';

        $this->assertSame(['PageLayout::renderFontPreconnectLinks()'], self::nonRuleShapedTrustedArguments($src));
    }

    public function testClassifierRejectsPropertyFetchArgument(): void
    {
        $src = '<?= HtmlSanitizer::trusted($this->csrfHtml) ?>';

        $this->assertSame(['$this->csrfHtml'], self::nonRuleShapedTrustedArguments($src));
    }

    /**
     * Whitespace-normalised argument texts of every `X::trusted(...)` call that
     * the PHPStan rule would reject. Mirrors RequireTrustedAnnotationRule::isSafeArgument():
     * string and numeric literals, (int)/(float)/(bool) casts, $this->...() calls.
     *
     * @return list<string>
     */
    private static function nonRuleShapedTrustedArguments(string $source): array
    {
        $tokens = \PhpToken::tokenize($source);
        $significant = [];
        foreach ($tokens as $index => $token) {
            if (!$token->isIgnorable()) {
                $significant[] = $index;
            }
        }

        $rejected = [];
        $count = count($significant);
        for ($i = 1; $i < $count - 1; $i++) {
            $name = $tokens[$significant[$i]];
            if (!$name->is(T_STRING) || $name->text !== 'trusted') {
                continue;
            }
            if (!$tokens[$significant[$i - 1]]->is(T_DOUBLE_COLON) || $tokens[$significant[$i + 1]]->text !== '(') {
                continue;
            }

            $depth = 1;
            $text = '';
            for ($j = $significant[$i + 1] + 1; $j < count($tokens); $j++) {
                if ($tokens[$j]->text === '(') {
                    $depth++;
                } elseif ($tokens[$j]->text === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
                $text .= $tokens[$j]->text;
            }

            $argument = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
            if (preg_match('/^([\'"]|\d|\((int|float|bool)\)\s*|\$this->[A-Za-z_]\w*(->[A-Za-z_]\w*)?\()/', $argument) !== 1) {
                $rejected[] = $argument;
            }
        }

        return $rejected;
    }

    private static function readView(string $relPath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/classes/' . $relPath);
        self::assertNotFalse($source, "Cannot read {$relPath}");

        return $source;
    }
}
