# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke/visual-regression.spec.ts >> Visual regression — public pages (full-page) >> rivalries-gms-pair-mobile
- Location: tests/e2e/smoke/visual-regression.spec.ts:242:9

# Error details

```
Error: A snapshot doesn't exist at /ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots/rivalries-gms-pair-mobile.png, writing actual.
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
      - paragraph [ref=f1e29]:
        - link "GM TestUser" [ref=f1e30] [cursor=pointer]:
          - /url: modules.php?name=Rivalries&op=gm&gm=GM+TestUser
        - text: vs
        - link "GM Stars" [ref=f1e31] [cursor=pointer]:
          - /url: modules.php?name=Rivalries&op=gm&gm=GM+Stars
      - paragraph [ref=f1e32]: "All-time: 4-1 (5 games)"
      - paragraph [ref=f1e33]: "Playoffs: 1-0"
      - paragraph [ref=f1e34]: GM TestUser has won 1 straight
      - paragraph [ref=f1e35]:
        - text: "Score:"
        - generic [ref=f1e36]: "17"
      - paragraph [ref=f1e37]: Fewer than 10 meetings, so this pair is not ranked as a rivalry.
    - generic [ref=f1e38]:
      - heading "Playoff series" [level=2] [ref=f1e39]
      - generic [ref=f1e40]: No playoff series between them.
    - generic [ref=f1e41]:
      - generic [ref=f1e42]:
        - heading "Closest games" [level=2] [ref=f1e43]
        - table [ref=f1e45]:
          - rowgroup [ref=f1e46]:
            - row [ref=f1e47]:
              - columnheader "Date" [ref=f1e48]
              - columnheader "Visitor" [ref=f1e49]
              - columnheader "Score" [ref=f1e50]
              - columnheader "Home" [ref=f1e51]
          - rowgroup [ref=f1e52]:
            - row [ref=f1e53]:
              - cell "2025-10-18" [ref=f1e54]
              - cell "New York Metros" [ref=f1e55]
              - cell "102-98" [ref=f1e56]
              - cell "Los Angeles Stars" [ref=f1e57]
            - row [ref=f1e58]:
              - cell "2026-06-05" [ref=f1e59]
              - cell "New York Metros" [ref=f1e60]
              - cell "98-91" [ref=f1e61]
              - cell "Los Angeles Stars" [ref=f1e62]
            - row [ref=f1e63]:
              - cell "2026-03-05" [ref=f1e64]
              - cell "Los Angeles Stars" [ref=f1e65]
              - cell "95-88" [ref=f1e66]
              - cell "New York Metros" [ref=f1e67]
            - row [ref=f1e68]:
              - cell "2026-02-20" [ref=f1e69]
              - cell "New York Metros" [ref=f1e70]
              - cell "105-98" [ref=f1e71]
              - cell "Los Angeles Stars" [ref=f1e72]
            - row [ref=f1e73]:
              - cell "2026-02-26" [ref=f1e74]
              - cell "New York Metros" [ref=f1e75]
              - cell "108-99" [ref=f1e76]
              - cell "Los Angeles Stars" [ref=f1e77]
      - generic [ref=f1e78]:
        - heading "Biggest blowouts" [level=2] [ref=f1e79]
        - table [ref=f1e81]:
          - rowgroup [ref=f1e82]:
            - row [ref=f1e83]:
              - columnheader "Date" [ref=f1e84]
              - columnheader "Visitor" [ref=f1e85]
              - columnheader "Score" [ref=f1e86]
              - columnheader "Home" [ref=f1e87]
          - rowgroup [ref=f1e88]:
            - row [ref=f1e89]:
              - cell "2026-02-26" [ref=f1e90]
              - cell "New York Metros" [ref=f1e91]
              - cell "108-99" [ref=f1e92]
              - cell "Los Angeles Stars" [ref=f1e93]
            - row [ref=f1e94]:
              - cell "2026-06-05" [ref=f1e95]
              - cell "New York Metros" [ref=f1e96]
              - cell "98-91" [ref=f1e97]
              - cell "Los Angeles Stars" [ref=f1e98]
            - row [ref=f1e99]:
              - cell "2026-03-05" [ref=f1e100]
              - cell "Los Angeles Stars" [ref=f1e101]
              - cell "95-88" [ref=f1e102]
              - cell "New York Metros" [ref=f1e103]
            - row [ref=f1e104]:
              - cell "2026-02-20" [ref=f1e105]
              - cell "New York Metros" [ref=f1e106]
              - cell "105-98" [ref=f1e107]
              - cell "Los Angeles Stars" [ref=f1e108]
            - row [ref=f1e109]:
              - cell "2025-10-18" [ref=f1e110]
              - cell "New York Metros" [ref=f1e111]
              - cell "102-98" [ref=f1e112]
              - cell "Los Angeles Stars" [ref=f1e113]
    - generic [ref=f1e114]:
      - generic [ref=f1e115]:
        - heading "Top scorers in the matchup" [level=2] [ref=f1e116]
        - table [ref=f1e118]:
          - rowgroup [ref=f1e119]:
            - row [ref=f1e120]:
              - columnheader "Player" [ref=f1e121]
              - columnheader "Side" [ref=f1e122]
              - columnheader "Games" [ref=f1e123]
              - columnheader "Points" [ref=f1e124]
          - rowgroup [ref=f1e125]:
            - row [ref=f1e126]:
              - cell [ref=f1e127]:
                - link "Metros PG" [ref=f1e128] [cursor=pointer]:
                  - /url: modules.php?name=Player&pa=showpage&pid=20
              - cell "GM TestUser" [ref=f1e129]
              - cell "1" [ref=f1e130]
              - cell "31" [ref=f1e131]
            - row [ref=f1e132]:
              - cell [ref=f1e133]:
                - link "Test Player" [ref=f1e134] [cursor=pointer]:
                  - /url: modules.php?name=Player&pa=showpage&pid=1
              - cell "GM TestUser" [ref=f1e135]
              - cell "1" [ref=f1e136]
              - cell "25" [ref=f1e137]
            - row [ref=f1e138]:
              - cell [ref=f1e139]:
                - link "Stars Guard" [ref=f1e140] [cursor=pointer]:
                  - /url: modules.php?name=Player&pa=showpage&pid=4
              - cell "GM Stars" [ref=f1e141]
              - cell "1" [ref=f1e142]
              - cell "25" [ref=f1e143]
            - row [ref=f1e144]:
              - cell [ref=f1e145]:
                - link "Test Player Two" [ref=f1e146] [cursor=pointer]:
                  - /url: modules.php?name=Player&pa=showpage&pid=2
              - cell "GM TestUser" [ref=f1e147]
              - cell "1" [ref=f1e148]
              - cell "19" [ref=f1e149]
            - row [ref=f1e150]:
              - cell [ref=f1e151]:
                - link "Stars Forward" [ref=f1e152] [cursor=pointer]:
                  - /url: modules.php?name=Player&pa=showpage&pid=5
              - cell "GM Stars" [ref=f1e153]
              - cell "1" [ref=f1e154]
              - cell "19" [ref=f1e155]
      - generic [ref=f1e156]:
        - heading "Best single games" [level=2] [ref=f1e157]
        - table [ref=f1e159]:
          - rowgroup [ref=f1e160]:
            - row [ref=f1e161]:
              - columnheader "Player" [ref=f1e162]
              - columnheader "Side" [ref=f1e163]
              - columnheader "Points" [ref=f1e164]
              - columnheader "Date" [ref=f1e165]
          - rowgroup [ref=f1e166]:
            - row [ref=f1e167]:
              - cell [ref=f1e168]:
                - link "Metros PG" [ref=f1e169] [cursor=pointer]:
                  - /url: modules.php?name=Player&pa=showpage&pid=20
              - cell "GM TestUser" [ref=f1e170]
              - cell "31" [ref=f1e171]
              - cell "2026-02-20" [ref=f1e172]
            - row [ref=f1e173]:
              - cell [ref=f1e174]:
                - link "Test Player" [ref=f1e175] [cursor=pointer]:
                  - /url: modules.php?name=Player&pa=showpage&pid=1
              - cell "GM TestUser" [ref=f1e176]
              - cell "25" [ref=f1e177]
              - cell "2026-02-20" [ref=f1e178]
            - row [ref=f1e179]:
              - cell [ref=f1e180]:
                - link "Stars Guard" [ref=f1e181] [cursor=pointer]:
                  - /url: modules.php?name=Player&pa=showpage&pid=4
              - cell "GM Stars" [ref=f1e182]
              - cell "25" [ref=f1e183]
              - cell "2026-02-20" [ref=f1e184]
```

