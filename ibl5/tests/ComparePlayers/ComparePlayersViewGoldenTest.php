<?php

declare(strict_types=1);

namespace Tests\ComparePlayers;

use ComparePlayers\ComparePlayersView;
use PHPUnit\Framework\TestCase;

/**
 * Exact-markup tests for ComparePlayersView.
 *
 * The existing ComparePlayersViewTest asserts fragments. This class compares
 * whole sections of the rendered HTML against inline expected strings, so a
 * dropped tag, a dropped cast, a lost concatenation operand or a missing
 * escape changes the output and fails a named test.
 *
 * Fixtures use strings and floats that change under `(int)`: player A carries
 * values such as '112.9' (prints 112) and player B zero-padded values such as
 * '0212' (prints 212). Dropping an `(int)` cast in the view then prints the
 * raw value and the exact compare fails.
 */
class ComparePlayersViewGoldenTest extends TestCase
{
    private const SECTION_SPLIT = '/(?=<h2 class="ibl-title">)/';

    /**
     * Numeric columns in the order the view reads them. Each gets its own value.
     *
     * @var list<string>
     */
    private const NUMERIC_KEYS = [
        'age',
        'r_fga', 'r_fgp', 'r_fta', 'r_ftp', 'r_3ga', 'r_3gp',
        'r_orb', 'r_drb', 'r_ast', 'r_stl', 'r_tvr', 'r_blk', 'r_foul',
        'oo', 'r_drive_off', 'po', 'r_trans_off', 'od', 'dd', 'pd', 'td',
        'stats_gm', 'stats_gs', 'stats_min', 'stats_fgm', 'stats_fga',
        'stats_ftm', 'stats_fta', 'stats_3gm', 'stats_3ga', 'stats_orb',
        'stats_drb', 'stats_ast', 'stats_stl', 'stats_tvr', 'stats_blk', 'stats_pf',
        'car_gm', 'car_min', 'car_fgm', 'car_fga', 'car_ftm', 'car_fta',
        'car_3gm', 'car_3ga', 'car_orb', 'car_drb', 'car_reb', 'car_ast',
        'car_stl', 'car_tvr', 'car_blk', 'car_pf', 'car_pts',
    ];

    private ComparePlayersView $view;

    protected function setUp(): void
    {
        $this->view = new ComparePlayersView();
    }

    // ------------------------------------------------------------------
    // Search form
    // ------------------------------------------------------------------

    public function testRenderSearchFormMatchesGoldenMarkup(): void
    {
        $html = $this->view->renderSearchForm(['Plain Name', "O\\'Brien", '<Z&"Q>']);

        $this->assertSame(self::flat(<<<'HTML'
<h1 class="ibl-title">Compare Players</h1>
<form action="modules.php?name=ComparePlayers" method="POST" class="d-card d-card-border bg-base-200 p-4 mb-6"><div class="flex flex-wrap items-center justify-center gap-4"><div class="flex items-center gap-2">
<label class="d-label" for="Player1">Player 1</label>
<input type="text" name="Player1" id="Player1" class="d-input d-input-sm w-40" list="player-names" placeholder="Search player..." autocomplete="off"></div><div class="flex items-center gap-2">
<label class="d-label" for="Player2">Player 2</label>
<input type="text" name="Player2" id="Player2" class="d-input d-input-sm w-40" list="player-names" placeholder="Search player..." autocomplete="off"></div>
<button type="submit" class="d-btn d-btn-primary d-btn-sm">Compare</button></div>
</form>
<datalist id="player-names">
<option value="Plain Name">
<option value="O&apos;Brien">
<option value="&lt;Z&amp;&quot;Q&gt;">
</datalist>
HTML), $html);
    }

