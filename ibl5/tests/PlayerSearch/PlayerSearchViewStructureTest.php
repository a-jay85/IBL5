<?php

declare(strict_types=1);

namespace Tests\PlayerSearch;

use PHPUnit\Framework\TestCase;
use PlayerSearch\Contracts\PlayerSearchViewInterface;
use PlayerSearch\PlayerSearchView;

/**
 * Structural security invariants for PlayerSearchView.
 *
 * renderSearchForm() echoes user-supplied params into HTML. These tests pin the
 * properties that keep that safe for any input: the view holds no state, its
 * public surface equals the interface, and every echo in the search-form region
 * is either HtmlSanitizer::e() or a fixed ' selected' literal.
 */
final class PlayerSearchViewStructureTest extends TestCase
{
    public function testViewIsStateFree(): void
    {
        $reflection = new \ReflectionClass(PlayerSearchView::class);

        $this->assertSame([], $reflection->getProperties());
        $this->assertNull($reflection->getConstructor());
    }

    public function testPublicSurfaceMatchesInterface(): void
    {
        $this->assertSame(
            $this->interfaceMethodNames(),
            $this->publicMethodNames(),
        );
    }

    public function testNonInterfaceMethodsArePrivate(): void
    {
        $interfaceMethods = $this->interfaceMethodNames();
        $reflection = new \ReflectionClass(PlayerSearchView::class);

        $offenders = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== PlayerSearchView::class) {
                continue;
            }
            if (in_array($method->getName(), $interfaceMethods, true)) {
                continue;
            }
            if (!$method->isPrivate()) {
                $offenders[] = $method->getName();
            }
        }

        $this->assertSame([], $offenders, 'Non-interface methods must be private');
    }

    public function testSearchFormRegionEchoesOnlyEscapedOrLiteralValues(): void
    {
        $region = $this->searchFormRegion();

        $totalTags = preg_match_all('/<\?=/', $region);
        $safeTags = preg_match_all(
            '/<\?=\s*(?:HtmlSanitizer::e\(\$\w+\)|\([^?]*\)\s*\?\s*\' selected\'\s*:\s*\'\')\s*\?>/',
            $region,
        );

        $this->assertGreaterThan(0, $totalTags);
        $this->assertSame($totalTags, $safeTags, 'Every <?= must be HtmlSanitizer::e($var) or a selected ternary');
        $this->assertSame(0, preg_match('/\becho\s|\bprint\s|\bprintf\(/', $region));
    }

    /**
     * @return list<string>
     */
    private function interfaceMethodNames(): array
    {
        $names = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            (new \ReflectionClass(PlayerSearchViewInterface::class))->getMethods(),
        );
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function publicMethodNames(): array
    {
        $names = [];
        foreach ((new \ReflectionClass(PlayerSearchView::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === PlayerSearchView::class) {
                $names[] = $method->getName();
            }
        }
        sort($names);

        return $names;
    }

    private function searchFormRegion(): string
    {
        $file = (new \ReflectionClass(PlayerSearchView::class))->getFileName();
        $this->assertIsString($file);
        $source = file_get_contents($file);
        $this->assertIsString($source);

        $start = strpos($source, 'public function renderSearchForm(');
        $end = strpos($source, 'public function renderTableHeader(');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $this->assertGreaterThan($start, $end);

        return substr($source, $start, $end - $start);
    }
}
