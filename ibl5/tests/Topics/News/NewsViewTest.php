<?php

declare(strict_types=1);

namespace Tests\Topics\News;

use PHPUnit\Framework\TestCase;
use Topics\News\NewsView;

/**
 * The topic banner replaced the legacy OpenTable()/CloseTable() echo sites in NewsController.
 * The fixtures under fixtures/table-chrome/ hold the bytes those functions produced.
 *
 * @see NewsView::renderTopicBanner()
 * @see NewsView::renderPageTitle()
 */
final class NewsViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The banner strings are defined at the top of NewsController.php.
        class_exists(\Topics\News\NewsController::class);

        if (!defined('_SEARCH')) {
            define('_SEARCH', 'Search');
        }
    }

    private function fixture(string $name): string
    {
        $bytes = file_get_contents(__DIR__ . '/fixtures/table-chrome/' . $name);
        self::assertIsString($bytes);

        return $bytes;
    }

    public function testTopicBannerChromeMatchesCapturedRemoteBytes(): void
    {
        $html = (new NewsView())->renderTopicBanner('IBL', 'Trades', 3, '#EEEEEE', '#CCCCCC');

        self::assertStringStartsWith($this->fixture('open-remote.html'), $html);
        self::assertStringEndsWith($this->fixture('close.html') . '<br>', $html);
    }

    public function testTopicBannerChromeMatchesCapturedLocalhostBytes(): void
    {
        $html = (new NewsView())->renderTopicBanner('IBL', 'Trades', 3, '#BBBBBB', '#CCCCCC');

        self::assertStringStartsWith($this->fixture('open-localhost.html'), $html);
        self::assertStringEndsWith($this->fixture('close.html') . '<br>', $html);
    }

    public function testTopicBannerUnknownTopicRendersNoInfoMessage(): void
    {
        $html = (new NewsView())->renderTopicBanner('IBL', null, 3, '#EEEEEE', '#CCCCCC');

        self::assertStringContainsString(_NOINFO4TOPIC, $html);
        self::assertStringNotContainsString('<form', $html);
    }

    public function testPageTitleSwitchesOnHomeFile(): void
    {
        $view = new NewsView();

        self::assertSame('<h1 class="ibl-title">IBL: Internet Basketball League</h1>', $view->renderPageTitle(true));
        self::assertSame('<h1 class="ibl-title">News</h1>', $view->renderPageTitle(false));
    }
}
