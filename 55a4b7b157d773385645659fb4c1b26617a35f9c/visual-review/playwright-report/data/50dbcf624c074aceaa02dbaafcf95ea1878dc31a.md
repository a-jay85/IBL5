# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke/visual-regression.spec.ts >> Visual regression — public pages (full-page) >> rivalries-gms-pair-mobile
- Location: tests/e2e/smoke/visual-regression.spec.ts:255:9

# Error details

```
Error: expect(page).toHaveScreenshot(expected) failed

  Expected an image 375px by 2613px, received 375px by 2114px. 208785 pixels (ratio 0.22 of all image pixels) are different.

  Snapshot: rivalries-gms-pair-mobile.png

Call log:
  - Expect "toHaveScreenshot(rivalries-gms-pair-mobile.png)" with timeout 10000ms
    - verifying given screenshot expectation
  - taking page screenshot
    - disabled all CSS animations
  - waiting for fonts to load...
  - fonts loaded
  - Expected an image 375px by 2613px, received 375px by 2114px. 208785 pixels (ratio 0.22 of all image pixels) are different.
  - waiting 100ms before taking screenshot
  - taking page screenshot
    - disabled all CSS animations
  - waiting for fonts to load...
  - fonts loaded
  - captured a stable screenshot
  - Expected an image 375px by 2613px, received 375px by 2114px. 208785 pixels (ratio 0.22 of all image pixels) are different.

```

# Page snapshot

