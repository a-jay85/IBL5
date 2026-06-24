# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke/visual-regression.spec.ts >> Visual regression — public pages (full-page) >> injuries
- Location: tests/e2e/smoke/visual-regression.spec.ts:126:9

# Error details

```
TimeoutError: page.goto: Timeout 20000ms exceeded.
Call log:
  - navigating to "http://php/ibl5/modules.php?name=Injuries", waiting until "load"

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - navigation [ref=e2]:
    - generic [ref=e4]:
      - link "IBL Sim League" [ref=e5] [cursor=pointer]:
        - /url: index.php
        - img [ref=e8]
        - generic [ref=e12]: IBL Sim League
      - generic [ref=e13]:
        - generic [ref=e14]:
          - button "Season" [ref=e15]:
            - img [ref=e17]
            - text: Season
            - img [ref=e19]
          - generic [ref=e22]:
            - generic [ref=e23]:
              - link "Standings" [ref=e24] [cursor=pointer]:
                - /url: modules.php?name=Standings
                - generic [ref=e25]: Standings
              - link "Schedule" [ref=e26] [cursor=pointer]:
                - /url: modules.php?name=Schedule
                - generic [ref=e27]: Schedule
              - link "Injuries" [ref=e28] [cursor=pointer]:
                - /url: modules.php?name=Injuries
                - generic [ref=e29]: Injuries
              - link "Player Database" [ref=e30] [cursor=pointer]:
                - /url: modules.php?name=PlayerDatabase
                - generic [ref=e31]: Player Database
              - link "Player Export" [ref=e32] [cursor=pointer]:
                - /url: modules.php?name=PlayerExportGuide
                - generic [ref=e33]: Player Export
              - link "Cap Space" [ref=e34] [cursor=pointer]:
                - /url: modules.php?name=CapSpace
                - generic [ref=e35]: Cap Space
              - link "Projected Draft Order" [ref=e36] [cursor=pointer]:
                - /url: modules.php?name=ProjectedDraftOrder
                - generic [ref=e37]: Projected Draft Order
              - link "Draft Pick Locator" [ref=e38] [cursor=pointer]:
                - /url: modules.php?name=DraftPickLocator
                - generic [ref=e39]: Draft Pick Locator
              - link "Training Camp Ratings Diff" [ref=e40] [cursor=pointer]:
                - /url: modules.php?name=TrainingCampRatingsDiff
                - generic [ref=e41]: Training Camp Ratings Diff
              - link "Free Agency Preview" [ref=e42] [cursor=pointer]:
                - /url: modules.php?name=FreeAgencyPreview
                - generic [ref=e43]: Free Agency Preview
              - link "Contract List" [ref=e44] [cursor=pointer]:
                - /url: modules.php?name=ContractList
                - generic [ref=e45]: Contract List
              - link "Player Movement" [ref=e46] [cursor=pointer]:
                - /url: modules.php?name=PlayerMovement
                - generic [ref=e47]: Player Movement
              - link "JSB Export" [ref=e48] [cursor=pointer]:
                - /url: ibl/IBL
                - generic [ref=e49]:
                  - text: JSB Export
                  - img [ref=e50]
            - generic [ref=e52]:
              - text: League
              - generic [ref=e53]:
                - combobox "League" [ref=e54]:
                  - option "IBL" [selected]
                  - option "Olympics"
                - img [ref=e55]
        - generic [ref=e57]:
          - button "Stats" [ref=e58]:
            - img [ref=e60]
            - text: Stats
            - img [ref=e62]
          - generic [ref=e66]:
            - link "League Starters" [ref=e67] [cursor=pointer]:
              - /url: modules.php?name=LeagueStarters
              - generic [ref=e68]: League Starters
            - link "Compare Players" [ref=e69] [cursor=pointer]:
              - /url: modules.php?name=ComparePlayers
              - generic [ref=e70]: Compare Players
            - link "Season Highs" [ref=e71] [cursor=pointer]:
              - /url: modules.php?name=SeasonHighs
              - generic [ref=e72]: Season Highs
            - link "Series Records" [ref=e73] [cursor=pointer]:
              - /url: modules.php?name=SeriesRecords
              - generic [ref=e74]: Series Records
            - link "Team Off/Def Stats" [ref=e75] [cursor=pointer]:
              - /url: modules.php?name=TeamOffDefStats
              - generic [ref=e76]: Team Off/Def Stats
        - generic [ref=e77]:
          - button "History" [ref=e78]:
            - img [ref=e80]
            - text: History
            - img [ref=e82]
          - generic [ref=e86]:
            - link "Franchise History" [ref=e87] [cursor=pointer]:
              - /url: modules.php?name=FranchiseHistory
              - generic [ref=e88]: Franchise History
            - link "Transaction History" [ref=e89] [cursor=pointer]:
              - /url: modules.php?name=TransactionHistory
              - generic [ref=e90]: Transaction History
            - link "Draft History" [ref=e91] [cursor=pointer]:
              - /url: modules.php?name=DraftHistory
              - generic [ref=e92]: Draft History
            - link "Award History" [ref=e93] [cursor=pointer]:
              - /url: modules.php?name=AwardHistory
              - generic [ref=e94]: Award History
            - link "Record Holders" [ref=e95] [cursor=pointer]:
              - /url: modules.php?name=RecordHolders
              - generic [ref=e96]: Record Holders
            - link "Franchise Record Book" [ref=e97] [cursor=pointer]:
              - /url: modules.php?name=FranchiseRecordBook
              - generic [ref=e98]: Franchise Record Book
            - link "All-Star Appearances" [ref=e99] [cursor=pointer]:
              - /url: modules.php?name=AllStarAppearances
              - generic [ref=e100]: All-Star Appearances
            - link "Season Leaderboards" [ref=e101] [cursor=pointer]:
              - /url: modules.php?name=SeasonLeaderboards
              - generic [ref=e102]: Season Leaderboards
            - link "Career Leaderboards" [ref=e103] [cursor=pointer]:
              - /url: modules.php?name=CareerLeaderboards
              - generic [ref=e104]: Career Leaderboards
            - link "Season Archive" [ref=e105] [cursor=pointer]:
              - /url: modules.php?name=SeasonArchive
              - generic [ref=e106]: Season Archive
            - link "1-On-1 Game" [ref=e107] [cursor=pointer]:
              - /url: modules.php?name=OneOnOneGame
              - generic [ref=e108]: 1-On-1 Game
        - generic [ref=e109]:
          - button "Community" [ref=e110]:
            - img [ref=e112]
            - text: Community
            - img [ref=e114]
          - generic [ref=e118]:
            - link "Discord Server" [ref=e119] [cursor=pointer]:
              - /url: https://discord.com/invite/QXwBQxR
              - generic [ref=e120]:
                - text: Discord Server
                - img [ref=e121]
            - link "Prime Time Football" [ref=e123] [cursor=pointer]:
              - /url: http://www.thakfu.com/ptf/index.php
              - generic [ref=e124]:
                - text: Prime Time Football
                - img [ref=e125]
            - link "Activity Tracker" [ref=e127] [cursor=pointer]:
              - /url: modules.php?name=ActivityTracker
              - generic [ref=e128]: Activity Tracker
            - link "Topics (News)" [ref=e129] [cursor=pointer]:
              - /url: modules.php?name=Topics
              - generic [ref=e130]: Topics (News)
        - generic [ref=e132]:
          - button "Teams" [ref=e133]:
            - img [ref=e135]
            - text: Teams
            - img [ref=e139]
          - generic [ref=e143]:
            - generic [ref=e144]:
              - generic [ref=e145]: Western Conference
              - generic [ref=e146]: Midwest
              - link "Dallas Mavericks" [ref=e147] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=21
                - text: Dallas Mavericks
              - link "Houston Apollos" [ref=e148] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=13
                - text: Houston Apollos
              - link "Memphis Blues" [ref=e149] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=15
                - text: Memphis Blues
              - link "Portland Pioneers" [ref=e150] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=11
                - text: Portland Pioneers
              - link "San Antonio Spurs" [ref=e151] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=10
                - text: San Antonio Spurs
              - link "Utah Jazz" [ref=e152] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=27
                - text: Utah Jazz
              - generic [ref=e153]: Pacific
              - link "Denver Nuggets" [ref=e154] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=19
                - text: Denver Nuggets
              - link "Los Angeles Stars" [ref=e155] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=2
                - text: Los Angeles Stars
              - link "Minnesota Blizzard" [ref=e156] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=16
                - text: Minnesota Blizzard
              - link "Oklahoma City Thunder" [ref=e157] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=28
                - text: Oklahoma City Thunder
              - link "Phoenix Flames" [ref=e158] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=9
                - text: Phoenix Flames
              - link "Sacramento Pilots" [ref=e159] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=20
                - text: Sacramento Pilots
              - link "Seattle Supersonics" [ref=e160] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=23
                - text: Seattle Supersonics
            - generic [ref=e161]:
              - generic [ref=e162]: Eastern Conference
              - generic [ref=e163]: Atlantic
              - link "Boston Minutemen" [ref=e164] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=5
                - text: Boston Minutemen
              - link "Miami Monarchs" [ref=e165] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=8
                - text: Miami Monarchs
              - link "New Jersey Nets" [ref=e166] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=24
                - text: New Jersey Nets
              - link "New York Metros" [ref=e167] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=1
                - text: New York Metros
              - link "Orlando Tropics" [ref=e168] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=7
                - text: Orlando Tropics
              - link "Philadelphia Rage" [ref=e169] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=6
                - text: Philadelphia Rage
              - link "Toronto Huskies" [ref=e170] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=17
                - text: Toronto Huskies
              - generic [ref=e171]: Central
              - link "Atlanta Phoenixes" [ref=e172] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=14
                - text: Atlanta Phoenixes
              - link "Charlotte Royals" [ref=e173] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=12
                - text: Charlotte Royals
              - link "Chicago Cougars" [ref=e174] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=3
                - text: Chicago Cougars
              - link "Cleveland Cavaliers" [ref=e175] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=22
                - text: Cleveland Cavaliers
              - link "Detroit Diesels" [ref=e176] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=4
                - text: Detroit Diesels
              - link "Indiana Pacers" [ref=e177] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=26
                - text: Indiana Pacers
              - link "Milwaukee Bucks" [ref=e178] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=18
                - text: Milwaukee Bucks
              - link "Washington Generals" [ref=e179] [cursor=pointer]:
                - /url: modules.php?name=Team&op=team&teamid=25
                - text: Washington Generals
        - generic [ref=e180]:
          - button "Login" [ref=e181]:
            - img [ref=e183]
            - text: Login
            - img [ref=e185]
          - generic [ref=e188]:
            - generic [ref=e190]:
              - generic [ref=e191]:
                - text: Username
                - generic [ref=e192]:
                  - img [ref=e194]
                  - textbox "Username" [ref=e196]:
                    - /placeholder: Enter username
              - generic [ref=e197]:
                - text: Password
                - generic [ref=e198]:
                  - img [ref=e200]
                  - textbox "Password" [ref=e202]:
                    - /placeholder: Enter password
              - generic [ref=e203]:
                - generic [ref=e204]:
                  - checkbox "Remember me" [ref=e205]
                  - img [ref=e206]
                - text: Remember me
              - button "Login" [ref=e208]
            - generic [ref=e209]:
              - link "Sign Up" [ref=e210] [cursor=pointer]:
                - /url: modules.php?name=YourAccount&op=new_user
                - generic [ref=e211]: Sign Up
              - link "Forgot Password" [ref=e212] [cursor=pointer]:
                - /url: modules.php?name=YourAccount&op=pass_lost
                - generic [ref=e213]: Forgot Password
        - button "Switch to mobile view" [ref=e214]:
          - img
      - generic [ref=e216]:
        - button "Switch to desktop view" [ref=e217]:
          - img
        - button "Toggle menu" [ref=e219]
  - navigation [ref=e220]:
    - generic [ref=e221]:
      - generic [ref=e222]:
        - button "Teams" [ref=e223]:
          - generic [ref=e224]:
            - img [ref=e226]
            - text: Teams
          - img [ref=e230]
        - generic [ref=e232]:
          - generic [ref=e234]: Eastern Conference
          - generic [ref=e236]: Atlantic
          - link "Boston Minutemen" [ref=e237] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=5
            - text: Boston Minutemen
          - link "Miami Monarchs" [ref=e238] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=8
            - text: Miami Monarchs
          - link "New Jersey Nets" [ref=e239] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=24
            - text: New Jersey Nets
          - link "New York Metros" [ref=e240] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=1
            - text: New York Metros
          - link "Orlando Tropics" [ref=e241] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=7
            - text: Orlando Tropics
          - link "Philadelphia Rage" [ref=e242] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=6
            - text: Philadelphia Rage
          - link "Toronto Huskies" [ref=e243] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=17
            - text: Toronto Huskies
          - generic [ref=e245]: Central
          - link "Atlanta Phoenixes" [ref=e246] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=14
            - text: Atlanta Phoenixes
          - link "Charlotte Royals" [ref=e247] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=12
            - text: Charlotte Royals
          - link "Chicago Cougars" [ref=e248] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=3
            - text: Chicago Cougars
          - link "Cleveland Cavaliers" [ref=e249] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=22
            - text: Cleveland Cavaliers
          - link "Detroit Diesels" [ref=e250] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=4
            - text: Detroit Diesels
          - link "Indiana Pacers" [ref=e251] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=26
            - text: Indiana Pacers
          - link "Milwaukee Bucks" [ref=e252] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=18
            - text: Milwaukee Bucks
          - link "Washington Generals" [ref=e253] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=25
            - text: Washington Generals
          - generic [ref=e255]: Western Conference
          - generic [ref=e257]: Midwest
          - link "Dallas Mavericks" [ref=e258] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=21
            - text: Dallas Mavericks
          - link "Houston Apollos" [ref=e259] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=13
            - text: Houston Apollos
          - link "Memphis Blues" [ref=e260] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=15
            - text: Memphis Blues
          - link "Portland Pioneers" [ref=e261] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=11
            - text: Portland Pioneers
          - link "San Antonio Spurs" [ref=e262] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=10
            - text: San Antonio Spurs
          - link "Utah Jazz" [ref=e263] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=27
            - text: Utah Jazz
          - generic [ref=e265]: Pacific
          - link "Denver Nuggets" [ref=e266] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=19
            - text: Denver Nuggets
          - link "Los Angeles Stars" [ref=e267] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=2
            - text: Los Angeles Stars
          - link "Minnesota Blizzard" [ref=e268] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=16
            - text: Minnesota Blizzard
          - link "Oklahoma City Thunder" [ref=e269] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=28
            - text: Oklahoma City Thunder
          - link "Phoenix Flames" [ref=e270] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=9
            - text: Phoenix Flames
          - link "Sacramento Pilots" [ref=e271] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=20
            - text: Sacramento Pilots
          - link "Seattle Supersonics" [ref=e272] [cursor=pointer]:
            - /url: modules.php?name=Team&op=team&teamid=23
            - text: Seattle Supersonics
      - generic [ref=e273]:
        - button "Season" [ref=e274]:
          - generic [ref=e275]:
            - img [ref=e277]
            - text: Season
          - img [ref=e279]
        - generic [ref=e281]:
          - link "Standings" [ref=e282] [cursor=pointer]:
            - /url: modules.php?name=Standings
          - link "Schedule" [ref=e283] [cursor=pointer]:
            - /url: modules.php?name=Schedule
          - link "Injuries" [ref=e284] [cursor=pointer]:
            - /url: modules.php?name=Injuries
          - link "Player Database" [ref=e285] [cursor=pointer]:
            - /url: modules.php?name=PlayerDatabase
          - link "Player Export" [ref=e286] [cursor=pointer]:
            - /url: modules.php?name=PlayerExportGuide
          - link "Cap Space" [ref=e287] [cursor=pointer]:
            - /url: modules.php?name=CapSpace
          - link "Projected Draft Order" [ref=e288] [cursor=pointer]:
            - /url: modules.php?name=ProjectedDraftOrder
          - link "Draft Pick Locator" [ref=e289] [cursor=pointer]:
            - /url: modules.php?name=DraftPickLocator
          - link "Training Camp Ratings Diff" [ref=e290] [cursor=pointer]:
            - /url: modules.php?name=TrainingCampRatingsDiff
          - link "Free Agency Preview" [ref=e291] [cursor=pointer]:
            - /url: modules.php?name=FreeAgencyPreview
          - link "Contract List" [ref=e292] [cursor=pointer]:
            - /url: modules.php?name=ContractList
          - link "Player Movement" [ref=e293] [cursor=pointer]:
            - /url: modules.php?name=PlayerMovement
          - link "JSB Export" [ref=e294] [cursor=pointer]:
            - /url: ibl/IBL
            - text: JSB Export
            - img [ref=e295]
          - generic [ref=e297]:
            - text: League
            - generic [ref=e298]:
              - combobox "League" [ref=e299]:
                - option "IBL" [selected]
                - option "Olympics"
              - img [ref=e300]
      - generic [ref=e302]:
        - button "Stats" [ref=e303]:
          - generic [ref=e304]:
            - img [ref=e306]
            - text: Stats
          - img [ref=e308]
        - generic [ref=e310]:
          - link "League Starters" [ref=e311] [cursor=pointer]:
            - /url: modules.php?name=LeagueStarters
          - link "Compare Players" [ref=e312] [cursor=pointer]:
            - /url: modules.php?name=ComparePlayers
          - link "Season Highs" [ref=e313] [cursor=pointer]:
            - /url: modules.php?name=SeasonHighs
          - link "Series Records" [ref=e314] [cursor=pointer]:
            - /url: modules.php?name=SeriesRecords
          - link "Team Off/Def Stats" [ref=e315] [cursor=pointer]:
            - /url: modules.php?name=TeamOffDefStats
      - generic [ref=e316]:
        - button "History" [ref=e317]:
          - generic [ref=e318]:
            - img [ref=e320]
            - text: History
          - img [ref=e322]
        - generic [ref=e324]:
          - link "Franchise History" [ref=e325] [cursor=pointer]:
            - /url: modules.php?name=FranchiseHistory
          - link "Transaction History" [ref=e326] [cursor=pointer]:
            - /url: modules.php?name=TransactionHistory
          - link "Draft History" [ref=e327] [cursor=pointer]:
            - /url: modules.php?name=DraftHistory
          - link "Award History" [ref=e328] [cursor=pointer]:
            - /url: modules.php?name=AwardHistory
          - link "Record Holders" [ref=e329] [cursor=pointer]:
            - /url: modules.php?name=RecordHolders
          - link "Franchise Record Book" [ref=e330] [cursor=pointer]:
            - /url: modules.php?name=FranchiseRecordBook
          - link "All-Star Appearances" [ref=e331] [cursor=pointer]:
            - /url: modules.php?name=AllStarAppearances
          - link "Season Leaderboards" [ref=e332] [cursor=pointer]:
            - /url: modules.php?name=SeasonLeaderboards
          - link "Career Leaderboards" [ref=e333] [cursor=pointer]:
            - /url: modules.php?name=CareerLeaderboards
          - link "Season Archive" [ref=e334] [cursor=pointer]:
            - /url: modules.php?name=SeasonArchive
          - link "1-On-1 Game" [ref=e335] [cursor=pointer]:
            - /url: modules.php?name=OneOnOneGame
      - generic [ref=e336]:
        - button "Community" [ref=e337]:
          - generic [ref=e338]:
            - img [ref=e340]
            - text: Community
          - img [ref=e342]
        - generic [ref=e344]:
          - link "Discord Server" [ref=e345] [cursor=pointer]:
            - /url: https://discord.com/invite/QXwBQxR
            - text: Discord Server
            - img [ref=e346]
          - link "Prime Time Football" [ref=e348] [cursor=pointer]:
            - /url: http://www.thakfu.com/ptf/index.php
            - text: Prime Time Football
            - img [ref=e349]
          - link "Activity Tracker" [ref=e351] [cursor=pointer]:
            - /url: modules.php?name=ActivityTracker
          - link "Topics (News)" [ref=e352] [cursor=pointer]:
            - /url: modules.php?name=Topics
      - generic [ref=e353]:
        - button "Login" [ref=e354]:
          - generic [ref=e355]:
            - img [ref=e357]
            - text: Login
          - img [ref=e359]
        - generic [ref=e361]:
          - generic [ref=e363]:
            - generic [ref=e364]:
              - text: Username
              - generic [ref=e365]:
                - img [ref=e367]
                - textbox "Username" [ref=e369]:
                  - /placeholder: Enter username
            - generic [ref=e370]:
              - text: Password
              - generic [ref=e371]:
                - img [ref=e373]
                - textbox "Password" [ref=e375]:
                  - /placeholder: Enter password
            - generic [ref=e376]:
              - generic [ref=e377]:
                - checkbox "Remember me" [ref=e378]
                - img [ref=e379]
              - text: Remember me
            - button "Login" [ref=e381]
          - link "Sign Up" [ref=e382] [cursor=pointer]:
            - /url: modules.php?name=YourAccount&op=new_user
          - link "Forgot Password" [ref=e383] [cursor=pointer]:
            - /url: modules.php?name=YourAccount&op=pass_lost
```

