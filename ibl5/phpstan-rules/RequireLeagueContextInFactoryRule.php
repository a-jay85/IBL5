<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Inside a `*Factory.php` method that already holds a LeagueContext, every `new X(...)`
 * whose effective constructor takes a `League\LeagueContext` must receive one.
 *
 * A method "holds" a LeagueContext when it has a typed parameter, a `$leagueContext*`
 * variable, or a `->leagueContext()` call anywhere in its body.
 *
 * @implements Rule<ClassMethod>
 */
final class RequireLeagueContextInFactoryRule implements Rule
{
    private const LEAGUE_CONTEXT_CLASS = 'League\LeagueContext';

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

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
        if (!str_ends_with($scope->getFile(), 'Factory.php') || $node->stmts === null) {
            return [];
        }

        if (!$this->hasLeagueContextInScope($node, $scope)) {
            return [];
        }

        $errors = [];
        foreach ((new NodeFinder())->findInstanceOf($node->stmts, New_::class) as $new) {
            if (!$new->class instanceof Name) {
                continue;
            }

            $className = $scope->resolveName($new->class);
            if (!$this->reflectionProvider->hasClass($className)) {
                continue;
            }

            $class = $this->reflectionProvider->getClass($className);
            if (!$class->hasConstructor()) {
                continue;
            }

            $param = $this->findLeagueContextParam($class->getConstructor());
            if ($param === null) {
                continue;
            }

            [$index, $paramName] = $param;
            if ($this->argumentSupplied($new->getArgs(), $index, $paramName)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(sprintf(
                '%s accepts a LeagueContext, but this factory method builds it without one. '
                . 'Pass the LeagueContext in scope as $%s.',
                $class->getName(),
                $paramName
            ))
                ->identifier('ibl.factoryOmitsLeagueContext')
                ->line($new->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function hasLeagueContextInScope(ClassMethod $node, Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();
        $methodName = $node->name->toString();
        if ($classReflection !== null && $classReflection->hasNativeMethod($methodName)) {
            foreach ($classReflection->getNativeMethod($methodName)->getVariants() as $variant) {
                foreach ($variant->getParameters() as $parameter) {
                    if ($this->isLeagueContextType($parameter->getType())) {
                        return true;
                    }
                }
            }
        }

        $finder = new NodeFinder();

        $variable = $finder->findFirst(
            $node->stmts ?? [],
            static fn (Node $n): bool => $n instanceof Variable
                && is_string($n->name)
                && stripos($n->name, 'leagueContext') !== false
        );
        if ($variable !== null) {
            return true;
        }

        $call = $finder->findFirst(
            $node->stmts ?? [],
            static fn (Node $n): bool => ($n instanceof MethodCall
                    || $n instanceof NullsafeMethodCall
                    || $n instanceof StaticCall)
                && $n->name instanceof Identifier
                && strcasecmp($n->name->toString(), 'leagueContext') === 0
        );

        return $call !== null;
    }

    /**
     * @return array{int, string}|null Position and name of the LeagueContext constructor parameter
     */
    private function findLeagueContextParam(ExtendedMethodReflection $ctor): ?array
    {
        foreach ($ctor->getVariants() as $variant) {
            foreach ($variant->getParameters() as $position => $parameter) {
                if ($this->isLeagueContextType($parameter->getType())) {
                    return [$position, $parameter->getName()];
                }
            }
        }

        return null;
    }

    /**
     * `?LeagueContext` is a union with null, whose getObjectClassNames() is empty.
     */
    private function isLeagueContextType(Type $type): bool
    {
        return in_array(
            self::LEAGUE_CONTEXT_CLASS,
            TypeCombinator::removeNull($type)->getObjectClassNames(),
            true
        );
    }

    /**
     * @param array<Arg> $args
     */
    private function argumentSupplied(array $args, int $index, string $paramName): bool
    {
        foreach ($args as $position => $arg) {
            if ($arg->unpack) {
                return true;
            }
            if ($arg->name !== null) {
                if ($arg->name->toString() === $paramName) {
                    return true;
                }
                continue;
            }
            if ($position === $index) {
                return true;
            }
        }

        return false;
    }
}
