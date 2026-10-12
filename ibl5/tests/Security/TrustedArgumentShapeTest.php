<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the exact residual set of HtmlSanitizer::trusted() arguments that
 * RequireTrustedAnnotationRule would reject, per view.
 *
 * The PHPStan baseline is a regenerable allowance (bin/regen-baselines rewrites
 * its counts to whatever the tree holds). This test is not: a new bare-variable
 * trusted($x) in these files fails CI even after a baseline regen, and a stale
 * entry (a site later converted) fails too.
 *
 * The CI baseline-drift gate (.github/workflows/tests.yml:505) only compares
 * counts, so it passes after a regen. This test is the independent check.
 */
final class TrustedArgumentShapeTest extends TestCase
{
    /** Non-rule-shaped trusted() arguments that are built HTML and intentionally stay. */
    private const ALLOWLIST = [
        'Search/SearchView.php' => ['$itemBody', '$time', '$date'],
        'Trading/TradeOfferFormView.php' => [
            "\$userPlayerRows['html']",
            "\$userPickRows['html']",
            "\$partnerPlayerRows['html']",
            "\$partnerPickRows['html']",
            '$thumbnail',
        ],
        'Trading/TradeReviewView.php' => ['$cell'],
        'Negotiation/NegotiationOfferView.php' => [
            "\$cardHtml ?? ''",
            'self::buildDemandDisplay($demands)',
            'self::renderEditableOfferFields($demands)',
            'self::renderMaxSalaryFields($maxYearOneSalary, $maxRaise, $demands)',
        ],
        'FreeAgency/FreeAgencyOfferView.php' => [
            'FreeAgencyFormView::renderPlayerRatings($player)',
            '$csrfHtml',
            '$birdRightsText',
        ],
        'FreeAgency/FreeAgencyTeamFreeAgentsSectionView.php' => ["\$resolved['thumbnail']"],
        'FreeAgency/FreeAgencyUnderContractSectionView.php' => ['$actionUrl'],
        'RookieOption/RookieOptionView.php' => ["\$cardHtml ?? ''"],
        'Waivers/WaiversView.php' => ['$optionHtml'],
        'YourAccount/LoginView.php' => ['nl2br(HtmlSanitizer::e($error))'],
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function viewProvider(): array
    {
        $sets = [];
        foreach (array_keys(self::ALLOWLIST) as $relPath) {
            $sets[$relPath] = [$relPath];
        }

        return $sets;
    }

    #[DataProvider('viewProvider')]
    public function testEveryTrustedArgumentIsRuleShapedOrAllowlisted(string $relPath): void
    {
        $args = self::nonRuleShapedTrustedArguments(self::readView($relPath));

        $this->assertSame(
            [],
            array_values(array_diff(array_unique($args), self::ALLOWLIST[$relPath])),
            "Unexpected trusted() argument in {$relPath}; escape at the echo with safeHtmlOutput() instead"
        );
    }

    #[DataProvider('viewProvider')]
    public function testAllowlistEntriesAreStillPresent(string $relPath): void
    {
        $args = self::nonRuleShapedTrustedArguments(self::readView($relPath));

        $this->assertSame(
            [],
            array_values(array_diff(self::ALLOWLIST[$relPath], $args)),
            "Stale allowlist entry for {$relPath}; remove it from ALLOWLIST"
        );
    }

    public function testClassifierRejectsBareVariableArgument(): void
    {
        $src = '<?php echo HtmlSanitizer::trusted($safeTitle); echo \Security\HtmlSanitizer::trusted($rows[\'html\']);';

        $this->assertSame(['$safeTitle', "\$rows['html']"], self::nonRuleShapedTrustedArguments($src));
    }

    /**
     * Accepted: literals, numeric casts, $this->method() and $this->prop->method()
     * calls. safeHtmlOutput( is ignored because it is not a `::trusted(` call.
     */
    public function testClassifierAcceptsRuleShapes(): void
    {
        $src = '<?php echo HtmlSanitizer::trusted(\'lit\'), HtmlSanitizer::trusted(42), HtmlSanitizer::trusted((int) $n),'
            . ' HtmlSanitizer::trusted((bool) $b), HtmlSanitizer::trusted($this->renderX($a, [1])),'
            . ' HtmlSanitizer::trusted($this->helper->render()), HtmlSanitizer::safeHtmlOutput($raw);';

        $this->assertSame([], self::nonRuleShapedTrustedArguments($src));
    }

    /**
     * Whitespace-normalised argument texts of every `X::trusted(...)` call that
     * the PHPStan rule would reject. Mirrors RequireTrustedAnnotationRule::isSafeArgument():
     * string and numeric literals, (int)/(float)/(bool) casts, $this->...() calls.
     *
     * @return list<string>
     */
    private static function nonRuleShapedTrustedArguments(string $source): array
    {
        $tokens = \PhpToken::tokenize($source);
        $significant = [];
        foreach ($tokens as $index => $token) {
            if (!$token->isIgnorable()) {
                $significant[] = $index;
            }
        }

        $rejected = [];
        $count = count($significant);
        for ($i = 1; $i < $count - 1; $i++) {
            $name = $tokens[$significant[$i]];
            if (!$name->is(T_STRING) || $name->text !== 'trusted') {
                continue;
            }
            if (!$tokens[$significant[$i - 1]]->is(T_DOUBLE_COLON) || $tokens[$significant[$i + 1]]->text !== '(') {
                continue;
            }

            $depth = 1;
            $text = '';
            for ($j = $significant[$i + 1] + 1; $j < count($tokens); $j++) {
                if ($tokens[$j]->text === '(') {
                    $depth++;
                } elseif ($tokens[$j]->text === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
                $text .= $tokens[$j]->text;
            }

            $argument = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
            if (preg_match('/^([\'"]|\d|\((int|float|bool)\)|\$this->)/', $argument) !== 1) {
                $rejected[] = $argument;
            }
        }

        return $rejected;
    }

    private static function readView(string $relPath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/classes/' . $relPath);
        self::assertNotFalse($source, "Cannot read {$relPath}");

        return $source;
    }
}
