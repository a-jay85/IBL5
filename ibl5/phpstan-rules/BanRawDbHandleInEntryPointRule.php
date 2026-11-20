<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\NullableType;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces the composition-root rule (ADR-0171): module entry points get role-based
 * services, never the raw mysqli handle.
 *
 * Two bans:
 *  1. A public `db()` method returning mysqli in a class under classes/Module/Factories/.
 *  2. `->db()->prepare(...)` / `->db()->query(...)` in modules/<Module>/index.php.
 *
 * @implements Rule<Node>
 */
final class BanRawDbHandleInEntryPointRule implements Rule
{
    private const FACTORY_DIR_SEGMENT = '/classes/Module/Factories/';

    private const BANNED_HANDLE_METHODS = ['prepare', 'query'];

    public const FACTORY_MESSAGE = 'A Module Factory must not expose the raw mysqli handle via a public db() method. '
        . 'Entry points get role-based services only (ADR-0171); build the repository/service inside the Factory.';

    public const ENTRY_POINT_MESSAGE = 'Module entry points must not run SQL through ->db()->%s(). '
        . 'Entry points get role-based services only (ADR-0171); move the query into a Repository.';

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node instanceof ClassMethod) {
            return $this->checkFactoryMethod($node, $scope);
        }

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            return $this->checkEntryPointCall($node, $scope);
        }

        return [];
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkFactoryMethod(ClassMethod $node, Scope $scope): array
    {
        if (strtolower($node->name->toString()) !== 'db' || !$node->isPublic()) {
            return [];
        }

        if (!str_contains(self::normalize($scope->getFile()), self::FACTORY_DIR_SEGMENT)) {
            return [];
        }

        $returnType = $node->returnType;
        if ($returnType instanceof NullableType) {
            $returnType = $returnType->type;
        }

        if (!$returnType instanceof Name && !$returnType instanceof Identifier) {
            return [];
        }

        if (strtolower(ltrim($returnType->toString(), '\\')) !== 'mysqli') {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::FACTORY_MESSAGE)
                ->identifier('ibl.rawDbHandleInFactory')
                ->build(),
        ];
    }

    /**
     * @param MethodCall|NullsafeMethodCall $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkEntryPointCall(MethodCall|NullsafeMethodCall $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $method = strtolower($node->name->toString());
        if (!in_array($method, self::BANNED_HANDLE_METHODS, true)) {
            return [];
        }

        $receiver = $node->var;
        if (!($receiver instanceof MethodCall || $receiver instanceof NullsafeMethodCall)
            || !$receiver->name instanceof Identifier
            || strtolower($receiver->name->toString()) !== 'db'
        ) {
            return [];
        }

        if (!self::isModuleEntryPoint($scope->getFile())) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(self::ENTRY_POINT_MESSAGE, $method))
                ->identifier('ibl.rawDbHandleInEntryPoint')
                ->build(),
        ];
    }

    private static function isModuleEntryPoint(string $file): bool
    {
        $file = self::normalize($file);

        return (bool) preg_match('#/modules/[^/]+/index\.php$#', $file);
    }

    private static function normalize(string $file): string
    {
        return str_replace('\\', '/', $file);
    }
}
