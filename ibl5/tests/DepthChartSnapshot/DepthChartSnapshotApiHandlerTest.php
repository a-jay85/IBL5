<?php

declare(strict_types=1);

namespace Tests\DepthChartSnapshot;

use DepthChartSnapshot\DepthChartSnapshotApiHandler;
use Tests\WideUnit\WideUnitTestCase;

/**
 * @covers \DepthChartSnapshot\DepthChartSnapshotApiHandler
 */
class DepthChartSnapshotApiHandlerTest extends WideUnitTestCase
{
    private DepthChartSnapshotApiHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new DepthChartSnapshotApiHandler($this->mockDb);
    }

    public function testHandleUnknownActionReturnsError(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('unknown', 1, 'testuser', []));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Unknown action', $data['error']);
    }

    public function testHandleRenameRejectsInvalidId(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 0, 'name' => 'Test']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    public function testHandleRenameRejectsNegativeId(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => -5, 'name' => 'Test']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    public function testHandleRenameRejectsEmptyName(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 1, 'name' => '']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Name cannot be empty', $data['error']);
    }

    public function testHandleRenameSuccessReturnsJson(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 5, 'name' => 'My DC']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertTrue($data['success']);
        $this->assertSame('My DC', $data['name']);
    }

    public function testHandleRenameTruncatesLongName(): void
    {
        $longName = str_repeat('a', 150);

        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 5, 'name' => $longName]));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame(100, mb_strlen($data['name']));
    }

    public function testHandleRenameStripsHtmlTags(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename', 1, 'testuser', ['id' => 5, 'name' => '<b>Bold DC</b>']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Bold DC', $data['name']);
    }

    public function testHandleRenameActiveRejectsEmptyName(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('rename-active', 1, 'testuser', ['name' => '']));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Name cannot be empty', $data['error']);
    }

    public function testHandleLoadRejectsInvalidId(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('load', 1, 'testuser', ['id' => 0]));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }

    public function testHandleLoadRejectsNegativeId(): void
    {
        $output = $this->captureOutput(fn () => $this->handler->handle('load', 1, 'testuser', ['id' => -5]));

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertSame('Invalid depth chart ID', $data['error']);
    }
}
