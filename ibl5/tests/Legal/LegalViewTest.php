<?php

declare(strict_types=1);

namespace Tests\Legal;

use Legal\LegalView;
use PHPUnit\Framework\TestCase;

final class LegalViewTest extends TestCase
{
    public function testPrivacyPolicyCoversGoogleSheetsAccess(): void
    {
        $html = (new LegalView())->renderPrivacy();

        self::assertStringContainsString('Privacy Policy', $html);
        self::assertStringContainsString('Last updated: ' . LegalView::LAST_UPDATED, $html);
        self::assertStringContainsString('only the files IBL creates', $html);
        self::assertStringContainsString('encrypted Google token', $html);
        self::assertStringContainsString('You can disconnect anytime', $html);
    }

    public function testPrivacyPolicyCarriesGoogleLimitedUseDisclosure(): void
    {
        $html = (new LegalView())->renderPrivacy();

        self::assertStringContainsString('https://developers.google.com/terms/api-services-user-data-policy', $html);
        self::assertStringContainsString('including the Limited Use requirements', $html);
    }

    public function testTermsOfServiceLinksToPrivacyPolicy(): void
    {
        $html = (new LegalView())->renderTerms();

        self::assertStringContainsString('Terms of Service', $html);
        self::assertStringContainsString('Last updated: ' . LegalView::LAST_UPDATED, $html);
        self::assertStringContainsString('href="modules.php?name=Privacy"', $html);
        self::assertStringContainsString('No warranty', $html);
    }
}
