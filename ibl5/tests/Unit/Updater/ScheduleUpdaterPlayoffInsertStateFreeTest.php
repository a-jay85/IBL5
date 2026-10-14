<?php

declare(strict_types=1);

namespace Tests\Unit\Updater;

use PHPUnit\Framework\TestCase;
use Updater\ScheduleUpdater;

/**
 * Pins the playoff-insert split as state-free: ScheduleUpdater gained no property,
 * the three extracted steps take only array, int and string parameters (so no
 * session, request, DB handle or actor identity can reach the INSERT), and the
 * reflection seam keeps its private, zero-argument, string-returning shape.
 *
 * @covers \Updater\ScheduleUpdater
 */
final class ScheduleUpdaterPlayoffInsertStateFreeTest extends TestCase
{
    private const PRE_SPLIT_PROPERTIES = [
        'season', 'teamIdToNameMap', 'teamNameToIdMap', 'realTeamIds',
        'sourceResolver', 'basePath', 'channelLogger',
    ];

    private const EXTRACTED_STEPS = [
        'parsePlayoffGamesFromScheduleHtm', 'classifyPlayoffGame', 'insertPlayoffGame',
    ];

    /**
     * @param \ReflectionClass<object> $class
     * @return list<string>
     */
    private function declaredPropertyNames(\ReflectionClass $class): array
    {
        $names = [];
        foreach ($class->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() === $class->getName()) {
                $names[] = $property->getName();
            }
        }
        return $names;
    }

    public function testScheduleUpdaterDeclaresNoPropertyBeyondPreSplitSet(): void
    {
        self::assertSame(
            self::PRE_SPLIT_PROPERTIES,
            $this->declaredPropertyNames(new \ReflectionClass(ScheduleUpdater::class)),
        );
    }

    public function testExtractedStepsTakeOnlyScalarAndArrayParameters(): void
    {
        $class = new \ReflectionClass(ScheduleUpdater::class);

        foreach (self::EXTRACTED_STEPS as $name) {
            $method = $class->getMethod($name);
            self::assertTrue($method->isPrivate(), "{$name}() must stay private");

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                self::assertInstanceOf(\ReflectionNamedType::class, $type);
                self::assertTrue($type->isBuiltin(), "{$name}() must not take an object");
                self::assertContains($type->getName(), ['array', 'int', 'string']);
            }
        }
    }

    public function testPlayoffInsertSeamIsPrivateZeroArgAndReturnsString(): void
    {
        $seam = (new \ReflectionClass(ScheduleUpdater::class))->getMethod('insertPlayoffGamesFromScheduleHtm');

        self::assertTrue($seam->isPrivate());
        self::assertSame(0, $seam->getNumberOfParameters());

        $return = $seam->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $return);
        self::assertSame('string', $return->getName());
    }

    public function testPropertyScanFlagsInjectedIdentityProperty(): void
    {
        self::assertSame(
            ['loggedInTeamID'],
            $this->declaredPropertyNames(new \ReflectionClass(ScheduleUpdaterWithIdentityProbe::class)),
        );
    }
}

/**
 * Reflection-only fixture: a ScheduleUpdater subclass carrying an identity property.
 */
final class ScheduleUpdaterWithIdentityProbe extends ScheduleUpdater
{
    /** @phpstan-ignore property.onlyWritten (reflection fixture) */
    private int $loggedInTeamID = 0;
}
