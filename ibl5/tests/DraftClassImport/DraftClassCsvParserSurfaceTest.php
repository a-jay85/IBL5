<?php

declare(strict_types=1);

namespace Tests\DraftClassImport;

use DraftClassImport\DraftClassCsvParser;
use PHPUnit\Framework\TestCase;

/**
 * Pins the structural surface of the CSV parser: it validates user-uploaded
 * input, so it must stay a stateless class with a two-method public API and
 * no access to superglobals, mysqli, or output.
 *
 * @covers \DraftClassImport\DraftClassCsvParser
 */
class DraftClassCsvParserSurfaceTest extends TestCase
{
    private const FORBIDDEN_VARIABLES = ['$_SESSION', '$_POST', '$_GET', '$_REQUEST', '$_COOKIE', '$_FILES', '$_SERVER', '$GLOBALS'];

    public function testParserIsAStatelessFinalClassWithNoConstructor(): void
    {
        $ref = new \ReflectionClass(DraftClassCsvParser::class);

        $this->assertTrue($ref->isFinal());
        $this->assertNull($ref->getConstructor());
        $this->assertSame([], $ref->getProperties());
        $this->assertFalse($ref->getParentClass());
        $this->assertSame([], $ref->getInterfaceNames());
    }

    public function testPublicApiIsExactlyParseAndValidateRowsWithUnchangedSignatures(): void
    {
        $ref = new \ReflectionClass(DraftClassCsvParser::class);
        $names = array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            $ref->getMethods(\ReflectionMethod::IS_PUBLIC)
        );
        sort($names);

        $this->assertSame(['parse', 'validateRows'], $names);
        $this->assertSignature($ref->getMethod('parse'), 'raw', 'string');
        $this->assertSignature($ref->getMethod('validateRows'), 'rows', 'array');
    }

    public function testOnlyPublicConstantIsColumnLabels(): void
    {
        $ref = new \ReflectionClass(DraftClassCsvParser::class);

        $this->assertSame(['COLUMN_LABELS'], array_keys($ref->getConstants(\ReflectionClassConstant::IS_PUBLIC)));
    }

    public function testSourceCarriesNoSuperglobalDatabaseOutputOrIncludeTokens(): void
    {
        $file = (new \ReflectionClass(DraftClassCsvParser::class))->getFileName();
        $this->assertIsString($file);
        // phpunit-hygiene-allow: structural token scan is the file's shape, no runtime seam exists for "never references a superglobal"
        $source = file_get_contents($file);
        $this->assertIsString($source);

        foreach (token_get_all($source) as $token) {
            if (!is_array($token)) {
                continue;
            }
            [$id, $text] = $token;
            $this->assertNotContains(
                $id,
                [T_GLOBAL, T_ECHO, T_PRINT, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_EVAL],
                'Forbidden token: ' . token_name($id)
            );
            if ($id === T_VARIABLE) {
                $this->assertNotContains($text, self::FORBIDDEN_VARIABLES);
            }
            if (in_array($id, [T_STRING, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true)) {
                $this->assertStringNotContainsStringIgnoringCase('mysqli', $text);
            }
        }
    }

    private function assertSignature(\ReflectionMethod $method, string $paramName, string $paramType): void
    {
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame($paramName, $params[0]->getName());
        $type = $params[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $type);
        $this->assertSame($paramType, $type->getName());
        $return = $method->getReturnType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $return);
        $this->assertSame('array', $return->getName());
    }
}
