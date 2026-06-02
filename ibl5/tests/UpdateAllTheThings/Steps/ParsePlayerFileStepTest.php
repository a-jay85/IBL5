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
    /** @var PlrParserServiceInterface&\PHPUnit\Framework\MockObject\Stub */
    private PlrParserServiceInterface $stubService;
    /** @var JsbSourceResolverInterface&\PHPUnit\Framework\MockObject\Stub */
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubService = self::createStub(PlrParserServiceInterface::class);
        $this->stubResolver = self::createStub(JsbSourceResolverInterface::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        self::assertContains(
            PipelineStepInterface::class,
            (array) class_implements(ParsePlayerFileStep::class)
        );
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

        /** @var PlrParserServiceInterface&\PHPUnit\Framework\MockObject\MockObject */
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
