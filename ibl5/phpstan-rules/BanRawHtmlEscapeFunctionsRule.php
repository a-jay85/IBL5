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
final class BanRawHtmlEscapeFunctionsRule implements Rule
{
    private const BANNED_FUNCTIONS = ['htmlspecialchars', 'htmlentities'];

    private const ALLOWED_FILES = [
        // The canonical escaper — its e()/safeHtmlOutput() legitimately call htmlspecialchars.
        'HtmlSanitizer.php',
        // Two-step <br>-restore dumper; e()'s stripslashes would mangle debug dumps (PR #360).
        'DebugOutput.php',
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

        return [
            RuleErrorBuilder::message(
                $functionName . '() is banned. Use HtmlSanitizer::e() instead of raw '
                . 'htmlspecialchars/htmlentities (canonical flags + charset).'
            )
                ->identifier('ibl.rawHtmlEscape')
                ->build(),
        ];
    }
}
