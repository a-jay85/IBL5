<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\SlotNetTallies;

/**
 * F1 and F3 for the `Engine\` namespace: pure classes hold no database
 * handle, and no public method maps one player to a float.
 */
final class EnginePurityTest extends TestCase
{
    /** Classes that wire the engine to the database or the cache. */
    private const array DB_WIRING = [
        'TradeSuggestions\\Engine\\SnapshotKeyRepository',
        'TradeSuggestions\\Engine\\LeagueSnapshotLoader',
        'TradeSuggestions\\Engine\\CachedLeagueSnapshotProvider',
    ];

    private const array PLAYER_TYPES = [
        'TradeSuggestions\\Projection\\ProjectionPlayer',
        'TradeSuggestions\\SuggestionPlayer',
    ];

    public function testNoEngineClassHoldsADatabaseHandle(): void
    {
        $checked = 0;
        foreach (self::engineClasses() as $class) {
            if (in_array($class->getName(), self::DB_WIRING, true) || str_contains($class->getName(), '\\Timing\\')) {
                continue;
            }
            $checked++;
            $types = [];
            $constructor = $class->getConstructor();
            foreach ($constructor !== null ? $constructor->getParameters() : [] as $param) {
                $types[] = $param->getType();
            }
            foreach ($class->getProperties() as $property) {
                $types[] = $property->getType();
            }
            foreach ($types as $type) {
                if ($type instanceof \ReflectionNamedType) {
                    self::assertNotSame(\mysqli::class, $type->getName(), $class->getName());
                    self::assertFalse(is_a($type->getName(), \Database\BaseMysqliRepository::class, true), $class->getName());
                }
            }
        }
        self::assertGreaterThan(20, $checked);
    }

    public function testSlotNetTalliesExposesNoPerPlayerTally(): void
    {
        $names = [];
        foreach ((new \ReflectionClass(SlotNetTallies::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $names[] = $method->getName();
            $paramNames = array_map(static fn (\ReflectionParameter $p): string => $p->getName(), $method->getParameters());
            self::assertFalse(
                in_array('slot', $paramNames, true) && in_array('pid', $paramNames, true),
                $method->getName() . ' reads one player\'s tally at a slot',
            );
        }

        self::assertContains('build', $names);
        self::assertContains('toArray', $names);
        self::assertContains('fromArray', $names);
    }

    public function testNoEngineClassReturnsAFloatPerPlayer(): void
    {
        foreach (self::engineClasses() as $class) {
            foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $params = $method->getParameters();
                $return = $method->getReturnType();
                if (count($params) !== 1 || !$return instanceof \ReflectionNamedType || $return->getName() !== 'float') {
                    continue;
                }
                $type = $params[0]->getType();
                self::assertFalse(
                    $type instanceof \ReflectionNamedType && in_array($type->getName(), self::PLAYER_TYPES, true),
                    $class->getName() . '::' . $method->getName() . ' maps one player to a float',
                );
            }
        }
    }

    /**
     * @return list<\ReflectionClass<object>>
     */
    private static function engineClasses(): array
    {
        $root = dirname(__DIR__, 3) . '/classes/TradeSuggestions/Engine';
        $out = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $name = 'TradeSuggestions\\Engine\\' . str_replace('/', '\\', $relative);
            if (class_exists($name)) {
                $out[] = new \ReflectionClass($name);
            }
        }

        return $out;
    }
}
