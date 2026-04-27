<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserServiceInterface;
use PlrParser\PlrParseResult;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ParsePlayerFileStep;

class ParsePlayerFileStepTest extends TestCase
{
    private PlrParserServiceInterface $stubService;
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubService = $this->createStub(PlrParserServiceInterface::class);
        $this->stubResolver = $this->createStub(JsbSourceResolverInterface::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new ParsePlayerFileStep($this->stubService, $this->stubResolver);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = new ParsePlayerFileStep($this->stubService, $this->stubResolver);

        $this->assertSame('Player file', $step->getLabel());
    }

    public function testSkipsWhenResolverReturnsNull(): void
    {
        $this->stubResolver->method('getContents')->willReturn(null);

        $step = new ParsePlayerFileStep($this->stubService, $this->stubResolver);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No IBL5.plr file found', $result->detail);
    }

    public function testSuccessfulParse(): void
    {
        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 150;

        $this->stubResolver->method('getContents')->willReturn('plr-bytes');

        $mockService = $this->createMock(PlrParserServiceInterface::class);
        $mockService->expects($this->once())
            ->method('processPlrData')
            ->with('plr-bytes')
            ->willReturn($plrResult);

        $step = new ParsePlayerFileStep($mockService, $this->stubResolver);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Player file parsed', $result->label);
        $this->assertNotSame('', $result->detail);
    }
}
