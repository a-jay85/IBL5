<?php

declare(strict_types=1);

namespace Team\Views;

use Utilities\HtmlSanitizer;

/**
 * Pure renderer for championship/conference/division banners.
 *
 * @phpstan-import-type BannerData from \Team\Contracts\TeamServiceInterface
 * @phpstan-import-type BannerGroupData from \Team\Contracts\TeamServiceInterface
 * @phpstan-import-type BannerItemData from \Team\Contracts\TeamServiceInterface
 */
class BannersView
{
    /**
     * Render banner HTML from pre-computed data.
     *
     * @param BannerData $bannerData
     */
    public function render(array $bannerData): string
    {
        $teamName = $bannerData['teamName'];
        $color1 = $bannerData['color1'];
        $color2 = $bannerData['color2'];

        $iblBanner = $this->renderBannerGroup($bannerData['championships']);
        $confBanner = $this->renderBannerGroup($bannerData['conferenceTitles']);
        $divBanner = $this->renderBannerGroup($bannerData['divisionTitles']);

        $bannerOutput = $iblBanner . $confBanner . $divBanner;

        if ($bannerOutput === '') {
            return '';
        }

        return '<div class="banners-container" style="--banner-primary: #' . HtmlSanitizer::e($color1) . '; --banner-secondary: #' . HtmlSanitizer::e($color2) . ';">'
            . '<div class="banners-header"><h2>' . HtmlSanitizer::e($teamName) . ' Banners</h2></div>'
            . $bannerOutput . '</div>';
    }

    /**
     * Render a single banner group (championships, conference titles, or division titles).
     *
     * @param BannerGroupData $group
     */
    private function renderBannerGroup(array $group): string
    {
        if ($group['banners'] === []) {
            return '';
        }

        $output = '';
        $count = 0;

        foreach ($group['banners'] as $banner) {
            if ($count % 5 === 0) {
                $output .= '<div class="banners-row">';
            }

            $bgStyle = '';
            if ($banner['bgImage'] !== null) {
                $bgImage = $banner['bgImage'];
                if (str_starts_with($bgImage, './') || str_starts_with($bgImage, '/')) {
                    $bgStyle = ' style="background-image: url(' . HtmlSanitizer::e($bgImage) . ')"';
                }
            }

            $output .= '<div class="banner-item"' . $bgStyle . '>'
                . '<strong>' . HtmlSanitizer::e((string) $banner['year']) . '<br>'
                . HtmlSanitizer::e($banner['name']) . '<br>' . HtmlSanitizer::e($banner['label']) . '</strong>'
                . '</div>';

            $count++;

            if ($count % 5 === 0) {
                $output .= '</div>';
            }
        }

        if ($count % 5 !== 0) {
            $output .= '</div>';
        }

        return $output;
    }
}
