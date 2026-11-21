<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Flags a call made outside a class to one of its public instance methods
 * when none of the class's Contracts interfaces declares that method.
 * Enforces the ADR-0001 interface/contract split (IBL5-backlog#1629).
 *
 * @implements Rule<MethodCall>
 */
final class RequireInterfaceDeclaresPublicMethodsRule implements Rule
{
    /**
     * @param list<string> $receiverSuffixes Empty = every receiver class.
     */
    public function __construct(private readonly array $receiverSuffixes = [])
    {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }
        $methodName = $node->name->toString();
        if (str_starts_with($methodName, '__')) {
            return [];
        }

        $file = $scope->getFile();
        $sep = DIRECTORY_SEPARATOR;
        if (!str_contains($file, $sep . 'classes' . $sep) && !str_contains($file, $sep . 'modules' . $sep)) {
            return [];
        }

        $errors = [];
        foreach ($scope->getType($node->var)->getObjectClassReflections() as $receiver) {
            $error = $this->checkReceiver($receiver, $methodName, $scope);
            if ($error !== null) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    private function checkReceiver(ClassReflection $receiver, string $methodName, Scope $scope): ?IdentifierRuleError
    {
        if ($receiver->isInterface() || !$receiver->hasNativeMethod($methodName)) {
            return null;
        }
        $method = $receiver->getNativeMethod($methodName);
        if (!$method->isPublic() || $method->isStatic()) {
            return null;
        }

        $declaring = $method->getDeclaringClass();
        $declaringName = $declaring->getName();
        $shortName = substr((string) strrchr('\\' . $declaringName, '\\'), 1);
        if (str_ends_with($shortName, 'Factory') || !$this->matchesSuffix($shortName)) {
            return null;
        }

        $caller = $scope->getClassReflection();
        if ($caller !== null && $caller->is($declaringName)) {
            return null;
        }

        $contracts = [];
        foreach ($declaring->getInterfaces() as $interface) {
            if (str_contains('\\' . $interface->getName(), '\\Contracts\\')) {
                if ($interface->hasMethod($methodName)) {
                    return null;
                }
                $contracts[] = $interface->getName();
            }
        }
        if ($contracts === []) {
            return null;
        }
        sort($contracts);

        return RuleErrorBuilder::message(sprintf(
            'Method %s::%s() is called outside its class, but no Contracts interface it implements declares it (%s). '
            . 'Declare it in the interface, or make it non-public if no outside caller needs it.',
            $declaringName,
            $methodName,
            implode(', ', $contracts),
        ))
            ->identifier('ibl.interfaceMissingPublicMethod')
            ->build();
    }

    private function matchesSuffix(string $shortName): bool
    {
        if ($this->receiverSuffixes === []) {
            return true;
        }
        foreach ($this->receiverSuffixes as $suffix) {
            if (str_ends_with($shortName, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
