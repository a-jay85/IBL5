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
 * Bans direct PHP-Nuke global function calls outside of NukeCompat and LegacyFunctions.
 * Use Utilities\NukeCompat adapter instead for injectable/mockable wrappers.
 *
 * @implements Rule<FuncCall>
 */
final class BanDirectNukeGlobalsRule implements Rule
{
    /** @var list<string> */
    private const BANNED_FUNCTIONS = [
        'is_user',
        'cookiedecode',
        'is_admin',
        'formattimestamp',
        'getusrinfo',
        'loginbox',
        'get_theme',
        'get_lang',
    ];

    /** @var list<string> */
    private const ALLOWED_FILES = [
        'NukeCompat.php',
        'LegacyFunctions.php',
        'PageLayout.php',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
     * @return list<\PHPStan\Rules\RuleError>
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

        // Allow calls inside NukeCompat (the approved wrapper) and LegacyFunctions (defines the globals)
        $file = $scope->getFile();
        foreach (self::ALLOWED_FILES as $allowedFile) {
            if (str_contains($file, $allowedFile)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Direct ' . $node->name->toString() . '() calls are banned. '
                . 'Use Utilities\NukeCompat adapter instead (injectable, mockable).'
            )
                ->identifier('ibl.bannedNukeGlobal')
                ->build(),
        ];
    }
}
