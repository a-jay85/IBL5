<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans deprecated/presentational HTML tags (`<b>`, `<i>`, `<center>`, `<font>`,
 * `<u>`) in PHP string literals under classes/. Use semantic alternatives
 * (`<strong>`, `<em>`, CSS `text-align: center`, CSS font properties,
 * `<em>` or CSS `text-decoration: underline`).
 *
 * Regex uses `<tag[\s>]` to avoid matching longer tags that share a prefix
 * (e.g. `<br>`, `<body>`, `<img>`, `<iframe>`, `<ul>`).
 *
 * @implements Rule<String_>
 */
final class BanDeprecatedHtmlTagsRule implements Rule
{
    /**
     * Deprecated tag → suggested replacement (informational, included in error).
     *
     * @var array<string, string>
     */
    private const DEPRECATED_TAGS = [
        'b' => '<strong>',
        'i' => '<em>',
        'center' => 'CSS `text-align: center`',
        'font' => 'CSS font properties',
        'u' => '<em> or CSS `text-decoration: underline`',
    ];

    public function getNodeType(): string
    {
        return String_::class;
    }

    /**
     * @param String_ $node
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Only enforce in classes/
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $value = $node->value;
        $errors = [];

        foreach (self::DEPRECATED_TAGS as $tag => $replacement) {
            $pattern = '/<' . $tag . '[\s>]/i';
            if (preg_match($pattern, $value) === 1) {
                $errors[] = RuleErrorBuilder::message(
                    'Deprecated HTML tag `<' . $tag . '>` found in string literal. '
                    . 'Use ' . $replacement . ' instead.'
                )
                    ->identifier('ibl.deprecatedHtmlTag')
                    ->build();
            }
        }

        return $errors;
    }
}
