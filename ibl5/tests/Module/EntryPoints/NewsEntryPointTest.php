<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class NewsEntryPointTest extends ModuleEntryPointTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCharacterizationHomeStoryRendersReadMore(): void
    {
        $this->assertFalse(defined('_READMORE'), '_READMORE leaked in from test scaffolding');
        $this->mockDb->onQuery('LEFT JOIN', [
            ['topicid' => 1, 'topicname' => 'IBL', 'topicimage' => 'i.png', 'topictext' => 't'],
        ]);
        $this->mockDb->onQuery('nuke_stories_cat', [
            ['title' => 'Trades'],
        ]);
        $this->mockDb->setMockData([
            [
                'sid' => 1, 'catid' => 0, 'aid' => 'AP', 'title' => 'Story One',
                'time' => '2026-05-13 12:00:00', 'hometext' => 'home', 'bodytext' => 'body',
                'comments' => 0, 'counter' => 0, 'topic' => 1, 'informant' => 'AP',
                'notes' => '', 'acomm' => 0,
            ],
        ]);
        $output = $this->runModule(
            'News',
            extraGlobals: ['storyhome' => 10, 'multilingual' => 0, 'user_news' => 0, 'articlecomm' => 0],
        );
        $this->assertStringContainsString('Read More...', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCharacterizationTopicPathRendersTopicNavigationText(): void
    {
        $this->assertFalse(defined('_SEARCHONTOPIC'), '_SEARCHONTOPIC leaked in from test scaffolding');
        $this->mockDb->onQuery('topictext', [
            ['topictext' => 'Some Topic'],
        ]);
        $this->mockDb->onQuery('LEFT JOIN', [
            ['topicid' => 1, 'topicname' => 'IBL', 'topicimage' => 'i.png', 'topictext' => 't'],
        ]);
        $this->mockDb->onQuery('nuke_stories_cat', [
            ['title' => 'Trades'],
        ]);
        $this->mockDb->setMockData([
            [
                'sid' => 2, 'catid' => 0, 'aid' => 'AP', 'title' => 'Topic Story',
                'time' => '2026-05-13 12:00:00', 'hometext' => 'home', 'bodytext' => 'body',
                'comments' => 0, 'counter' => 0, 'topic' => 1, 'informant' => 'AP',
                'notes' => '', 'acomm' => 0,
            ],
        ]);
        $output = $this->runModule(
            'News',
            get: ['new_topic' => '1'],
            extraGlobals: ['storyhome' => 10, 'multilingual' => 0, 'user_news' => 0, 'articlecomm' => 0],
        );
        $this->assertStringContainsString('Search on This Topic', $output);
        $this->assertStringContainsString('Go to Home', $output);
        $this->assertStringContainsString('Select a New Topic', $output);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCharacterizationEmptyTopicRendersNoInfoText(): void
    {
        $this->assertFalse(defined('_NOINFO4TOPIC'), '_NOINFO4TOPIC leaked in from test scaffolding');
        $this->mockDb->setMockData([]);
        $output = $this->runModule(
            'News',
            get: ['new_topic' => '1'],
            extraGlobals: ['storyhome' => 10, 'multilingual' => 0, 'user_news' => 0, 'articlecomm' => 0],
        );
        $this->assertStringContainsString("Sorry, there isn't information for the selected topic.", $output);
        $this->assertStringContainsString('Go to News Index', $output);
    }

    public function testRendersHomePageStories(): void
    {
        // Topic JOIN query (contains LEFT JOIN)
        $this->mockDb->onQuery('LEFT JOIN', [
            ['topicid' => 1, 'topicname' => 'IBL', 'topicimage' => 'i.png', 'topictext' => 't'],
        ]);
        // Category title query (contains nuke_stories_cat)
        $this->mockDb->onQuery('nuke_stories_cat', [
            ['title' => 'Trades'],
        ]);
        // Main story list (fallback pool — unrouted SELECT)
        $this->mockDb->setMockData([
            [
                'sid' => 1, 'catid' => 0, 'aid' => 'AP', 'title' => 'Story One',
                'time' => '2026-05-13 12:00:00', 'hometext' => 'home', 'bodytext' => 'body',
                'comments' => 0, 'counter' => 0, 'topic' => 1, 'informant' => 'AP',
                'notes' => '', 'acomm' => 0,
            ],
        ]);

        $output = $this->runModule(
            'News',
            extraGlobals: ['storyhome' => 10, 'multilingual' => 0, 'user_news' => 0, 'articlecomm' => 0],
        );

        $this->assertNotEmpty($output);
        // bodytext='body' → fullcount > 0 → Read More link is built
        $this->assertStringContainsString('news-article__link', $output);
    }

    public function testTopicPathRendersWithoutError(): void
    {
        // Topic text query (contains topictext)
        $this->mockDb->onQuery('topictext', [
            ['topictext' => 'Some Topic'],
        ]);
        // Topic JOIN query (contains LEFT JOIN)
        $this->mockDb->onQuery('LEFT JOIN', [
            ['topicid' => 1, 'topicname' => 'IBL', 'topicimage' => 'i.png', 'topictext' => 't'],
        ]);
        // Category title query (contains nuke_stories_cat)
        $this->mockDb->onQuery('nuke_stories_cat', [
            ['title' => 'Trades'],
        ]);
        // Main story list (fallback pool)
        $this->mockDb->setMockData([
            [
                'sid' => 2, 'catid' => 0, 'aid' => 'AP', 'title' => 'Topic Story',
                'time' => '2026-05-13 12:00:00', 'hometext' => 'home', 'bodytext' => 'body',
                'comments' => 0, 'counter' => 0, 'topic' => 1, 'informant' => 'AP',
                'notes' => '', 'acomm' => 0,
            ],
        ]);

        $output = $this->runModule(
            'News',
            get: ['new_topic' => '1'],
            extraGlobals: ['storyhome' => 10, 'multilingual' => 0, 'user_news' => 0, 'articlecomm' => 0],
        );

        $this->assertNotEmpty($output);
    }
}
