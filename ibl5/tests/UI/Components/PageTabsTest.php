<?php

declare(strict_types=1);

namespace Tests\UI\Components;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UI\Components\PageTabs;
use UI\Contracts\PageTabsInterface;

final class PageTabsTest extends TestCase
{
    private const BASE_URL = 'modules.php?name=Stats';

    private function makeTabs(): PageTabs
    {
        return new PageTabs(['ratings' => 'Ratings', 'totals' => 'Season Totals', 'qa' => 'Q&A'], 'ratings');
    }

    // --- Phase 1: interface contract assertions ---

    public function testPageTabsInterfaceDeclaresResolve(): void
    {
        $rc = new ReflectionClass(PageTabsInterface::class);
        $this->assertTrue($rc->hasMethod('resolve'));
        $m = $rc->getMethod('resolve');
        $returnType = $m->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
        $params = $m->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('raw', $params[0]->getName());
        $paramType = $params[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertSame('mixed', $paramType->getName());
    }

    public function testPageTabsInterfaceDeclaresRenderTabBar(): void
    {
        $rc = new ReflectionClass(PageTabsInterface::class);
        $this->assertTrue($rc->hasMethod('renderTabBar'));
        $m = $rc->getMethod('renderTabBar');
        $returnType = $m->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
        $params = $m->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('activeTab', $params[0]->getName());
        $this->assertSame('baseUrl', $params[1]->getName());
    }

    public function testPageTabsInterfaceDeclaresWrapPanel(): void
    {
        $rc = new ReflectionClass(PageTabsInterface::class);
        $this->assertTrue($rc->hasMethod('wrapPanel'));
        $m = $rc->getMethod('wrapPanel');
        $returnType = $m->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
        $this->assertSame('string', $returnType->getName());
        $params = $m->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('content', $params[0]->getName());
        $this->assertSame('tabKey', $params[1]->getName());
    }

    // --- Phase 2: resolve() ---

    public function testResolveReturnsValidKey(): void
    {
        $tabs = $this->makeTabs();
        $this->assertSame('totals', $tabs->resolve('totals'));
    }

    public function testResolveFallsBackForUnknownKey(): void
    {
        $tabs = $this->makeTabs();
        $this->assertSame('ratings', $tabs->resolve('bogus'));
    }

    public function testResolveIsCaseSensitive(): void
    {
        $tabs = $this->makeTabs();
        $this->assertSame('ratings', $tabs->resolve('Totals'));
    }

    public function testResolveFallsBackForNonString(): void
    {
        $tabs = $this->makeTabs();
        $this->assertSame('ratings', $tabs->resolve(null));
        $this->assertSame('ratings', $tabs->resolve(1));
        $this->assertSame('ratings', $tabs->resolve(true));
    }

    public function testResolveFallsBackForArray(): void
    {
        $tabs = $this->makeTabs();
        $this->assertSame('ratings', $tabs->resolve(['totals']));
    }

    // --- Phase 2: renderTabBar() ---

    public function testRenderTabBarWrapsInIblTabsDiv(): void
    {
        $tabs = $this->makeTabs();
        $html = $tabs->renderTabBar('ratings', self::BASE_URL);
        $this->assertStringStartsWith('<div class="ibl-tabs">', $html);
        $this->assertStringEndsWith('</div>', $html);
        // 1 wrapper open div + 3 links each have class="ibl-tab
        $this->assertSame(4, substr_count($html, 'class="ibl-tab'));
    }

    public function testRenderTabBarMarksActiveTab(): void
    {
        $tabs = $this->makeTabs();
        $html = $tabs->renderTabBar('totals', self::BASE_URL);
        $this->assertStringContainsString(
            'class="ibl-tab ibl-tab--active" href="modules.php?name=Stats&amp;tab=totals" data-tab="totals" aria-current="page">Season Totals</a>',
            $html
        );
    }

    public function testRenderTabBarOmitsAriaCurrentOnInactiveTabs(): void
    {
        $tabs = $this->makeTabs();
        $html = $tabs->renderTabBar('ratings', self::BASE_URL);
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertSame(1, substr_count($html, 'ibl-tab--active'));
    }

    public function testRenderTabBarWithUnknownActiveTabMarksNone(): void
    {
        $tabs = $this->makeTabs();
        $html = $tabs->renderTabBar('bogus', self::BASE_URL);
        $this->assertStringNotContainsString('aria-current', $html);
        $this->assertStringNotContainsString('ibl-tab--active', $html);
    }

    public function testRenderTabBarEmitsDataTabAttribute(): void
    {
        $tabs = $this->makeTabs();
        $html = $tabs->renderTabBar('ratings', self::BASE_URL);
        $this->assertStringContainsString('data-tab="ratings"', $html);
        $this->assertStringContainsString('data-tab="totals"', $html);
        $this->assertStringContainsString('data-tab="qa"', $html);
    }

    public function testRenderTabBarEscapesAmpersandInHref(): void
    {
        $tabs = $this->makeTabs();
        $html = $tabs->renderTabBar('ratings', self::BASE_URL);
        $this->assertStringContainsString('href="modules.php?name=Stats&amp;tab=ratings"', $html);
        $this->assertStringNotContainsString('Stats&tab=', $html);
    }

    public function testRenderTabBarPercentEncodesKeyInHref(): void
    {
        $tabs = new PageTabs(['a b' => 'Spaced'], 'a b');
        $html = $tabs->renderTabBar('a b', 'modules.php?name=X');
        $this->assertStringContainsString('&amp;tab=a%20b"', $html);
    }

    public function testRenderTabBarMatchesNumericStringKey(): void
    {
        $tabs = new PageTabs(['2024' => 'Season 2024', '2025' => 'Season 2025'], '2024');
        $html = $tabs->renderTabBar('2025', 'modules.php?name=Records');
        $this->assertStringContainsString('data-tab="2025" aria-current="page"', $html);
    }

    public function testRenderTabBarEscapesAmpersandInLabel(): void
    {
        $tabs = $this->makeTabs();
        $html = $tabs->renderTabBar('ratings', self::BASE_URL);
        $this->assertStringContainsString('>Q&amp;A</a>', $html);
        $this->assertStringNotContainsString('>Q&A</a>', $html);
    }

    public function testRenderTabBarEscapesSpecialCharsInKey(): void
    {
        $tabs = new PageTabs(['x&y' => 'X'], 'x&y');
        $html = $tabs->renderTabBar('x&y', 'modules.php?name=X');
        $this->assertStringContainsString('data-tab="x&amp;y"', $html);
    }

    public function testRenderTabBarEscapesScriptTagInLabel(): void
    {
        $tabs = new PageTabs(['evil' => '<script>alert(1)</script>'], 'evil');
        $html = $tabs->renderTabBar('evil', self::BASE_URL);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderTabBarEscapesHtmlInLabel(): void
    {
        $tabs = new PageTabs(['evil' => '<b>Bold</b>'], 'evil');
        $html = $tabs->renderTabBar('evil', self::BASE_URL);
        $this->assertStringContainsString('&lt;b&gt;Bold&lt;/b&gt;', $html);
    }

    public function testRenderTabBarEscapesHtmlInKey(): void
    {
        $tabs = new PageTabs(['<b>' => 'X'], '<b>');
        $html = $tabs->renderTabBar('<b>', self::BASE_URL);
        $this->assertStringContainsString('data-tab="&lt;b&gt;"', $html);
    }

    public function testRenderTabBarEscapesAttributeInjectionInKey(): void
    {
        $tabs = new PageTabs(['a"b' => 'Q'], 'a"b');
        $html = $tabs->renderTabBar('a"b', self::BASE_URL);
        $this->assertStringContainsString('data-tab="a&quot;b"', $html);
        $this->assertStringNotContainsString('data-tab="a"b"', $html);
        $this->assertStringContainsString('tab=a%22b', $html);
    }

    // --- Phase 2: wrapPanel() ---

    public function testWrapPanelContainsIblTabPanelClass(): void
    {
        $tabs = $this->makeTabs();
        $out = $tabs->wrapPanel('<p>x</p>', 'totals');
        $this->assertStringContainsString('class="ibl-tab-panel"', $out);
        $this->assertStringContainsString('data-tab="totals"', $out);
    }

    public function testWrapPanelIncludesContent(): void
    {
        $tabs = $this->makeTabs();
        $content = '<table><tr><td>A&amp;B</td></tr></table>';
        $out = $tabs->wrapPanel($content, 'ratings');
        $this->assertStringContainsString($content, $out);
    }

    public function testWrapPanelEmitsClassAndDataTab(): void
    {
        $tabs = $this->makeTabs();
        $this->assertSame(
            '<div class="ibl-tab-panel" data-tab="totals"><p>x</p></div>',
            $tabs->wrapPanel('<p>x</p>', 'totals')
        );
    }

    public function testWrapPanelIncludesContentVerbatim(): void
    {
        $tabs = $this->makeTabs();
        $content = '<table><tr><td>A&amp;B</td></tr></table>';
        $out = $tabs->wrapPanel($content, 'ratings');
        $this->assertStringContainsString($content, $out);
    }

    public function testWrapPanelEscapesKey(): void
    {
        $tabs = $this->makeTabs();
        $out = $tabs->wrapPanel('', '"><script>');
        $this->assertStringContainsString('data-tab="&quot;&gt;&lt;script&gt;"', $out);
        $this->assertStringNotContainsString('<script>', $out);
    }

    // --- Phase 5: security-discharge structural assertions ---

    public function testPageTabsHoldsNoDbOrActorIdentity(): void
    {
        $rc = new ReflectionClass(PageTabs::class);
        $forbidden = ['db', 'loggedInTeamID', 'userId', 'username', 'session'];
        foreach ($rc->getProperties() as $prop) {
            $this->assertNotContains(
                $prop->getName(),
                $forbidden,
                "PageTabs must not hold property \${$prop->getName()}"
            );
            $type = $prop->getType();
            if ($type instanceof \ReflectionNamedType) {
                $typeName = ltrim($type->getName(), '?\\');
                $this->assertStringNotContainsString('mysqli', $typeName);
                $this->assertStringNotContainsString('Database', $typeName);
            }
        }
    }

    public function testDbPropertyDetectionHelperCanFail(): void
    {
        $fixture = new class {
            // @phpstan-ignore property.unused
            private \stdClass $db;
        };
        $rc = new ReflectionClass($fixture);
        $props = array_map(fn ($p) => $p->getName(), $rc->getProperties());
        $this->assertContains('db', $props, 'Fixture sanity: anonymous class must declare $db so detection is meaningful');
    }

    public function testSecurityLabelIsHtmlEscaped(): void
    {
        /** PageTabs passes every user-visible value (key, label, href segment) through HtmlSanitizer::safeHtmlOutput before inserting into HTML. */
        $tabs = new PageTabs(['evil' => '<script>alert(1)</script>'], 'evil');
        $html = $tabs->renderTabBar('evil', 'modules.php?name=Test');
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testSecurityTabKeyCannotInjectAttribute(): void
    {
        /** PageTabs passes every user-visible value (key, label, href segment) through HtmlSanitizer::safeHtmlOutput before inserting into HTML. */
        $tabs = new PageTabs(['"onclick="' => 'Evil'], '"onclick="');
        $html = $tabs->renderTabBar('"onclick="', 'modules.php?name=Test');
        $this->assertStringNotContainsString('"onclick="', $html);
        $this->assertMatchesRegularExpression('/&quot;onclick=&quot;|%22onclick%3D%22/', $html);
    }

    public function testSecurityBaseUrlIsEscapedInHref(): void
    {
        /** PageTabs passes every user-visible value (key, label, href segment) through HtmlSanitizer::safeHtmlOutput before inserting into HTML. */
        $tabs = new PageTabs(['tab' => 'Tab'], 'tab');
        $html = $tabs->renderTabBar('tab', 'modules.php?name=Test&foo=<bar>');
        $this->assertStringContainsString('&amp;foo=&lt;bar&gt;', $html);
        $this->assertStringNotContainsString('<bar>', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }
}