```yaml
- generic [active] [ref=f1e1]:
  - navigation [ref=f1e2]:
    - generic [ref=f1e6]:
      - link "IBL Sim League" [ref=f1e7] [cursor=pointer]:
        - /url: index.php
        - generic [ref=f1e14]:
          - generic [ref=f1e15]: IBL
          - generic [ref=f1e16]: Sim League
      - generic [ref=f1e17]:
        - button "Switch to desktop view" [ref=f1e18]
        - button "Toggle menu" [ref=f1e21]
  - main [ref=f1e26]:
    - heading "GM TestUser vs GM Stars" [level=1] [ref=f1e27]
    - generic [ref=f1e28]:
      - generic [ref=f1e29]:
        - heading "Head to head" [level=2] [ref=f1e30]
        - generic [ref=f1e31]:
          - paragraph [ref=f1e32]:
            - link "GM TestUser" [ref=f1e33] [cursor=pointer]:
              - /url: modules.php?name=Rivalries&op=gm&gm=GM+TestUser
            - text: vs
            - link "GM Stars" [ref=f1e34] [cursor=pointer]:
              - /url: modules.php?name=Rivalries&op=gm&gm=GM+Stars
          - list [ref=f1e35]:
            - listitem [ref=f1e36]: All-time 4-1 (5 games)
            - listitem [ref=f1e37]: Playoffs 1-0
            - listitem [ref=f1e38]: Streak GM TestUser has won 1 straight
          - paragraph [ref=f1e39]: Fewer than 10 meetings, so this pair is not ranked as a rivalry.
      - generic [ref=f1e40]:
        - heading "Playoff series" [level=2] [ref=f1e41]
        - table [ref=f1e43]:
          - rowgroup [ref=f1e44]:
            - row [ref=f1e45]:
              - columnheader "Year" [ref=f1e46]
              - columnheader "Round" [ref=f1e47]
              - columnheader "Winner" [ref=f1e48]
              - columnheader "Result" [ref=f1e49]
          - rowgroup [ref=f1e50]:
            - row [ref=f1e51]:
              - cell "No playoff series between them." [ref=f1e52]
      - generic [ref=f1e54]:
        - generic [ref=f1e55]:
          - heading "Closest games" [level=2] [ref=f1e56]
          - table [ref=f1e58]:
            - rowgroup [ref=f1e59]:
              - row [ref=f1e60]:
                - columnheader "Date" [ref=f1e61]
                - columnheader "Away" [ref=f1e62]
                - columnheader "Score" [ref=f1e63]
                - columnheader "Home" [ref=f1e64]
                - columnheader "+/-" [ref=f1e65]
            - rowgroup [ref=f1e66]:
              - row [ref=f1e67]:
                - cell [ref=f1e68]:
                  - link "2025-10-18" [ref=f1e69] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2025-10-18&game=1
                - cell [ref=f1e70]:
                  - link "Metros" [ref=f1e71] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell [ref=f1e73]:
                  - link "102-98" [ref=f1e74] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2025-10-18&game=1
                - cell [ref=f1e75]:
                  - link "Stars" [ref=f1e76] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell "4" [ref=f1e78]
              - row [ref=f1e79]:
                - cell [ref=f1e80]:
                  - link "2026-06-05" [ref=f1e81] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-06-05&game=1
                - cell [ref=f1e82]:
                  - link "Metros" [ref=f1e83] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell [ref=f1e85]:
                  - link "98-91" [ref=f1e86] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-06-05&game=1
                - cell [ref=f1e87]:
                  - link "Stars" [ref=f1e88] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell "7" [ref=f1e90]
              - row [ref=f1e91]:
                - cell [ref=f1e92]:
                  - link "2026-03-05" [ref=f1e93] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-03-05&game=1
                - cell [ref=f1e94]:
                  - link "Stars" [ref=f1e95] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell [ref=f1e97]:
                  - link "95-88" [ref=f1e98] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-03-05&game=1
                - cell [ref=f1e99]:
                  - link "Metros" [ref=f1e100] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell "7" [ref=f1e102]
              - row [ref=f1e103]:
                - cell [ref=f1e104]:
                  - link "2026-02-20" [ref=f1e105] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                - cell [ref=f1e106]:
                  - link "Metros" [ref=f1e107] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell [ref=f1e109]:
                  - link "105-98" [ref=f1e110] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                - cell [ref=f1e111]:
                  - link "Stars" [ref=f1e112] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell "7" [ref=f1e114]
              - row [ref=f1e115]:
                - cell [ref=f1e116]:
                  - link "2026-02-26" [ref=f1e117] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-02-26&game=1
                - cell [ref=f1e118]:
                  - link "Metros" [ref=f1e119] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell [ref=f1e121]:
                  - link "108-99" [ref=f1e122] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-02-26&game=1
                - cell [ref=f1e123]:
                  - link "Stars" [ref=f1e124] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell "9" [ref=f1e126]
        - generic [ref=f1e127]:
          - heading "Biggest blowouts" [level=2] [ref=f1e128]
          - table [ref=f1e130]:
            - rowgroup [ref=f1e131]:
              - row [ref=f1e132]:
                - columnheader "Date" [ref=f1e133]
                - columnheader "Away" [ref=f1e134]
                - columnheader "Score" [ref=f1e135]
                - columnheader "Home" [ref=f1e136]
                - columnheader "+/-" [ref=f1e137]
            - rowgroup [ref=f1e138]:
              - row [ref=f1e139]:
                - cell [ref=f1e140]:
                  - link "2026-02-26" [ref=f1e141] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-02-26&game=1
                - cell [ref=f1e142]:
                  - link "Metros" [ref=f1e143] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell [ref=f1e145]:
                  - link "108-99" [ref=f1e146] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-02-26&game=1
                - cell [ref=f1e147]:
                  - link "Stars" [ref=f1e148] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell "9" [ref=f1e150]
              - row [ref=f1e151]:
                - cell [ref=f1e152]:
                  - link "2026-06-05" [ref=f1e153] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-06-05&game=1
                - cell [ref=f1e154]:
                  - link "Metros" [ref=f1e155] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell [ref=f1e157]:
                  - link "98-91" [ref=f1e158] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-06-05&game=1
                - cell [ref=f1e159]:
                  - link "Stars" [ref=f1e160] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell "7" [ref=f1e162]
              - row [ref=f1e163]:
                - cell [ref=f1e164]:
                  - link "2026-03-05" [ref=f1e165] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-03-05&game=1
                - cell [ref=f1e166]:
                  - link "Stars" [ref=f1e167] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell [ref=f1e169]:
                  - link "95-88" [ref=f1e170] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-03-05&game=1
                - cell [ref=f1e171]:
                  - link "Metros" [ref=f1e172] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell "7" [ref=f1e174]
              - row [ref=f1e175]:
                - cell [ref=f1e176]:
                  - link "2026-02-20" [ref=f1e177] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                - cell [ref=f1e178]:
                  - link "Metros" [ref=f1e179] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell [ref=f1e181]:
                  - link "105-98" [ref=f1e182] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                - cell [ref=f1e183]:
                  - link "Stars" [ref=f1e184] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell "7" [ref=f1e186]
              - row [ref=f1e187]:
                - cell [ref=f1e188]:
                  - link "2025-10-18" [ref=f1e189] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2025-10-18&game=1
                - cell [ref=f1e190]:
                  - link "Metros" [ref=f1e191] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=1
                - cell [ref=f1e193]:
                  - link "102-98" [ref=f1e194] [cursor=pointer]:
                    - /url: modules.php?name=GameBoxscore&date=2025-10-18&game=1
                - cell [ref=f1e195]:
                  - link "Stars" [ref=f1e196] [cursor=pointer]:
                    - /url: modules.php?name=Team&op=team&teamid=2
                - cell "4" [ref=f1e198]
        - generic [ref=f1e199]:
          - heading "Top scorers in the matchup" [level=2] [ref=f1e200]
          - table [ref=f1e202]:
            - rowgroup [ref=f1e203]:
              - row [ref=f1e204]:
                - columnheader "Player" [ref=f1e205]
                - columnheader "GM" [ref=f1e206]
                - columnheader "Games" [ref=f1e207]
                - columnheader "Points" [ref=f1e208]
            - rowgroup [ref=f1e209]:
              - row [ref=f1e210]:
                - cell [ref=f1e211]:
                  - link "M. PG" [ref=f1e212] [cursor=pointer]:
                    - /url: ./modules.php?name=Player&pa=showpage&pid=20
                - cell "GM TestUser" [ref=f1e214]
                - cell "1" [ref=f1e215]
                - cell "31" [ref=f1e216]
              - row [ref=f1e217]:
                - cell [ref=f1e218]:
                  - link "T. Player" [ref=f1e219] [cursor=pointer]:
                    - /url: ./modules.php?name=Player&pa=showpage&pid=1
                - cell "GM TestUser" [ref=f1e221]
                - cell "1" [ref=f1e222]
                - cell "25" [ref=f1e223]
              - row [ref=f1e224]:
                - cell [ref=f1e225]:
                  - link "S. Guard" [ref=f1e226] [cursor=pointer]:
                    - /url: ./modules.php?name=Player&pa=showpage&pid=4
                - cell "GM Stars" [ref=f1e228]
                - cell "1" [ref=f1e229]
                - cell "25" [ref=f1e230]
              - row [ref=f1e231]:
                - cell [ref=f1e232]:
                  - link "T. Player Two" [ref=f1e233] [cursor=pointer]:
                    - /url: ./modules.php?name=Player&pa=showpage&pid=2
                - cell "GM TestUser" [ref=f1e235]
                - cell "1" [ref=f1e236]
                - cell "19" [ref=f1e237]
              - row [ref=f1e238]:
                - cell [ref=f1e239]:
                  - link "S. Forward" [ref=f1e240] [cursor=pointer]:
                    - /url: ./modules.php?name=Player&pa=showpage&pid=5
                - cell "GM Stars" [ref=f1e242]
                - cell "1" [ref=f1e243]
                - cell "19" [ref=f1e244]
        - generic [ref=f1e245]:
          - heading "Best single games" [level=2] [ref=f1e246]
          - region "Scrollable data table" [ref=f1e249]:
            - table [ref=f1e250]:
              - rowgroup [ref=f1e251]:
                - row [ref=f1e252]:
                  - columnheader "Player" [ref=f1e253]
                  - columnheader "GM" [ref=f1e254]
                  - columnheader "Points" [ref=f1e255]
                  - columnheader "Date" [ref=f1e256]
              - rowgroup [ref=f1e257]:
                - row [ref=f1e258]:
                  - cell [ref=f1e259]:
                    - link "M. PG" [ref=f1e260] [cursor=pointer]:
                      - /url: ./modules.php?name=Player&pa=showpage&pid=20
                  - cell "GM TestUser" [ref=f1e262]
                  - cell "31" [ref=f1e263]
                  - cell [ref=f1e264]:
                    - link "2026-02-20" [ref=f1e265] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                - row [ref=f1e266]:
                  - cell [ref=f1e267]:
                    - link "T. Player" [ref=f1e268] [cursor=pointer]:
                      - /url: ./modules.php?name=Player&pa=showpage&pid=1
                  - cell "GM TestUser" [ref=f1e270]
                  - cell "25" [ref=f1e271]
                  - cell [ref=f1e272]:
                    - link "2026-02-20" [ref=f1e273] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                - row [ref=f1e274]:
                  - cell [ref=f1e275]:
                    - link "S. Guard" [ref=f1e276] [cursor=pointer]:
                      - /url: ./modules.php?name=Player&pa=showpage&pid=4
                  - cell "GM Stars" [ref=f1e278]
                  - cell "25" [ref=f1e279]
                  - cell [ref=f1e280]:
                    - link "2026-02-20" [ref=f1e281] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
```