# Test source

```ts
  83  |       // eslint-disable-next-line playwright/no-wait-for-timeout -- deliberate settle: let a transiently-failing render advance before retrying
  84  |       await page.waitForTimeout(STABLE_SETTLE_MS);
  85  |       continue;
  86  |     }
  87  |     if (prev && consecutiveDiffRatio(prev, shot) <= STABLE_MAX_DIFF_RATIO) {
  88  |       mkdirSync(dirname(path), { recursive: true });
  89  |       writeFileSync(path, shot);
  90  |       return;
  91  |     }
  92  |     prev = shot;
  93  |     // eslint-disable-next-line playwright/no-wait-for-timeout -- deliberate settle: let the render advance (fonts/images/height) before the next sample
  94  |     await page.waitForTimeout(STABLE_SETTLE_MS);
  95  |   }
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
  117 |   // Re-establish the same visual state after a (re)load: settle the network,
  118 |   // wait for the anchor, and re-trigger the HTMX tab swap if any. Runs after
  119 |   // both the initial navigation and the render-B reload.
  120 |   async function settle(): Promise<void> {
  121 |     await page.waitForLoadState('networkidle');
  122 |     await anchor.waitFor({ state: 'visible' });
  123 |     if (tab) {
  124 |       await page.locator(tab.trigger).first().click();
  125 |       await page.locator(tab.swapTarget).first().waitFor({ state: 'visible' });
  126 |       await page.waitForLoadState('networkidle');
  127 |     }
  128 |   }
  129 | 
  130 |   await gotoWithRetry(page, row.url);
  131 |   await assertNoPhpErrors(page, `on ${row.url}`);
  132 |   await settle();
  133 | 
  134 |   // What to screenshot, and whether it's a full-page capture (page only).
  135 |   const fullPage = !tab?.swapTarget && !row.elementScreenshot;
  136 |   const captureTarget: Locator | Page = tab?.swapTarget
  137 |     ? page.locator(tab.swapTarget).first()
  138 |     : row.elementScreenshot
  139 |       ? anchor
  140 |       : page;
  141 | 
  142 |   // Capture options for the raw PR renders. Deliberately EXCLUDE
  143 |   // maxDiffPixelRatio — that governs the toHaveScreenshot() gate below, not a
  144 |   // raw render capture.
  145 |   const captureOpts = {
  146 |     animations: 'disabled' as const,
  147 |     mask: buildMasks(page, row.extraMask),
  148 |     ...(fullPage ? { fullPage: true } : {}),
  149 |   };
  150 | 
  151 |   // Render A — the PR's actual render of this cell. captureStable retries a
  152 |   // thrown capture and re-samples until settled; if every attempt throws it
  153 |   // writes no .a.png and the gallery builder triages the cell as infra.
  154 |   await captureStable(page, captureTarget, `${ACTUALS_DIR}/${title}.a.png`, captureOpts);
  155 | 
  156 |   // Render B — an independent second render after a full reload, used to demote
  157 |   // self-disagreeing (flaky) cells out of the change gallery.
  158 |   try {
  159 |     await page.reload({ waitUntil: 'load' });
  160 |     await settle();
  161 |     await captureStable(page, captureTarget, `${ACTUALS_DIR}/${title}.b.png`, captureOpts);
  162 |   } catch {
  163 |     // A missing .b.png skips the self-stability check (gallery handles null B).
  164 |   }
  165 | 
  166 |   // The pass/fail gate stays LAST and unchanged — this is what the
  167 |   // `update-baselines` regen workflow signs off and what the green/red check
  168 |   // reflects. The gallery above is independent of this assertion's outcome.
  169 |   const screenshotOpts = {
  170 |     animations: 'disabled' as const,
  171 |     mask: buildMasks(page, row.extraMask),
  172 |     ...(row.extraMaxDiffPixelRatio !== undefined
  173 |       ? { maxDiffPixelRatio: row.extraMaxDiffPixelRatio }
  174 |       : {}),
  175 |   };
  176 | 
  177 |   if (tab?.swapTarget) {
  178 |     const target = page.locator(tab.swapTarget).first();
  179 |     await expect(target).toHaveScreenshot(filename, screenshotOpts);
  180 |   } else if (row.elementScreenshot) {
  181 |     await expect(anchor).toHaveScreenshot(filename, screenshotOpts);
  182 |   } else {
> 183 |     await expect(page).toHaveScreenshot(filename, {
      |     ^ Error: A snapshot doesn't exist at /ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots/rivalries-gms-pair-mobile.png, writing actual.
  184 |       fullPage: true,
  185 |       ...screenshotOpts,
  186 |     });
  187 |   }
  188 | }
  189 | 
  190 | function rowsByAuth(auth: AuthMode): VrRow[] {
  191 |   return VR_MANIFEST.filter((r) => r.auth === auth);
  192 | }
  193 | 
  194 | function expandRow(row: VrRow): Array<{
  195 |   state: StateVariant;
  196 |   viewport: Viewport;
  197 |   tab?: HtmxTab;
  198 |   testName: string;
  199 | }> {
  200 |   const states = row.states ?? [DEFAULT_STATE];
  201 |   const viewports = row.viewports ?? ['desktop'];
  202 |   const tabs: Array<HtmxTab | undefined> = [undefined, ...(row.htmxTabs ?? [])];
  203 |   const cells: Array<{
  204 |     state: StateVariant;
  205 |     viewport: Viewport;
  206 |     tab?: HtmxTab;
  207 |     testName: string;
  208 |   }> = [];
  209 | 
  210 |   for (const state of states) {
  211 |     for (const viewport of viewports) {
  212 |       for (const tab of tabs) {
  213 |         const filename = snapshotFilename(row, state, viewport, tab);
  214 |         cells.push({
  215 |           state,
  216 |           viewport,
  217 |           tab: tab ?? undefined,
  218 |           testName: filename.replace(/\.png$/, ''),
  219 |         });
  220 |       }
  221 |     }
  222 |   }
  223 |   return cells;
  224 | }
  225 | 
  226 | function registerTests(
  227 |   testFn: typeof publicTest,
  228 |   auth: AuthMode,
  229 |   label: string,
  230 |   beforeEachHook?: (fixtures: { appState: (s: Record<string, string>) => Promise<void> }) => Promise<void>,
  231 | ): void {
  232 |   testFn.describe(`Visual regression — ${label}`, () => {
  233 |     if (beforeEachHook) {
  234 |       testFn.beforeEach(async ({ appState }) => {
  235 |         await beforeEachHook({ appState });
  236 |       });
  237 |     }
  238 | 
  239 |     for (const row of rowsByAuth(auth)) {
  240 |       const cells = expandRow(row);
  241 |       for (const cell of cells) {
  242 |         testFn(cell.testName, async ({ appState, page }) => {
  243 |           if (cell.state.appState && Object.keys(cell.state.appState).length > 0) {
  244 |             await appState(cell.state.appState);
  245 |           }
  246 |           if (row.notes) {
  247 |             console.log(`[visual-regression] ${row.name}: ${row.notes}`);
  248 |           }
  249 |           await captureSnapshot(page, row, cell.state, cell.viewport, cell.tab);
  250 |         });
  251 |       }
  252 |     }
  253 |   });
  254 | }
  255 | 
  256 | // ============================================================
  257 | // Public visual regression — no authentication required
  258 | // ============================================================
  259 | 
  260 | registerTests(publicTest, 'public', 'public pages (full-page)', async ({ appState }) => {
  261 |   await appState({ 'Trivia Mode': 'Off' });
  262 | });
  263 | 
  264 | // ============================================================
  265 | // Authenticated visual regression — requires test user
  266 | // ============================================================
  267 | 
  268 | registerTests(authTest, 'auth', 'authenticated pages (full-page)');
  269 | 
  270 | // ============================================================
  271 | // Non-admin visual regression — roles_mask=0, no franchise
  272 | // ============================================================
  273 | 
  274 | registerTests(authRegularTest, 'auth-regular', 'non-admin authenticated pages');
  275 | 
```