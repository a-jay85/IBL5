<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;

/**
 * F3 guard: no composite quality scalar (an `ovr` twin) under classes/TradeSuggestions/.
 *
 * Comments are stripped before matching. A banned name is flagged when it appears
 * as a method name (`function x`), a property or method access (`->x`), a
 * variable or property (`$x`), a constant (`const x`), or a quoted array key
 * (`'x' =>`). A banned word inside a plain string or a comment is not flagged.
 *
 * One-time check: the v2 tree had a single hit, the word "quality" inside a
 * docblock in ProductionLine.php. Comment stripping covers it, so the regex
 * needed no narrowing.
 */
final class NoCompositeQualityScalarTest extends TestCase
{
    private const string NAMES = 'ovr|overall|quality(?:Score|Scalar|Index)?|playerScore|compositeRating|ratingSum|ratingAverage';

    public function testNoCompositeQualityScalarUnderTradeSuggestions(): void
    {
        $root = __DIR__ . '/../../classes/TradeSuggestions';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $hits = [];
        $scanned = 0;
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            // phpunit-hygiene-allow: F3 source-shape guard; the banned property is a name in the file text, no runtime seam exists
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            $scanned++;
            foreach (self::findBannedNames($source) as $hit) {
                $hits[] = $file->getFilename() . ': ' . $hit;
            }
        }

        self::assertGreaterThan(0, $scanned);
        self::assertSame([], $hits, 'Composite quality scalar found: ' . implode(', ', $hits));
    }

    public function testGuardCatchesSyntheticOverallMethod(): void
    {
        self::assertNotSame([], self::findBannedNames('<?php class X { public function ovr(): float { return 1.0; } }'));
        self::assertNotSame([], self::findBannedNames('<?php class X { public function overall(): float { return 1.0; } }'));
        self::assertNotSame([], self::findBannedNames('<?php $a = $p->qualityScore;'));
        self::assertNotSame([], self::findBannedNames("<?php \$row = ['ratingSum' => 3];"));
        self::assertNotSame([], self::findBannedNames('<?php class X { const OVR = 1; }'));
    }

    public function testGuardIgnoresCommentsAndStrings(): void
    {
        self::assertSame([], self::findBannedNames("<?php\n// overall this is fine\n\$x = 1;"));
        self::assertSame([], self::findBannedNames("<?php\n/* ovr in a block comment */\n/** @return float overall */\n\$x = 1;"));
        self::assertSame([], self::findBannedNames("<?php \$label = 'overall';"));
        self::assertSame([], self::findBannedNames('<?php function overallocate(): void {}'));
    }

    /**
     * @return list<string>
     */
    private static function findBannedNames(string $source): array
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $code .= ' ';
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        $pattern = '/(?:function\s+&?|->|\$|const\s+(?:[\w\\\\]+\s+)?)(' . self::NAMES . ')\b'
            . '|[\'"](' . self::NAMES . ')[\'"]\s*=>/i';
        preg_match_all($pattern, $code, $matches);

        return array_values(array_filter($matches[0], static fn (string $m): bool => $m !== ''));
    }
}
