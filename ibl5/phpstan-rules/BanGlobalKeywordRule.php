<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Global_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans the `global` keyword inside classes/ except in Bootstrap and legacy-compat files.
 *
 * The `global` keyword creates invisible coupling between a method and the
 * global scope. Inject collaborators via constructor instead.
 *
 * @implements Rule<Global_>
 */
final class BanGlobalKeywordRule implements Rule
{
    /** @var list<string> */
    private const ALLOWED_FILES = [
        'LegacyFunctions.php',
        'ConfigBootstrap.php',
        'NukeCompat.php',
        'PageLayout.php',
        'PdoConnection.php',
        'DebugOutput.php',
    ];

    public function getNodeType(): string
    {
        return Global_::class;
    }

    /**
     * @param Global_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        foreach (self::ALLOWED_FILES as $allowedFile) {
            if (str_ends_with($file, DIRECTORY_SEPARATOR . $allowedFile)) {
                return [];
            }
        }

        $errors = [];
        foreach ($node->vars as $var) {
            if ($var instanceof Node\Expr\Variable && is_string($var->name)) {
                $errors[] = RuleErrorBuilder::message(
                    '`global $' . $var->name . ';` is banned outside Bootstrap and legacy-compat files. '
                    . 'Inject collaborators via constructor.'
                )
                    ->identifier('ibl.globalKeyword')
                    ->build();
            }
        }

        return $errors;
    }
}
