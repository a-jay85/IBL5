<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\DeclareDeclare;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces declare(strict_types=1) at the top of every PHP file in classes/.
 *
 * @implements Rule<FileNode>
 */
final class RequireStrictTypesRule implements Rule
{
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Only enforce in classes/ directory
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        foreach ($node->getNodes() as $stmt) {
            if ($stmt instanceof Declare_) {
                foreach ($stmt->declares as $declare) {
                    if (
                        $declare instanceof DeclareDeclare
                        && $declare->key->name === 'strict_types'
                    ) {
                        return [];
                    }
                }
            }
        }

        return [
            RuleErrorBuilder::message(
                'Missing declare(strict_types=1) at the top of the file.'
            )
                ->identifier('ibl.missingStrictTypes')
                ->build(),
        ];
    }
}
