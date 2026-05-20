<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final class BanAddslashesRule implements Rule
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

        if ($node->name->toLowerString() !== 'addslashes') {
            return [];
        }

        $file = $scope->getFile();
        if (str_contains($file, 'LegacyFunctions.php') && str_contains($file, 'Bootstrap')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'addslashes() is banned. Use prepared statements for database escaping '
                . 'or json_encode()/htmlspecialchars() for output escaping.'
            )
                ->identifier('ibl.bannedAddslashes')
                ->build(),
        ];
    }
}
