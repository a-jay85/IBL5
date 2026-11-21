<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\RequireInterfaceDeclaresPublicMethodsRule;

/**
 * @extends RuleTestCase<RequireInterfaceDeclaresPublicMethodsRule>
 */
final class RequireInterfaceDeclaresPublicMethodsRuleTest extends RuleTestCase
{
    private const CLASSES = __DIR__ . '/Fixtures/classes/InterfaceDeclares';
    // Matches the fixture directory so the composer PSR-4 map can reflect the classes.
    private const NS = 'Tests\\PHPStanRules\\Fixtures\\classes\\InterfaceDeclares';

    /** @var list<string> */
    private array $receiverSuffixes = [];

    protected function getRule(): Rule
    {
        return new RequireInterfaceDeclaresPublicMethodsRule($this->receiverSuffixes);
    }

    public function testFlagsServiceMethodCalledFromViewButMissingFromInterface(): void
    {
        $this->analyse(
            $this->viewFiles(),
            [
                [$this->serviceMessage(), 15],
            ],
        );
    }

    public function testFlagsControllerMethodCalledFromModuleIndexButMissingFromInterface(): void
    {
        $this->analyse(
            $this->moduleFiles(),
            [
                [$this->controllerMessage(), 10],
            ],
        );
    }

    public function testAllowsMethodDeclaredByContractsInterface(): void
    {
        $this->analyse(
            [
                self::CLASSES . '/Contracts/SeriesRecordsServiceInterface.php',
                self::CLASSES . '/SeriesRecordsService.php',
                self::CLASSES . '/InterfaceTypedCaller.php',
            ],
            [],
        );
    }

    public function testIgnoresClassWithoutContractsInterface(): void
    {
        $this->analyse(
            $this->moduleFiles(),
            [
                [$this->controllerMessage(), 10],
            ],
        );
        // The expectation list above is exhaustive: analyse() fails on any extra
        // error, so a getExtra() hit from the ContractsArchive near-miss would fail here.
    }

    public function testIgnoresFactoryReceivers(): void
    {
        $this->analyse(
            $this->moduleFiles(),
            [
                [$this->controllerMessage(), 10],
            ],
        );
        // Exhaustive expectations: a service() hit on the Factory receiver would fail here.
    }

    public function testIgnoresCallSitesOutsideClassesAndModules(): void
    {
        $this->analyse(
            [
                self::CLASSES . '/Contracts/SeriesRecordsServiceInterface.php',
                self::CLASSES . '/SeriesRecordsService.php',
                __DIR__ . '/Fixtures/outside/InterfaceDeclaresCaller.php',
            ],
            [],
        );
    }

    public function testReceiverSuffixFilterSkipsNonMatchingClass(): void
    {
        $this->receiverSuffixes = ['Controller'];

        $this->analyse(
            array_values(array_unique(array_merge($this->viewFiles(), $this->moduleFiles()))),
            [
                [$this->controllerMessage(), 10],
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function viewFiles(): array
    {
        return [
            self::CLASSES . '/Contracts/SeriesRecordsServiceInterface.php',
            self::CLASSES . '/SeriesRecordsService.php',
            self::CLASSES . '/SeriesRecordsView.php',
        ];
    }

    /**
     * @return list<string>
     */
    private function moduleFiles(): array
    {
        return [
            self::CLASSES . '/Contracts/SeriesRecordsControllerInterface.php',
            self::CLASSES . '/SeriesRecordsController.php',
            self::CLASSES . '/Contracts/SeriesRecordsFactoryInterface.php',
            self::CLASSES . '/SeriesRecordsFactory.php',
            self::CLASSES . '/Contracts/SeriesRecordsServiceInterface.php',
            self::CLASSES . '/SeriesRecordsService.php',
            self::CLASSES . '/ContractsArchive/LegacyStatsServiceInterface.php',
            self::CLASSES . '/LegacyStatsService.php',
            __DIR__ . '/Fixtures/modules/InterfaceDeclares/index.php',
        ];
    }

    private function serviceMessage(): string
    {
        return $this->message(
            self::NS . '\\SeriesRecordsService::getRecordFromMatrix()',
            self::NS . '\\Contracts\\SeriesRecordsServiceInterface',
        );
    }

    private function controllerMessage(): string
    {
        return $this->message(
            self::NS . '\\SeriesRecordsController::main()',
            self::NS . '\\Contracts\\SeriesRecordsControllerInterface',
        );
    }

    private function message(string $method, string $interface): string
    {
        return 'Method ' . $method . ' is called outside its class, but no Contracts interface it implements declares it ('
            . $interface . '). Declare it in the interface, or make it non-public if no outside caller needs it.';
    }
}
