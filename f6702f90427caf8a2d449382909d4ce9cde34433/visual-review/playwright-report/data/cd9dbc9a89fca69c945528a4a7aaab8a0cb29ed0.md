# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke/visual-regression.spec.ts >> Visual regression — authenticated pages (full-page) >> last-sim-recap
- Location: tests/e2e/smoke/visual-regression.spec.ts:242:9

# Error details

```
Error: A snapshot doesn't exist at /ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots/last-sim-recap.png, writing actual.
```

# Page snapshot

```yaml
- generic [active] [ref=f1e1]:
  - navigation [ref=f1e2]:
    - link "Switch to localhost" [ref=f1e5] [cursor=pointer]:
      - /url: http://localhost/ibl5/modules.php?name=News
    - generic [ref=f1e10]:
      - link "IBL Sim League" [ref=f1e11] [cursor=pointer]:
        - /url: index.php
        - generic [ref=f1e18]:
          - generic [ref=f1e19]: IBL
          - generic [ref=f1e20]: Sim League
      - generic [ref=f1e21]:
        - button "Debug" [ref=f1e23]
        - generic [ref=f1e30]:
          - button "Season" [ref=f1e31]
          - option "IBL" [selected]
          - option "Olympics"
        - button "Stats" [ref=f1e39]
        - button "History" [ref=f1e47]
        - button "Community" [ref=f1e55]
        - generic [ref=f1e62]:
          - button "Teams" [ref=f1e64]
          - button "Team Logo My Team" [ref=f1e74]:
            - img "Team Logo" [ref=f1e76]
            - generic [ref=f1e77]: My Team
  - main [ref=f1e80]:
    - heading "News" [level=1] [ref=f1e81]
    - generic [ref=f1e82]:
      - generic [ref=f1e83]:
        - generic [ref=f1e84]: Mar 1 – Mar 7, 2026 (2 games)
        - generic [ref=f1e85]:
          - text: "Last sim:"
          - generic [ref=f1e86]: "1"
          - generic [ref=f1e87]: –
          - text: "1"
        - generic [ref=f1e88]:
          - generic [ref=f1e89]: "Net margin: +9"
          - generic [ref=f1e90]:
            - generic [ref=f1e91]:
              - generic [ref=f1e92]: "Best:"
              - generic [ref=f1e93]: +16 @ Cougars
            - generic [ref=f1e94]:
              - generic [ref=f1e95]: "Worst:"
              - generic [ref=f1e96]: −7 vs Stars
      - tablist "Games in last sim" [ref=f1e97]:
        - tab "@ Cougars Mar 3 W 107–91" [selected] [ref=f1e98] [cursor=pointer]:
          - generic [ref=f1e99]:
            - generic [ref=f1e100]: "@"
            - generic [ref=f1e101]: Cougars
            - generic [ref=f1e102]: Mar 3
          - generic [ref=f1e103]:
            - generic [ref=f1e104]: W
            - generic [ref=f1e105]: 107–91
        - tab "vs Stars Mar 5 L 95–88" [ref=f1e106] [cursor=pointer]:
          - generic [ref=f1e107]:
            - generic [ref=f1e108]: vs
            - generic [ref=f1e109]: Stars
            - generic [ref=f1e110]: Mar 5
          - generic [ref=f1e111]:
            - generic [ref=f1e112]: L
            - generic [ref=f1e113]: 95–88
      - tabpanel "@ Cougars Mar 3 W 107–91" [ref=f1e114]:
        - generic [ref=f1e115]:
          - generic [ref=f1e116]: W +16
          - generic [ref=f1e117]: "@ Cougars"
          - generic [ref=f1e118]: Mar 3, 2026
        - generic [ref=f1e120]:
          - generic [ref=f1e121]:
            - generic [ref=f1e122]:
              - heading "Final" [level=4] [ref=f1e123]
              - link "Box score" [ref=f1e124] [cursor=pointer]:
                - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
            - generic [ref=f1e125]:
              - generic [ref=f1e126]:
                - link [ref=f1e127] [cursor=pointer]:
                  - /url: modules.php?name=Team&op=team&teamid=1
                - link "Metros 4–2" [ref=f1e128] [cursor=pointer]:
                  - /url: modules.php?name=Team&op=team&teamid=1
                  - text: Metros
                  - generic [ref=f1e129]: 4–2
                - link "107" [ref=f1e130] [cursor=pointer]:
                  - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
              - generic [ref=f1e131]:
                - link [ref=f1e132] [cursor=pointer]:
                  - /url: modules.php?name=Team&op=team&teamid=3
                - link "Cougars 30–30" [ref=f1e133] [cursor=pointer]:
                  - /url: modules.php?name=Team&op=team&teamid=3
                  - text: Cougars
                  - generic [ref=f1e134]: 30–30
                - link "91" [ref=f1e135] [cursor=pointer]:
                  - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
          - generic [ref=f1e136]:
            - heading "Quarter margin" [level=4] [ref=f1e137]
            - generic [ref=f1e139]:
              - generic [ref=f1e140]:
                - generic [ref=f1e141]: "+6"
                - generic [ref=f1e144]: −1
                - generic [ref=f1e147]: "+9"
                - generic [ref=f1e150]: "+2"
              - generic [ref=f1e153]:
                - generic [ref=f1e154]: Q1
                - generic [ref=f1e155]: Q2
                - generic [ref=f1e156]: Q3
                - generic [ref=f1e157]: Q4
          - generic [ref=f1e158]:
            - heading "Injury report" [level=4] [ref=f1e159]
            - generic [ref=f1e160]:
              - generic [ref=f1e162]:
                - link "Metros" [ref=f1e164] [cursor=pointer]:
                  - /url: modules.php?name=Team&op=team&teamid=1
                - generic [ref=f1e165]: Healthy
              - generic [ref=f1e167]:
                - link "Cougars" [ref=f1e169] [cursor=pointer]:
                  - /url: modules.php?name=Team&op=team&teamid=3
                - generic [ref=f1e170]: Healthy
        - generic [ref=f1e171]:
          - generic [ref=f1e172]:
            - generic [ref=f1e173]: PG
            - generic [ref=f1e175]:
              - link [ref=f1e176] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=20
              - link "PG" [ref=f1e177] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=20
              - generic [ref=f1e178]: 0 pts
            - generic [ref=f1e179]:
              - link [ref=f1e180] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - link:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - generic [ref=f1e181]: 0 pts
          - generic [ref=f1e182]:
            - generic [ref=f1e183]: SG
            - generic [ref=f1e185]:
              - link [ref=f1e186] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=1
              - link "Player" [ref=f1e187] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=1
              - generic [ref=f1e188]: 0 pts
            - generic [ref=f1e189]:
              - link [ref=f1e190] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - link:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - generic [ref=f1e191]: 0 pts
          - generic [ref=f1e192]:
            - generic [ref=f1e193]: SF
            - generic [ref=f1e195]:
              - link [ref=f1e196] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=21
              - link "SF" [ref=f1e197] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=21
              - generic [ref=f1e198]: 0 pts
            - generic [ref=f1e199]:
              - link [ref=f1e200] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - link:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - generic [ref=f1e201]: 0 pts
          - generic [ref=f1e202]:
            - generic [ref=f1e203]: PF
            - generic [ref=f1e205]:
              - link [ref=f1e206] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=2
              - link "Two" [ref=f1e207] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=2
              - generic [ref=f1e208]: 0 pts
            - generic [ref=f1e209]:
              - link [ref=f1e210] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - link:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - generic [ref=f1e211]: 0 pts
          - generic [ref=f1e212]:
            - generic [ref=f1e213]: C
            - generic [ref=f1e215]:
              - link [ref=f1e216] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=22
              - link "Center" [ref=f1e217] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=22
              - generic [ref=f1e218]: 0 pts
            - generic [ref=f1e219]:
              - link [ref=f1e220] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - link:
                - /url: modules.php?name=Player&pa=showpage&pid=0
              - generic [ref=f1e221]: 0 pts
    - article [ref=f1e222]:
      - generic [ref=f1e223]:
        - heading "All-Star Game recap" [level=2] [ref=f1e224]
        - generic [ref=f1e225]:
          - time [ref=f1e230]: Thursday, March 5 at 06:00 PM UTC
          - generic [ref=f1e231]: admin
          - generic [ref=f1e235]: 15 reads
      - generic [ref=f1e239]: The Eastern Conference won the All-Star Game.
      - generic [ref=f1e240]:
        - generic [ref=f1e241]:
          - link "Read More..." [ref=f1e242] [cursor=pointer]:
            - /url: modules.php?name=News&file=article&sid=29&mode=&order=0&thold=0
          - text: "|"
          - generic [ref=f1e243]: 88 bytes more
        - link "IBL News" [ref=f1e244] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=1
    - article [ref=f1e247]:
      - generic [ref=f1e248]:
        - heading "Trade deadline approaches" [level=2] [ref=f1e249]
        - generic [ref=f1e250]:
          - time [ref=f1e255]: Sunday, March 8 at 02:00 PM UTC
          - generic [ref=f1e256]: admin
          - generic [ref=f1e260]: 25 reads
      - generic [ref=f1e264]: Teams are making final moves before the deadline.
      - generic [ref=f1e265]:
        - generic [ref=f1e266]:
          - link "Read More..." [ref=f1e267] [cursor=pointer]:
            - /url: modules.php?name=News&file=article&sid=28&mode=&order=0&thold=0
          - text: "|"
          - generic [ref=f1e268]: 92 bytes more
        - link "IBL News" [ref=f1e269] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=1
    - article [ref=f1e272]:
      - generic [ref=f1e273]:
        - heading "Welcome to the new IBL season" [level=2] [ref=f1e274]
        - generic [ref=f1e275]:
          - time [ref=f1e280]: Tuesday, March 10 at 10:00 AM UTC
          - generic [ref=f1e281]: admin
          - generic [ref=f1e285]: 10 reads
      - generic [ref=f1e289]: The new season is here with exciting changes and new rosters.
      - generic [ref=f1e290]:
        - generic [ref=f1e291]:
          - link "Read More..." [ref=f1e292] [cursor=pointer]:
            - /url: modules.php?name=News&file=article&sid=27&mode=&order=0&thold=0
          - text: "|"
          - generic [ref=f1e293]: 123 bytes more
        - link "IBL News" [ref=f1e294] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=1
    - article [ref=f1e297]:
      - generic [ref=f1e298]:
        - heading "Blockbuster trade shakes up the league" [level=2] [ref=f1e299]
        - generic [ref=f1e300]:
          - time [ref=f1e305]: Tuesday, March 3 at 10:00 AM UTC
          - generic [ref=f1e306]: admin
          - generic [ref=f1e310]: 0 reads
      - generic [ref=f1e314]: Three-team deal completed
      - generic [ref=f1e315]:
        - link "Read More..." [ref=f1e317] [cursor=pointer]:
          - /url: modules.php?name=News&file=article&sid=25&mode=&order=0&thold=0
        - link "Trade News" [ref=f1e318] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=2
    - article [ref=f1e321]:
      - generic [ref=f1e322]:
        - heading "Stars acquire top free agent" [level=2] [ref=f1e323]
        - generic [ref=f1e324]:
          - time [ref=f1e329]: Wednesday, March 4 at 10:00 AM UTC
          - generic [ref=f1e330]: admin
          - generic [ref=f1e334]: 0 reads
      - generic [ref=f1e338]: Big move for the Stars
      - generic [ref=f1e339]:
        - link "Read More..." [ref=f1e341] [cursor=pointer]:
          - /url: modules.php?name=News&file=article&sid=24&mode=&order=0&thold=0
        - link "IBL News" [ref=f1e342] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=1
    - article [ref=f1e345]:
      - generic [ref=f1e346]:
        - heading "Metros win season opener" [level=2] [ref=f1e347]
        - generic [ref=f1e348]:
          - time [ref=f1e353]: Thursday, March 5 at 10:00 AM UTC
          - generic [ref=f1e354]: admin
          - generic [ref=f1e358]: 0 reads
      - generic [ref=f1e362]: Great start to the season
      - generic [ref=f1e363]:
        - link "Read More..." [ref=f1e365] [cursor=pointer]:
          - /url: modules.php?name=News&file=article&sid=23&mode=&order=0&thold=0
        - link "IBL News" [ref=f1e366] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=1
    - article [ref=f1e369]:
      - generic [ref=f1e370]:
        - heading [level=2] [ref=f1e371]:
          - link "Category" [ref=f1e372] [cursor=pointer]:
            - /url: modules.php?name=News&file=categories&op=newindex&catid=3
          - text: ": The Mavericks extend the all-star guard"
        - generic [ref=f1e374]:
          - time [ref=f1e379]: Thursday, February 5 at 10:00 AM UTC
          - generic [ref=f1e380]: admin
          - generic [ref=f1e384]: 0 reads
      - generic [ref=f1e388]: The deal is the largest in IBL history
      - generic [ref=f1e389]:
        - link "Read More..." [ref=f1e391] [cursor=pointer]:
          - /url: modules.php?name=News&file=article&sid=22&mode=&order=0&thold=0
        - link "IBL News" [ref=f1e392] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=1
    - article [ref=f1e395]:
      - generic [ref=f1e396]:
        - heading [level=2] [ref=f1e397]:
          - link "Category" [ref=f1e398] [cursor=pointer]:
            - /url: modules.php?name=News&file=categories&op=newindex&catid=2
          - text: ": The Pilots trade the young prospect"
        - generic [ref=f1e400]:
          - time [ref=f1e405]: Friday, February 6 at 10:00 AM UTC
          - generic [ref=f1e406]: admin
          - generic [ref=f1e410]: 0 reads
      - generic [ref=f1e414]: The rebuild enters the next phase
      - generic [ref=f1e415]:
        - link "Read More..." [ref=f1e417] [cursor=pointer]:
          - /url: modules.php?name=News&file=article&sid=21&mode=&order=0&thold=0
        - link "IBL News" [ref=f1e418] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=1
    - article [ref=f1e421]:
      - generic [ref=f1e422]:
        - heading [level=2] [ref=f1e423]:
          - link "Category" [ref=f1e424] [cursor=pointer]:
            - /url: modules.php?name=News&file=categories&op=newindex&catid=8
          - text: ": The Nuggets sign the veteran shooter"
        - generic [ref=f1e426]:
          - time [ref=f1e431]: Saturday, February 7 at 10:00 AM UTC
          - generic [ref=f1e432]: admin
          - generic [ref=f1e436]: 0 reads
      - generic [ref=f1e440]: The addition fills the gap
      - generic [ref=f1e441]:
        - link "Read More..." [ref=f1e443] [cursor=pointer]:
          - /url: modules.php?name=News&file=article&sid=20&mode=&order=0&thold=0
        - link "IBL News" [ref=f1e444] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=1
    - article [ref=f1e447]:
      - generic [ref=f1e448]:
        - heading [level=2] [ref=f1e449]:
          - link "Category" [ref=f1e450] [cursor=pointer]:
            - /url: modules.php?name=News&file=categories&op=newindex&catid=1
          - text: ": The Bucks waive the reserve center"
        - generic [ref=f1e452]:
          - time [ref=f1e457]: Sunday, February 8 at 10:00 AM UTC
          - generic [ref=f1e458]: admin
          - generic [ref=f1e462]: 0 reads
      - generic [ref=f1e466]: The move opens the roster spot
      - generic [ref=f1e467]:
        - link "Read More..." [ref=f1e469] [cursor=pointer]:
          - /url: modules.php?name=News&file=article&sid=19&mode=&order=0&thold=0
        - link "IBL News" [ref=f1e470] [cursor=pointer]:
          - /url: modules.php?name=News&new_topic=1
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
      |     ^ Error: A snapshot doesn't exist at /ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots/last-sim-recap.png, writing actual.
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