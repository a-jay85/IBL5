<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Contracts\UpdaterViewInterface;
use Updater\StepResult;
use Updater\UpdaterController;
use Updater\UpdaterService;

class UpdaterControllerTest extends TestCase
{
    private UpdaterService $stubService;
    private UpdaterViewInterface $stubView;

    protected function setUp(): void
    {
        $this->stubService = $this->createStub(UpdaterService::class);
        $this->stubView = $this->createStub(UpdaterViewInterface::class);

        $this->stubService->method('getSuccessCount')->willReturn(0);
        $this->stubService->method('getErrorCount')->willReturn(0);

        $this->stubView->method('renderSectionOpen')->willReturn('<section>');
        $this->stubView->method('renderSectionClose')->willReturn('</section>');
        $this->stubView->method('renderSummary')->willReturn('<summary>');
        $this->stubView->method('renderStepStart')->willReturn('<start>');
        $this->stubView->method('renderStepComplete')->willReturn('<complete>');
        $this->stubView->method('renderStepError')->willReturn('<error>');
    }

    public function testRunOutputsSectionOpenAndClose(): void
    {
        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->expects($this->once())->method('renderSectionOpen')->with('Pipeline')->willReturn('<section>');
        $mockView->expects($this->once())->method('renderSectionClose')->willReturn('</section>');
        $mockView->method('renderSummary')->willReturn('<summary>');

        $controller = new UpdaterController($this->stubService, $mockView);

        $output = $this->captureOutput(fn () => $controller->run());

        $this->assertStringContainsString('<section>', $output);
        $this->assertStringContainsString('</section>', $output);
    }

    public function testRunOutputsSummary(): void
    {
        $mockService = $this->createStub(UpdaterService::class);
        $mockService->method('getSuccessCount')->willReturn(5);
        $mockService->method('getErrorCount')->willReturn(2);

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->expects($this->once())
            ->method('renderSummary')
            ->with(5, 2)
            ->willReturn('<summary:5:2>');

        $controller = new UpdaterController($mockService, $mockView);

        $output = $this->captureOutput(fn () => $controller->run());

        $this->assertStringContainsString('<summary:5:2>', $output);
    }

    #[DataProvider('stepProgressLabelProvider')]
    public function testGetStepProgressLabelMapsKnownLabels(string $stepLabel, string $expectedProgress): void
    {
        $step = $this->createStub(PipelineStepInterface::class);
        $step->method('getLabel')->willReturn($stepLabel);

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->method('renderSummary')->willReturn('');
        $mockView->method('renderStepComplete')->willReturn('');

        $mockView->expects($this->once())
            ->method('renderStepStart')
            ->with($expectedProgress)
            ->willReturn('<start>');

        $mockService = $this->createStub(UpdaterService::class);
        $mockService->method('getSuccessCount')->willReturn(0);
        $mockService->method('getErrorCount')->willReturn(0);
        $mockService->method('run')->willReturnCallback(
            function (callable $onStart, callable $onComplete) use ($step): array {
                $onStart($step);
                $onComplete(StepResult::success($step->getLabel()));
                return [];
            }
        );

        $controller = new UpdaterController($mockService, $mockView);
        $this->captureOutput(fn () => $controller->run());
    }

    public static function stepProgressLabelProvider(): array
    {
        return [
            'backup' => ['Backup extracted', 'Extracting files from backup archive...'],
            'league config' => ['League config', 'Importing league config (.lge)...'],
            'player file' => ['Player file', 'Parsing player file (.plr)...'],
            'preseason' => ['Preseason data cleaned', 'Cleaning preseason data...'],
            'schedule' => ['Schedule updated', 'Updating schedule...'],
            'standings' => ['Standings updated', 'Updating standings...'],
            'power rankings' => ['Power rankings updated', 'Updating power rankings...'],
            'extensions' => ['Extension attempts reset', 'Resetting extension attempts...'],
            'depth charts' => ['Saved depth charts updated', 'Updating saved depth charts...'],
            'boxscores' => ['Boxscores processed', 'Processing boxscores (.sco)...'],
            'all-star' => ['All-Star games processed', 'Processing All-Star games...'],
            'jsb' => ['JSB files parsed', 'Parsing JSB engine files...'],
            'end-of-season' => ['End-of-season imports', 'Running end-of-season imports (.dra, .ret, .hof, .awa)...'],
            'snapshot' => ['Player snapshot', 'Snapshotting player stats...'],
            'hist' => ['ibl_hist refreshed', 'Refreshing historical stats table...'],
            'default fallback' => ['Unknown step', 'Unknown step...'],
        ];
    }

