<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans inline `<style>` blocks and `style="..."` attributes in PHP string literals
 * under classes/. All CSS must live in `ibl5/design/components/`.
 *
 * Exception: `style="--..."` CSS custom-property declarations are allowed because
 * they are the sanctioned way to pass dynamic per-element theme values into the
 * component CSS layer.
 *
 * @implements Rule<String_>
 */
final class BanInlineCssRule implements Rule
{
    public function getNodeType(): string
    {
        return String_::class;
    }

    /**
     * @param String_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Only enforce in classes/
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $value = $node->value;

        // Detect <style> opening tag
        if (preg_match('/<style[\s>]/i', $value) === 1) {
            return [
                RuleErrorBuilder::message(
                    'Inline `<style>` blocks are banned in PHP. Move CSS to '
                    . 'ibl5/design/components/ and reference the stylesheet instead.'
                )
                    ->identifier('ibl.inlineCss')
                    ->build(),
            ];
        }

        // Detect style="..." or style='...' attribute (but allow style="--..." and
        // style='--...' CSS custom properties). Matches at start of string, after
        // whitespace, or after `>` so we catch style= without a preceding space
        // (e.g. an attributes-only string literal that starts with `style=`).
        if (preg_match('/(?:^|[\s>])style=["\'](?!--)/i', $value) === 1) {
            return [
                RuleErrorBuilder::message(
                    'Inline `style="..."` attributes are banned in PHP. Move CSS to '
                    . 'ibl5/design/components/. Exception: `style="--foo: ..."` CSS '
                    . 'custom properties are allowed.'
                )
                    ->identifier('ibl.inlineCss')
                    ->build(),
            ];
        }

        return [];
    }
}
