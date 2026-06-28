<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans re-declaring the query-assertion helpers that
 * `Tests\WideUnit\WideUnitTestCase` already provides.
 *
 * Tests copy-paste a private `assertQueryExecuted()` / `assertQueryNotExecuted()`
 * instead of extending `WideUnitTestCase`, which supplies them (plus `setUp`,
 * global `$mysqli_db` injection, and the query-tracking helpers). The canonical
 * pattern is documented in `docs/DEVELOPMENT_GUIDE.md`; this rule enforces it.
 *
 * The sole legitimate definer — `WideUnitTestCase.php` itself — is exempt by
 * basename, mirroring `BanGlobalKeywordRule`'s `ALLOWED_FILES` idiom.
 *
 * @implements Rule<ClassMethod>
 */
final class BanRedeclaredMockDbQueryHelperRule implements Rule
{
    /** @var list<string> */
    private const BANNED_METHODS = [
        'assertQueryExecuted',
        'assertQueryNotExecuted',
    ];

    /** @var list<string> */
    private const ALLOWED_FILES = [
        'WideUnitTestCase.php',
    ];

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $name = $node->name->toString();

        if (!in_array($name, self::BANNED_METHODS, true)) {
            return [];
        }

        $file = $scope->getFile();
        foreach (self::ALLOWED_FILES as $allowedFile) {
            if (str_ends_with($file, DIRECTORY_SEPARATOR . $allowedFile)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Test re-declares `' . $name . '()`, which Tests\WideUnit\WideUnitTestCase already provides. '
                . 'Extend Tests\WideUnit\WideUnitTestCase instead of re-implementing its query-assertion helper.'
            )
                ->identifier('ibl.redeclaredMockDbQueryHelper')
                ->build(),
        ];
    }
}