    public function testRenderStepResultSuccess(): void
    {
        $result = StepResult::success('Test step', 'detail text');

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->method('renderSummary')->willReturn('');
        $mockView->method('renderStepStart')->willReturn('');
        $mockView->expects($this->once())
            ->method('renderStepComplete')
            ->with('Test step', 'detail text')
            ->willReturn('<done>');

        $service = $this->createStub(UpdaterService::class);
        $service->method('getSuccessCount')->willReturn(0);
        $service->method('getErrorCount')->willReturn(0);
        $service->method('run')->willReturnCallback(
            function (callable $onStart, callable $onComplete) use ($result): array {
                $step = $this->createStub(PipelineStepInterface::class);
                $step->method('getLabel')->willReturn('Test step');
                $onStart($step);
                $onComplete($result);
                return [];
            }
        );

        $controller = new UpdaterController($service, $mockView);
        $output = $this->captureOutput(fn () => $controller->run());
        $this->assertStringContainsString('<done>', $output);
    }

    public function testRenderStepResultFailure(): void
    {
        $result = StepResult::failure('Failed step', 'Something broke');

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->method('renderSummary')->willReturn('');
        $mockView->method('renderStepStart')->willReturn('');
        $mockView->expects($this->once())
            ->method('renderStepError')
            ->with('Failed step', 'Something broke')
            ->willReturn('<error>');

        $service = $this->createStub(UpdaterService::class);
        $service->method('getSuccessCount')->willReturn(0);
        $service->method('getErrorCount')->willReturn(0);
        $service->method('run')->willReturnCallback(
            function (callable $onStart, callable $onComplete) use ($result): array {
                $step = $this->createStub(PipelineStepInterface::class);
                $step->method('getLabel')->willReturn('Failed step');
                $onStart($step);
                $onComplete($result);
                return [];
            }
        );

        $controller = new UpdaterController($service, $mockView);
        $output = $this->captureOutput(fn () => $controller->run());
        $this->assertStringContainsString('<error>', $output);
    }

    public function testRenderStepResultWithInlineHtml(): void
    {
        $result = StepResult::success('Step', inlineHtml: '<div>inline</div>');

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->method('renderSummary')->willReturn('');
        $mockView->method('renderStepStart')->willReturn('');
        $mockView->method('renderStepComplete')->willReturn('');
        $mockView->expects($this->once())
            ->method('renderInlineHtml')
            ->with('<div>inline</div>')
            ->willReturn('<inline>');
        $mockView->expects($this->never())->method('renderCollapsibleLog');

        $service = $this->stubServiceWithResult($result);

        $controller = new UpdaterController($service, $mockView);
        $output = $this->captureOutput(fn () => $controller->run());
        $this->assertStringContainsString('<inline>', $output);
    }

    public function testRenderStepResultWithCollapsibleLog(): void
    {
        $result = StepResult::success('Step', inlineHtml: '<div>log</div>', collapsibleLog: true);

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->method('renderSummary')->willReturn('');
        $mockView->method('renderStepStart')->willReturn('');
        $mockView->method('renderStepComplete')->willReturn('');
        $mockView->expects($this->once())
            ->method('renderCollapsibleLog')
            ->with('<div>log</div>')
            ->willReturn('<collapsible>');
        $mockView->expects($this->never())->method('renderInlineHtml');

        $service = $this->stubServiceWithResult($result);

        $controller = new UpdaterController($service, $mockView);
        $output = $this->captureOutput(fn () => $controller->run());
        $this->assertStringContainsString('<collapsible>', $output);
    }