# Test source

```ts
  1   | // Do NOT swap page.goto for gotoWithRetry in this PR — that's a behavior
  2   | // change; defer to a follow-up.
  3   | import type { Locator, Page } from '@playwright/test';
  4   | import { test as publicTest } from '../fixtures/public';
  5   | import { test as authTest } from '../fixtures/auth';
  6   | import { test as authRegularTest } from '../fixtures/auth-regular';
  7   | import { expect } from '../fixtures/base';
  8   | import { assertNoPhpErrors } from '../helpers/php-errors';
  9   | import {
  10  |   VR_MANIFEST,
  11  |   DEFAULT_STATE,
  12  |   snapshotFilename,
  13  |   type AuthMode,
  14  |   type StateVariant,
  15  |   type Viewport,
  16  |   type VrRow,
  17  |   type HtmxTab,
  18  | } from '../vr-manifest';
  19  | 
  20  | const GLOBAL_MASK_SELECTORS: string[] = [
  21  |   'time.local-time',
  22  |   '.news-article__meta',
  23  |   '[data-volatile="timestamp"]',
  24  | ];
  25  | 
  26  | function buildMasks(page: Page, extraMask: string[] = []): Locator[] {
  27  |   return [...GLOBAL_MASK_SELECTORS, ...extraMask].map((sel) => page.locator(sel));
  28  | }
  29  | 
  30  | async function captureSnapshot(
  31  |   page: Page,
  32  |   row: VrRow,
  33  |   state: StateVariant,
  34  |   viewport: Viewport,
  35  |   tab?: HtmxTab,
  36  | ): Promise<void> {
  37  |   if (viewport === 'mobile') {
  38  |     await page.setViewportSize({ width: 375, height: 812 });
  39  |   }
> 40  |   await page.goto(row.url);
      |              ^ TimeoutError: page.goto: Timeout 20000ms exceeded.
  41  |   await assertNoPhpErrors(page, `on ${row.url}`);
  42  |   await page.waitForLoadState('networkidle');
  43  |   const anchor = page.locator(row.anchor).first();
  44  |   await anchor.waitFor({ state: 'visible' });
  45  | 
  46  |   if (tab) {
  47  |     await page.locator(tab.trigger).first().click();
  48  |     await page.locator(tab.swapTarget).first().waitFor({ state: 'visible' });
  49  |     await page.waitForLoadState('networkidle');
  50  |   }
  51  | 
  52  |   const filename = snapshotFilename(row, state, viewport, tab);
  53  |   const screenshotOpts = {
  54  |     animations: 'disabled' as const,
  55  |     mask: buildMasks(page, row.extraMask),
  56  |     ...(row.extraMaxDiffPixelRatio !== undefined
  57  |       ? { maxDiffPixelRatio: row.extraMaxDiffPixelRatio }
  58  |       : {}),
  59  |   };
  60  | 
  61  |   if (tab?.swapTarget) {
  62  |     const target = page.locator(tab.swapTarget).first();
  63  |     await expect(target).toHaveScreenshot(filename, screenshotOpts);
  64  |   } else if (row.elementScreenshot) {
  65  |     await expect(anchor).toHaveScreenshot(filename, screenshotOpts);
  66  |   } else {
  67  |     await expect(page).toHaveScreenshot(filename, {
  68  |       fullPage: true,
  69  |       ...screenshotOpts,
  70  |     });
  71  |   }
  72  | }
  73  | 
  74  | function rowsByAuth(auth: AuthMode): VrRow[] {
  75  |   return VR_MANIFEST.filter((r) => r.auth === auth);
  76  | }
  77  | 
  78  | function expandRow(row: VrRow): Array<{
  79  |   state: StateVariant;
  80  |   viewport: Viewport;
  81  |   tab?: HtmxTab;
  82  |   testName: string;
  83  | }> {
  84  |   const states = row.states ?? [DEFAULT_STATE];
  85  |   const viewports = row.viewports ?? ['desktop'];
  86  |   const tabs: Array<HtmxTab | undefined> = [undefined, ...(row.htmxTabs ?? [])];
  87  |   const cells: Array<{
  88  |     state: StateVariant;
  89  |     viewport: Viewport;
  90  |     tab?: HtmxTab;
  91  |     testName: string;
  92  |   }> = [];
  93  | 
  94  |   for (const state of states) {
  95  |     for (const viewport of viewports) {
  96  |       for (const tab of tabs) {
  97  |         const filename = snapshotFilename(row, state, viewport, tab);
  98  |         cells.push({
  99  |           state,
  100 |           viewport,
  101 |           tab: tab ?? undefined,
  102 |           testName: filename.replace(/\.png$/, ''),
  103 |         });
  104 |       }
  105 |     }
  106 |   }
  107 |   return cells;
  108 | }
  109 | 
  110 | function registerTests(
  111 |   testFn: typeof publicTest,
  112 |   auth: AuthMode,
  113 |   label: string,
  114 |   beforeEachHook?: (fixtures: { appState: (s: Record<string, string>) => Promise<void> }) => Promise<void>,
  115 | ): void {
  116 |   testFn.describe(`Visual regression — ${label}`, () => {
  117 |     if (beforeEachHook) {
  118 |       testFn.beforeEach(async ({ appState }) => {
  119 |         await beforeEachHook({ appState });
  120 |       });
  121 |     }
  122 | 
  123 |     for (const row of rowsByAuth(auth)) {
  124 |       const cells = expandRow(row);
  125 |       for (const cell of cells) {
  126 |         testFn(cell.testName, async ({ appState, page }) => {
  127 |           if (cell.state.appState && Object.keys(cell.state.appState).length > 0) {
  128 |             await appState(cell.state.appState);
  129 |           }
  130 |           if (row.notes) {
  131 |             console.log(`[visual-regression] ${row.name}: ${row.notes}`);
  132 |           }
  133 |           await captureSnapshot(page, row, cell.state, cell.viewport, cell.tab);
  134 |         });
  135 |       }
  136 |     }
  137 |   });
  138 | }
  139 | 
  140 | // ============================================================
```