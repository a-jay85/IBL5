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
final class BanCastFunctionsRule implements Rule
{
    private const BANNED_FUNCTIONS = ['intval', 'floatval', 'strval', 'boolval'];

    private const ALLOWED_FILES = [
        'LegacyFunctions.php',
        'ConfigBootstrap.php',
        'StatsSanitizer.php',
    ];

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

        $functionName = $node->name->toLowerString();

        if (!in_array($functionName, self::BANNED_FUNCTIONS, true)) {
            return [];
        }

        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        foreach (self::ALLOWED_FILES as $allowedFile) {
            if (str_ends_with($file, DIRECTORY_SEPARATOR . $allowedFile)) {
                return [];
            }
        }

        $castMap = [
            'intval' => '(int)',
            'floatval' => '(float)',
            'strval' => '(string)',
            'boolval' => '(bool)',
        ];

        return [
            RuleErrorBuilder::message(
                $functionName . '() is banned. Use ' . $castMap[$functionName] . ' cast instead. '
                . 'PHPStan can narrow types through casts but not through these functions, '
                . 'and intval(\'08\') silently returns 0 (octal radix surprise).'
            )
                ->identifier('ibl.castFunction')
                ->build(),
        ];
    }
}
