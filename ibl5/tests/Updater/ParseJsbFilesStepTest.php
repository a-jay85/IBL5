<?php

declare(strict_types=1);

namespace Tests\Updater;

use JsbParser\JsbImportResult;
use JsbParser\JsbImportService;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Steps\ParseJsbFilesStep;

class ParseJsbFilesStepTest extends TestCase
{
    public function testGetLabelReturnsJsbFilesParsed(): void
    {
        $step = $this->buildStep(files: []);
        self::assertSame('JSB files parsed', $step->getLabel());
    }

    public function testNoFilesFoundReturnsSuccessWithNoChanges(): void
    {
        $step = $this->buildStep(files: []);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertSame('No changes', $result->detail);
        self::assertEmpty($result->messages);
    }

    public function testOnlyTrnFileProcessedWhenPresent(): void
    {
        $trnResult = new JsbImportResult();
        $trnResult->addInserted(3);

        $service = self::createStub(JsbImportService::class);
        $service->method('processTrnData')->willReturn($trnResult);

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')
            ->willReturnCallback(static fn (string $ext) => $ext === 'trn' ? 'trn-data' : null);

        $step = new ParseJsbFilesStep($service, $resolver, 2025);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertSame('3 inserted', $result->detail);
        self::assertCount(1, $result->messages);
        self::assertStringContainsString('TRN:', $result->messages[0]);
    }

    public function testMultipleFilesAreMergedIntoSummary(): void
    {
        $trnResult = new JsbImportResult();
        $trnResult->addInserted(2);

        $carResult = new JsbImportResult();
        $carResult->addUpdated(5);

        $service = self::createStub(JsbImportService::class);
        $service->method('processTrnData')->willReturn($trnResult);
        $service->method('processCarData')->willReturn($carResult);

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')
            ->willReturnCallback(static function (string $ext): ?string {
                return in_array($ext, ['trn', 'car'], true) ? $ext . '-data' : null;
            });

        $step = new ParseJsbFilesStep($service, $resolver, 2025);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertSame('2 inserted, 5 updated', $result->detail);
        self::assertCount(2, $result->messages);
    }

    public function testMessageErrorCountPropagatesFromImportResult(): void
    {
        $hisResult = new JsbImportResult();
        $hisResult->addError('Unknown player ID 999');

        $service = self::createStub(JsbImportService::class);
        $service->method('processHisData')->willReturn($hisResult);

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')
            ->willReturnCallback(static fn (string $ext) => $ext === 'his' ? 'his-data' : null);

        $step = new ParseJsbFilesStep($service, $resolver, 2025);
        $result = $step->execute();

        self::assertTrue($result->success);
        self::assertSame(1, $result->messageErrorCount);
        self::assertNotEmpty($result->messages);
    }

    /**
     * @param list<string> $files Extensions that should return non-null content
     */
    private function buildStep(array $files): ParseJsbFilesStep
    {
        $emptyResult = new JsbImportResult();

        $service = self::createStub(JsbImportService::class);
        $service->method('processTrnData')->willReturn($emptyResult);
        $service->method('processCarData')->willReturn($emptyResult);
        $service->method('processHisData')->willReturn($emptyResult);
        $service->method('processAswData')->willReturn($emptyResult);
        $service->method('processRcbData')->willReturn($emptyResult);

        $resolver = self::createStub(JsbSourceResolverInterface::class);
        $resolver->method('getContents')
            ->willReturnCallback(static fn (string $ext) => in_array($ext, $files, true) ? $ext . '-data' : null);

        return new ParseJsbFilesStep($service, $resolver, 2025);
    }
}
