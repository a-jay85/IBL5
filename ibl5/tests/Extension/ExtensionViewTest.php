<?php

declare(strict_types=1);

namespace Tests\Extension;

use Extension\ExtensionView;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Security\HtmlSanitizer;
use Team\TeamView;

/**
 * Tests for ExtensionView, the post-redirect extension result banner moved out of TeamView.
 *
 * @phpstan-import-type TeamPageData from \Team\Contracts\TeamServiceInterface
 */
class ExtensionViewTest extends TestCase
{
    private const ERROR_SUFFIX = ' Your extension attempt was not legal and will not be recorded.';
    private const ACCEPTED_NOTE = '<br>Note from the commissioner\'s office: You have used up your successful extension for this season and may not make any more extension attempts.';
    private const REJECTED_NOTE = '<br>Note from the commissioner\'s office: You will be able to make another attempt next sim as you have not yet used up your successful extension for this season.';

    private ExtensionView $view;

    protected function setUp(): void
    {
        $this->view = new ExtensionView();
    }

    public function testNullResultRendersEmptyString(): void
    {
        self::assertSame('', $this->view->renderResultBanner(null, 'anything'));
    }

    public function testErrorResultRendersErrorAlert(): void
    {
        self::assertSame(
            '<div class="ibl-alert ibl-alert--error">Over the cap.' . self::ERROR_SUFFIX . '</div>',
            $this->view->renderResultBanner('extension_error', 'Over the cap.')
        );
    }

    public function testAcceptedResultRendersSuccessAlert(): void
    {
        $output = $this->view->renderResultBanner('extension_accepted', 'Deal done.');

        self::assertSame(
            '<div class="ibl-alert ibl-alert--success"><strong>Player response:</strong> Deal done.' . self::ACCEPTED_NOTE . '</div>',
            $output
        );
        self::assertStringContainsString("commissioner's office", $output);
    }

    public function testRejectedResultRendersInfoAlert(): void
    {
        self::assertSame(
            '<div class="ibl-alert ibl-alert--info"><strong>Player response:</strong> Not enough.' . self::REJECTED_NOTE . '</div>',
            $this->view->renderResultBanner('extension_rejected', 'Not enough.')
        );
    }

    #[DataProvider('unknownResultProvider')]
    public function testUnknownResultRendersEmptyString(string $result): void
    {
        self::assertSame('', $this->view->renderResultBanner($result, 'message'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unknownResultProvider(): array
    {
        return [
            'forbidden' => ['extension_forbidden'],
            'empty string' => [''],
            'wrong case' => ['EXTENSION_ACCEPTED'],
            'short name' => ['accepted'],
        ];
    }

    public function testMsgIsHtmlEscaped(): void
    {
        $payload = '<script>alert(1)</script>';
        $output = $this->view->renderResultBanner('extension_accepted', $payload);

        self::assertStringNotContainsString('<script>', $output);
        self::assertStringContainsString(HtmlSanitizer::e($payload), $output);

        self::assertSame(
            '<div class="ibl-alert ibl-alert--error">' . self::ERROR_SUFFIX . '</div>',
            $this->view->renderResultBanner('extension_error', null)
        );
    }

    public function testOutputMatchesTeamViewRenderedBanner(): void
    {
        $msg = 'O\'Brien & <b>';
        $teamView = new TeamView();

        foreach (['extension_error', 'extension_accepted', 'extension_rejected'] as $result) {
            $expected = $this->view->renderResultBanner($result, $msg);
            self::assertNotSame('', $expected);
            self::assertStringContainsString($expected, $teamView->render($this->createPageData($result, $msg)));
        }

        self::assertStringNotContainsString('ibl-alert', $teamView->render($this->createPageData(null, $msg)));
    }

    public function testExtensionViewIsStateFreeAndEscapesAllDynamicOutput(): void
    {
        $rc = new \ReflectionClass(ExtensionView::class);

        self::assertNull($rc->getConstructor());
        self::assertSame([], $rc->getProperties());

        $publicMethods = array_values(array_map(
            static fn (\ReflectionMethod $m): string => $m->getName(),
            array_filter(
                $rc->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn (\ReflectionMethod $m): bool => $m->getDeclaringClass()->getName() === ExtensionView::class
            )
        ));
        self::assertSame(['renderResultBanner'], $publicMethods);

        $src = file_get_contents((string) $rc->getFileName());
        self::assertIsString($src);

        foreach (['mysqli', '$_SESSION', '$_GET', '$_POST', '$_REQUEST', '$loggedInTeamID', 'ob_start', 'new '] as $token) {
            self::assertFalse(str_contains($src, $token), "ExtensionView must not contain `$token`");
        }

        $body = (string) preg_replace('/^.*function renderResultBanner\(.*$/m', '', $src);
        $msgRefs = preg_match_all('/\$msg\b/', $body);
        $escapedRefs = preg_match_all('/HtmlSanitizer::e\(\$msg/', $body);

        self::assertGreaterThanOrEqual(1, $msgRefs);
        self::assertSame($msgRefs, $escapedRefs, 'every $msg reference must sit inside HtmlSanitizer::e(');
    }

    /**
     * @return TeamPageData
     */
    private function createPageData(?string $result, ?string $msg): array
    {
        $team = self::createStub(\Team\Team::class);
        $team->name = 'Celtics';
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->discord_id = null;

        return [
            'teamid' => 1,
            'team' => $team,
            'imagesPath' => 'images/',
            'yr' => null,
            'display' => 'ratings',
            'insertyear' => '',
            'isActualTeam' => true,
            'tableOutput' => '<table><tbody><tr><td>roster</td></tr></tbody></table>',
            'draftPicksTable' => '<table>picks</table>',
            'currentSeasonCard' => '<div class="team-card">current season</div>',
            'awardsCard' => '<div class="team-card">awards</div>',
            'franchiseHistoryCard' => '<div class="team-card">franchise history</div>',
            'rafters' => '<div>banners</div>',
            'userTeamName' => '',
            'isOwnTeam' => false,
            'extensionResult' => $result,
            'extensionMsg' => $msg,
        ];
    }
}