    public function testRenderSearchFormWithNoPlayersEmitsEmptyDatalist(): void
    {
        $html = $this->view->renderSearchForm([]);

        $this->assertSame(self::flat(<<<'HTML'
<h1 class="ibl-title">Compare Players</h1>
<form action="modules.php?name=ComparePlayers" method="POST" class="d-card d-card-border bg-base-200 p-4 mb-6"><div class="flex flex-wrap items-center justify-center gap-4"><div class="flex items-center gap-2">
<label class="d-label" for="Player1">Player 1</label>
<input type="text" name="Player1" id="Player1" class="d-input d-input-sm w-40" list="player-names" placeholder="Search player..." autocomplete="off"></div><div class="flex items-center gap-2">
<label class="d-label" for="Player2">Player 2</label>
<input type="text" name="Player2" id="Player2" class="d-input d-input-sm w-40" list="player-names" placeholder="Search player..." autocomplete="off"></div>
<button type="submit" class="d-btn d-btn-primary d-btn-sm">Compare</button></div>
</form>
<datalist id="player-names">
</datalist>
HTML), $html);
    }

    public function testRenderSearchFormEscapesMarkupAndStripsSlashes(): void
    {
        $html = $this->view->renderSearchForm(["O\\'Brien", '<Z&"Q>']);

        $this->assertStringContainsString('<option value="&lt;Z&amp;&quot;Q&gt;">', $html);
        $this->assertStringNotContainsString('<Z&"Q>', $html);
        $this->assertStringContainsString("O&apos;Brien", $html);
        $this->assertStringNotContainsString('\\', $html);
    }

    // ------------------------------------------------------------------
    // Comparison tables
    // ------------------------------------------------------------------

    public function testRenderComparisonResultsHasThreeTablesWithExpectedHeaderAndCellCounts(): void
    {
        $html = $this->view->renderComparisonResults($this->comparison());

        $this->assertStringNotContainsString("\n", $html);

        $sections = self::sections($html);
        $this->assertCount(3, $sections);
        $this->assertSame($html, implode('', $sections));

        $expectedHeaderCounts = [25, 20, 20];
        foreach ($sections as $index => $section) {
            $this->assertSame(1, substr_count($section, '<table'), "section $index table count");
            $this->assertSame(1, substr_count($section, '</table>'), "section $index closing table count");
            $this->assertSame(1, substr_count($section, '<thead>'), "section $index thead count");
            $this->assertSame(1, substr_count($section, '<tbody>'), "section $index tbody count");
            $this->assertSame(
                $expectedHeaderCounts[$index],
                substr_count($section, '<th>'),
                "section $index header count"
            );
            $this->assertSame(
                2,
                substr_count($section, '<tr>') - 1,
                "section $index body row count"
            );
            $this->assertSame(
                2 * $expectedHeaderCounts[$index],
                substr_count($section, '<td'),
                "section $index body cell count"
            );
            $this->assertSame(
                substr_count($section, '<div'),
                substr_count($section, '</div>'),
                "section $index div balance"
            );
        }
    }

