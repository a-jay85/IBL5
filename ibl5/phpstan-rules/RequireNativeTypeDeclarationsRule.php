<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Requires a native type on every param, return, and property, and bans the bare `object` type.
 *
 * A PHPDoc @param, @return, or @var never satisfies the rule. Closures and arrow
 * functions are exempt: they carry no API surface and PHPStan infers their types.
 * Constructors and destructors need no return type (PHP forbids one).
 *
 * @implements Rule<FileNode>
 */
final class RequireNativeTypeDeclarationsRule implements Rule
{
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $finder = new NodeFinder();
        $targets = $finder->find(
            $node->getNodes(),
            static fn (Node $n): bool => $n instanceof ClassLike || $n instanceof Function_,
        );

        $errors = [];
        foreach ($targets as $target) {
            if ($target instanceof Function_) {
                $label = 'Function ' . $target->name->toString() . '()';
                $this->checkFunctionLike($target, $label, true, $errors);
                continue;
            }

            if (!$target instanceof ClassLike) {
                continue;
            }

            $className = $target->name !== null ? $target->name->toString() : 'class@anonymous';

            foreach ($target->getMethods() as $method) {
                $label = 'Method ' . $className . '::' . $method->name->toString() . '()';
                $needsReturn = !in_array($method->name->toLowerString(), ['__construct', '__destruct'], true);
                $this->checkFunctionLike($method, $label, $needsReturn, $errors);
            }

            foreach ($target->getProperties() as $property) {
                foreach ($property->props as $item) {
                    $propLabel = 'Property ' . $className . '::$' . $item->name->toString();
                    if ($property->type === null) {
                        $errors[] = RuleErrorBuilder::message(
                            $propLabel . ' has no native type declaration. A PHPDoc @var does not count; '
                            . 'declare a native type (\Closure for a stored callable).'
                        )
                            ->identifier('ibl.missingNativePropertyType')
                            ->line($property->getStartLine())
                            ->build();
                    } elseif ($this->containsBareObject($property->type)) {
                        $errors[] = $this->bareObjectError($propLabel, $property->getStartLine());
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param ClassMethod|Function_ $function
     * @param list<\PHPStan\Rules\IdentifierRuleError> $errors
     */
    private function checkFunctionLike(Node\FunctionLike $function, string $label, bool $needsReturn, array &$errors): void
    {
        foreach ($function->getParams() as $param) {
            $name = $param->var instanceof Node\Expr\Variable && is_string($param->var->name)
                ? $param->var->name
                : '';
            if ($param->type === null) {
                $errors[] = RuleErrorBuilder::message(
                    'Parameter $' . $name . ' of ' . $label . ' has no native type declaration. '
                    . 'A PHPDoc @param does not count; declare a native type (mixed when nothing narrower fits).'
                )
                    ->identifier('ibl.missingNativeParamType')
                    ->line($param->getStartLine())
                    ->build();
            } elseif ($this->containsBareObject($param->type)) {
                $errors[] = $this->bareObjectError('Parameter $' . $name . ' of ' . $label, $param->getStartLine());
            }
        }

        $returnType = $function->getReturnType();
        if ($returnType === null) {
            if ($needsReturn) {
                $errors[] = RuleErrorBuilder::message(
                    $label . ' has no native return type declaration. '
                    . 'A PHPDoc @return does not count; declare a native type (void, mixed, or narrower).'
                )
                    ->identifier('ibl.missingNativeReturnType')
                    ->line($function->getStartLine())
                    ->build();
            }
        } elseif ($this->containsBareObject($returnType)) {
            $errors[] = $this->bareObjectError('Return type of ' . $label, $function->getStartLine());
        }
    }

    private function bareObjectError(string $subject, int $line): \PHPStan\Rules\IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            $subject . ' uses the bare object type. Declare the class, interface, or union it accepts.'
        )
            ->identifier('ibl.bareObjectType')
            ->line($line)
            ->build();
    }

    private function containsBareObject(?Node $type): bool
    {
        if ($type instanceof Node\Identifier) {
            return $type->toLowerString() === 'object';
        }

        if ($type instanceof Node\NullableType) {
            return $this->containsBareObject($type->type);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            foreach ($type->types as $member) {
                if ($this->containsBareObject($member)) {
                    return true;
                }
            }
        }

        return false;
    }
}
