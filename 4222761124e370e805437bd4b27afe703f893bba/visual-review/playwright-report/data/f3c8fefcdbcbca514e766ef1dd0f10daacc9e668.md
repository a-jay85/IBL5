# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke/visual-regression.spec.ts >> Visual regression — public pages (full-page) >> record-holders-see-all
- Location: tests/e2e/smoke/visual-regression.spec.ts:242:9

# Error details

```
Error: A snapshot doesn't exist at /ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots/record-holders-see-all.png, writing actual.
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
        - generic [ref=f1e18]:
          - button "Season" [ref=f1e19]
          - option "IBL" [selected]
          - option "Olympics"
        - button "Stats" [ref=f1e27]
        - button "History" [ref=f1e35]
        - button "Community" [ref=f1e43]
        - button "Teams" [ref=f1e52]
        - button "Login" [ref=f1e63]
  - main [ref=f1e70]:
    - heading "Record Holders" [level=1] [ref=f1e71]
    - generic [ref=f1e72]:
      - generic [ref=f1e73]:
        - heading "Player, Regular Season (Single Game)" [level=2] [ref=f1e75]
        - generic [ref=f1e76]:
          - generic [ref=f1e77]:
            - heading "Most Points in a Single Game" [level=3] [ref=f1e78]
            - table [ref=f1e79]:
              - rowgroup [ref=f1e86]:
                - row [ref=f1e87]:
                  - columnheader "Player" [ref=f1e88]
                  - columnheader "Team" [ref=f1e89]
                  - columnheader "Date" [ref=f1e90]
                  - columnheader "Opponent" [ref=f1e91]
                  - columnheader "Pts" [ref=f1e92]
              - rowgroup [ref=f1e93]:
                - row [ref=f1e94]:
                  - cell [ref=f1e95]:
                    - link [ref=f1e96] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                      - img "Metros PG" [ref=f1e97]
                    - link "Metros PG" [ref=f1e98] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                  - cell [ref=f1e99]:
                    - link [ref=f1e100] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e101]
                  - cell [ref=f1e102]:
                    - link "February 20, 2026" [ref=f1e103] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e104]:
                    - link [ref=f1e105] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e106]
                  - cell "31" [ref=f1e107]
          - generic [ref=f1e108]:
            - heading "Most Rebounds in a Single Game" [level=3] [ref=f1e109]
            - table [ref=f1e110]:
              - rowgroup [ref=f1e117]:
                - row [ref=f1e118]:
                  - columnheader "Player" [ref=f1e119]
                  - columnheader "Team" [ref=f1e120]
                  - columnheader "Date" [ref=f1e121]
                  - columnheader "Opponent" [ref=f1e122]
                  - columnheader "Reb" [ref=f1e123]
              - rowgroup [ref=f1e124]:
                - row [ref=f1e125]:
                  - cell [ref=f1e126]:
                    - link [ref=f1e127] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=5
                      - img "Stars Forward" [ref=f1e128]
                    - link "Stars Forward" [ref=f1e129] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=5
                  - cell [ref=f1e130]:
                    - link [ref=f1e131] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e132]
                  - cell [ref=f1e133]:
                    - link "February 20, 2026" [ref=f1e134] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e135]:
                    - link [ref=f1e136] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e137]
                  - cell "10" [ref=f1e138]
          - generic [ref=f1e139]:
            - heading "Most Assists in a Single Game" [level=3] [ref=f1e140]
            - table [ref=f1e141]:
              - rowgroup [ref=f1e148]:
                - row [ref=f1e149]:
                  - columnheader "Player" [ref=f1e150]
                  - columnheader "Team" [ref=f1e151]
                  - columnheader "Date" [ref=f1e152]
                  - columnheader "Opponent" [ref=f1e153]
                  - columnheader "Ast" [ref=f1e154]
              - rowgroup [ref=f1e155]:
                - row [ref=f1e156]:
                  - cell [ref=f1e157]:
                    - link [ref=f1e158] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                      - img "Metros PG" [ref=f1e159]
                    - link "Metros PG" [ref=f1e160] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                  - cell [ref=f1e161]:
                    - link [ref=f1e162] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e163]
                  - cell [ref=f1e164]:
                    - link "February 20, 2026" [ref=f1e165] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e166]:
                    - link [ref=f1e167] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e168]
                  - cell "8" [ref=f1e169]
          - generic [ref=f1e170]:
            - heading "Most Steals in a Single Game" [level=3] [ref=f1e171]
            - table [ref=f1e172]:
              - rowgroup [ref=f1e179]:
                - row [ref=f1e180]:
                  - columnheader "Player" [ref=f1e181]
                  - columnheader "Team" [ref=f1e182]
                  - columnheader "Date" [ref=f1e183]
                  - columnheader "Opponent" [ref=f1e184]
                  - columnheader "Stl" [ref=f1e185]
              - rowgroup [ref=f1e186]:
                - row [ref=f1e187]:
                  - cell [ref=f1e188]:
                    - link [ref=f1e189] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                      - img "Metros PG" [ref=f1e190]
                    - link "Metros PG" [ref=f1e191] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                  - cell [ref=f1e192]:
                    - link [ref=f1e193] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e194]
                  - cell [ref=f1e195]:
                    - link "February 20, 2026" [ref=f1e196] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e197]:
                    - link [ref=f1e198] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e199]
                  - cell "3" [ref=f1e200]
          - generic [ref=f1e201]:
            - heading "Most Blocks in a Single Game [tie]" [level=3] [ref=f1e202]
            - table [ref=f1e203]:
              - rowgroup [ref=f1e210]:
                - row [ref=f1e211]:
                  - columnheader "Player" [ref=f1e212]
                  - columnheader "Team" [ref=f1e213]
                  - columnheader "Date" [ref=f1e214]
                  - columnheader "Opponent" [ref=f1e215]
                  - columnheader "Blk" [ref=f1e216]
              - rowgroup [ref=f1e217]:
                - row [ref=f1e218]:
                  - cell [ref=f1e219]:
                    - link [ref=f1e220] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=5
                      - img "Stars Forward" [ref=f1e221]
                    - link "Stars Forward" [ref=f1e222] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=5
                  - cell [ref=f1e223]:
                    - link [ref=f1e224] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e225]
                  - cell [ref=f1e226]:
                    - link "February 20, 2026" [ref=f1e227] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e228]:
                    - link [ref=f1e229] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e230]
                  - cell "2" [ref=f1e231]
                - row [ref=f1e232]:
                  - cell [ref=f1e233]:
                    - link [ref=f1e234] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=2
                      - img "Test Player Two" [ref=f1e235]
                    - link "Test Player Two" [ref=f1e236] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=2
                  - cell [ref=f1e237]:
                    - link [ref=f1e238] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e239]
                  - cell [ref=f1e240]:
                    - link "February 20, 2026" [ref=f1e241] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e242]:
                    - link [ref=f1e243] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e244]
                  - cell "2" [ref=f1e245]
                - row [ref=f1e246]:
                  - cell [ref=f1e247]:
                    - link [ref=f1e248] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=2
                      - img "Test Player Two" [ref=f1e249]
                    - link "Test Player Two" [ref=f1e250] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=2
                  - cell [ref=f1e251]:
                    - link [ref=f1e252] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e253]
                  - cell "March 7, 2026" [ref=f1e254]
                  - cell [ref=f1e255]:
                    - link [ref=f1e256] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e257]
                  - cell "2" [ref=f1e258]
          - generic [ref=f1e259]:
            - heading "Most Turnovers in a Single Game" [level=3] [ref=f1e260]
            - table [ref=f1e261]:
              - rowgroup [ref=f1e268]:
                - row [ref=f1e269]:
                  - columnheader "Player" [ref=f1e270]
                  - columnheader "Team" [ref=f1e271]
                  - columnheader "Date" [ref=f1e272]
                  - columnheader "Opponent" [ref=f1e273]
                  - columnheader "TO" [ref=f1e274]
              - rowgroup [ref=f1e275]:
                - row [ref=f1e276]:
                  - cell [ref=f1e277]:
                    - link [ref=f1e278] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                      - img "Metros PG" [ref=f1e279]
                    - link "Metros PG" [ref=f1e280] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                  - cell [ref=f1e281]:
                    - link [ref=f1e282] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e283]
                  - cell [ref=f1e284]:
                    - link "February 20, 2026" [ref=f1e285] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e286]:
                    - link [ref=f1e287] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e288]
                  - cell "4" [ref=f1e289]
          - generic [ref=f1e290]:
            - heading "Most Field Goals in a Single Game" [level=3] [ref=f1e291]
            - table [ref=f1e292]:
              - rowgroup [ref=f1e299]:
                - row [ref=f1e300]:
                  - columnheader "Player" [ref=f1e301]
                  - columnheader "Team" [ref=f1e302]
                  - columnheader "Date" [ref=f1e303]
                  - columnheader "Opponent" [ref=f1e304]
                  - columnheader "Amount" [ref=f1e305]
              - rowgroup [ref=f1e306]:
                - row [ref=f1e307]:
                  - cell [ref=f1e308]:
                    - link [ref=f1e309] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                      - img "Metros PG" [ref=f1e310]
                    - link "Metros PG" [ref=f1e311] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                  - cell [ref=f1e312]:
                    - link [ref=f1e313] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e314]
                  - cell [ref=f1e315]:
                    - link "February 20, 2026" [ref=f1e316] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e317]:
                    - link [ref=f1e318] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e319]
                  - cell "11" [ref=f1e320]
          - generic [ref=f1e321]:
            - heading "Most Free Throws in a Single Game" [level=3] [ref=f1e322]
            - table [ref=f1e323]:
              - rowgroup [ref=f1e330]:
                - row [ref=f1e331]:
                  - columnheader "Player" [ref=f1e332]
                  - columnheader "Team" [ref=f1e333]
                  - columnheader "Date" [ref=f1e334]
                  - columnheader "Opponent" [ref=f1e335]
                  - columnheader "Amount" [ref=f1e336]
              - rowgroup [ref=f1e337]:
                - row [ref=f1e338]:
                  - cell [ref=f1e339]:
                    - link [ref=f1e340] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                      - img "Metros PG" [ref=f1e341]
                    - link "Metros PG" [ref=f1e342] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                  - cell [ref=f1e343]:
                    - link [ref=f1e344] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e345]
                  - cell [ref=f1e346]:
                    - link "February 20, 2026" [ref=f1e347] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e348]:
                    - link [ref=f1e349] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e350]
                  - cell "5" [ref=f1e351]
          - generic [ref=f1e352]:
            - heading "Most Three Pointers in a Single Game" [level=3] [ref=f1e353]
            - table [ref=f1e354]:
              - rowgroup [ref=f1e361]:
                - row [ref=f1e362]:
                  - columnheader "Player" [ref=f1e363]
                  - columnheader "Team" [ref=f1e364]
                  - columnheader "Date" [ref=f1e365]
                  - columnheader "Opponent" [ref=f1e366]
                  - columnheader "Amount" [ref=f1e367]
              - rowgroup [ref=f1e368]:
                - row [ref=f1e369]:
                  - cell [ref=f1e370]:
                    - link [ref=f1e371] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                      - img "Metros PG" [ref=f1e372]
                    - link "Metros PG" [ref=f1e373] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=20
                  - cell [ref=f1e374]:
                    - link [ref=f1e375] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e376]
                  - cell [ref=f1e377]:
                    - link "February 20, 2026" [ref=f1e378] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e379]:
                    - link [ref=f1e380] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e381]
                  - cell "4" [ref=f1e382]
          - generic [ref=f1e383]:
            - heading "Quadruple Doubles" [level=3] [ref=f1e384]
            - table [ref=f1e385]:
              - rowgroup [ref=f1e392]:
                - row [ref=f1e393]:
                  - columnheader "Player" [ref=f1e394]
                  - columnheader "Team" [ref=f1e395]
                  - columnheader "Date" [ref=f1e396]
                  - columnheader "Opponent" [ref=f1e397]
                  - columnheader "Amount" [ref=f1e398]
              - rowgroup
          - generic [ref=f1e399]:
            - heading "Most All-Star Appearances" [level=3] [ref=f1e400]
            - table [ref=f1e401]:
              - rowgroup [ref=f1e407]:
                - row [ref=f1e408]:
                  - columnheader "Player" [ref=f1e409]
                  - columnheader "Team" [ref=f1e410]
                  - columnheader "Apps" [ref=f1e411]
                  - columnheader "Years" [ref=f1e412]
              - rowgroup [ref=f1e413]:
                - row [ref=f1e414]:
                  - cell [ref=f1e415]:
                    - link [ref=f1e416] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                      - img "Test Player" [ref=f1e417]
                    - link "Test Player" [ref=f1e418] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                  - cell [ref=f1e419]
                  - cell "3" [ref=f1e420]
                  - cell [ref=f1e421]
            - paragraph [ref=f1e422]:
              - link "See all All-Star appearances" [ref=f1e423] [cursor=pointer]:
                - /url: modules.php?name=RecordHolders&op=allstar
      - generic [ref=f1e424]:
        - heading "Player, Regular Season (Full Season) [minimum 50 games]" [level=2] [ref=f1e426]
        - generic [ref=f1e427]:
          - generic [ref=f1e428]:
            - heading "Highest Scoring Average in a Regular Season" [level=3] [ref=f1e429]
            - table [ref=f1e430]:
              - rowgroup [ref=f1e436]:
                - row [ref=f1e437]:
                  - columnheader "Player" [ref=f1e438]
                  - columnheader "Team" [ref=f1e439]
                  - columnheader "Season" [ref=f1e440]
                  - columnheader "PPG" [ref=f1e441]
              - rowgroup [ref=f1e442]:
                - row [ref=f1e443]:
                  - cell [ref=f1e444]:
                    - link [ref=f1e445] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                      - img "Retired Legend" [ref=f1e446]
                    - link "Retired Legend" [ref=f1e447] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                  - cell [ref=f1e448]:
                    - link [ref=f1e449] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                      - img "MIA" [ref=f1e450]
                  - cell [ref=f1e451]:
                    - link "2023-24" [ref=f1e452] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                  - cell "16.7" [ref=f1e453]
          - generic [ref=f1e454]:
            - heading "Highest Rebounding Average in a Regular Season" [level=3] [ref=f1e455]
            - table [ref=f1e456]:
              - rowgroup [ref=f1e462]:
                - row [ref=f1e463]:
                  - columnheader "Player" [ref=f1e464]
                  - columnheader "Team" [ref=f1e465]
                  - columnheader "Season" [ref=f1e466]
                  - columnheader "RPG" [ref=f1e467]
              - rowgroup [ref=f1e468]:
                - row [ref=f1e469]:
                  - cell [ref=f1e470]:
                    - link [ref=f1e471] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                      - img "Retired Legend" [ref=f1e472]
                    - link "Retired Legend" [ref=f1e473] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                  - cell [ref=f1e474]:
                    - link [ref=f1e475] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                      - img "MIA" [ref=f1e476]
                  - cell [ref=f1e477]:
                    - link "2023-24" [ref=f1e478] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                  - cell "5.1" [ref=f1e479]
          - generic [ref=f1e480]:
            - heading "Highest Assist Average in a Regular Season" [level=3] [ref=f1e481]
            - table [ref=f1e482]:
              - rowgroup [ref=f1e488]:
                - row [ref=f1e489]:
                  - columnheader "Player" [ref=f1e490]
                  - columnheader "Team" [ref=f1e491]
                  - columnheader "Season" [ref=f1e492]
                  - columnheader "Amount" [ref=f1e493]
              - rowgroup [ref=f1e494]:
                - row [ref=f1e495]:
                  - cell [ref=f1e496]:
                    - link [ref=f1e497] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                      - img "Retired Legend" [ref=f1e498]
                    - link "Retired Legend" [ref=f1e499] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                  - cell [ref=f1e500]:
                    - link [ref=f1e501] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                      - img "MIA" [ref=f1e502]
                  - cell [ref=f1e503]:
                    - link "2023-24" [ref=f1e504] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                  - cell "2.4" [ref=f1e505]
          - generic [ref=f1e506]:
            - heading "Highest Steals Average in a Regular Season" [level=3] [ref=f1e507]
            - table [ref=f1e508]:
              - rowgroup [ref=f1e514]:
                - row [ref=f1e515]:
                  - columnheader "Player" [ref=f1e516]
                  - columnheader "Team" [ref=f1e517]
                  - columnheader "Season" [ref=f1e518]
                  - columnheader "SPG" [ref=f1e519]
              - rowgroup [ref=f1e520]:
                - row [ref=f1e521]:
                  - cell [ref=f1e522]:
                    - link [ref=f1e523] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                      - img "Retired Legend" [ref=f1e524]
                    - link "Retired Legend" [ref=f1e525] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                  - cell [ref=f1e526]:
                    - link [ref=f1e527] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                      - img "MIA" [ref=f1e528]
                  - cell [ref=f1e529]:
                    - link "2023-24" [ref=f1e530] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                  - cell "0.9" [ref=f1e531]
          - generic [ref=f1e532]:
            - heading "Highest Blocks Average in a Regular Season" [level=3] [ref=f1e533]
            - table [ref=f1e534]:
              - rowgroup [ref=f1e540]:
                - row [ref=f1e541]:
                  - columnheader "Player" [ref=f1e542]
                  - columnheader "Team" [ref=f1e543]
                  - columnheader "Season" [ref=f1e544]
                  - columnheader "BPG" [ref=f1e545]
              - rowgroup [ref=f1e546]:
                - row [ref=f1e547]:
                  - cell [ref=f1e548]:
                    - link [ref=f1e549] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                      - img "Retired Legend" [ref=f1e550]
                    - link "Retired Legend" [ref=f1e551] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=3
                  - cell [ref=f1e552]:
                    - link [ref=f1e553] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                      - img "MIA" [ref=f1e554]
                  - cell [ref=f1e555]:
                    - link "2023-24" [ref=f1e556] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2024
                  - cell "1.1" [ref=f1e557]
      - generic [ref=f1e558]:
        - heading "Player, Playoffs" [level=2] [ref=f1e560]
        - generic [ref=f1e561]:
          - generic [ref=f1e562]:
            - heading "Most Points in a Single Game" [level=3] [ref=f1e563]
            - table [ref=f1e564]:
              - rowgroup [ref=f1e571]:
                - row [ref=f1e572]:
                  - columnheader "Player" [ref=f1e573]
                  - columnheader "Team" [ref=f1e574]
                  - columnheader "Date" [ref=f1e575]
                  - columnheader "Opponent" [ref=f1e576]
                  - columnheader "Pts" [ref=f1e577]
              - rowgroup [ref=f1e578]:
                - row [ref=f1e579]:
                  - cell [ref=f1e580]:
                    - link [ref=f1e581] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                      - img "Test Player" [ref=f1e582]
                    - link "Test Player" [ref=f1e583] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                  - cell [ref=f1e584]:
                    - link [ref=f1e585] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e586]
                  - cell "June 15, 2026" [ref=f1e587]
                  - cell [ref=f1e588]:
                    - link [ref=f1e589] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e590]
                  - cell "36" [ref=f1e591]
          - generic [ref=f1e592]:
            - heading "Most Rebounds in a Single Game" [level=3] [ref=f1e593]
            - table [ref=f1e594]:
              - rowgroup [ref=f1e601]:
                - row [ref=f1e602]:
                  - columnheader "Player" [ref=f1e603]
                  - columnheader "Team" [ref=f1e604]
                  - columnheader "Date" [ref=f1e605]
                  - columnheader "Opponent" [ref=f1e606]
                  - columnheader "Reb" [ref=f1e607]
              - rowgroup [ref=f1e608]:
                - row [ref=f1e609]:
                  - cell [ref=f1e610]:
                    - link [ref=f1e611] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                      - img "Test Player" [ref=f1e612]
                    - link "Test Player" [ref=f1e613] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                  - cell [ref=f1e614]:
                    - link [ref=f1e615] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e616]
                  - cell "June 15, 2026" [ref=f1e617]
                  - cell [ref=f1e618]:
                    - link [ref=f1e619] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e620]
                  - cell "9" [ref=f1e621]
          - generic [ref=f1e622]:
            - heading "Most Assists in a Single Game" [level=3] [ref=f1e623]
            - table [ref=f1e624]:
              - rowgroup [ref=f1e631]:
                - row [ref=f1e632]:
                  - columnheader "Player" [ref=f1e633]
                  - columnheader "Team" [ref=f1e634]
                  - columnheader "Date" [ref=f1e635]
                  - columnheader "Opponent" [ref=f1e636]
                  - columnheader "Ast" [ref=f1e637]
              - rowgroup [ref=f1e638]:
                - row [ref=f1e639]:
                  - cell [ref=f1e640]:
                    - link [ref=f1e641] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=4
                      - img "Stars Guard" [ref=f1e642]
                    - link "Stars Guard" [ref=f1e643] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=4
                  - cell [ref=f1e644]:
                    - link [ref=f1e645] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e646]
                  - cell "June 15, 2026" [ref=f1e647]
                  - cell [ref=f1e648]:
                    - link [ref=f1e649] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e650]
                  - cell "9" [ref=f1e651]
          - generic [ref=f1e652]:
            - heading "Most Steals in a Single Game" [level=3] [ref=f1e653]
            - table [ref=f1e654]:
              - rowgroup [ref=f1e661]:
                - row [ref=f1e662]:
                  - columnheader "Player" [ref=f1e663]
                  - columnheader "Team" [ref=f1e664]
                  - columnheader "Date" [ref=f1e665]
                  - columnheader "Opponent" [ref=f1e666]
                  - columnheader "Stl" [ref=f1e667]
              - rowgroup [ref=f1e668]:
                - row [ref=f1e669]:
                  - cell [ref=f1e670]:
                    - link [ref=f1e671] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=4
                      - img "Stars Guard" [ref=f1e672]
                    - link "Stars Guard" [ref=f1e673] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=4
                  - cell [ref=f1e674]:
                    - link [ref=f1e675] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e676]
                  - cell "June 15, 2026" [ref=f1e677]
                  - cell [ref=f1e678]:
                    - link [ref=f1e679] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e680]
                  - cell "3" [ref=f1e681]
          - generic [ref=f1e682]:
            - heading "Most Blocks in a Single Game" [level=3] [ref=f1e683]
            - table [ref=f1e684]:
              - rowgroup [ref=f1e691]:
                - row [ref=f1e692]:
                  - columnheader "Player" [ref=f1e693]
                  - columnheader "Team" [ref=f1e694]
                  - columnheader "Date" [ref=f1e695]
                  - columnheader "Opponent" [ref=f1e696]
                  - columnheader "Blk" [ref=f1e697]
              - rowgroup [ref=f1e698]:
                - row [ref=f1e699]:
                  - cell [ref=f1e700]:
                    - link [ref=f1e701] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                      - img "Test Player" [ref=f1e702]
                    - link "Test Player" [ref=f1e703] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                  - cell [ref=f1e704]:
                    - link [ref=f1e705] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e706]
                  - cell "June 15, 2026" [ref=f1e707]
                  - cell [ref=f1e708]:
                    - link [ref=f1e709] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e710]
                  - cell "1" [ref=f1e711]
          - generic [ref=f1e712]:
            - heading "Most Turnovers in a Single Game" [level=3] [ref=f1e713]
            - table [ref=f1e714]:
              - rowgroup [ref=f1e721]:
                - row [ref=f1e722]:
                  - columnheader "Player" [ref=f1e723]
                  - columnheader "Team" [ref=f1e724]
                  - columnheader "Date" [ref=f1e725]
                  - columnheader "Opponent" [ref=f1e726]
                  - columnheader "TO" [ref=f1e727]
              - rowgroup [ref=f1e728]:
                - row [ref=f1e729]:
                  - cell [ref=f1e730]:
                    - link [ref=f1e731] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=4
                      - img "Stars Guard" [ref=f1e732]
                    - link "Stars Guard" [ref=f1e733] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=4
                  - cell [ref=f1e734]:
                    - link [ref=f1e735] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e736]
                  - cell "June 15, 2026" [ref=f1e737]
                  - cell [ref=f1e738]:
                    - link [ref=f1e739] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e740]
                  - cell "3" [ref=f1e741]
          - generic [ref=f1e742]:
            - heading "Most Field Goals in a Single Game" [level=3] [ref=f1e743]
            - table [ref=f1e744]:
              - rowgroup [ref=f1e751]:
                - row [ref=f1e752]:
                  - columnheader "Player" [ref=f1e753]
                  - columnheader "Team" [ref=f1e754]
                  - columnheader "Date" [ref=f1e755]
                  - columnheader "Opponent" [ref=f1e756]
                  - columnheader "Amount" [ref=f1e757]
              - rowgroup [ref=f1e758]:
                - row [ref=f1e759]:
                  - cell [ref=f1e760]:
                    - link [ref=f1e761] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                      - img "Test Player" [ref=f1e762]
                    - link "Test Player" [ref=f1e763] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                  - cell [ref=f1e764]:
                    - link [ref=f1e765] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e766]
                  - cell "June 15, 2026" [ref=f1e767]
                  - cell [ref=f1e768]:
                    - link [ref=f1e769] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e770]
                  - cell "13" [ref=f1e771]
          - generic [ref=f1e772]:
            - heading "Most Free Throws in a Single Game" [level=3] [ref=f1e773]
            - table [ref=f1e774]:
              - rowgroup [ref=f1e781]:
                - row [ref=f1e782]:
                  - columnheader "Player" [ref=f1e783]
                  - columnheader "Team" [ref=f1e784]
                  - columnheader "Date" [ref=f1e785]
                  - columnheader "Opponent" [ref=f1e786]
                  - columnheader "Amount" [ref=f1e787]
              - rowgroup [ref=f1e788]:
                - row [ref=f1e789]:
                  - cell [ref=f1e790]:
                    - link [ref=f1e791] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                      - img "Test Player" [ref=f1e792]
                    - link "Test Player" [ref=f1e793] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                  - cell [ref=f1e794]:
                    - link [ref=f1e795] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e796]
                  - cell "June 15, 2026" [ref=f1e797]
                  - cell [ref=f1e798]:
                    - link [ref=f1e799] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e800]
                  - cell "6" [ref=f1e801]
          - generic [ref=f1e802]:
            - heading "Most Three Pointers in a Single Game" [level=3] [ref=f1e803]
            - table [ref=f1e804]:
              - rowgroup [ref=f1e811]:
                - row [ref=f1e812]:
                  - columnheader "Player" [ref=f1e813]
                  - columnheader "Team" [ref=f1e814]
                  - columnheader "Date" [ref=f1e815]
                  - columnheader "Opponent" [ref=f1e816]
                  - columnheader "Amount" [ref=f1e817]
              - rowgroup [ref=f1e818]:
                - row [ref=f1e819]:
                  - cell [ref=f1e820]:
                    - link [ref=f1e821] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                      - img "Test Player" [ref=f1e822]
                    - link "Test Player" [ref=f1e823] [cursor=pointer]:
                      - /url: modules.php?name=Player&pa=showpage&pid=1
                  - cell [ref=f1e824]:
                    - link [ref=f1e825] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e826]
                  - cell "June 15, 2026" [ref=f1e827]
                  - cell [ref=f1e828]:
                    - link [ref=f1e829] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e830]
                  - cell "4" [ref=f1e831]
      - generic [ref=f1e832]:
        - heading "Player, H.E.A.T." [level=2] [ref=f1e834]
        - generic [ref=f1e835]:
          - generic [ref=f1e836]:
            - heading "Most Points in a Single Game" [level=3] [ref=f1e837]
            - table [ref=f1e838]:
              - rowgroup [ref=f1e845]:
                - row [ref=f1e846]:
                  - columnheader "Player" [ref=f1e847]
                  - columnheader "Team" [ref=f1e848]
                  - columnheader "Date" [ref=f1e849]
                  - columnheader "Opponent" [ref=f1e850]
                  - columnheader "Pts" [ref=f1e851]
              - rowgroup
          - generic [ref=f1e852]:
            - heading "Most Rebounds in a Single Game" [level=3] [ref=f1e853]
            - table [ref=f1e854]:
              - rowgroup [ref=f1e861]:
                - row [ref=f1e862]:
                  - columnheader "Player" [ref=f1e863]
                  - columnheader "Team" [ref=f1e864]
                  - columnheader "Date" [ref=f1e865]
                  - columnheader "Opponent" [ref=f1e866]
                  - columnheader "Reb" [ref=f1e867]
              - rowgroup
          - generic [ref=f1e868]:
            - heading "Most Assists in a Single Game" [level=3] [ref=f1e869]
            - table [ref=f1e870]:
              - rowgroup [ref=f1e877]:
                - row [ref=f1e878]:
                  - columnheader "Player" [ref=f1e879]
                  - columnheader "Team" [ref=f1e880]
                  - columnheader "Date" [ref=f1e881]
                  - columnheader "Opponent" [ref=f1e882]
                  - columnheader "Ast" [ref=f1e883]
              - rowgroup
          - generic [ref=f1e884]:
            - heading "Most Steals in a Single Game" [level=3] [ref=f1e885]
            - table [ref=f1e886]:
              - rowgroup [ref=f1e893]:
                - row [ref=f1e894]:
                  - columnheader "Player" [ref=f1e895]
                  - columnheader "Team" [ref=f1e896]
                  - columnheader "Date" [ref=f1e897]
                  - columnheader "Opponent" [ref=f1e898]
                  - columnheader "Stl" [ref=f1e899]
              - rowgroup
          - generic [ref=f1e900]:
            - heading "Most Blocks in a Single Game" [level=3] [ref=f1e901]
            - table [ref=f1e902]:
              - rowgroup [ref=f1e909]:
                - row [ref=f1e910]:
                  - columnheader "Player" [ref=f1e911]
                  - columnheader "Team" [ref=f1e912]
                  - columnheader "Date" [ref=f1e913]
                  - columnheader "Opponent" [ref=f1e914]
                  - columnheader "Blk" [ref=f1e915]
              - rowgroup
          - generic [ref=f1e916]:
            - heading "Most Turnovers in a Single Game" [level=3] [ref=f1e917]
            - table [ref=f1e918]:
              - rowgroup [ref=f1e925]:
                - row [ref=f1e926]:
                  - columnheader "Player" [ref=f1e927]
                  - columnheader "Team" [ref=f1e928]
                  - columnheader "Date" [ref=f1e929]
                  - columnheader "Opponent" [ref=f1e930]
                  - columnheader "TO" [ref=f1e931]
              - rowgroup
          - generic [ref=f1e932]:
            - heading "Most Field Goals in a Single Game" [level=3] [ref=f1e933]
            - table [ref=f1e934]:
              - rowgroup [ref=f1e941]:
                - row [ref=f1e942]:
                  - columnheader "Player" [ref=f1e943]
                  - columnheader "Team" [ref=f1e944]
                  - columnheader "Date" [ref=f1e945]
                  - columnheader "Opponent" [ref=f1e946]
                  - columnheader "Amount" [ref=f1e947]
              - rowgroup
          - generic [ref=f1e948]:
            - heading "Most Free Throws in a Single Game" [level=3] [ref=f1e949]
            - table [ref=f1e950]:
              - rowgroup [ref=f1e957]:
                - row [ref=f1e958]:
                  - columnheader "Player" [ref=f1e959]
                  - columnheader "Team" [ref=f1e960]
                  - columnheader "Date" [ref=f1e961]
                  - columnheader "Opponent" [ref=f1e962]
                  - columnheader "Amount" [ref=f1e963]
              - rowgroup
          - generic [ref=f1e964]:
            - heading "Most Three Pointers in a Single Game" [level=3] [ref=f1e965]
            - table [ref=f1e966]:
              - rowgroup [ref=f1e973]:
                - row [ref=f1e974]:
                  - columnheader "Player" [ref=f1e975]
                  - columnheader "Team" [ref=f1e976]
                  - columnheader "Date" [ref=f1e977]
                  - columnheader "Opponent" [ref=f1e978]
                  - columnheader "Amount" [ref=f1e979]
              - rowgroup
      - generic [ref=f1e980]:
        - heading "Team Records" [level=2] [ref=f1e982]
        - generic [ref=f1e983]:
          - heading "Game Records" [level=3] [ref=f1e984]
          - generic [ref=f1e985]:
            - heading "Most Points in a Single Game" [level=3] [ref=f1e986]
            - table [ref=f1e987]:
              - rowgroup [ref=f1e993]:
                - row [ref=f1e994]:
                  - columnheader "Team" [ref=f1e995]
                  - columnheader "Date" [ref=f1e996]
                  - columnheader "Opponent" [ref=f1e997]
                  - columnheader "Pts" [ref=f1e998]
              - rowgroup [ref=f1e999]:
                - row [ref=f1e1000]:
                  - cell [ref=f1e1001]:
                    - link [ref=f1e1002] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=5&yr=2026
                      - img "ORL" [ref=f1e1003]
                  - cell "March 10, 2026" [ref=f1e1004]
                  - cell [ref=f1e1005]:
                    - link [ref=f1e1006] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=12&yr=2026
                      - img "TOR" [ref=f1e1007]
                  - cell "114" [ref=f1e1008]
          - generic [ref=f1e1009]:
            - heading "Most Rebounds in a Single Game" [level=3] [ref=f1e1010]
            - table [ref=f1e1011]:
              - rowgroup [ref=f1e1017]:
                - row [ref=f1e1018]:
                  - columnheader "Team" [ref=f1e1019]
                  - columnheader "Date" [ref=f1e1020]
                  - columnheader "Opponent" [ref=f1e1021]
                  - columnheader "Reb" [ref=f1e1022]
              - rowgroup [ref=f1e1023]:
                - row [ref=f1e1024]:
                  - cell [ref=f1e1025]:
                    - link [ref=f1e1026] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1027]
                  - cell [ref=f1e1028]:
                    - link "March 3, 2026" [ref=f1e1029] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
                  - cell [ref=f1e1030]:
                    - link [ref=f1e1031] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=3&yr=2026
                      - img "NYK" [ref=f1e1032]
                  - cell "41" [ref=f1e1033]
          - generic [ref=f1e1034]:
            - heading "Most Assists in a Single Game [tie]" [level=3] [ref=f1e1035]
            - table [ref=f1e1036]:
              - rowgroup [ref=f1e1042]:
                - row [ref=f1e1043]:
                  - columnheader "Team" [ref=f1e1044]
                  - columnheader "Date" [ref=f1e1045]
                  - columnheader "Opponent" [ref=f1e1046]
                  - columnheader "Ast" [ref=f1e1047]
              - rowgroup [ref=f1e1048]:
                - row [ref=f1e1049]:
                  - cell [ref=f1e1050]:
                    - link [ref=f1e1051] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1052]
                  - cell [ref=f1e1053]:
                    - link "March 3, 2026" [ref=f1e1054] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
                  - cell [ref=f1e1055]:
                    - link [ref=f1e1056] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=3&yr=2026
                      - img "NYK" [ref=f1e1057]
                  - cell "24" [ref=f1e1058]
                - row [ref=f1e1059]:
                  - cell [ref=f1e1060]:
                    - link [ref=f1e1061] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=5&yr=2026
                      - img "ORL" [ref=f1e1062]
                  - cell "March 10, 2026" [ref=f1e1063]
                  - cell [ref=f1e1064]:
                    - link [ref=f1e1065] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=12&yr=2026
                      - img "TOR" [ref=f1e1066]
                  - cell "24" [ref=f1e1067]
          - generic [ref=f1e1068]:
            - heading "Most Steals in a Single Game [tie]" [level=3] [ref=f1e1069]
            - table [ref=f1e1070]:
              - rowgroup [ref=f1e1076]:
                - row [ref=f1e1077]:
                  - columnheader "Team" [ref=f1e1078]
                  - columnheader "Date" [ref=f1e1079]
                  - columnheader "Opponent" [ref=f1e1080]
                  - columnheader "Stl" [ref=f1e1081]
              - rowgroup [ref=f1e1082]:
                - row [ref=f1e1083]:
                  - cell [ref=f1e1084]:
                    - link [ref=f1e1085] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1086]
                  - cell [ref=f1e1087]:
                    - link "February 26, 2026" [ref=f1e1088] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-26&game=1
                  - cell [ref=f1e1089]:
                    - link [ref=f1e1090] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e1091]
                  - cell "8" [ref=f1e1092]
                - row [ref=f1e1093]:
                  - cell [ref=f1e1094]:
                    - link [ref=f1e1095] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1096]
                  - cell [ref=f1e1097]:
                    - link "March 3, 2026" [ref=f1e1098] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
                  - cell [ref=f1e1099]:
                    - link [ref=f1e1100] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=3&yr=2026
                      - img "NYK" [ref=f1e1101]
                  - cell "8" [ref=f1e1102]
                - row [ref=f1e1103]:
                  - cell [ref=f1e1104]:
                    - link [ref=f1e1105] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=5&yr=2026
                      - img "ORL" [ref=f1e1106]
                  - cell "March 10, 2026" [ref=f1e1107]
                  - cell [ref=f1e1108]:
                    - link [ref=f1e1109] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=12&yr=2026
                      - img "TOR" [ref=f1e1110]
                  - cell "8" [ref=f1e1111]
          - generic [ref=f1e1112]:
            - heading "Most Blocks in a Single Game [tie]" [level=3] [ref=f1e1113]
            - table [ref=f1e1114]:
              - rowgroup [ref=f1e1120]:
                - row [ref=f1e1121]:
                  - columnheader "Team" [ref=f1e1122]
                  - columnheader "Date" [ref=f1e1123]
                  - columnheader "Opponent" [ref=f1e1124]
                  - columnheader "Blk" [ref=f1e1125]
              - rowgroup [ref=f1e1126]:
                - row [ref=f1e1127]:
                  - cell [ref=f1e1128]:
                    - link [ref=f1e1129] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1130]
                  - cell [ref=f1e1131]:
                    - link "February 26, 2026" [ref=f1e1132] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-26&game=1
                  - cell [ref=f1e1133]:
                    - link [ref=f1e1134] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e1135]
                  - cell "5" [ref=f1e1136]
                - row [ref=f1e1137]:
                  - cell [ref=f1e1138]:
                    - link [ref=f1e1139] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1140]
                  - cell [ref=f1e1141]:
                    - link "March 3, 2026" [ref=f1e1142] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
                  - cell [ref=f1e1143]:
                    - link [ref=f1e1144] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=3&yr=2026
                      - img "NYK" [ref=f1e1145]
                  - cell "5" [ref=f1e1146]
                - row [ref=f1e1147]:
                  - cell [ref=f1e1148]:
                    - link [ref=f1e1149] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=5&yr=2026
                      - img "ORL" [ref=f1e1150]
                  - cell "March 10, 2026" [ref=f1e1151]
                  - cell [ref=f1e1152]:
                    - link [ref=f1e1153] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=12&yr=2026
                      - img "TOR" [ref=f1e1154]
                  - cell "5" [ref=f1e1155]
          - generic [ref=f1e1156]:
            - heading "Most Field Goals in a Single Game" [level=3] [ref=f1e1157]
            - table [ref=f1e1158]:
              - rowgroup [ref=f1e1164]:
                - row [ref=f1e1165]:
                  - columnheader "Team" [ref=f1e1166]
                  - columnheader "Date" [ref=f1e1167]
                  - columnheader "Opponent" [ref=f1e1168]
                  - columnheader "Amount" [ref=f1e1169]
              - rowgroup [ref=f1e1170]:
                - row [ref=f1e1171]:
                  - cell [ref=f1e1172]:
                    - link [ref=f1e1173] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=5&yr=2026
                      - img "ORL" [ref=f1e1174]
                  - cell "March 10, 2026" [ref=f1e1175]
                  - cell [ref=f1e1176]:
                    - link [ref=f1e1177] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=12&yr=2026
                      - img "TOR" [ref=f1e1178]
                  - cell "42" [ref=f1e1179]
          - generic [ref=f1e1180]:
            - heading "Most Free Throws in a Single Game [tie]" [level=3] [ref=f1e1181]
            - table [ref=f1e1182]:
              - rowgroup [ref=f1e1188]:
                - row [ref=f1e1189]:
                  - columnheader "Team" [ref=f1e1190]
                  - columnheader "Date" [ref=f1e1191]
                  - columnheader "Opponent" [ref=f1e1192]
                  - columnheader "Amount" [ref=f1e1193]
              - rowgroup [ref=f1e1194]:
                - row [ref=f1e1195]:
                  - cell [ref=f1e1196]:
                    - link [ref=f1e1197] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1198]
                  - cell [ref=f1e1199]:
                    - link "March 3, 2026" [ref=f1e1200] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
                  - cell [ref=f1e1201]:
                    - link [ref=f1e1202] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=3&yr=2026
                      - img "NYK" [ref=f1e1203]
                  - cell "20" [ref=f1e1204]
                - row [ref=f1e1205]:
                  - cell [ref=f1e1206]:
                    - link [ref=f1e1207] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=12&yr=2026
                      - img "TOR" [ref=f1e1208]
                  - cell "March 10, 2026" [ref=f1e1209]
                  - cell [ref=f1e1210]:
                    - link [ref=f1e1211] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=5&yr=2026
                      - img "ORL" [ref=f1e1212]
                  - cell "20" [ref=f1e1213]
          - generic [ref=f1e1214]:
            - heading "Most Three Pointers in a Single Game" [level=3] [ref=f1e1215]
            - table [ref=f1e1216]:
              - rowgroup [ref=f1e1222]:
                - row [ref=f1e1223]:
                  - columnheader "Team" [ref=f1e1224]
                  - columnheader "Date" [ref=f1e1225]
                  - columnheader "Opponent" [ref=f1e1226]
                  - columnheader "Amount" [ref=f1e1227]
              - rowgroup [ref=f1e1228]:
                - row [ref=f1e1229]:
                  - cell [ref=f1e1230]:
                    - link [ref=f1e1231] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=5&yr=2026
                      - img "ORL" [ref=f1e1232]
                  - cell "March 10, 2026" [ref=f1e1233]
                  - cell [ref=f1e1234]:
                    - link [ref=f1e1235] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=12&yr=2026
                      - img "TOR" [ref=f1e1236]
                  - cell "12" [ref=f1e1237]
          - generic [ref=f1e1238]:
            - heading "Fewest Points in a Single Game [tie]" [level=3] [ref=f1e1239]
            - table [ref=f1e1240]:
              - rowgroup [ref=f1e1246]:
                - row [ref=f1e1247]:
                  - columnheader "Team" [ref=f1e1248]
                  - columnheader "Date" [ref=f1e1249]
                  - columnheader "Opponent" [ref=f1e1250]
                  - columnheader "Amount" [ref=f1e1251]
              - rowgroup [ref=f1e1252]:
                - row [ref=f1e1253]:
                  - cell [ref=f1e1254]:
                    - link [ref=f1e1255] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e1256]
                  - cell [ref=f1e1257]:
                    - link "February 20, 2026" [ref=f1e1258] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-20&game=1
                  - cell [ref=f1e1259]:
                    - link [ref=f1e1260] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1261]
                  - cell "89" [ref=f1e1262]
                - row [ref=f1e1263]:
                  - cell [ref=f1e1264]:
                    - link [ref=f1e1265] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1266]
                  - cell [ref=f1e1267]:
                    - link "March 5, 2026" [ref=f1e1268] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-03-05&game=1
                  - cell [ref=f1e1269]:
                    - link [ref=f1e1270] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e1271]
                  - cell "89" [ref=f1e1272]
          - generic [ref=f1e1273]:
            - heading "Most Points in a Single Half" [level=3] [ref=f1e1274]
            - table [ref=f1e1275]:
              - rowgroup [ref=f1e1281]:
                - row [ref=f1e1282]:
                  - columnheader "Team" [ref=f1e1283]
                  - columnheader "Date" [ref=f1e1284]
                  - columnheader "Opponent" [ref=f1e1285]
                  - columnheader "Pts" [ref=f1e1286]
              - rowgroup [ref=f1e1287]:
                - row [ref=f1e1288]:
                  - cell [ref=f1e1289]:
                    - link [ref=f1e1290] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1291]
                  - cell [ref=f1e1292]:
                    - link "February 26, 2026" [ref=f1e1293] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-02-26&game=1
                  - cell [ref=f1e1294]:
                    - link [ref=f1e1295] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e1296]
                  - cell "56" [ref=f1e1297]
          - generic [ref=f1e1298]:
            - heading "Fewest Points in a Single Half" [level=3] [ref=f1e1299]
            - table [ref=f1e1300]:
              - rowgroup [ref=f1e1306]:
                - row [ref=f1e1307]:
                  - columnheader "Team" [ref=f1e1308]
                  - columnheader "Date" [ref=f1e1309]
                  - columnheader "Opponent" [ref=f1e1310]
                  - columnheader "Amount" [ref=f1e1311]
              - rowgroup [ref=f1e1312]:
                - row [ref=f1e1313]:
                  - cell [ref=f1e1314]:
                    - link [ref=f1e1315] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=3&yr=2026
                      - img "NYK" [ref=f1e1316]
                  - cell [ref=f1e1317]:
                    - link "March 3, 2026" [ref=f1e1318] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
                  - cell [ref=f1e1319]:
                    - link [ref=f1e1320] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1321]
                  - cell "43" [ref=f1e1322]
          - generic [ref=f1e1323]:
            - heading "Largest Margin of Victory [overall]" [level=3] [ref=f1e1324]
            - table [ref=f1e1325]:
              - rowgroup [ref=f1e1331]:
                - row [ref=f1e1332]:
                  - columnheader "Team" [ref=f1e1333]
                  - columnheader "Date" [ref=f1e1334]
                  - columnheader "Opponent" [ref=f1e1335]
                  - columnheader "Amount" [ref=f1e1336]
              - rowgroup [ref=f1e1337]:
                - row [ref=f1e1338]:
                  - cell [ref=f1e1339]:
                    - link [ref=f1e1340] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1341]
                  - cell [ref=f1e1342]:
                    - link "March 3, 2026" [ref=f1e1343] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-03-03&game=1
                  - cell [ref=f1e1344]:
                    - link [ref=f1e1345] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=3&yr=2026
                      - img "NYK" [ref=f1e1346]
                  - cell "16" [ref=f1e1347]
          - generic [ref=f1e1348]:
            - heading "Largest Margin of Victory [playoffs]" [level=3] [ref=f1e1349]
            - table [ref=f1e1350]:
              - rowgroup [ref=f1e1356]:
                - row [ref=f1e1357]:
                  - columnheader "Team" [ref=f1e1358]
                  - columnheader "Date" [ref=f1e1359]
                  - columnheader "Opponent" [ref=f1e1360]
                  - columnheader "Amount" [ref=f1e1361]
              - rowgroup [ref=f1e1362]:
                - row [ref=f1e1363]:
                  - cell [ref=f1e1364]:
                    - link [ref=f1e1365] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=1&yr=2026
                      - img "BOS" [ref=f1e1366]
                  - cell [ref=f1e1367]:
                    - link "June 5, 2026" [ref=f1e1368] [cursor=pointer]:
                      - /url: modules.php?name=GameBoxscore&date=2026-06-05&game=1
                  - cell [ref=f1e1369]:
                    - link [ref=f1e1370] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=2&yr=2026
                      - img "MIA" [ref=f1e1371]
                  - cell "7" [ref=f1e1372]
          - heading "Season Records" [level=3] [ref=f1e1373]
          - generic [ref=f1e1374]:
            - heading "Best Season Record" [level=3] [ref=f1e1375]
            - table [ref=f1e1376]:
              - rowgroup [ref=f1e1381]:
                - row [ref=f1e1382]:
                  - columnheader "Team" [ref=f1e1383]
                  - columnheader "Season" [ref=f1e1384]
                  - columnheader "Record" [ref=f1e1385]
              - rowgroup [ref=f1e1386]:
                - row [ref=f1e1387]:
                  - cell "Team" [ref=f1e1388]:
                    - link "Team":
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell [ref=f1e1389]:
                    - link "2025-26" [ref=f1e1390] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell "1-0" [ref=f1e1391]
          - generic [ref=f1e1392]:
            - heading "Worst Season Record" [level=3] [ref=f1e1393]
            - table [ref=f1e1394]:
              - rowgroup [ref=f1e1399]:
                - row [ref=f1e1400]:
                  - columnheader "Team" [ref=f1e1401]
                  - columnheader "Season" [ref=f1e1402]
                  - columnheader "Record" [ref=f1e1403]
              - rowgroup [ref=f1e1404]:
                - row [ref=f1e1405]:
                  - cell "Team" [ref=f1e1406]:
                    - link "Team":
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell [ref=f1e1407]:
                    - link "2025-26" [ref=f1e1408] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell "0-1" [ref=f1e1409]
                - row [ref=f1e1410]:
                  - cell "Team" [ref=f1e1411]:
                    - link "Team":
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell [ref=f1e1412]:
                    - link "2025-26" [ref=f1e1413] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell "0-1" [ref=f1e1414]
          - generic [ref=f1e1415]:
            - heading "Best Season Record, Start" [level=3] [ref=f1e1416]
            - table [ref=f1e1417]:
              - rowgroup [ref=f1e1422]:
                - row [ref=f1e1423]:
                  - columnheader "Team" [ref=f1e1424]
                  - columnheader "Season" [ref=f1e1425]
                  - columnheader "Record" [ref=f1e1426]
              - rowgroup [ref=f1e1427]:
                - row [ref=f1e1428]:
                  - cell "Team" [ref=f1e1429]:
                    - link "Team":
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell [ref=f1e1430]:
                    - link "2025-26" [ref=f1e1431] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell "3-0" [ref=f1e1432]
          - generic [ref=f1e1433]:
            - heading "Worst Season Record, Start" [level=3] [ref=f1e1434]
            - table [ref=f1e1435]:
              - rowgroup [ref=f1e1440]:
                - row [ref=f1e1441]:
                  - columnheader "Team" [ref=f1e1442]
                  - columnheader "Season" [ref=f1e1443]
                  - columnheader "Record" [ref=f1e1444]
              - rowgroup [ref=f1e1445]:
                - row [ref=f1e1446]:
                  - cell "Team" [ref=f1e1447]:
                    - link "Team":
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell [ref=f1e1448]:
                    - link "2025-26" [ref=f1e1449] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell "0-2" [ref=f1e1450]
          - generic [ref=f1e1451]:
            - heading "Longest Winning Streak" [level=3] [ref=f1e1452]
            - table [ref=f1e1453]:
              - rowgroup [ref=f1e1458]:
                - row [ref=f1e1459]:
                  - columnheader "Team" [ref=f1e1460]
                  - columnheader "Season" [ref=f1e1461]
                  - columnheader "Wins" [ref=f1e1462]
              - rowgroup [ref=f1e1463]:
                - row [ref=f1e1464]:
                  - cell "Team" [ref=f1e1465]:
                    - link "Team":
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell [ref=f1e1466]:
                    - link "2025-26" [ref=f1e1467] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell "3" [ref=f1e1468]
          - generic [ref=f1e1469]:
            - heading "Longest Losing Streak" [level=3] [ref=f1e1470]
            - table [ref=f1e1471]:
              - rowgroup [ref=f1e1476]:
                - row [ref=f1e1477]:
                  - columnheader "Team" [ref=f1e1478]
                  - columnheader "Season" [ref=f1e1479]
                  - columnheader "Losses" [ref=f1e1480]
              - rowgroup [ref=f1e1481]:
                - row [ref=f1e1482]:
                  - cell "Team" [ref=f1e1483]:
                    - link "Team":
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell [ref=f1e1484]:
                    - link "2025-26" [ref=f1e1485] [cursor=pointer]:
                      - /url: modules.php?name=Team&op=team&teamid=0&yr=2026
                  - cell "2" [ref=f1e1486]
          - heading "Franchise Records" [level=3] [ref=f1e1487]
          - generic [ref=f1e1488]:
            - heading "Most Playoff Appearances" [level=3] [ref=f1e1489]
            - table [ref=f1e1490]:
              - rowgroup [ref=f1e1495]:
                - row [ref=f1e1496]:
                  - columnheader "Team" [ref=f1e1497]
                  - columnheader "Apps" [ref=f1e1498]
                  - columnheader "Years" [ref=f1e1499]
              - rowgroup
          - generic [ref=f1e1500]:
            - heading "Most Division Championships" [level=3] [ref=f1e1501]
            - table [ref=f1e1502]:
              - rowgroup [ref=f1e1507]:
                - row [ref=f1e1508]:
                  - columnheader "Team" [ref=f1e1509]
                  - columnheader "Amount" [ref=f1e1510]
                  - columnheader "Years" [ref=f1e1511]
              - rowgroup
          - generic [ref=f1e1512]:
            - heading "Most IBL Finals Appearances" [level=3] [ref=f1e1513]
            - table [ref=f1e1514]:
              - rowgroup [ref=f1e1519]:
                - row [ref=f1e1520]:
                  - columnheader "Team" [ref=f1e1521]
                  - columnheader "Amount" [ref=f1e1522]
                  - columnheader "Years" [ref=f1e1523]
              - rowgroup
          - generic [ref=f1e1524]:
            - heading "Most IBL Championships" [level=3] [ref=f1e1525]
            - table [ref=f1e1526]:
              - rowgroup [ref=f1e1531]:
                - row [ref=f1e1532]:
                  - columnheader "Team" [ref=f1e1533]
                  - columnheader "Amount" [ref=f1e1534]
                  - columnheader "Years" [ref=f1e1535]
              - rowgroup
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
      |     ^ Error: A snapshot doesn't exist at /ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots/record-holders-see-all.png, writing actual.
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