    public function testRenderComparisonResultsRatingsSectionMatchesGoldenMarkup(): void
    {
        $html = $this->view->renderComparisonResults($this->comparison());

        $this->assertSame(self::flat(<<<'HTML'
<h2 class="ibl-title">Current Ratings</h2>
<div class="table-scroll-wrapper"><div class="table-scroll-container" tabindex="0" role="region" aria-label="Current ratings comparison">
<table class="sortable ibl-data-table responsive-table">
<thead>
<tr>
<th>Pos</th>
<th>Player</th>
<th>Team</th>
<th>Age</th>
<th>2ga</th>
<th>2g%</th>
<th>fta</th>
<th>ft%</th>
<th>3ga</th>
<th>3g%</th>
<th>orb</th>
<th>drb</th>
<th>ast</th>
<th>stl</th>
<th>tvr</th>
<th>blk</th>
<th>foul</th>
<th>oo</th>
<th>do</th>
<th>po</th>
<th>to</th>
<th>od</th>
<th>dd</th>
<th>pd</th>
<th>td</th>
</tr>
</thead>
<tbody>
<tr>
<td>&lt;PG&gt;&amp;</td>
<td class="ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=900001"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy">Al &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; O&apos;Neil</a></td>
<td class="ibl-team-cell--colored" style="--team-cell-bg: #AA0000; --team-cell-color: #ffffff; --team-cell-shadow: #0000BB;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Bo&amp;ston &quot;Celts&quot; &lt;i&gt;</span></a></td>
<td>100</td>
<td>101</td>
<td>102</td>
<td>103</td>
<td>104</td>
<td>105</td>
<td>106</td>
<td>107</td>
<td>108</td>
<td>109</td>
<td>110</td>
<td>111</td>
<td>112</td>
<td>113</td>
<td>114</td>
<td>115</td>
<td>116</td>
<td>117</td>
<td>118</td>
<td>119</td>
<td>120</td>
<td>121</td>
</tr>
<tr>
<td>C</td>
<td class="ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=900002"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy">Bea Two</a></td>
<td>Free Agent</td>
<td>200</td>
<td>201</td>
<td>202</td>
<td>203</td>
<td>204</td>
<td>205</td>
<td>206</td>
<td>207</td>
<td>208</td>
<td>209</td>
<td>210</td>
<td>211</td>
<td>212</td>
<td>213</td>
<td>214</td>
<td>215</td>
<td>216</td>
<td>217</td>
<td>218</td>
<td>219</td>
<td>220</td>
<td>221</td>
</tr>
</tbody>
</table>
</div></div>
HTML), self::sections($html)[0]);
    }

    public function testRenderComparisonResultsSeasonStatsSectionMatchesGoldenMarkup(): void
    {
        $html = $this->view->renderComparisonResults($this->comparison());

        $this->assertSame(self::flat(<<<'HTML'
<h2 class="ibl-title">Current Season Stats</h2>
<div class="table-scroll-wrapper"><div class="table-scroll-container" tabindex="0" role="region" aria-label="Current season stats comparison">
<table class="sortable ibl-data-table responsive-table">
<thead>
<tr>
<th>Pos</th>
<th>Player</th>
<th>Team</th>
<th>g</th>
<th>gs</th>
<th>min</th>
<th>fgm</th>
<th>fga</th>
<th>ftm</th>
<th>fta</th>
<th>3gm</th>
<th>3ga</th>
<th>orb</th>
<th>reb</th>
<th>ast</th>
<th>stl</th>
<th>to</th>
<th>blk</th>
<th>pf</th>
<th>pts</th>
</tr>
</thead>
<tbody>
<tr>
<td>&lt;PG&gt;&amp;</td>
<td class="ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=900001"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy">Al &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; O&apos;Neil</a></td>
<td class="ibl-team-cell--colored" style="--team-cell-bg: #AA0000; --team-cell-color: #ffffff; --team-cell-shadow: #0000BB;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Bo&amp;ston &quot;Celts&quot; &lt;i&gt;</span></a></td>
<td>122</td>
<td>123</td>
<td>124</td>
<td>9</td>
<td>126</td>
<td>4</td>
<td>128</td>
<td>3</td>
<td>130</td>
<td>131</td>
<td>132</td>
<td>133</td>
<td>134</td>
<td>135</td>
<td>136</td>
<td>137</td>
<td>25</td>
</tr>
<tr>
<td>C</td>
<td class="ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=900002"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy">Bea Two</a></td>
<td>Free Agent</td>
<td>222</td>
<td>223</td>
<td>224</td>
<td>225</td>
<td>226</td>
<td>227</td>
<td>228</td>
<td>229</td>
<td>230</td>
<td>231</td>
<td>232</td>
<td>233</td>
<td>234</td>
<td>235</td>
<td>236</td>
<td>237</td>
<td>906</td>
</tr>
</tbody>
</table>
</div></div>
HTML), self::sections($html)[1]);
    }

