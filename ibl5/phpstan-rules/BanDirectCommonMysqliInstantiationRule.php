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
 * Bans `new TeamIdentityRepository(...)`, `new PlayerLookupRepository(...)`, and
 * `new SalaryCapRepository(...)` outside composition roots and tests.
 * Use constructor injection via the narrow interfaces instead.
 *
 * @implements Rule<New_>
 */
final class BanDirectCommonMysqliInstantiationRule implements Rule
{
    /** @var list<string> */
    private const TARGET_CLASSES = [
        'Repositories\TeamIdentityRepository',
        'Repositories\PlayerLookupRepository',
        'Repositories\SalaryCapRepository',
    ];

    /** @var list<string> Patterns that are allowed to instantiate directly */
    private const ALLOWED_PATTERNS = [
        '/modules/',
        '/tests/',
        '/scripts/',
        '/Bootstrap/',
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
        $normalizedName = ltrim($className, '\\');

        if (!in_array($normalizedName, self::TARGET_CLASSES, true)) {
            return [];
        }

        $file = $scope->getFile();
        foreach (self::ALLOWED_PATTERNS as $pattern) {
            if (str_contains($file, $pattern)) {
                return [];
            }
        }

        $lastSlash = strrpos($normalizedName, '\\');
        $shortName = $lastSlash !== false ? substr($normalizedName, $lastSlash + 1) : $normalizedName;

        return [
            RuleErrorBuilder::message(
                "Direct instantiation of $shortName is banned in class files. "
                . 'Inject the corresponding interface via the constructor instead.'
            )
                ->identifier('ibl.directRepoInstantiation')
                ->build(),
        ];
    }
}