# Test source

```ts
  96  |   if (prev) {
  97  |     mkdirSync(dirname(path), { recursive: true });
  98  |     writeFileSync(path, prev);
  99  |   }
  100 | }
  101 | 
  102 | async function captureSnapshot(
  103 |   page: Page,
  104 |   row: VrRow,
  105 |   state: StateVariant,
  106 |   viewport: Viewport,
  107 |   tab?: HtmxTab,
  108 | ): Promise<void> {
  109 |   if (viewport === 'mobile') {
  110 |     await page.setViewportSize({ width: 375, height: 812 });
  111 |   }
  112 | 
  113 |   const filename = snapshotFilename(row, state, viewport, tab);
  114 |   const title = filename.replace(/\.png$/, '');
  115 |   const anchor = page.locator(row.anchor).first();
  116 | 
  117 |   // Re-establish the same visual state after a (re)load: wait for the anchor,
  118 |   // and re-trigger the HTMX tab swap if any. Runs after both the initial
  119 |   // navigation and the render-B reload. Late image/font loads settle inside
  120 |   // captureStable() (re-samples until two consecutive shots differ by at most
  121 |   // STABLE_MAX_DIFF_RATIO) and the toHaveScreenshot() gate.
  122 |   async function settle(): Promise<void> {
  123 |     await anchor.waitFor({ state: 'visible' });
  124 |     if (tab) {
  125 |       // The swap target already exists before the click, so waiting on its
  126 |       // visibility alone can pass against the old tab. Start the response wait
  127 |       // before the click so the HTMX GET is confirmed to have landed. The
  128 |       // module name keeps an unrelated API fetch from satisfying the wait.
  129 |       await Promise.all([
  130 |         page.waitForResponse(
  131 |           (r) =>
  132 |             r.url().includes('name=LeagueStarters') &&
  133 |             r.url().includes('op=api') &&
  134 |             r.request().method() === 'GET' &&
  135 |             r.status() === 200,
  136 |         ),
  137 |         page.locator(tab.trigger).first().click(),
  138 |       ]);
  139 |       await page.locator(tab.swapTarget).first().waitFor({ state: 'visible' });
  140 |     }
  141 |   }
  142 | 
  143 |   await gotoWithRetry(page, row.url);
  144 |   await assertNoPhpErrors(page, `on ${row.url}`);
  145 |   await settle();
  146 | 
  147 |   // What to screenshot, and whether it's a full-page capture (page only).
  148 |   const fullPage = !tab?.swapTarget && !row.elementScreenshot;
  149 |   const captureTarget: Locator | Page = tab?.swapTarget
  150 |     ? page.locator(tab.swapTarget).first()
  151 |     : row.elementScreenshot
  152 |       ? anchor
  153 |       : page;
  154 | 
  155 |   // Capture options for the raw PR renders. Deliberately EXCLUDE
  156 |   // maxDiffPixelRatio — that governs the toHaveScreenshot() gate below, not a
  157 |   // raw render capture.
  158 |   const captureOpts = {
  159 |     animations: 'disabled' as const,
  160 |     mask: buildMasks(page, row.extraMask),
  161 |     ...(fullPage ? { fullPage: true } : {}),
  162 |   };
  163 | 
  164 |   // Render A — the PR's actual render of this cell. captureStable retries a
  165 |   // thrown capture and re-samples until settled; if every attempt throws it
  166 |   // writes no .a.png and the gallery builder triages the cell as infra.
  167 |   await captureStable(page, captureTarget, `${ACTUALS_DIR}/${title}.a.png`, captureOpts);
  168 | 
  169 |   // Render B — an independent second render after a full reload, used to demote
  170 |   // self-disagreeing (flaky) cells out of the change gallery.
  171 |   try {
  172 |     await page.reload({ waitUntil: 'load' });
  173 |     await settle();
  174 |     await captureStable(page, captureTarget, `${ACTUALS_DIR}/${title}.b.png`, captureOpts);
  175 |   } catch {
  176 |     // A missing .b.png skips the self-stability check (gallery handles null B).
  177 |   }
  178 | 
  179 |   // The pass/fail gate stays LAST and unchanged — this is what the
  180 |   // `update-baselines` regen workflow signs off and what the green/red check
  181 |   // reflects. The gallery above is independent of this assertion's outcome.
  182 |   const screenshotOpts = {
  183 |     animations: 'disabled' as const,
  184 |     mask: buildMasks(page, row.extraMask),
  185 |     ...(row.extraMaxDiffPixelRatio !== undefined
  186 |       ? { maxDiffPixelRatio: row.extraMaxDiffPixelRatio }
  187 |       : {}),
  188 |   };
  189 | 
  190 |   if (tab?.swapTarget) {
  191 |     const target = page.locator(tab.swapTarget).first();
  192 |     await expect(target).toHaveScreenshot(filename, screenshotOpts);
  193 |   } else if (row.elementScreenshot) {
  194 |     await expect(anchor).toHaveScreenshot(filename, screenshotOpts);
  195 |   } else {
> 196 |     await expect(page).toHaveScreenshot(filename, {
      |                        ^ Error: expect(page).toHaveScreenshot(expected) failed
  197 |       fullPage: true,
  198 |       ...screenshotOpts,
  199 |     });
  200 |   }
  201 | }
  202 | 
  203 | function rowsByAuth(auth: AuthMode): VrRow[] {
  204 |   return VR_MANIFEST.filter((r) => r.auth === auth);
  205 | }
  206 | 
  207 | function expandRow(row: VrRow): Array<{
  208 |   state: StateVariant;
  209 |   viewport: Viewport;
  210 |   tab?: HtmxTab;
  211 |   testName: string;
  212 | }> {
  213 |   const states = row.states ?? [DEFAULT_STATE];
  214 |   const viewports = row.viewports ?? ['desktop'];
  215 |   const tabs: Array<HtmxTab | undefined> = [undefined, ...(row.htmxTabs ?? [])];
  216 |   const cells: Array<{
  217 |     state: StateVariant;
  218 |     viewport: Viewport;
  219 |     tab?: HtmxTab;
  220 |     testName: string;
  221 |   }> = [];
  222 | 
  223 |   for (const state of states) {
  224 |     for (const viewport of viewports) {
  225 |       for (const tab of tabs) {
  226 |         const filename = snapshotFilename(row, state, viewport, tab);
  227 |         cells.push({
  228 |           state,
  229 |           viewport,
  230 |           tab: tab ?? undefined,
  231 |           testName: filename.replace(/\.png$/, ''),
  232 |         });
  233 |       }
  234 |     }
  235 |   }
  236 |   return cells;
  237 | }
  238 | 
  239 | function registerTests(
  240 |   testFn: typeof publicTest,
  241 |   auth: AuthMode,
  242 |   label: string,
  243 |   beforeEachHook?: (fixtures: { appState: (s: Record<string, string>) => Promise<void> }) => Promise<void>,
  244 | ): void {
  245 |   testFn.describe(`Visual regression — ${label}`, () => {
  246 |     if (beforeEachHook) {
  247 |       testFn.beforeEach(async ({ appState }) => {
  248 |         await beforeEachHook({ appState });
  249 |       });
  250 |     }
  251 | 
  252 |     for (const row of rowsByAuth(auth)) {
  253 |       const cells = expandRow(row);
  254 |       for (const cell of cells) {
  255 |         testFn(cell.testName, async ({ appState, page }) => {
  256 |           if (cell.state.appState && Object.keys(cell.state.appState).length > 0) {
  257 |             await appState(cell.state.appState);
  258 |           }
  259 |           if (row.notes) {
  260 |             console.log(`[visual-regression] ${row.name}: ${row.notes}`);
  261 |           }
  262 |           await captureSnapshot(page, row, cell.state, cell.viewport, cell.tab);
  263 |         });
  264 |       }
  265 |     }
  266 |   });
  267 | }
  268 | 
  269 | // ============================================================
  270 | // Public visual regression — no authentication required
  271 | // ============================================================
  272 | 
  273 | registerTests(publicTest, 'public', 'public pages (full-page)', async ({ appState }) => {
  274 |   await appState({ 'Trivia Mode': 'Off' });
  275 | });
  276 | 
  277 | // ============================================================
  278 | // Authenticated visual regression — requires test user
  279 | // ============================================================
  280 | 
  281 | registerTests(authTest, 'auth', 'authenticated pages (full-page)');
  282 | 
  283 | // ============================================================
  284 | // Non-admin visual regression — roles_mask=0, no franchise
  285 | // ============================================================
  286 | 
  287 | registerTests(authRegularTest, 'auth-regular', 'non-admin authenticated pages');
  288 | 
```