    public function testRenderComparisonResultsCareerStatsSectionMatchesGoldenMarkup(): void
    {
        $html = $this->view->renderComparisonResults($this->comparison());

        $this->assertSame(self::flat(<<<'HTML'
<h2 class="ibl-title">Career Stats</h2>
<div class="table-scroll-wrapper"><div class="table-scroll-container" tabindex="0" role="region" aria-label="Career stats comparison">
<table class="sortable ibl-data-table responsive-table">
<thead>
<tr>
<th>Pos</th>
<th>Player</th>
<th>Team</th>
<th>g</th>
<th>min</th>
<th>fgm</th>
<th>fga</th>
<th>ftm</th>
<th>fta</th>
<th>3gm</th>
<th>3ga</th>
<th>orb</th>
<th>drb</th>
<th>reb</th>
<th>ast</th>
<th>stl</th>
<th>to</th>
<th>blk</th>
<th>pf</th>
<th>pts</th>
</tr>
</thead>
<tbody>
<tr>
<td>&lt;PG&gt;&amp;</td>
<td class="ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=900001"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy">Al &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; O&apos;Neil</a></td>
<td class="ibl-team-cell--colored" style="--team-cell-bg: #AA0000; --team-cell-color: #ffffff; --team-cell-shadow: #0000BB;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name"><img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy"><span class="ibl-team-cell__text">Bo&amp;ston &quot;Celts&quot; &lt;i&gt;</span></a></td>
<td>138</td>
<td>139</td>
<td>140</td>
<td>141</td>
<td>142</td>
<td>143</td>
<td>144</td>
<td>145</td>
<td>146</td>
<td>147</td>
<td>148</td>
<td>149</td>
<td>150</td>
<td>151</td>
<td>152</td>
<td>153</td>
<td>154</td>
</tr>
<tr>
<td>C</td>
<td class="ibl-player-cell"><a href="./modules.php?name=Player&amp;pa=showpage&amp;pid=900002"><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==" alt="" class="ibl-player-photo" width="24" height="24" loading="lazy">Bea Two</a></td>
<td>Free Agent</td>
<td>238</td>
<td>239</td>
<td>240</td>
<td>241</td>
<td>242</td>
<td>243</td>
<td>244</td>
<td>245</td>
<td>246</td>
<td>247</td>
<td>248</td>
<td>249</td>
<td>250</td>
<td>251</td>
<td>252</td>
<td>253</td>
<td>254</td>
</tr>
</tbody>
</table>
</div></div>
HTML), self::sections($html)[2]);
    }

    public function testMissingTeamKeysFallBackToDefaultColorsInAllThreeTables(): void
    {
        $withoutKeys = self::playerA();
        $withoutKeys['teamid'] = 7;
        $withoutKeys['name'] = 'Plain One';
        $withoutKeys['pos'] = 'PG';
        unset($withoutKeys['teamname'], $withoutKeys['color1'], $withoutKeys['color2']);

        $withKeys = self::playerA();
        $withKeys['name'] = 'Plain Two';
        $withKeys['pos'] = 'SG';
        $withKeys['teamid'] = 5;
        $withKeys['teamname'] = 'Celtics';
        $withKeys['color1'] = 'AA0000';
        $withKeys['color2'] = '0000BB';

        $html = $this->view->renderComparisonResults(['player1' => $withoutKeys, 'player2' => $withKeys]);

        // Missing keys: empty name, white background, black text and shadow.
        $defaultCell = '<td class="ibl-team-cell--colored" style="--team-cell-bg: #FFFFFF; --team-cell-color: #000000; --team-cell-shadow: #000000;">'
            . '<a href="modules.php?name=Team&amp;op=team&amp;teamid=7" class="ibl-team-cell__name">'
            . '<img src="images/logo/new7.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">'
            . '<span class="ibl-team-cell__text"></span></a></td>';
        // Present keys: the player's own name and colors.
        $ownCell = '<td class="ibl-team-cell--colored" style="--team-cell-bg: #AA0000; --team-cell-color: #ffffff; --team-cell-shadow: #0000BB;">'
            . '<a href="modules.php?name=Team&amp;op=team&amp;teamid=5" class="ibl-team-cell__name">'
            . '<img src="images/logo/new5.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">'
            . '<span class="ibl-team-cell__text">Celtics</span></a></td>';

        $this->assertSame(3, substr_count($html, $defaultCell));
        $this->assertSame(3, substr_count($html, $ownCell));
    }

