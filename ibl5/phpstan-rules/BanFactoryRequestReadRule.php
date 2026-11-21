<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;

/**
 * Bans module factories from reading request fields or taking actor identity (ADR-0171).
 *
 * Applies only to files under classes/Module/Factories/. A factory may hand the
 * Http\HttpRequest object to a constructor or return it, but it may not call a
 * field reader on it, and no factory method parameter may carry an identity-shaped
 * name. The module index.php resolves both at the boundary.
 *
 * @implements Rule<Node>
 */
final class BanFactoryRequestReadRule implements Rule
{
    /** Byte-identical to Tests\Security\ModuleFactorySecuritySurfaceTest::IDENTITY_PARAMETER_PATTERN; a rule test asserts it. */
    public const IDENTITY_PARAMETER_PATTERN = '/(loggedin|currentuser|username|userteam|ownteam|highlight\w*team|session|cookie|actor)/i';

    private const FACTORY_PATH_FRAGMENT = '/classes/Module/Factories/';

    private const REQUEST_CLASS = 'Http\\HttpRequest';

    private const READ_METHODS = ['get', 'post', 'request', 'server', 'allget', 'allpost'];

    public function getNodeType(): string
    {
        return Node::class;
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!str_contains(str_replace('\\', '/', $scope->getFile()), self::FACTORY_PATH_FRAGMENT)) {
            return [];
        }
        if ($node instanceof MethodCall) {
            return $this->checkRequestRead($node, $scope);
        }
        if ($node instanceof ClassMethod) {
            return $this->checkIdentityParameters($node);
        }

        return [];
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkRequestRead(MethodCall $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }
        $method = $node->name->toString();
        if (!in_array(strtolower($method), self::READ_METHODS, true)) {
            return [];
        }
        $receiverType = $scope->getType($node->var);
        $requestType = new ObjectType(self::REQUEST_CLASS);
        if (!$requestType->isSuperTypeOf($receiverType)->yes()) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Module factory reads a request field via Http\HttpRequest::%s(). Read it in the module index.php and pass the value in (ADR-0171).',
                $method,
            ))
                ->identifier('ibl.factoryRequestRead')
                ->build(),
        ];
    }

    /**
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function checkIdentityParameters(ClassMethod $node): array
    {
        $errors = [];
        foreach ($node->params as $param) {
            if (!$param->var instanceof Variable) {
                continue;
            }
            $name = $param->var->name;
            if (is_string($name) && preg_match(self::IDENTITY_PARAMETER_PATTERN, $name) === 1) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Module factory parameter $%s looks like actor identity. Resolve identity in index.php and pass it to the controller or service (ADR-0171).',
                    $name,
                ))
                    ->identifier('ibl.factoryIdentityParameter')
                    ->line($param->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }
}
