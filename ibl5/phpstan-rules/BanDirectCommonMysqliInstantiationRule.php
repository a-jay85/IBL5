<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans `new CommonMysqliRepository(...)` outside composition roots and tests.
 * Use constructor injection via CommonMysqliRepositoryInterface instead.
 *
 * @implements Rule<New_>
 */
final class BanDirectCommonMysqliInstantiationRule implements Rule
{
    private const TARGET_CLASS = 'Services\CommonMysqliRepository';

    /** @var list<string> Patterns that are allowed to instantiate directly */
    private const ALLOWED_PATTERNS = [
        '/modules/',
        '/tests/',
        '/scripts/',
        'mainfile.php',
        'api.php',
    ];

    public function getNodeType(): string
    {
        return New_::class;
    }

    /**
     * @param New_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Name) {
            return [];
        }

        $className = $node->class->toString();

        if ($className !== self::TARGET_CLASS && $className !== '\\' . self::TARGET_CLASS) {
            return [];
        }

        $file = $scope->getFile();
        foreach (self::ALLOWED_PATTERNS as $pattern) {
            if (str_contains($file, $pattern)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Direct instantiation of CommonMysqliRepository is banned in class files. '
                . 'Inject CommonMysqliRepositoryInterface via the constructor instead.'
            )
                ->identifier('ibl.directCommonMysqliInstantiation')
                ->build(),
        ];
    }
}
