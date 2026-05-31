<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans direct \mysqli::query() calls outside the DB-access boundary.
 *
 * Raw query() bypasses prepared-statement parameterization. DB access must go
 * through a repository helper (BaseMysqliRepository::execute()/fetchOne()/fetchAll())
 * which prepares and binds. The boundary classes that wrap \mysqli directly
 * (BaseMysqliRepository, Database\MySQL) are allowlisted.
 *
 * @implements Rule<MethodCall>
 */
final class BanDirectMysqliQueryRule implements Rule
{
    /**
     * Path tails of the DB-access-boundary classes permitted to call \mysqli::query() directly.
     *
     * @var list<string>
     */
    private const ALLOWED_FILE_TAILS = [
        DIRECTORY_SEPARATOR . 'BaseMysqliRepository.php',
        DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'MySQL.php',
    ];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->name !== 'query') {
            return [];
        }

        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        foreach (self::ALLOWED_FILE_TAILS as $tail) {
            if (str_ends_with($file, $tail)) {
                return [];
            }
        }

        $receiverType = $scope->getType($node->var);
        if (!in_array('mysqli', $receiverType->getObjectClassNames(), true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Direct \mysqli::query() is banned outside the DB-access boundary. '
                . 'Use a BaseMysqliRepository helper (execute()/fetchOne()/fetchAll()) instead — '
                . 'raw query() bypasses prepared-statement parameterization.'
            )
                ->identifier('ibl.directMysqliQuery')
                ->build(),
        ];
    }
}
