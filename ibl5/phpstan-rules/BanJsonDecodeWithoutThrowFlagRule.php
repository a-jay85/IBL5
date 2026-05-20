<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BitwiseOr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final class BanJsonDecodeWithoutThrowFlagRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'json_decode') {
            return [];
        }

        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 4) {
            return [$this->buildError()];
        }

        $flagsArg = $args[3]->value;
        if (!$this->mentionsThrowOnError($flagsArg)) {
            return [$this->buildError()];
        }

        return [];
    }

    private function mentionsThrowOnError(Node $expr): bool
    {
        if ($expr instanceof ConstFetch && $expr->name->toString() === 'JSON_THROW_ON_ERROR') {
            return true;
        }
        if ($expr instanceof BitwiseOr) {
            return $this->mentionsThrowOnError($expr->left) || $this->mentionsThrowOnError($expr->right);
        }

        return false;
    }

    private function buildError(): \PHPStan\Rules\IdentifierRuleError
    {
        return RuleErrorBuilder::message(
            'json_decode() must include JSON_THROW_ON_ERROR in the $flags argument (4th positional). '
            . 'Malformed JSON returns null silently, causing downstream failures far from the parse site. '
            . 'Use: json_decode($x, true, 512, JSON_THROW_ON_ERROR).'
        )
            ->identifier('ibl.jsonDecodeWithoutThrow')
            ->build();
    }
}
