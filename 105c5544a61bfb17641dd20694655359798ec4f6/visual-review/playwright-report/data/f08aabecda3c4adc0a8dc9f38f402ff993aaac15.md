# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke/visual-regression.spec.ts >> Visual regression — public pages (full-page) >> head-to-head-records
- Location: tests/e2e/smoke/visual-regression.spec.ts:242:9

# Error details

```
Error: expect(page).toHaveScreenshot(expected) failed

  349619 pixels (ratio 0.12 of all image pixels) are different.

  Snapshot: head-to-head-records.png

Call log:
  - Expect "toHaveScreenshot(head-to-head-records.png)" with timeout 10000ms
    - verifying given screenshot expectation
  - taking page screenshot
    - disabled all CSS animations
  - waiting for fonts to load...
  - fonts loaded
  - 349619 pixels (ratio 0.12 of all image pixels) are different.
  - waiting 100ms before taking screenshot
  - taking page screenshot
    - disabled all CSS animations
  - waiting for fonts to load...
  - fonts loaded
  - captured a stable screenshot
  - 349619 pixels (ratio 0.12 of all image pixels) are different.

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
    - heading "Head-to-Head Records" [level=1] [ref=f1e71]
    - generic [ref=f1e72]:
      - generic [ref=f1e73]:
        - text: Dimension
        - combobox "Dimension" [ref=f1e74]:
          - option "Franchises" [selected]
          - option "Teams"
          - option "GMs"
      - generic [ref=f1e75]:
        - text: Phase
        - combobox "Phase" [ref=f1e76]:
          - option "HEAT"
          - option "Regular Season"
          - option "Playoffs"
          - option "All Phases" [selected]
      - generic [ref=f1e77]:
        - text: Scope
        - combobox "Scope" [ref=f1e78]:
          - option "Current Season" [selected]
          - option "All-Time"
      - button "Filter" [ref=f1e79]
    - region "Scrollable data table" [ref=f1e82]:
      - table [ref=f1e83]:
        - rowgroup [ref=f1e84]:
          - row [ref=f1e85]:
            - columnheader "Team" [ref=f1e86]
            - columnheader "Houston Apollos" [ref=f1e88]
            - columnheader "Minnesota Blizzard" [ref=f1e89]
            - columnheader "Memphis Blues" [ref=f1e90]
            - columnheader "Milwaukee Bucks" [ref=f1e91]
            - columnheader "Cleveland Cavaliers" [ref=f1e92]
            - columnheader "Chicago Cougars" [ref=f1e93]
            - columnheader "Detroit Diesels" [ref=f1e94]
            - columnheader "Phoenix Flames" [ref=f1e95]
            - columnheader "Washington Generals" [ref=f1e96]
            - columnheader "Toronto Huskies" [ref=f1e97]
            - columnheader "Utah Jazz" [ref=f1e98]
            - columnheader "Dallas Mavericks" [ref=f1e99]
            - columnheader "New York Metros" [ref=f1e100]
            - columnheader "Boston Minutemen" [ref=f1e101]
            - columnheader "Miami Monarchs" [ref=f1e102]
            - columnheader "New Jersey Nets" [ref=f1e103]
            - columnheader "Denver Nuggets" [ref=f1e104]
            - columnheader "Indiana Pacers" [ref=f1e105]
            - columnheader "Atlanta Phoenixes" [ref=f1e106]
            - columnheader "Sacramento Pilots" [ref=f1e107]
            - columnheader "Portland Pioneers" [ref=f1e108]
            - columnheader "Philadelphia Rage" [ref=f1e109]
            - columnheader "Charlotte Royals" [ref=f1e110]
            - columnheader "San Antonio Spurs" [ref=f1e111]
            - columnheader "Los Angeles Stars" [ref=f1e112]
            - columnheader "Seattle Supersonics" [ref=f1e113]
            - columnheader "Oklahoma City Thunder" [ref=f1e114]
            - columnheader "Orlando Tropics" [ref=f1e115]
        - rowgroup [ref=f1e116]:
          - row [ref=f1e117]:
            - rowheader "Houston Apollos" [ref=f1e118]
            - cell [ref=f1e119]
            - cell "0-0" [ref=f1e120]
            - cell "0-0" [ref=f1e121]
            - cell "0-0" [ref=f1e122]
            - cell "0-0" [ref=f1e123]
            - cell "0-0" [ref=f1e124]
            - cell "0-0" [ref=f1e125]
            - cell "0-0" [ref=f1e126]
            - cell "0-0" [ref=f1e127]
            - cell "0-0" [ref=f1e128]
            - cell "0-0" [ref=f1e129]
            - cell "0-0" [ref=f1e130]
            - cell "0-0" [ref=f1e131]
            - cell "0-0" [ref=f1e132]
            - cell "0-0" [ref=f1e133]
            - cell "0-0" [ref=f1e134]
            - cell "0-0" [ref=f1e135]
            - cell "0-0" [ref=f1e136]
            - cell "0-0" [ref=f1e137]
            - cell "0-0" [ref=f1e138]
            - cell "0-0" [ref=f1e139]
            - cell "0-0" [ref=f1e140]
            - cell "0-0" [ref=f1e141]
            - cell "0-0" [ref=f1e142]
            - cell "0-0" [ref=f1e143]
            - cell "0-0" [ref=f1e144]
            - cell "0-0" [ref=f1e145]
            - cell "0-0" [ref=f1e146]
          - row [ref=f1e147]:
            - rowheader "Minnesota Blizzard" [ref=f1e148]
            - cell "0-0" [ref=f1e149]
            - cell [ref=f1e150]
            - cell "0-0" [ref=f1e151]
            - cell "0-0" [ref=f1e152]
            - cell "0-0" [ref=f1e153]
            - cell "0-0" [ref=f1e154]
            - cell "0-0" [ref=f1e155]
            - cell "0-0" [ref=f1e156]
            - cell "0-0" [ref=f1e157]
            - cell "0-0" [ref=f1e158]
            - cell "0-0" [ref=f1e159]
            - cell "0-0" [ref=f1e160]
            - cell "0-0" [ref=f1e161]
            - cell "0-0" [ref=f1e162]
            - cell "0-0" [ref=f1e163]
            - cell "0-0" [ref=f1e164]
            - cell "0-0" [ref=f1e165]
            - cell "0-0" [ref=f1e166]
            - cell "0-0" [ref=f1e167]
            - cell "0-0" [ref=f1e168]
            - cell "0-0" [ref=f1e169]
            - cell "0-0" [ref=f1e170]
            - cell "0-0" [ref=f1e171]
            - cell "0-0" [ref=f1e172]
            - cell "0-0" [ref=f1e173]
            - cell "0-0" [ref=f1e174]
            - cell "0-0" [ref=f1e175]
            - cell "0-0" [ref=f1e176]
          - row [ref=f1e177]:
            - rowheader "Memphis Blues" [ref=f1e178]
            - cell "0-0" [ref=f1e179]
            - cell "0-0" [ref=f1e180]
            - cell [ref=f1e181]
            - cell "0-0" [ref=f1e182]
            - cell "0-0" [ref=f1e183]
            - cell "0-0" [ref=f1e184]
            - cell "0-0" [ref=f1e185]
            - cell "0-0" [ref=f1e186]
            - cell "0-0" [ref=f1e187]
            - cell "0-0" [ref=f1e188]
            - cell "0-0" [ref=f1e189]
            - cell "0-0" [ref=f1e190]
            - cell "0-0" [ref=f1e191]
            - cell "0-0" [ref=f1e192]
            - cell "0-0" [ref=f1e193]
            - cell "0-0" [ref=f1e194]
            - cell "0-0" [ref=f1e195]
            - cell "0-0" [ref=f1e196]
            - cell "0-0" [ref=f1e197]
            - cell "0-0" [ref=f1e198]
            - cell "0-0" [ref=f1e199]
            - cell "0-0" [ref=f1e200]
            - cell "0-0" [ref=f1e201]
            - cell "0-0" [ref=f1e202]
            - cell "0-0" [ref=f1e203]
            - cell "0-0" [ref=f1e204]
            - cell "0-0" [ref=f1e205]
            - cell "0-0" [ref=f1e206]
          - row [ref=f1e207]:
            - rowheader "Milwaukee Bucks" [ref=f1e208]
            - cell "0-0" [ref=f1e209]
            - cell "0-0" [ref=f1e210]
            - cell "0-0" [ref=f1e211]
            - cell [ref=f1e212]
            - cell "0-0" [ref=f1e213]
            - cell "0-0" [ref=f1e214]
            - cell "0-0" [ref=f1e215]
            - cell "0-0" [ref=f1e216]
            - cell "0-0" [ref=f1e217]
            - cell "0-0" [ref=f1e218]
            - cell "0-0" [ref=f1e219]
            - cell "0-0" [ref=f1e220]
            - cell "0-0" [ref=f1e221]
            - cell "0-0" [ref=f1e222]
            - cell "0-0" [ref=f1e223]
            - cell "0-0" [ref=f1e224]
            - cell "0-0" [ref=f1e225]
            - cell "0-0" [ref=f1e226]
            - cell "0-0" [ref=f1e227]
            - cell "0-0" [ref=f1e228]
            - cell "0-0" [ref=f1e229]
            - cell "0-0" [ref=f1e230]
            - cell "0-0" [ref=f1e231]
            - cell "0-0" [ref=f1e232]
            - cell "0-0" [ref=f1e233]
            - cell "0-0" [ref=f1e234]
            - cell "0-0" [ref=f1e235]
            - cell "0-0" [ref=f1e236]
          - row [ref=f1e237]:
            - rowheader "Cleveland Cavaliers" [ref=f1e238]
            - cell "0-0" [ref=f1e239]
            - cell "0-0" [ref=f1e240]
            - cell "0-0" [ref=f1e241]
            - cell "0-0" [ref=f1e242]
            - cell [ref=f1e243]
            - cell "0-0" [ref=f1e244]
            - cell "0-0" [ref=f1e245]
            - cell "0-0" [ref=f1e246]
            - cell "0-0" [ref=f1e247]
            - cell "0-0" [ref=f1e248]
            - cell "0-0" [ref=f1e249]
            - cell "0-0" [ref=f1e250]
            - cell "0-0" [ref=f1e251]
            - cell "0-0" [ref=f1e252]
            - cell "0-0" [ref=f1e253]
            - cell "0-0" [ref=f1e254]
            - cell "0-0" [ref=f1e255]
            - cell "0-0" [ref=f1e256]
            - cell "0-0" [ref=f1e257]
            - cell "0-0" [ref=f1e258]
            - cell "0-0" [ref=f1e259]
            - cell "0-0" [ref=f1e260]
            - cell "0-0" [ref=f1e261]
            - cell "0-0" [ref=f1e262]
            - cell "0-0" [ref=f1e263]
            - cell "0-0" [ref=f1e264]
            - cell "0-0" [ref=f1e265]
            - cell "0-0" [ref=f1e266]
          - row [ref=f1e267]:
            - rowheader "Chicago Cougars" [ref=f1e268]
            - cell "0-0" [ref=f1e269]
            - cell "0-0" [ref=f1e270]
            - cell "0-0" [ref=f1e271]
            - cell "0-0" [ref=f1e272]
            - cell "0-0" [ref=f1e273]
            - cell [ref=f1e274]
            - cell "0-0" [ref=f1e275]
            - cell "0-0" [ref=f1e276]
            - cell "0-0" [ref=f1e277]
            - cell "0-0" [ref=f1e278]
            - cell "0-0" [ref=f1e279]
            - cell "0-0" [ref=f1e280]
            - cell "0-1" [ref=f1e281]
            - cell "0-0" [ref=f1e282]
            - cell "0-0" [ref=f1e283]
            - cell "0-0" [ref=f1e284]
            - cell "0-0" [ref=f1e285]
            - cell "0-0" [ref=f1e286]
            - cell "0-0" [ref=f1e287]
            - cell "0-0" [ref=f1e288]
            - cell "0-0" [ref=f1e289]
            - cell "0-0" [ref=f1e290]
            - cell "0-0" [ref=f1e291]
            - cell "0-0" [ref=f1e292]
            - cell "0-0" [ref=f1e293]
            - cell "0-0" [ref=f1e294]
            - cell "0-0" [ref=f1e295]
            - cell "0-0" [ref=f1e296]
          - row [ref=f1e297]:
            - rowheader "Detroit Diesels" [ref=f1e298]
            - cell "0-0" [ref=f1e299]
            - cell "0-0" [ref=f1e300]
            - cell "0-0" [ref=f1e301]
            - cell "0-0" [ref=f1e302]
            - cell "0-0" [ref=f1e303]
            - cell "0-0" [ref=f1e304]
            - cell [ref=f1e305]
            - cell "0-0" [ref=f1e306]
            - cell "0-0" [ref=f1e307]
            - cell "0-0" [ref=f1e308]
            - cell "0-0" [ref=f1e309]
            - cell "0-0" [ref=f1e310]
            - cell "0-0" [ref=f1e311]
            - cell "0-0" [ref=f1e312]
            - cell "0-0" [ref=f1e313]
            - cell "0-0" [ref=f1e314]
            - cell "0-0" [ref=f1e315]
            - cell "0-0" [ref=f1e316]
            - cell "0-0" [ref=f1e317]
            - cell "0-0" [ref=f1e318]
            - cell "0-0" [ref=f1e319]
            - cell "0-0" [ref=f1e320]
            - cell "0-0" [ref=f1e321]
            - cell "0-0" [ref=f1e322]
            - cell "0-0" [ref=f1e323]
            - cell "0-0" [ref=f1e324]
            - cell "0-0" [ref=f1e325]
            - cell "0-0" [ref=f1e326]
          - row [ref=f1e327]:
            - rowheader "Phoenix Flames" [ref=f1e328]
            - cell "0-0" [ref=f1e329]
            - cell "0-0" [ref=f1e330]
            - cell "0-0" [ref=f1e331]
            - cell "0-0" [ref=f1e332]
            - cell "0-0" [ref=f1e333]
            - cell "0-0" [ref=f1e334]
            - cell "0-0" [ref=f1e335]
            - cell [ref=f1e336]
            - cell "0-0" [ref=f1e337]
            - cell "0-0" [ref=f1e338]
            - cell "0-0" [ref=f1e339]
            - cell "0-0" [ref=f1e340]
            - cell "0-0" [ref=f1e341]
            - cell "0-0" [ref=f1e342]
            - cell "0-0" [ref=f1e343]
            - cell "0-0" [ref=f1e344]
            - cell "0-0" [ref=f1e345]
            - cell "0-0" [ref=f1e346]
            - cell "0-0" [ref=f1e347]
            - cell "0-0" [ref=f1e348]
            - cell "0-0" [ref=f1e349]
            - cell "0-0" [ref=f1e350]
            - cell "0-0" [ref=f1e351]
            - cell "0-0" [ref=f1e352]
            - cell "0-0" [ref=f1e353]
            - cell "0-0" [ref=f1e354]
            - cell "0-0" [ref=f1e355]
            - cell "0-0" [ref=f1e356]
          - row [ref=f1e357]:
            - rowheader "Washington Generals" [ref=f1e358]
            - cell "0-0" [ref=f1e359]
            - cell "0-0" [ref=f1e360]
            - cell "0-0" [ref=f1e361]
            - cell "0-0" [ref=f1e362]
            - cell "0-0" [ref=f1e363]
            - cell "0-0" [ref=f1e364]
            - cell "0-0" [ref=f1e365]
            - cell "0-0" [ref=f1e366]
            - cell [ref=f1e367]
            - cell "0-0" [ref=f1e368]
            - cell "0-0" [ref=f1e369]
            - cell "0-0" [ref=f1e370]
            - cell "0-0" [ref=f1e371]
            - cell "0-0" [ref=f1e372]
            - cell "0-0" [ref=f1e373]
            - cell "0-0" [ref=f1e374]
            - cell "0-0" [ref=f1e375]
            - cell "0-0" [ref=f1e376]
            - cell "0-0" [ref=f1e377]
            - cell "0-0" [ref=f1e378]
            - cell "0-0" [ref=f1e379]
            - cell "0-0" [ref=f1e380]
            - cell "0-0" [ref=f1e381]
            - cell "0-0" [ref=f1e382]
            - cell "0-0" [ref=f1e383]
            - cell "0-0" [ref=f1e384]
            - cell "0-0" [ref=f1e385]
            - cell "0-0" [ref=f1e386]
          - row [ref=f1e387]:
            - rowheader "Toronto Huskies" [ref=f1e388]
            - cell "0-0" [ref=f1e389]
            - cell "0-0" [ref=f1e390]
            - cell "0-0" [ref=f1e391]
            - cell "0-0" [ref=f1e392]
            - cell "0-0" [ref=f1e393]
            - cell "0-0" [ref=f1e394]
            - cell "0-0" [ref=f1e395]
            - cell "0-0" [ref=f1e396]
            - cell "0-0" [ref=f1e397]
            - cell [ref=f1e398]
            - cell "0-0" [ref=f1e399]
            - cell "0-0" [ref=f1e400]
            - cell "0-0" [ref=f1e401]
            - cell "0-0" [ref=f1e402]
            - cell "0-0" [ref=f1e403]
            - cell "0-0" [ref=f1e404]
            - cell "0-0" [ref=f1e405]
            - cell "0-0" [ref=f1e406]
            - cell "0-0" [ref=f1e407]
            - cell "0-0" [ref=f1e408]
            - cell "0-0" [ref=f1e409]
            - cell "0-0" [ref=f1e410]
            - cell "0-0" [ref=f1e411]
            - cell "0-0" [ref=f1e412]
            - cell "0-0" [ref=f1e413]
            - cell "0-0" [ref=f1e414]
            - cell "0-0" [ref=f1e415]
            - cell "0-0" [ref=f1e416]
          - row [ref=f1e417]:
            - rowheader "Utah Jazz" [ref=f1e418]
            - cell "0-0" [ref=f1e419]
            - cell "0-0" [ref=f1e420]
            - cell "0-0" [ref=f1e421]
            - cell "0-0" [ref=f1e422]
            - cell "0-0" [ref=f1e423]
            - cell "0-0" [ref=f1e424]
            - cell "0-0" [ref=f1e425]
            - cell "0-0" [ref=f1e426]
            - cell "0-0" [ref=f1e427]
            - cell "0-0" [ref=f1e428]
            - cell [ref=f1e429]
            - cell "0-0" [ref=f1e430]
            - cell "0-0" [ref=f1e431]
            - cell "0-0" [ref=f1e432]
            - cell "0-0" [ref=f1e433]
            - cell "0-0" [ref=f1e434]
            - cell "0-0" [ref=f1e435]
            - cell "0-0" [ref=f1e436]
            - cell "0-0" [ref=f1e437]
            - cell "0-0" [ref=f1e438]
            - cell "0-0" [ref=f1e439]
            - cell "0-0" [ref=f1e440]
            - cell "0-0" [ref=f1e441]
            - cell "0-0" [ref=f1e442]
            - cell "0-0" [ref=f1e443]
            - cell "0-0" [ref=f1e444]
            - cell "0-0" [ref=f1e445]
            - cell "0-0" [ref=f1e446]
          - row [ref=f1e447]:
            - rowheader "Dallas Mavericks" [ref=f1e448]
            - cell "0-0" [ref=f1e449]
            - cell "0-0" [ref=f1e450]
            - cell "0-0" [ref=f1e451]
            - cell "0-0" [ref=f1e452]
            - cell "0-0" [ref=f1e453]
            - cell "0-0" [ref=f1e454]
            - cell "0-0" [ref=f1e455]
            - cell "0-0" [ref=f1e456]
            - cell "0-0" [ref=f1e457]
            - cell "0-0" [ref=f1e458]
            - cell "0-0" [ref=f1e459]
            - cell [ref=f1e460]
            - cell "0-0" [ref=f1e461]
            - cell "0-0" [ref=f1e462]
            - cell "0-0" [ref=f1e463]
            - cell "0-0" [ref=f1e464]
            - cell "0-0" [ref=f1e465]
            - cell "0-0" [ref=f1e466]
            - cell "0-0" [ref=f1e467]
            - cell "0-0" [ref=f1e468]
            - cell "0-0" [ref=f1e469]
            - cell "0-0" [ref=f1e470]
            - cell "0-0" [ref=f1e471]
            - cell "0-0" [ref=f1e472]
            - cell "0-0" [ref=f1e473]
            - cell "0-0" [ref=f1e474]
            - cell "0-0" [ref=f1e475]
            - cell "0-0" [ref=f1e476]
          - row [ref=f1e477]:
            - rowheader "New York Metros" [ref=f1e478]
            - cell "0-0" [ref=f1e479]
            - cell "0-0" [ref=f1e480]
            - cell "0-0" [ref=f1e481]
            - cell "0-0" [ref=f1e482]
            - cell "0-0" [ref=f1e483]
            - cell "1-0" [ref=f1e484]
            - cell "0-0" [ref=f1e485]
            - cell "0-0" [ref=f1e486]
            - cell "0-0" [ref=f1e487]
            - cell "0-0" [ref=f1e488]
            - cell "0-0" [ref=f1e489]
            - cell "0-0" [ref=f1e490]
            - cell [ref=f1e491]
            - cell "0-0" [ref=f1e492]
            - cell "0-0" [ref=f1e493]
            - cell "0-0" [ref=f1e494]
            - cell "0-0" [ref=f1e495]
            - cell "0-0" [ref=f1e496]
            - cell "0-0" [ref=f1e497]
            - cell "0-0" [ref=f1e498]
            - cell "0-0" [ref=f1e499]
            - cell "0-0" [ref=f1e500]
            - cell "0-0" [ref=f1e501]
            - cell "0-0" [ref=f1e502]
            - cell "3-1" [ref=f1e503]
            - cell "0-0" [ref=f1e504]
            - cell "0-0" [ref=f1e505]
            - cell "0-0" [ref=f1e506]
          - row [ref=f1e507]:
            - rowheader "Boston Minutemen" [ref=f1e508]
            - cell "0-0" [ref=f1e509]
            - cell "0-0" [ref=f1e510]
            - cell "0-0" [ref=f1e511]
            - cell "0-0" [ref=f1e512]
            - cell "0-0" [ref=f1e513]
            - cell "0-0" [ref=f1e514]
            - cell "0-0" [ref=f1e515]
            - cell "0-0" [ref=f1e516]
            - cell "0-0" [ref=f1e517]
            - cell "0-0" [ref=f1e518]
            - cell "0-0" [ref=f1e519]
            - cell "0-0" [ref=f1e520]
            - cell "0-0" [ref=f1e521]
            - cell [ref=f1e522]
            - cell "0-0" [ref=f1e523]
            - cell "0-0" [ref=f1e524]
            - cell "0-0" [ref=f1e525]
            - cell "0-0" [ref=f1e526]
            - cell "0-0" [ref=f1e527]
            - cell "0-0" [ref=f1e528]
            - cell "0-0" [ref=f1e529]
            - cell "0-0" [ref=f1e530]
            - cell "1-0" [ref=f1e531]
            - cell "0-0" [ref=f1e532]
            - cell "0-0" [ref=f1e533]
            - cell "0-0" [ref=f1e534]
            - cell "0-0" [ref=f1e535]
            - cell "0-0" [ref=f1e536]
          - row [ref=f1e537]:
            - rowheader "Miami Monarchs" [ref=f1e538]
            - cell "0-0" [ref=f1e539]
            - cell "0-0" [ref=f1e540]
            - cell "0-0" [ref=f1e541]
            - cell "0-0" [ref=f1e542]
            - cell "0-0" [ref=f1e543]
            - cell "0-0" [ref=f1e544]
            - cell "0-0" [ref=f1e545]
            - cell "0-0" [ref=f1e546]
            - cell "0-0" [ref=f1e547]
            - cell "0-0" [ref=f1e548]
            - cell "0-0" [ref=f1e549]
            - cell "0-0" [ref=f1e550]
            - cell "0-0" [ref=f1e551]
            - cell "0-0" [ref=f1e552]
            - cell [ref=f1e553]
            - cell "0-0" [ref=f1e554]
            - cell "0-0" [ref=f1e555]
            - cell "0-0" [ref=f1e556]
            - cell "0-0" [ref=f1e557]
            - cell "0-0" [ref=f1e558]
            - cell "0-0" [ref=f1e559]
            - cell "0-0" [ref=f1e560]
            - cell "0-0" [ref=f1e561]
            - cell "0-0" [ref=f1e562]
            - cell "0-0" [ref=f1e563]
            - cell "0-0" [ref=f1e564]
            - cell "0-0" [ref=f1e565]
            - cell "0-0" [ref=f1e566]
          - row [ref=f1e567]:
            - rowheader "New Jersey Nets" [ref=f1e568]
            - cell "0-0" [ref=f1e569]
            - cell "0-0" [ref=f1e570]
            - cell "0-0" [ref=f1e571]
            - cell "0-0" [ref=f1e572]
            - cell "0-0" [ref=f1e573]
            - cell "0-0" [ref=f1e574]
            - cell "0-0" [ref=f1e575]
            - cell "0-0" [ref=f1e576]
            - cell "0-0" [ref=f1e577]
            - cell "0-0" [ref=f1e578]
            - cell "0-0" [ref=f1e579]
            - cell "0-0" [ref=f1e580]
            - cell "0-0" [ref=f1e581]
            - cell "0-0" [ref=f1e582]
            - cell "0-0" [ref=f1e583]
            - cell [ref=f1e584]
            - cell "0-0" [ref=f1e585]
            - cell "0-0" [ref=f1e586]
            - cell "0-0" [ref=f1e587]
            - cell "0-0" [ref=f1e588]
            - cell "0-0" [ref=f1e589]
            - cell "0-0" [ref=f1e590]
            - cell "0-0" [ref=f1e591]
            - cell "0-0" [ref=f1e592]
            - cell "0-0" [ref=f1e593]
            - cell "0-0" [ref=f1e594]
            - cell "0-0" [ref=f1e595]
            - cell "0-0" [ref=f1e596]
          - row [ref=f1e597]:
            - rowheader "Denver Nuggets" [ref=f1e598]
            - cell "0-0" [ref=f1e599]
            - cell "0-0" [ref=f1e600]
            - cell "0-0" [ref=f1e601]
            - cell "0-0" [ref=f1e602]
            - cell "0-0" [ref=f1e603]
            - cell "0-0" [ref=f1e604]
            - cell "0-0" [ref=f1e605]
            - cell "0-0" [ref=f1e606]
            - cell "0-0" [ref=f1e607]
            - cell "0-0" [ref=f1e608]
            - cell "0-0" [ref=f1e609]
            - cell "0-0" [ref=f1e610]
            - cell "0-0" [ref=f1e611]
            - cell "0-0" [ref=f1e612]
            - cell "0-0" [ref=f1e613]
            - cell "0-0" [ref=f1e614]
            - cell [ref=f1e615]
            - cell "0-0" [ref=f1e616]
            - cell "0-0" [ref=f1e617]
            - cell "0-0" [ref=f1e618]
            - cell "0-0" [ref=f1e619]
            - cell "0-0" [ref=f1e620]
            - cell "0-0" [ref=f1e621]
            - cell "0-0" [ref=f1e622]
            - cell "0-0" [ref=f1e623]
            - cell "0-0" [ref=f1e624]
            - cell "0-0" [ref=f1e625]
            - cell "0-0" [ref=f1e626]
          - row [ref=f1e627]:
            - rowheader "Indiana Pacers" [ref=f1e628]
            - cell "0-0" [ref=f1e629]
            - cell "0-0" [ref=f1e630]
            - cell "0-0" [ref=f1e631]
            - cell "0-0" [ref=f1e632]
            - cell "0-0" [ref=f1e633]
            - cell "0-0" [ref=f1e634]
            - cell "0-0" [ref=f1e635]
            - cell "0-0" [ref=f1e636]
            - cell "0-0" [ref=f1e637]
            - cell "0-0" [ref=f1e638]
            - cell "0-0" [ref=f1e639]
            - cell "0-0" [ref=f1e640]
            - cell "0-0" [ref=f1e641]
            - cell "0-0" [ref=f1e642]
            - cell "0-0" [ref=f1e643]
            - cell "0-0" [ref=f1e644]
            - cell "0-0" [ref=f1e645]
            - cell [ref=f1e646]
            - cell "0-0" [ref=f1e647]
            - cell "0-0" [ref=f1e648]
            - cell "0-0" [ref=f1e649]
            - cell "0-0" [ref=f1e650]
            - cell "0-0" [ref=f1e651]
            - cell "0-0" [ref=f1e652]
            - cell "0-0" [ref=f1e653]
            - cell "0-0" [ref=f1e654]
            - cell "0-0" [ref=f1e655]
            - cell "0-0" [ref=f1e656]
          - row [ref=f1e657]:
            - rowheader "Atlanta Phoenixes" [ref=f1e658]
            - cell "0-0" [ref=f1e659]
            - cell "0-0" [ref=f1e660]
            - cell "0-0" [ref=f1e661]
            - cell "0-0" [ref=f1e662]
            - cell "0-0" [ref=f1e663]
            - cell "0-0" [ref=f1e664]
            - cell "0-0" [ref=f1e665]
            - cell "0-0" [ref=f1e666]
            - cell "0-0" [ref=f1e667]
            - cell "0-0" [ref=f1e668]
            - cell "0-0" [ref=f1e669]
            - cell "0-0" [ref=f1e670]
            - cell "0-0" [ref=f1e671]
            - cell "0-0" [ref=f1e672]
            - cell "0-0" [ref=f1e673]
            - cell "0-0" [ref=f1e674]
            - cell "0-0" [ref=f1e675]
            - cell "0-0" [ref=f1e676]
            - cell [ref=f1e677]
            - cell "0-0" [ref=f1e678]
            - cell "0-0" [ref=f1e679]
            - cell "0-0" [ref=f1e680]
            - cell "0-0" [ref=f1e681]
            - cell "0-0" [ref=f1e682]
            - cell "0-0" [ref=f1e683]
            - cell "0-0" [ref=f1e684]
            - cell "0-0" [ref=f1e685]
            - cell "0-0" [ref=f1e686]
          - row [ref=f1e687]:
            - rowheader "Sacramento Pilots" [ref=f1e688]
            - cell "0-0" [ref=f1e689]
            - cell "0-0" [ref=f1e690]
            - cell "0-0" [ref=f1e691]
            - cell "0-0" [ref=f1e692]
            - cell "0-0" [ref=f1e693]
            - cell "0-0" [ref=f1e694]
            - cell "0-0" [ref=f1e695]
            - cell "0-0" [ref=f1e696]
            - cell "0-0" [ref=f1e697]
            - cell "0-0" [ref=f1e698]
            - cell "0-0" [ref=f1e699]
            - cell "0-0" [ref=f1e700]
            - cell "0-0" [ref=f1e701]
            - cell "0-0" [ref=f1e702]
            - cell "0-0" [ref=f1e703]
            - cell "0-0" [ref=f1e704]
            - cell "0-0" [ref=f1e705]
            - cell "0-0" [ref=f1e706]
            - cell "0-0" [ref=f1e707]
            - cell [ref=f1e708]
            - cell "0-0" [ref=f1e709]
            - cell "0-0" [ref=f1e710]
            - cell "0-0" [ref=f1e711]
            - cell "0-0" [ref=f1e712]
            - cell "0-0" [ref=f1e713]
            - cell "0-0" [ref=f1e714]
            - cell "0-0" [ref=f1e715]
            - cell "0-0" [ref=f1e716]
          - row [ref=f1e717]:
            - rowheader "Portland Pioneers" [ref=f1e718]
            - cell "0-0" [ref=f1e719]
            - cell "0-0" [ref=f1e720]
            - cell "0-0" [ref=f1e721]
            - cell "0-0" [ref=f1e722]
            - cell "0-0" [ref=f1e723]
            - cell "0-0" [ref=f1e724]
            - cell "0-0" [ref=f1e725]
            - cell "0-0" [ref=f1e726]
            - cell "0-0" [ref=f1e727]
            - cell "0-0" [ref=f1e728]
            - cell "0-0" [ref=f1e729]
            - cell "0-0" [ref=f1e730]
            - cell "0-0" [ref=f1e731]
            - cell "0-0" [ref=f1e732]
            - cell "0-0" [ref=f1e733]
            - cell "0-0" [ref=f1e734]
            - cell "0-0" [ref=f1e735]
            - cell "0-0" [ref=f1e736]
            - cell "0-0" [ref=f1e737]
            - cell "0-0" [ref=f1e738]
            - cell [ref=f1e739]
            - cell "0-0" [ref=f1e740]
            - cell "0-0" [ref=f1e741]
            - cell "0-0" [ref=f1e742]
            - cell "0-0" [ref=f1e743]
            - cell "0-0" [ref=f1e744]
            - cell "0-0" [ref=f1e745]
            - cell "0-0" [ref=f1e746]
          - row [ref=f1e747]:
            - rowheader "Philadelphia Rage" [ref=f1e748]
            - cell "0-0" [ref=f1e749]
            - cell "0-0" [ref=f1e750]
            - cell "0-0" [ref=f1e751]
            - cell "0-0" [ref=f1e752]
            - cell "0-0" [ref=f1e753]
            - cell "0-0" [ref=f1e754]
            - cell "0-0" [ref=f1e755]
            - cell "0-0" [ref=f1e756]
            - cell "0-0" [ref=f1e757]
            - cell "0-0" [ref=f1e758]
            - cell "0-0" [ref=f1e759]
            - cell "0-0" [ref=f1e760]
            - cell "0-0" [ref=f1e761]
            - cell "0-0" [ref=f1e762]
            - cell "0-0" [ref=f1e763]
            - cell "0-0" [ref=f1e764]
            - cell "0-0" [ref=f1e765]
            - cell "0-0" [ref=f1e766]
            - cell "0-0" [ref=f1e767]
            - cell "0-0" [ref=f1e768]
            - cell "0-0" [ref=f1e769]
            - cell [ref=f1e770]
            - cell "0-0" [ref=f1e771]
            - cell "0-0" [ref=f1e772]
            - cell "0-0" [ref=f1e773]
            - cell "0-0" [ref=f1e774]
            - cell "0-0" [ref=f1e775]
            - cell "0-0" [ref=f1e776]
          - row [ref=f1e777]:
            - rowheader "Charlotte Royals" [ref=f1e778]
            - cell "0-0" [ref=f1e779]
            - cell "0-0" [ref=f1e780]
            - cell "0-0" [ref=f1e781]
            - cell "0-0" [ref=f1e782]
            - cell "0-0" [ref=f1e783]
            - cell "0-0" [ref=f1e784]
            - cell "0-0" [ref=f1e785]
            - cell "0-0" [ref=f1e786]
            - cell "0-0" [ref=f1e787]
            - cell "0-0" [ref=f1e788]
            - cell "0-0" [ref=f1e789]
            - cell "0-0" [ref=f1e790]
            - cell "0-0" [ref=f1e791]
            - cell "0-1" [ref=f1e792]
            - cell "0-0" [ref=f1e793]
            - cell "0-0" [ref=f1e794]
            - cell "0-0" [ref=f1e795]
            - cell "0-0" [ref=f1e796]
            - cell "0-0" [ref=f1e797]
            - cell "0-0" [ref=f1e798]
            - cell "0-0" [ref=f1e799]
            - cell "0-0" [ref=f1e800]
            - cell [ref=f1e801]
            - cell "0-0" [ref=f1e802]
            - cell "0-0" [ref=f1e803]
            - cell "0-0" [ref=f1e804]
            - cell "0-0" [ref=f1e805]
            - cell "0-0" [ref=f1e806]
          - row [ref=f1e807]:
            - rowheader "San Antonio Spurs" [ref=f1e808]
            - cell "0-0" [ref=f1e809]
            - cell "0-0" [ref=f1e810]
            - cell "0-0" [ref=f1e811]
            - cell "0-0" [ref=f1e812]
            - cell "0-0" [ref=f1e813]
            - cell "0-0" [ref=f1e814]
            - cell "0-0" [ref=f1e815]
            - cell "0-0" [ref=f1e816]
            - cell "0-0" [ref=f1e817]
            - cell "0-0" [ref=f1e818]
            - cell "0-0" [ref=f1e819]
            - cell "0-0" [ref=f1e820]
            - cell "0-0" [ref=f1e821]
            - cell "0-0" [ref=f1e822]
            - cell "0-0" [ref=f1e823]
            - cell "0-0" [ref=f1e824]
            - cell "0-0" [ref=f1e825]
            - cell "0-0" [ref=f1e826]
            - cell "0-0" [ref=f1e827]
            - cell "0-0" [ref=f1e828]
            - cell "0-0" [ref=f1e829]
            - cell "0-0" [ref=f1e830]
            - cell "0-0" [ref=f1e831]
            - cell [ref=f1e832]
            - cell "0-0" [ref=f1e833]
            - cell "0-0" [ref=f1e834]
            - cell "0-0" [ref=f1e835]
            - cell "0-0" [ref=f1e836]
          - row [ref=f1e837]:
            - rowheader "Los Angeles Stars" [ref=f1e838]
            - cell "0-0" [ref=f1e839]
            - cell "0-0" [ref=f1e840]
            - cell "0-0" [ref=f1e841]
            - cell "0-0" [ref=f1e842]
            - cell "0-0" [ref=f1e843]
            - cell "0-0" [ref=f1e844]
            - cell "0-0" [ref=f1e845]
            - cell "0-0" [ref=f1e846]
            - cell "0-0" [ref=f1e847]
            - cell "0-0" [ref=f1e848]
            - cell "0-0" [ref=f1e849]
            - cell "0-0" [ref=f1e850]
            - cell "1-3" [ref=f1e851]
            - cell "0-0" [ref=f1e852]
            - cell "0-0" [ref=f1e853]
            - cell "0-0" [ref=f1e854]
            - cell "0-0" [ref=f1e855]
            - cell "0-0" [ref=f1e856]
            - cell "0-0" [ref=f1e857]
            - cell "0-0" [ref=f1e858]
            - cell "0-0" [ref=f1e859]
            - cell "0-0" [ref=f1e860]
            - cell "0-0" [ref=f1e861]
            - cell "0-0" [ref=f1e862]
            - cell [ref=f1e863]
            - cell "0-0" [ref=f1e864]
            - cell "0-0" [ref=f1e865]
            - cell "0-0" [ref=f1e866]
          - row [ref=f1e867]:
            - rowheader "Seattle Supersonics" [ref=f1e868]
            - cell "0-0" [ref=f1e869]
            - cell "0-0" [ref=f1e870]
            - cell "0-0" [ref=f1e871]
            - cell "0-0" [ref=f1e872]
            - cell "0-0" [ref=f1e873]
            - cell "0-0" [ref=f1e874]
            - cell "0-0" [ref=f1e875]
            - cell "0-0" [ref=f1e876]
            - cell "0-0" [ref=f1e877]
            - cell "0-0" [ref=f1e878]
            - cell "0-0" [ref=f1e879]
            - cell "0-0" [ref=f1e880]
            - cell "0-0" [ref=f1e881]
            - cell "0-0" [ref=f1e882]
            - cell "0-0" [ref=f1e883]
            - cell "0-0" [ref=f1e884]
            - cell "0-0" [ref=f1e885]
            - cell "0-0" [ref=f1e886]
            - cell "0-0" [ref=f1e887]
            - cell "0-0" [ref=f1e888]
            - cell "0-0" [ref=f1e889]
            - cell "0-0" [ref=f1e890]
            - cell "0-0" [ref=f1e891]
            - cell "0-0" [ref=f1e892]
            - cell "0-0" [ref=f1e893]
            - cell [ref=f1e894]
            - cell "0-0" [ref=f1e895]
            - cell "0-0" [ref=f1e896]
          - row [ref=f1e897]:
            - rowheader "Oklahoma City Thunder" [ref=f1e898]
            - cell "0-0" [ref=f1e899]
            - cell "0-0" [ref=f1e900]
            - cell "0-0" [ref=f1e901]
            - cell "0-0" [ref=f1e902]
            - cell "0-0" [ref=f1e903]
            - cell "0-0" [ref=f1e904]
            - cell "0-0" [ref=f1e905]
            - cell "0-0" [ref=f1e906]
            - cell "0-0" [ref=f1e907]
            - cell "0-0" [ref=f1e908]
            - cell "0-0" [ref=f1e909]
            - cell "0-0" [ref=f1e910]
            - cell "0-0" [ref=f1e911]
            - cell "0-0" [ref=f1e912]
            - cell "0-0" [ref=f1e913]
            - cell "0-0" [ref=f1e914]
            - cell "0-0" [ref=f1e915]
            - cell "0-0" [ref=f1e916]
            - cell "0-0" [ref=f1e917]
            - cell "0-0" [ref=f1e918]
            - cell "0-0" [ref=f1e919]
            - cell "0-0" [ref=f1e920]
            - cell "0-0" [ref=f1e921]
            - cell "0-0" [ref=f1e922]
            - cell "0-0" [ref=f1e923]
            - cell "0-0" [ref=f1e924]
            - cell [ref=f1e925]
            - cell "0-0" [ref=f1e926]
          - row [ref=f1e927]:
            - rowheader "Orlando Tropics" [ref=f1e928]
            - cell "0-0" [ref=f1e929]
            - cell "0-0" [ref=f1e930]
            - cell "0-0" [ref=f1e931]
            - cell "0-0" [ref=f1e932]
            - cell "0-0" [ref=f1e933]
            - cell "0-0" [ref=f1e934]
            - cell "0-0" [ref=f1e935]
            - cell "0-0" [ref=f1e936]
            - cell "0-0" [ref=f1e937]
            - cell "0-0" [ref=f1e938]
            - cell "0-0" [ref=f1e939]
            - cell "0-0" [ref=f1e940]
            - cell "0-0" [ref=f1e941]
            - cell "0-0" [ref=f1e942]
            - cell "0-0" [ref=f1e943]
            - cell "0-0" [ref=f1e944]
            - cell "0-0" [ref=f1e945]
            - cell "0-0" [ref=f1e946]
            - cell "0-0" [ref=f1e947]
            - cell "0-0" [ref=f1e948]
            - cell "0-0" [ref=f1e949]
            - cell "0-0" [ref=f1e950]
            - cell "0-0" [ref=f1e951]
            - cell "0-0" [ref=f1e952]
            - cell "0-0" [ref=f1e953]
            - cell "0-0" [ref=f1e954]
            - cell "0-0" [ref=f1e955]
            - cell [ref=f1e956]
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
      |                        ^ Error: expect(page).toHaveScreenshot(expected) failed
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