    public function testUserControlledFieldsAreEscapedInEveryTable(): void
    {
        $html = $this->view->renderComparisonResults($this->comparison());

        $sections = self::sections($html);
        $this->assertCount(3, $sections);

        foreach ($sections as $index => $section) {
            $this->assertStringNotContainsString('<script>', $section, "section $index raw script");
            $this->assertStringNotContainsString('<PG>', $section, "section $index raw pos");
            $this->assertStringNotContainsString('<i>', $section, "section $index raw team name");
            $this->assertStringNotContainsString('"x"', $section, "section $index raw quotes");

            $this->assertStringContainsString(
                'loading="lazy">Al &lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; O&apos;Neil</a>',
                $section,
                "section $index escaped name"
            );
            $this->assertStringContainsString('<td>&lt;PG&gt;&amp;</td>', $section, "section $index escaped pos");
            $this->assertStringContainsString(
                '<span class="ibl-team-cell__text">Bo&amp;ston &quot;Celts&quot; &lt;i&gt;</span>',
                $section,
                "section $index escaped team name"
            );
        }
    }

    // ------------------------------------------------------------------
    // Fixtures and helpers
    // ------------------------------------------------------------------

    /**
     * @return array{player1: array<string, mixed>, player2: array<string, mixed>}
     */
    private function comparison(): array
    {
        return ['player1' => self::playerA(), 'player2' => self::playerB()];
    }

    /**
     * Player A: float-like strings, team keys present, markup in name, pos and team name.
     *
     * @return array<string, mixed>
     */
    private static function playerA(): array
    {
        $row = [
            'pid' => 900001,
            'name' => 'Al <script>alert("x")</script> & O\'Neil',
            'pos' => '<PG>&',
            'teamid' => 5,
            'teamname' => 'Bo&ston "Celts" <i>',
            'color1' => 'AA0000',
            'color2' => '0000BB',
            'team_city' => 'Boston',
        ];

        foreach (self::NUMERIC_KEYS as $index => $key) {
            $row[$key] = (string) (100 + $index) . '.9';
        }

        // Points = 2 * 9 + 4 + 3 = 25, which no other season column holds.
        $row['stats_fgm'] = '9.9';
        $row['stats_ftm'] = '4.9';
        $row['stats_3gm'] = '3.9';

        return $row;
    }

    /**
     * Player B: zero-padded strings, Free Agent (teamid 0), no team keys.
     *
     * @return array<string, mixed>
     */
    private static function playerB(): array
    {
        $row = [
            'pid' => 900002,
            'name' => 'Bea Two',
            'pos' => 'C',
            'teamid' => 0,
            'team_city' => null,
        ];

        foreach (self::NUMERIC_KEYS as $index => $key) {
            $row[$key] = '0' . (string) (200 + $index);
        }

        return $row;
    }

    /**
     * Remove the line breaks that keep the expected-markup literals readable.
     */
    private static function flat(string $nowdoc): string
    {
        return str_replace(["\r\n", "\n"], '', $nowdoc);
    }

    /**
     * Split the comparison output into its three table sections.
     *
     * @return list<string>
     */
    private static function sections(string $html): array
    {
        $parts = preg_split(self::SECTION_SPLIT, $html, -1, PREG_SPLIT_NO_EMPTY);
        self::assertIsArray($parts);

        $sections = [];
        foreach ($parts as $part) {
            self::assertIsString($part);
            $sections[] = $part;
        }

        return $sections;
    }
}
