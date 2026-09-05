<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FileNode>
 */
final class BanProcOpenUncheckedExitRule implements Rule
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
        $nodes = $node->getNodes();

        /** @var FuncCall[] $opens */
        $opens = $finder->find($nodes, static function (Node $n): bool {
            return $n instanceof FuncCall
                && $n->name instanceof Name
                && strtolower((string) $n->name) === 'proc_open';
        });

        if ($opens === []) {
            return [];
        }

        $errors = [];

        // Discarded proc_close(): a bare expression statement whose expr is a FuncCall named proc_close
        /** @var Node\Stmt\Expression[] $discarded */
        $discarded = $finder->find($nodes, static function (Node $n): bool {
            return $n instanceof Node\Stmt\Expression
                && $n->expr instanceof FuncCall
                && $n->expr->name instanceof Name
                && strtolower((string) $n->expr->name) === 'proc_close';
        });

        foreach ($discarded as $stmt) {
            /** @var FuncCall $call */
            $call = $stmt->expr;
            $errors[] = RuleErrorBuilder::message(
                'proc_close() return value is discarded. Capture it and check the exit status — an unchecked subprocess failure is silent.'
            )
                ->identifier('ibl.procOpenExitUnchecked')
                ->line($call->getStartLine())
                ->build();
        }

        // proc_open() with no proc_close() at all
        /** @var FuncCall[] $closes */
        $closes = $finder->find($nodes, static function (Node $n): bool {
            return $n instanceof FuncCall
                && $n->name instanceof Name
                && strtolower((string) $n->name) === 'proc_close';
        });

        if ($closes === []) {
            $errors[] = RuleErrorBuilder::message(
                'proc_open() is called but proc_close() never is. Close the process and check its exit status.'
            )
                ->identifier('ibl.procOpenExitUnchecked')
                ->line($opens[0]->getStartLine())
                ->build();
        }

        return $errors;
    }
}
