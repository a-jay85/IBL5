<?php

declare(strict_types=1);

namespace Team\Views;

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

        $iblBanner = $this->renderBannerGroup($bannerData['championships'], $color1, $color2);
        $confBanner = $this->renderBannerGroup($bannerData['conferenceTitles'], $color1, $color2);
        $divBanner = $this->renderBannerGroup($bannerData['divisionTitles'], $color1, $color2);

        $bannerOutput = $iblBanner . $confBanner . $divBanner;

        if ($bannerOutput === '') {
            return '';
        }

        return "<center><table><tr><td bgcolor=\"#$color1\" align=\"center\"><font color=\"#$color2\"><h2>$teamName Banners</h2></font></td></tr>"
            . $bannerOutput . "</table></center>";
    }

    /**
     * Render a single banner group (championships, conference titles, or division titles).
     *
     * @param BannerGroupData $group
     */
    private function renderBannerGroup(array $group, string $color1, string $color2): string
    {
        if ($group['banners'] === []) {
            return '';
        }

        $output = '';
        $count = 0;

        foreach ($group['banners'] as $banner) {
            if ($count % 5 === 0) {
                $output .= "<tr><td align=\"center\"><table><tr>";
            }

            $bgAttr = $banner['bgImage'] !== null ? " background=\"{$banner['bgImage']}\"" : '';
            $output .= "<td><table><tr bgcolor=$color1><td valign=top height=80 width=120$bgAttr><font color=#$color2>
                    <center><b>{$banner['year']}<br>
                    {$banner['name']}<br>{$banner['label']}</b></center></td></tr></table></td>";

            $count++;

            if ($count % 5 === 0) {
                $output .= "</tr></table></td></tr>";
            }
        }

        if (substr($output, -23) !== "</tr></table></td></tr>") {
            $output .= "</tr></table></td></tr>";
        }

        return $output;
    }
}