    public function testRenderStepResultWithCapturedLog(): void
    {
        $result = StepResult::success('Step', capturedLog: 'log output');

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->method('renderSummary')->willReturn('');
        $mockView->method('renderStepStart')->willReturn('');
        $mockView->method('renderStepComplete')->willReturn('');
        $mockView->expects($this->once())
            ->method('renderLog')
            ->with('log output')
            ->willReturn('<log>');

        $service = $this->stubServiceWithResult($result);

        $controller = new UpdaterController($service, $mockView);
        $output = $this->captureOutput(fn () => $controller->run());
        $this->assertStringContainsString('<log>', $output);
    }

    public function testRenderStepResultWithCapturedLogCollapsible(): void
    {
        $result = StepResult::success('Step', capturedLog: 'log output', collapsibleLog: true);

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->method('renderSummary')->willReturn('');
        $mockView->method('renderStepStart')->willReturn('');
        $mockView->method('renderStepComplete')->willReturn('');
        $mockView->expects($this->once())
            ->method('renderCollapsibleLog')
            ->with('log output')
            ->willReturn('<collapsible-log>');
        $mockView->expects($this->never())->method('renderLog');

        $service = $this->stubServiceWithResult($result);

        $controller = new UpdaterController($service, $mockView);
        $output = $this->captureOutput(fn () => $controller->run());
        $this->assertStringContainsString('<collapsible-log>', $output);
    }

    public function testRenderStepResultWithMessages(): void
    {
        $result = StepResult::success('Step', messages: ['msg1', 'msg2'], messageErrorCount: 1);

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->method('renderSummary')->willReturn('');
        $mockView->method('renderStepStart')->willReturn('');
        $mockView->method('renderStepComplete')->willReturn('');
        $mockView->expects($this->once())
            ->method('renderMessageLog')
            ->with(['msg1', 'msg2'], 1)
            ->willReturn('<messages>');

        $service = $this->stubServiceWithResult($result);

        $controller = new UpdaterController($service, $mockView);
        $output = $this->captureOutput(fn () => $controller->run());
        $this->assertStringContainsString('<messages>', $output);
    }

    public function testRenderStepResultWithMessageErrorCountOnly(): void
    {
        $result = StepResult::success('Step', messages: [], messageErrorCount: 3);

        $mockView = $this->createMock(UpdaterViewInterface::class);
        $mockView->method('renderSectionOpen')->willReturn('');
        $mockView->method('renderSectionClose')->willReturn('');
        $mockView->method('renderSummary')->willReturn('');
        $mockView->method('renderStepStart')->willReturn('');
        $mockView->method('renderStepComplete')->willReturn('');
        $mockView->expects($this->once())
            ->method('renderMessageLog')
            ->with([], 3)
            ->willReturn('<error-messages>');

        $service = $this->stubServiceWithResult($result);

        $controller = new UpdaterController($service, $mockView);
        $output = $this->captureOutput(fn () => $controller->run());
        $this->assertStringContainsString('<error-messages>', $output);
    }

    private function stubServiceWithResult(StepResult $result): UpdaterService
    {
        $service = $this->createStub(UpdaterService::class);
        $service->method('getSuccessCount')->willReturn(0);
        $service->method('getErrorCount')->willReturn(0);
        $service->method('run')->willReturnCallback(
            function (callable $onStart, callable $onComplete) use ($result): array {
                $step = $this->createStub(PipelineStepInterface::class);
                $step->method('getLabel')->willReturn($result->label);
                $onStart($step);
                $onComplete($result);
                return [];
            }
        );
        return $service;
    }

    private function captureOutput(callable $fn): string
    {
        ob_start();
        try {
            $fn();
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
