<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class TopicsEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersTopicList(): void
    {
        $this->mockDb->setMockData([
            [
                'topicid' => 1,
                'topicname' => 'IBL News',
                'topicimage' => 'ibl.png',
                'topictext' => 'IBL',
                'stories' => 0,
                'total_reads' => 0,
            ],
        ]);
        // SearchRepository::getCategories() reads catid/title from a different table,
        // but MockDatabase returns the same data for unrouted queries — route it explicitly.
        $this->mockDb->onQuery('_stories_cat', []);

        $output = $this->runModule('Topics');

        $this->assertNotEmpty($output);
    }

    public function testHandlesEmptyTopicList(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Topics');

        $this->assertNotEmpty($output);
    }
}
