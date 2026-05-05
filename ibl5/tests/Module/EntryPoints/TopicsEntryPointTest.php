<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class TopicsEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersTopicList(): void
    {
        $this->mockDb->setMockData([
            ['topicid' => 1, 'topicname' => 'IBL News', 'topicimage' => 'ibl.png', 'topictext' => 'IBL'],
        ]);

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
