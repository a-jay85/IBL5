<?php

declare(strict_types=1);

namespace Tests\SavedDepthChart;

use SavedDepthChart\SavedDepthChartApiHandler;
use Tests\Integration\IntegrationTestCase;

/**
 * @covers \SavedDepthChart\SavedDepthChartApiHandler
 */
class SavedDepthChartApiHandlerTest extends IntegrationTestCase
{
    private SavedDepthChartApiHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new SavedDepthChartApiHandler($this->mockDb);
    }

    public function testHandleUnknownActionReturnsError(): void
    {
        ob_start();
        $this->handler->handle('unknown', 1, 'testuser', []);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('Unknown action', $data['error']);
    }

    public function testHandleRenameRejectsInvalidId(): void
    {
        ob_start();
        $this->handler->handle('rename', 1, 'testuser', ['id' => 0, 'name' => 'Test']);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    public function testHandleRenameRejectsNegativeId(): void
    {
        ob_start();
        $this->handler->handle('rename', 1, 'testuser', ['id' => -5, 'name' => 'Test']);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    public function testHandleRenameRejectsEmptyName(): void
    {
        ob_start();
        $this->handler->handle('rename', 1, 'testuser', ['id' => 1, 'name' => '']);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('Name cannot be empty', $data['error']);
    }

    public function testHandleRenameSuccessReturnsJson(): void
    {
        ob_start();
        $this->handler->handle('rename', 1, 'testuser', ['id' => 5, 'name' => 'My DC']);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertSame('My DC', $data['name']);
    }

    public function testHandleRenameTruncatesLongName(): void
    {
        $longName = str_repeat('a', 150);

        ob_start();
        $this->handler->handle('rename', 1, 'testuser', ['id' => 5, 'name' => $longName]);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame(100, mb_strlen($data['name']));
    }

    public function testHandleRenameStripsHtmlTags(): void
    {
        ob_start();
        $this->handler->handle('rename', 1, 'testuser', ['id' => 5, 'name' => '<b>Bold DC</b>']);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('Bold DC', $data['name']);
    }

    public function testHandleRenameActiveRejectsEmptyName(): void
    {
        ob_start();
        $this->handler->handle('rename-active', 1, 'testuser', ['name' => '']);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('Name cannot be empty', $data['error']);
    }

    public function testHandleLoadRejectsInvalidId(): void
    {
        ob_start();
        $this->handler->handle('load', 1, 'testuser', ['id' => 0]);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    public function testHandleLoadRejectsNegativeId(): void
    {
        ob_start();
        $this->handler->handle('load', 1, 'testuser', ['id' => -5]);
        $output = ob_get_clean();

        $data = json_decode((string) $output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }
}
