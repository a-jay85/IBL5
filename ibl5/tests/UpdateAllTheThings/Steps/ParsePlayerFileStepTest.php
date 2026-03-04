<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use PlrParser\PlrParseResult;
use PlrParser\PlrParserService;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ParsePlayerFileStep;

class ParsePlayerFileStepTest extends TestCase
{
    private PlrParserService $stubService;

    protected function setUp(): void
    {
        $this->stubService = $this->createStub(PlrParserService::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new ParsePlayerFileStep($this->stubService, '/tmp/IBL5.plr');

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = new ParsePlayerFileStep($this->stubService, '/tmp/IBL5.plr');

        $this->assertSame('Player file', $step->getLabel());
    }

    public function testSkipsWhenFileNotFound(): void
    {
        $step = new ParsePlayerFileStep($this->stubService, '/nonexistent/IBL5.plr');
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No IBL5.plr file found', $result->detail);
    }

    public function testSuccessfulParse(): void
    {
        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 150;
        $plrResult->historyRowsUpserted = 300;
        $plrResult->teamsAssigned = 28;

        $this->stubService->method('processPlrFile')->willReturn($plrResult);

        $path = tempnam(sys_get_temp_dir(), 'plr_test_');
        if ($path === false) {
            $this->fail('Failed to create temp file');
        }

        $step = new ParsePlayerFileStep($this->stubService, $path);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Player file parsed', $result->label);
        $this->assertNotSame('', $result->detail);
    }
}
