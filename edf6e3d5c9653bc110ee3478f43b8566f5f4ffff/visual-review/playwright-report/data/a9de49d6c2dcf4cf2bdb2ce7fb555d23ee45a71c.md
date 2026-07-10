# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke/visual-regression.spec.ts >> Visual regression — authenticated pages (full-page) >> free-agency-out-of-phase
- Location: tests/e2e/smoke/visual-regression.spec.ts:242:9

# Error details

```
Error: expect(page).toHaveScreenshot(expected) failed

  Expected an image 1280px by 1920px, received 1280px by 1957px. 22963 pixels (ratio 0.01 of all image pixels) are different.

  Snapshot: free-agency-out-of-phase.png

Call log:
  - Expect "toHaveScreenshot(free-agency-out-of-phase.png)" with timeout 10000ms
    - verifying given screenshot expectation
  - taking page screenshot
    - disabled all CSS animations
  - waiting for fonts to load...
  - fonts loaded
  - Expected an image 1280px by 1920px, received 1280px by 1957px. 22963 pixels (ratio 0.01 of all image pixels) are different.
  - waiting 100ms before taking screenshot
  - taking page screenshot
    - disabled all CSS animations
  - waiting for fonts to load...
  - fonts loaded
  - captured a stable screenshot
  - Expected an image 1280px by 1920px, received 1280px by 1957px. 22963 pixels (ratio 0.01 of all image pixels) are different.

```

# Page snapshot

```yaml
- generic [active] [ref=e1]:
  - navigation [ref=e2]:
    - link "Switch to localhost" [ref=e5] [cursor=pointer]:
      - /url: http://localhost/ibl5/modules.php?name=FreeAgency
      - img [ref=e6]
    - generic [ref=e10]:
      - link "IBL Sim League" [ref=e11] [cursor=pointer]:
        - /url: index.php
        - img [ref=e14]
        - generic [ref=e18]:
          - generic [ref=e19]: IBL
          - generic [ref=e20]: Sim League
      - generic [ref=e21]:
        - button "Debug" [ref=e23]:
          - img [ref=e25]
          - generic [ref=e27]: Debug
          - img [ref=e28]
        - generic [ref=e30]:
          - button "Season" [ref=e31]:
            - img [ref=e33]
            - generic [ref=e35]: Season
            - img [ref=e36]
          - option "IBL" [selected]
          - option "Olympics"
        - button "Stats" [ref=e39]:
          - img [ref=e41]
          - generic [ref=e43]: Stats
          - img [ref=e44]
        - button "History" [ref=e47]:
          - img [ref=e49]
          - generic [ref=e51]: History
          - img [ref=e52]
        - button "Community" [ref=e55]:
          - img [ref=e57]
          - generic [ref=e59]: Community
          - img [ref=e60]
        - generic [ref=e62]:
          - button "Teams" [ref=e64]:
            - img [ref=e66]
            - generic [ref=e70]: Teams
            - img [ref=e71]
          - button "Team Logo My Team" [ref=e74]:
            - img "Team Logo" [ref=e76]
            - generic [ref=e77]: My Team
            - img [ref=e78]
  - main [ref=e80]:
    - generic [ref=e81]: "Admin mode: You can view this module, but it is currently closed to non-admin GMs."
    - heading "Free Agency" [level=1] [ref=e82]
    - img "Team Logo" [ref=e83]
    - region "Players under contract" [ref=e85]:
      - table [ref=e86]:
        - rowgroup [ref=e94]:
          - row "Players Under Contract" [ref=e95]:
            - columnheader "Players Under Contract" [ref=e96]
          - row "Pos Player Age 2ga 2g% fta ft% 3ga 3g% orb drb ast stl tvr blk foul oo do po to od dd pd td T S I 25-26 26-27 27-28 28-29 29-30 30-31 Loy PFW PT Sec Trd" [ref=e97]:
            - columnheader "Pos" [ref=e98] [cursor=pointer]
            - columnheader "Player" [ref=e99] [cursor=pointer]
            - columnheader "Age" [ref=e100] [cursor=pointer]
            - columnheader "2ga" [ref=e101] [cursor=pointer]
            - columnheader "2g%" [ref=e102] [cursor=pointer]
            - columnheader "fta" [ref=e103] [cursor=pointer]
            - columnheader "ft%" [ref=e104] [cursor=pointer]
            - columnheader "3ga" [ref=e105] [cursor=pointer]
            - columnheader "3g%" [ref=e106] [cursor=pointer]
            - columnheader "orb" [ref=e107] [cursor=pointer]
            - columnheader "drb" [ref=e108] [cursor=pointer]
            - columnheader "ast" [ref=e109] [cursor=pointer]
            - columnheader "stl" [ref=e110] [cursor=pointer]
            - columnheader "tvr" [ref=e111] [cursor=pointer]
            - columnheader "blk" [ref=e112] [cursor=pointer]
            - columnheader "foul" [ref=e113] [cursor=pointer]
            - columnheader "oo" [ref=e114] [cursor=pointer]
            - columnheader "do" [ref=e115] [cursor=pointer]
            - columnheader "po" [ref=e116] [cursor=pointer]
            - columnheader "to" [ref=e117] [cursor=pointer]
            - columnheader "od" [ref=e118] [cursor=pointer]
            - columnheader "dd" [ref=e119] [cursor=pointer]
            - columnheader "pd" [ref=e120] [cursor=pointer]
            - columnheader "td" [ref=e121] [cursor=pointer]
            - columnheader "T" [ref=e122] [cursor=pointer]
            - columnheader "S" [ref=e123] [cursor=pointer]
            - columnheader "I" [ref=e124] [cursor=pointer]
            - columnheader "25-26" [ref=e125] [cursor=pointer]
            - columnheader "26-27" [ref=e126] [cursor=pointer]
            - columnheader "27-28" [ref=e127] [cursor=pointer]
            - columnheader "28-29" [ref=e128] [cursor=pointer]
            - columnheader "29-30" [ref=e129] [cursor=pointer]
            - columnheader "30-31" [ref=e130] [cursor=pointer]
            - columnheader "Loy" [ref=e131] [cursor=pointer]
            - columnheader "PFW" [ref=e132] [cursor=pointer]
            - columnheader "PT" [ref=e133] [cursor=pointer]
            - columnheader "Sec" [ref=e134] [cursor=pointer]
            - columnheader "Trd" [ref=e135] [cursor=pointer]
        - rowgroup [ref=e136]:
          - row "SG Test Player 28 0 0 0 0 0 0 0 0 0 0 65 0 0 75 65 72 70 70 60 68 0 0 0 0 880 0 0 0 0 0 0 0 0 0 0" [ref=e137]:
            - cell "SG" [ref=e138]
            - cell "Test Player" [ref=e139]:
              - link "Test Player" [ref=e140] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=1
            - cell "28" [ref=e141]
            - cell "0" [ref=e142]
            - cell "0" [ref=e143]
            - cell "0" [ref=e144]
            - cell "0" [ref=e145]
            - cell "0" [ref=e146]
            - cell "0" [ref=e147]
            - cell "0" [ref=e148]
            - cell "0" [ref=e149]
            - cell "0" [ref=e150]
            - cell "0" [ref=e151]
            - cell "65" [ref=e152]
            - cell "0" [ref=e153]
            - cell "0" [ref=e154]
            - cell "75" [ref=e155]
            - cell "65" [ref=e156]
            - cell "72" [ref=e157]
            - cell "70" [ref=e158]
            - cell "70" [ref=e159]
            - cell "60" [ref=e160]
            - cell "68" [ref=e161]
            - cell "0" [ref=e162]
            - cell "0" [ref=e163]
            - cell "0" [ref=e164]
            - cell "0" [ref=e165]
            - cell "880" [ref=e166]
            - cell "0" [ref=e167]
            - cell "0" [ref=e168]
            - cell "0" [ref=e169]
            - cell "0" [ref=e170]
            - cell "0" [ref=e171]
            - cell "0" [ref=e172]
            - cell "0" [ref=e173]
            - cell "0" [ref=e174]
            - cell "0" [ref=e175]
            - cell "0" [ref=e176]
          - row "PF Test Player Two 26 0 0 0 0 0 0 0 0 0 0 63 0 0 72 63 70 68 68 58 66 0 0 0 0 660 0 0 0 0 0 0 0 0 0 0" [ref=e177]:
            - cell "PF" [ref=e178]
            - cell "Test Player Two" [ref=e179]:
              - link "Test Player Two" [ref=e180] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=2
            - cell "26" [ref=e181]
            - cell "0" [ref=e182]
            - cell "0" [ref=e183]
            - cell "0" [ref=e184]
            - cell "0" [ref=e185]
            - cell "0" [ref=e186]
            - cell "0" [ref=e187]
            - cell "0" [ref=e188]
            - cell "0" [ref=e189]
            - cell "0" [ref=e190]
            - cell "0" [ref=e191]
            - cell "63" [ref=e192]
            - cell "0" [ref=e193]
            - cell "0" [ref=e194]
            - cell "72" [ref=e195]
            - cell "63" [ref=e196]
            - cell "70" [ref=e197]
            - cell "68" [ref=e198]
            - cell "68" [ref=e199]
            - cell "58" [ref=e200]
            - cell "66" [ref=e201]
            - cell "0" [ref=e202]
            - cell "0" [ref=e203]
            - cell "0" [ref=e204]
            - cell "0" [ref=e205]
            - cell "660" [ref=e206]
            - cell "0" [ref=e207]
            - cell "0" [ref=e208]
            - cell "0" [ref=e209]
            - cell "0" [ref=e210]
            - cell "0" [ref=e211]
            - cell "0" [ref=e212]
            - cell "0" [ref=e213]
            - cell "0" [ref=e214]
            - cell "0" [ref=e215]
            - cell "0" [ref=e216]
          - row "PG Metros PG 27 0 0 0 0 0 0 0 0 0 0 65 0 0 76 66 72 70 70 60 68 0 0 0 0 330 0 0 0 0 0 0 0 0 0 0" [ref=e217]:
            - cell "PG" [ref=e218]
            - cell "Metros PG" [ref=e219]:
              - link "Metros PG" [ref=e220] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=20
            - cell "27" [ref=e221]
            - cell "0" [ref=e222]
            - cell "0" [ref=e223]
            - cell "0" [ref=e224]
            - cell "0" [ref=e225]
            - cell "0" [ref=e226]
            - cell "0" [ref=e227]
            - cell "0" [ref=e228]
            - cell "0" [ref=e229]
            - cell "0" [ref=e230]
            - cell "0" [ref=e231]
            - cell "65" [ref=e232]
            - cell "0" [ref=e233]
            - cell "0" [ref=e234]
            - cell "76" [ref=e235]
            - cell "66" [ref=e236]
            - cell "72" [ref=e237]
            - cell "70" [ref=e238]
            - cell "70" [ref=e239]
            - cell "60" [ref=e240]
            - cell "68" [ref=e241]
            - cell "0" [ref=e242]
            - cell "0" [ref=e243]
            - cell "0" [ref=e244]
            - cell "0" [ref=e245]
            - cell "330" [ref=e246]
            - cell "0" [ref=e247]
            - cell "0" [ref=e248]
            - cell "0" [ref=e249]
            - cell "0" [ref=e250]
            - cell "0" [ref=e251]
            - cell "0" [ref=e252]
            - cell "0" [ref=e253]
            - cell "0" [ref=e254]
            - cell "0" [ref=e255]
            - cell "0" [ref=e256]
          - row "SF Metros SF 26 0 0 0 0 0 0 0 0 0 0 63 0 0 74 64 70 68 68 58 66 0 0 0 0 220 0 0 0 0 0 0 0 0 0 0" [ref=e257]:
            - cell "SF" [ref=e258]
            - cell "Metros SF" [ref=e259]:
              - link "Metros SF" [ref=e260] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=21
            - cell "26" [ref=e261]
            - cell "0" [ref=e262]
            - cell "0" [ref=e263]
            - cell "0" [ref=e264]
            - cell "0" [ref=e265]
            - cell "0" [ref=e266]
            - cell "0" [ref=e267]
            - cell "0" [ref=e268]
            - cell "0" [ref=e269]
            - cell "0" [ref=e270]
            - cell "0" [ref=e271]
            - cell "63" [ref=e272]
            - cell "0" [ref=e273]
            - cell "0" [ref=e274]
            - cell "74" [ref=e275]
            - cell "64" [ref=e276]
            - cell "70" [ref=e277]
            - cell "68" [ref=e278]
            - cell "68" [ref=e279]
            - cell "58" [ref=e280]
            - cell "66" [ref=e281]
            - cell "0" [ref=e282]
            - cell "0" [ref=e283]
            - cell "0" [ref=e284]
            - cell "0" [ref=e285]
            - cell "220" [ref=e286]
            - cell "0" [ref=e287]
            - cell "0" [ref=e288]
            - cell "0" [ref=e289]
            - cell "0" [ref=e290]
            - cell "0" [ref=e291]
            - cell "0" [ref=e292]
            - cell "0" [ref=e293]
            - cell "0" [ref=e294]
            - cell "0" [ref=e295]
            - cell "0" [ref=e296]
          - row "C Metros Center 29 0 0 0 0 0 0 0 0 0 0 69 0 0 78 68 76 74 72 64 72 0 0 0 0 330 0 0 0 0 0 0 0 0 0 0" [ref=e297]:
            - cell "C" [ref=e298]
            - cell "Metros Center" [ref=e299]:
              - link "Metros Center" [ref=e300] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=22
            - cell "29" [ref=e301]
            - cell "0" [ref=e302]
            - cell "0" [ref=e303]
            - cell "0" [ref=e304]
            - cell "0" [ref=e305]
            - cell "0" [ref=e306]
            - cell "0" [ref=e307]
            - cell "0" [ref=e308]
            - cell "0" [ref=e309]
            - cell "0" [ref=e310]
            - cell "0" [ref=e311]
            - cell "69" [ref=e312]
            - cell "0" [ref=e313]
            - cell "0" [ref=e314]
            - cell "78" [ref=e315]
            - cell "68" [ref=e316]
            - cell "76" [ref=e317]
            - cell "74" [ref=e318]
            - cell "72" [ref=e319]
            - cell "64" [ref=e320]
            - cell "72" [ref=e321]
            - cell "0" [ref=e322]
            - cell "0" [ref=e323]
            - cell "0" [ref=e324]
            - cell "0" [ref=e325]
            - cell "330" [ref=e326]
            - cell "0" [ref=e327]
            - cell "0" [ref=e328]
            - cell "0" [ref=e329]
            - cell "0" [ref=e330]
            - cell "0" [ref=e331]
            - cell "0" [ref=e332]
            - cell "0" [ref=e333]
            - cell "0" [ref=e334]
            - cell "0" [ref=e335]
            - cell "0" [ref=e336]
          - row "PG Metros Backup PG 24 0 0 0 0 0 0 0 0 0 0 60 0 0 70 60 67 65 65 55 63 0 0 0 0 110 0 0 0 0 0 0 0 0 0 0" [ref=e337]:
            - cell "PG" [ref=e338]
            - cell "Metros Backup PG" [ref=e339]:
              - link "Metros Backup PG" [ref=e340] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=25
            - cell "24" [ref=e341]
            - cell "0" [ref=e342]
            - cell "0" [ref=e343]
            - cell "0" [ref=e344]
            - cell "0" [ref=e345]
            - cell "0" [ref=e346]
            - cell "0" [ref=e347]
            - cell "0" [ref=e348]
            - cell "0" [ref=e349]
            - cell "0" [ref=e350]
            - cell "0" [ref=e351]
            - cell "60" [ref=e352]
            - cell "0" [ref=e353]
            - cell "0" [ref=e354]
            - cell "70" [ref=e355]
            - cell "60" [ref=e356]
            - cell "67" [ref=e357]
            - cell "65" [ref=e358]
            - cell "65" [ref=e359]
            - cell "55" [ref=e360]
            - cell "63" [ref=e361]
            - cell "0" [ref=e362]
            - cell "0" [ref=e363]
            - cell "0" [ref=e364]
            - cell "0" [ref=e365]
            - cell "110" [ref=e366]
            - cell "0" [ref=e367]
            - cell "0" [ref=e368]
            - cell "0" [ref=e369]
            - cell "0" [ref=e370]
            - cell "0" [ref=e371]
            - cell "0" [ref=e372]
            - cell "0" [ref=e373]
            - cell "0" [ref=e374]
            - cell "0" [ref=e375]
            - cell "0" [ref=e376]
          - row "C Konstantinos Papadopoulos 27 0 0 0 0 0 0 0 0 0 0 0 0 0 70 60 68 66 65 55 64 61 0 0 0 440 0 0 0 0 0 0 0 0 0 0" [ref=e377]:
            - cell "C" [ref=e378]
            - cell "Konstantinos Papadopoulos" [ref=e379]:
              - link "Konstantinos Papadopoulos" [ref=e380] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=200000030
            - cell "27" [ref=e381]
            - cell "0" [ref=e382]
            - cell "0" [ref=e383]
            - cell "0" [ref=e384]
            - cell "0" [ref=e385]
            - cell "0" [ref=e386]
            - cell "0" [ref=e387]
            - cell "0" [ref=e388]
            - cell "0" [ref=e389]
            - cell "0" [ref=e390]
            - cell "0" [ref=e391]
            - cell "0" [ref=e392]
            - cell "0" [ref=e393]
            - cell "0" [ref=e394]
            - cell "70" [ref=e395]
            - cell "60" [ref=e396]
            - cell "68" [ref=e397]
            - cell "66" [ref=e398]
            - cell "65" [ref=e399]
            - cell "55" [ref=e400]
            - cell "64" [ref=e401]
            - cell "61" [ref=e402]
            - cell "0" [ref=e403]
            - cell "0" [ref=e404]
            - cell "0" [ref=e405]
            - cell "440" [ref=e406]
            - cell "0" [ref=e407]
            - cell "0" [ref=e408]
            - cell "0" [ref=e409]
            - cell "0" [ref=e410]
            - cell "0" [ref=e411]
            - cell "0" [ref=e412]
            - cell "0" [ref=e413]
            - cell "0" [ref=e414]
            - cell "0" [ref=e415]
            - cell "0" [ref=e416]
          - row "SF Metros Backup SF 25 0 0 0 0 0 0 0 0 0 0 59 0 0 69 59 66 64 64 54 62 0 0 0 0 110 0 0 0 0 0 0 0 0 0 0" [ref=e417]:
            - cell "SF" [ref=e418]
            - cell "Metros Backup SF" [ref=e419]:
              - link "Metros Backup SF" [ref=e420] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=26
            - cell "25" [ref=e421]
            - cell "0" [ref=e422]
            - cell "0" [ref=e423]
            - cell "0" [ref=e424]
            - cell "0" [ref=e425]
            - cell "0" [ref=e426]
            - cell "0" [ref=e427]
            - cell "0" [ref=e428]
            - cell "0" [ref=e429]
            - cell "0" [ref=e430]
            - cell "0" [ref=e431]
            - cell "59" [ref=e432]
            - cell "0" [ref=e433]
            - cell "0" [ref=e434]
            - cell "69" [ref=e435]
            - cell "59" [ref=e436]
            - cell "66" [ref=e437]
            - cell "64" [ref=e438]
            - cell "64" [ref=e439]
            - cell "54" [ref=e440]
            - cell "62" [ref=e441]
            - cell "0" [ref=e442]
            - cell "0" [ref=e443]
            - cell "0" [ref=e444]
            - cell "0" [ref=e445]
            - cell "110" [ref=e446]
            - cell "0" [ref=e447]
            - cell "0" [ref=e448]
            - cell "0" [ref=e449]
            - cell "0" [ref=e450]
            - cell "0" [ref=e451]
            - cell "0" [ref=e452]
            - cell "0" [ref=e453]
            - cell "0" [ref=e454]
            - cell "0" [ref=e455]
            - cell "0" [ref=e456]
          - row "PF Metros Utility 26 0 0 0 0 0 0 0 0 0 0 61 0 0 71 61 68 66 66 56 64 0 0 0 0 130 0 0 0 0 0 0 0 0 0 0" [ref=e457]:
            - cell "PF" [ref=e458]
            - cell "Metros Utility" [ref=e459]:
              - link "Metros Utility" [ref=e460] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=27
            - cell "26" [ref=e461]
            - cell "0" [ref=e462]
            - cell "0" [ref=e463]
            - cell "0" [ref=e464]
            - cell "0" [ref=e465]
            - cell "0" [ref=e466]
            - cell "0" [ref=e467]
            - cell "0" [ref=e468]
            - cell "0" [ref=e469]
            - cell "0" [ref=e470]
            - cell "0" [ref=e471]
            - cell "61" [ref=e472]
            - cell "0" [ref=e473]
            - cell "0" [ref=e474]
            - cell "71" [ref=e475]
            - cell "61" [ref=e476]
            - cell "68" [ref=e477]
            - cell "66" [ref=e478]
            - cell "66" [ref=e479]
            - cell "56" [ref=e480]
            - cell "64" [ref=e481]
            - cell "0" [ref=e482]
            - cell "0" [ref=e483]
            - cell "0" [ref=e484]
            - cell "0" [ref=e485]
            - cell "130" [ref=e486]
            - cell "0" [ref=e487]
            - cell "0" [ref=e488]
            - cell "0" [ref=e489]
            - cell "0" [ref=e490]
            - cell "0" [ref=e491]
            - cell "0" [ref=e492]
            - cell "0" [ref=e493]
            - cell "0" [ref=e494]
            - cell "0" [ref=e495]
            - cell "0" [ref=e496]
          - row "SF Metros Backup SF2 23 0 0 0 0 0 0 0 0 0 0 58 0 0 68 58 65 63 63 53 61 0 0 0 0 88 0 0 0 0 0 0 0 0 0 0" [ref=e497]:
            - cell "SF" [ref=e498]
            - cell "Metros Backup SF2" [ref=e499]:
              - link "Metros Backup SF2" [ref=e500] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=28
            - cell "23" [ref=e501]
            - cell "0" [ref=e502]
            - cell "0" [ref=e503]
            - cell "0" [ref=e504]
            - cell "0" [ref=e505]
            - cell "0" [ref=e506]
            - cell "0" [ref=e507]
            - cell "0" [ref=e508]
            - cell "0" [ref=e509]
            - cell "0" [ref=e510]
            - cell "0" [ref=e511]
            - cell "58" [ref=e512]
            - cell "0" [ref=e513]
            - cell "0" [ref=e514]
            - cell "68" [ref=e515]
            - cell "58" [ref=e516]
            - cell "65" [ref=e517]
            - cell "63" [ref=e518]
            - cell "63" [ref=e519]
            - cell "53" [ref=e520]
            - cell "61" [ref=e521]
            - cell "0" [ref=e522]
            - cell "0" [ref=e523]
            - cell "0" [ref=e524]
            - cell "0" [ref=e525]
            - cell "88" [ref=e526]
            - cell "0" [ref=e527]
            - cell "0" [ref=e528]
            - cell "0" [ref=e529]
            - cell "0" [ref=e530]
            - cell "0" [ref=e531]
            - cell "0" [ref=e532]
            - cell "0" [ref=e533]
            - cell "0" [ref=e534]
            - cell "0" [ref=e535]
            - cell "0" [ref=e536]
          - row "PF Metros Backup PF 27 0 0 0 0 0 0 0 0 0 0 62 0 0 72 62 69 67 67 57 65 0 0 0 0 155 0 0 0 0 0 0 0 0 0 0" [ref=e537]:
            - cell "PF" [ref=e538]
            - cell "Metros Backup PF" [ref=e539]:
              - link "Metros Backup PF" [ref=e540] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=29
            - cell "27" [ref=e541]
            - cell "0" [ref=e542]
            - cell "0" [ref=e543]
            - cell "0" [ref=e544]
            - cell "0" [ref=e545]
            - cell "0" [ref=e546]
            - cell "0" [ref=e547]
            - cell "0" [ref=e548]
            - cell "0" [ref=e549]
            - cell "0" [ref=e550]
            - cell "0" [ref=e551]
            - cell "62" [ref=e552]
            - cell "0" [ref=e553]
            - cell "0" [ref=e554]
            - cell "72" [ref=e555]
            - cell "62" [ref=e556]
            - cell "69" [ref=e557]
            - cell "67" [ref=e558]
            - cell "67" [ref=e559]
            - cell "57" [ref=e560]
            - cell "65" [ref=e561]
            - cell "0" [ref=e562]
            - cell "0" [ref=e563]
            - cell "0" [ref=e564]
            - cell "0" [ref=e565]
            - cell "155" [ref=e566]
            - cell "0" [ref=e567]
            - cell "0" [ref=e568]
            - cell "0" [ref=e569]
            - cell "0" [ref=e570]
            - cell "0" [ref=e571]
            - cell "0" [ref=e572]
            - cell "0" [ref=e573]
            - cell "0" [ref=e574]
            - cell "0" [ref=e575]
            - cell "0" [ref=e576]
          - row "SF Waive Target 27 0 0 0 0 0 0 0 0 0 0 63 0 0 72 63 70 68 68 58 66 0 0 0 0 1 0 0 0 0 0 0 0 0 0 0" [ref=e577]:
            - cell "SF" [ref=e578]
            - cell "Waive Target" [ref=e579]:
              - link "Waive Target" [ref=e580] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=200000031
            - cell "27" [ref=e581]
            - cell "0" [ref=e582]
            - cell "0" [ref=e583]
            - cell "0" [ref=e584]
            - cell "0" [ref=e585]
            - cell "0" [ref=e586]
            - cell "0" [ref=e587]
            - cell "0" [ref=e588]
            - cell "0" [ref=e589]
            - cell "0" [ref=e590]
            - cell "0" [ref=e591]
            - cell "63" [ref=e592]
            - cell "0" [ref=e593]
            - cell "0" [ref=e594]
            - cell "72" [ref=e595]
            - cell "63" [ref=e596]
            - cell "70" [ref=e597]
            - cell "68" [ref=e598]
            - cell "68" [ref=e599]
            - cell "58" [ref=e600]
            - cell "66" [ref=e601]
            - cell "0" [ref=e602]
            - cell "0" [ref=e603]
            - cell "0" [ref=e604]
            - cell "0" [ref=e605]
            - cell "1" [ref=e606]
            - cell "0" [ref=e607]
            - cell "0" [ref=e608]
            - cell "0" [ref=e609]
            - cell "0" [ref=e610]
            - cell "0" [ref=e611]
            - cell "0" [ref=e612]
            - cell "0" [ref=e613]
            - cell "0" [ref=e614]
            - cell "0" [ref=e615]
            - cell "0" [ref=e616]
          - row "PG Rookie Option Target 23 0 0 0 0 0 0 0 0 0 0 64 0 0 74 64 71 69 69 59 67 0 0 0 0 1 500 0 0 0 0 0 0 0 0 0" [ref=e617]:
            - cell "PG" [ref=e618]
            - cell "Rookie Option Target" [ref=e619]:
              - link "Rookie Option Target" [ref=e620] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=200000032
            - cell "23" [ref=e621]
            - cell "0" [ref=e622]
            - cell "0" [ref=e623]
            - cell "0" [ref=e624]
            - cell "0" [ref=e625]
            - cell "0" [ref=e626]
            - cell "0" [ref=e627]
            - cell "0" [ref=e628]
            - cell "0" [ref=e629]
            - cell "0" [ref=e630]
            - cell "0" [ref=e631]
            - cell "64" [ref=e632]
            - cell "0" [ref=e633]
            - cell "0" [ref=e634]
            - cell "74" [ref=e635]
            - cell "64" [ref=e636]
            - cell "71" [ref=e637]
            - cell "69" [ref=e638]
            - cell "69" [ref=e639]
            - cell "59" [ref=e640]
            - cell "67" [ref=e641]
            - cell "0" [ref=e642]
            - cell "0" [ref=e643]
            - cell "0" [ref=e644]
            - cell "0" [ref=e645]
            - cell "1" [ref=e646]
            - cell "500" [ref=e647]
            - cell "0" [ref=e648]
            - cell "0" [ref=e649]
            - cell "0" [ref=e650]
            - cell "0" [ref=e651]
            - cell "0" [ref=e652]
            - cell "0" [ref=e653]
            - cell "0" [ref=e654]
            - cell "0" [ref=e655]
            - cell "0" [ref=e656]
          - row "Cash from Trade 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0" [ref=e657]:
            - cell [ref=e658]
            - cell "Cash from Trade" [ref=e659]:
              - link "Cash from Trade" [ref=e660] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=0
            - cell "0" [ref=e661]
            - cell "0" [ref=e662]
            - cell "0" [ref=e663]
            - cell "0" [ref=e664]
            - cell "0" [ref=e665]
            - cell "0" [ref=e666]
            - cell "0" [ref=e667]
            - cell "0" [ref=e668]
            - cell "0" [ref=e669]
            - cell "0" [ref=e670]
            - cell "0" [ref=e671]
            - cell "0" [ref=e672]
            - cell "0" [ref=e673]
            - cell "0" [ref=e674]
            - cell "0" [ref=e675]
            - cell "0" [ref=e676]
            - cell "0" [ref=e677]
            - cell "0" [ref=e678]
            - cell "0" [ref=e679]
            - cell "0" [ref=e680]
            - cell "0" [ref=e681]
            - cell "0" [ref=e682]
            - cell "0" [ref=e683]
            - cell "0" [ref=e684]
            - cell "0" [ref=e685]
            - cell "0" [ref=e686]
            - cell "0" [ref=e687]
            - cell "0" [ref=e688]
            - cell "0" [ref=e689]
            - cell "0" [ref=e690]
            - cell "0" [ref=e691]
            - cell "0" [ref=e692]
            - cell "0" [ref=e693]
            - cell "0" [ref=e694]
            - cell "0" [ref=e695]
            - cell "0" [ref=e696]
        - rowgroup [ref=e697]:
          - row "Metros Total Salary 5315 2216 1300 0 0 0" [ref=e698]:
            - cell [ref=e699]
            - cell "Metros Total Salary" [ref=e700]:
              - strong [ref=e701]: Metros Total Salary
            - cell "5315" [ref=e702]:
              - strong [ref=e703]: "5315"
            - cell "2216" [ref=e704]:
              - strong [ref=e705]: "2216"
            - cell "1300" [ref=e706]:
              - strong [ref=e707]: "1300"
            - cell "0" [ref=e708]:
              - strong [ref=e709]: "0"
            - cell "0" [ref=e710]:
              - strong [ref=e711]: "0"
            - cell "0" [ref=e712]:
              - strong [ref=e713]: "0"
            - cell [ref=e714]
    - region "Contract offers" [ref=e716]:
      - table [ref=e717]:
        - rowgroup [ref=e725]:
          - row "Contract Offers" [ref=e726]:
            - columnheader "Contract Offers" [ref=e727]
          - row "Actions Pos Player Age 2ga 2g% fta ft% 3ga 3g% orb drb ast stl tvr blk foul oo do po to od dd pd td T S I 25-26 26-27 27-28 28-29 29-30 30-31 Loy PFW PT Sec Trd" [ref=e728]:
            - columnheader "Actions" [ref=e729] [cursor=pointer]:
              - generic [ref=e730]: Actions
            - columnheader "Pos" [ref=e731] [cursor=pointer]
            - columnheader "Player" [ref=e732] [cursor=pointer]
            - columnheader "Age" [ref=e733] [cursor=pointer]
            - columnheader "2ga" [ref=e734] [cursor=pointer]
            - columnheader "2g%" [ref=e735] [cursor=pointer]
            - columnheader "fta" [ref=e736] [cursor=pointer]
            - columnheader "ft%" [ref=e737] [cursor=pointer]
            - columnheader "3ga" [ref=e738] [cursor=pointer]
            - columnheader "3g%" [ref=e739] [cursor=pointer]
            - columnheader "orb" [ref=e740] [cursor=pointer]
            - columnheader "drb" [ref=e741] [cursor=pointer]
            - columnheader "ast" [ref=e742] [cursor=pointer]
            - columnheader "stl" [ref=e743] [cursor=pointer]
            - columnheader "tvr" [ref=e744] [cursor=pointer]
            - columnheader "blk" [ref=e745] [cursor=pointer]
            - columnheader "foul" [ref=e746] [cursor=pointer]
            - columnheader "oo" [ref=e747] [cursor=pointer]
            - columnheader "do" [ref=e748] [cursor=pointer]
            - columnheader "po" [ref=e749] [cursor=pointer]
            - columnheader "to" [ref=e750] [cursor=pointer]
            - columnheader "od" [ref=e751] [cursor=pointer]
            - columnheader "dd" [ref=e752] [cursor=pointer]
            - columnheader "pd" [ref=e753] [cursor=pointer]
            - columnheader "td" [ref=e754] [cursor=pointer]
            - columnheader "T" [ref=e755] [cursor=pointer]
            - columnheader "S" [ref=e756] [cursor=pointer]
            - columnheader "I" [ref=e757] [cursor=pointer]
            - columnheader "25-26" [ref=e758] [cursor=pointer]
            - columnheader "26-27" [ref=e759] [cursor=pointer]
            - columnheader "27-28" [ref=e760] [cursor=pointer]
            - columnheader "28-29" [ref=e761] [cursor=pointer]
            - columnheader "29-30" [ref=e762] [cursor=pointer]
            - columnheader "30-31" [ref=e763] [cursor=pointer]
            - columnheader "Loy" [ref=e764] [cursor=pointer]
            - columnheader "PFW" [ref=e765] [cursor=pointer]
            - columnheader "PT" [ref=e766] [cursor=pointer]
            - columnheader "Sec" [ref=e767] [cursor=pointer]
            - columnheader "Trd" [ref=e768] [cursor=pointer]
        - rowgroup [ref=e769]:
          - row "Offer C FA Center 30 0 0 0 0 0 0 0 0 0 0 0 0 0 72 63 70 68 68 58 66 63 0 0 0 480 528 0 0 0 0 0 0 0 0 0" [ref=e770]:
            - cell "Offer" [ref=e771]:
              - link "Offer" [ref=e772] [cursor=pointer]:
                - /url: modules.php?name=FreeAgency&pa=negotiate&pid=11
            - cell "C" [ref=e773]
            - cell "FA Center" [ref=e774]:
              - link "FA Center" [ref=e775] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=11
            - cell "30" [ref=e776]
            - cell "0" [ref=e777]
            - cell "0" [ref=e778]
            - cell "0" [ref=e779]
            - cell "0" [ref=e780]
            - cell "0" [ref=e781]
            - cell "0" [ref=e782]
            - cell "0" [ref=e783]
            - cell "0" [ref=e784]
            - cell "0" [ref=e785]
            - cell "0" [ref=e786]
            - cell "0" [ref=e787]
            - cell "0" [ref=e788]
            - cell "0" [ref=e789]
            - cell "72" [ref=e790]
            - cell "63" [ref=e791]
            - cell "70" [ref=e792]
            - cell "68" [ref=e793]
            - cell "68" [ref=e794]
            - cell "58" [ref=e795]
            - cell "66" [ref=e796]
            - cell "63" [ref=e797]
            - cell "0" [ref=e798]
            - cell "0" [ref=e799]
            - cell "0" [ref=e800]
            - cell "480" [ref=e801]
            - cell "528" [ref=e802]
            - cell "0" [ref=e803]
            - cell "0" [ref=e804]
            - cell "0" [ref=e805]
            - cell "0" [ref=e806]
            - cell "0" [ref=e807]
            - cell "0" [ref=e808]
            - cell "0" [ref=e809]
            - cell "0" [ref=e810]
            - cell "0" [ref=e811]
          - row "Offer SF FA Forward 25 0 0 0 0 0 0 0 0 0 0 0 0 0 74 64 71 69 69 59 67 64 0 0 0 380 418 460 0 0 0 0 0 0 0 0" [ref=e812]:
            - cell "Offer" [ref=e813]:
              - link "Offer" [ref=e814] [cursor=pointer]:
                - /url: modules.php?name=FreeAgency&pa=negotiate&pid=12
            - cell "SF" [ref=e815]
            - cell "FA Forward" [ref=e816]:
              - link "FA Forward" [ref=e817] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=12
            - cell "25" [ref=e818]
            - cell "0" [ref=e819]
            - cell "0" [ref=e820]
            - cell "0" [ref=e821]
            - cell "0" [ref=e822]
            - cell "0" [ref=e823]
            - cell "0" [ref=e824]
            - cell "0" [ref=e825]
            - cell "0" [ref=e826]
            - cell "0" [ref=e827]
            - cell "0" [ref=e828]
            - cell "0" [ref=e829]
            - cell "0" [ref=e830]
            - cell "0" [ref=e831]
            - cell "74" [ref=e832]
            - cell "64" [ref=e833]
            - cell "71" [ref=e834]
            - cell "69" [ref=e835]
            - cell "69" [ref=e836]
            - cell "59" [ref=e837]
            - cell "67" [ref=e838]
            - cell "64" [ref=e839]
            - cell "0" [ref=e840]
            - cell "0" [ref=e841]
            - cell "0" [ref=e842]
            - cell "380" [ref=e843]
            - cell "418" [ref=e844]
            - cell "460" [ref=e845]
            - cell "0" [ref=e846]
            - cell "0" [ref=e847]
            - cell "0" [ref=e848]
            - cell "0" [ref=e849]
            - cell "0" [ref=e850]
            - cell "0" [ref=e851]
            - cell "0" [ref=e852]
            - cell "0" [ref=e853]
          - row "Offer SG FA Guard 26 0 0 0 0 0 0 0 0 0 0 0 0 0 75 65 72 70 70 60 68 65 0 0 0 700 770 840 0 0 0 0 0 0 0 0" [ref=e854]:
            - cell "Offer" [ref=e855]:
              - link "Offer" [ref=e856] [cursor=pointer]:
                - /url: modules.php?name=FreeAgency&pa=negotiate&pid=10
            - cell "SG" [ref=e857]
            - cell "FA Guard" [ref=e858]:
              - link "FA Guard" [ref=e859] [cursor=pointer]:
                - /url: ./modules.php?name=Player&pa=showpage&pid=10
            - cell "26" [ref=e860]
            - cell "0" [ref=e861]
            - cell "0" [ref=e862]
            - cell "0" [ref=e863]
            - cell "0" [ref=e864]
            - cell "0" [ref=e865]
            - cell "0" [ref=e866]
            - cell "0" [ref=e867]
            - cell "0" [ref=e868]
            - cell "0" [ref=e869]
            - cell "0" [ref=e870]
            - cell "0" [ref=e871]
            - cell "0" [ref=e872]
            - cell "0" [ref=e873]
            - cell "75" [ref=e874]
            - cell "65" [ref=e875]
            - cell "72" [ref=e876]
            - cell "70" [ref=e877]
            - cell "70" [ref=e878]
            - cell "60" [ref=e879]
            - cell "68" [ref=e880]
            - cell "65" [ref=e881]
            - cell "0" [ref=e882]
            - cell "0" [ref=e883]
            - cell "0" [ref=e884]
            - cell "700" [ref=e885]
            - cell "770" [ref=e886]
            - cell "840" [ref=e887]
            - cell "0" [ref=e888]
            - cell "0" [ref=e889]
            - cell "0" [ref=e890]
            - cell "0" [ref=e891]
            - cell "0" [ref=e892]
            - cell "0" [ref=e893]
            - cell "0" [ref=e894]
            - cell "0" [ref=e895]
        - rowgroup [ref=e896]:
          - row "Metros Total Salary Plus Contract Offers 5315 2216 1300 0 0 0" [ref=e897]:
            - cell [ref=e898]
            - cell "Metros Total Salary Plus Contract Offers" [ref=e899]:
              - strong [ref=e900]: Metros Total Salary Plus Contract Offers
            - cell "5315" [ref=e901]:
              - strong [ref=e902]: "5315"
            - cell "2216" [ref=e903]:
              - strong [ref=e904]: "2216"
            - cell "1300" [ref=e905]:
              - strong [ref=e906]: "1300"
            - cell "0" [ref=e907]:
              - strong [ref=e908]: "0"
            - cell "0" [ref=e909]:
              - strong [ref=e910]: "0"
            - cell "0" [ref=e911]:
              - strong [ref=e912]: "0"
            - cell [ref=e913]
          - 'row "Soft Cap Space -315 2784 3700 5000 5000 5000 MLE: ✅" [ref=e914]':
            - cell [ref=e915]
            - cell "Soft Cap Space" [ref=e916]
            - cell "-315" [ref=e917]
            - cell "2784" [ref=e918]
            - cell "3700" [ref=e919]
            - cell "5000" [ref=e920]
            - cell "5000" [ref=e921]
            - cell "5000" [ref=e922]
            - cell [ref=e923]
            - cell "MLE:" [ref=e924]:
              - strong [ref=e925]: "MLE:"
            - cell "✅" [ref=e926]
            - cell [ref=e927]
          - 'row "Hard Cap Space 1685 4784 5700 7000 7000 7000 LLE: ✅" [ref=e928]':
            - cell [ref=e929]
            - cell "Hard Cap Space" [ref=e930]
            - cell "1685" [ref=e931]
            - cell "4784" [ref=e932]
            - cell "5700" [ref=e933]
            - cell "7000" [ref=e934]
            - cell "7000" [ref=e935]
            - cell "7000" [ref=e936]
            - cell [ref=e937]
            - cell "LLE:" [ref=e938]:
              - strong [ref=e939]: "LLE:"
            - cell "✅" [ref=e940]
            - cell [ref=e941]
          - row "Empty Roster Slots -1 11 13 15 15 15" [ref=e942]:
            - cell [ref=e943]
            - cell "Empty Roster Slots" [ref=e944]
            - cell "-1" [ref=e945]
            - cell "11" [ref=e946]
            - cell "13" [ref=e947]
            - cell "15" [ref=e948]
            - cell "15" [ref=e949]
            - cell "15" [ref=e950]
            - cell [ref=e951]
    - region "Unsigned free agents" [ref=e953]:
      - table [ref=e954]:
        - rowgroup [ref=e962]:
          - 'row "Unsigned Free Agents (Note: * and italicized indicates player has Bird Rights)" [ref=e963]':
            - 'columnheader "Unsigned Free Agents (Note: * and italicized indicates player has Bird Rights)" [ref=e964]':
              - text: Unsigned Free Agents
              - generic [ref=e965]:
                - text: "(Note: * and"
                - emphasis [ref=e966]: italicized
                - text: indicates player has Bird Rights)
          - row "Actions Pos Player Age 2ga 2g% fta ft% 3ga 3g% orb drb ast stl tvr blk foul oo do po to od dd pd td T S I 25-26 26-27 27-28 28-29 29-30 30-31 Loy PFW PT Sec Trd" [ref=e967]:
            - columnheader "Actions" [ref=e968] [cursor=pointer]:
              - generic [ref=e969]: Actions
            - columnheader "Pos" [ref=e970] [cursor=pointer]
            - columnheader "Player" [ref=e971] [cursor=pointer]
            - columnheader "Age" [ref=e972] [cursor=pointer]
            - columnheader "2ga" [ref=e973] [cursor=pointer]
            - columnheader "2g%" [ref=e974] [cursor=pointer]
            - columnheader "fta" [ref=e975] [cursor=pointer]
            - columnheader "ft%" [ref=e976] [cursor=pointer]
            - columnheader "3ga" [ref=e977] [cursor=pointer]
            - columnheader "3g%" [ref=e978] [cursor=pointer]
            - columnheader "orb" [ref=e979] [cursor=pointer]
            - columnheader "drb" [ref=e980] [cursor=pointer]
            - columnheader "ast" [ref=e981] [cursor=pointer]
            - columnheader "stl" [ref=e982] [cursor=pointer]
            - columnheader "tvr" [ref=e983] [cursor=pointer]
            - columnheader "blk" [ref=e984] [cursor=pointer]
            - columnheader "foul" [ref=e985] [cursor=pointer]
            - columnheader "oo" [ref=e986] [cursor=pointer]
            - columnheader "do" [ref=e987] [cursor=pointer]
            - columnheader "po" [ref=e988] [cursor=pointer]
            - columnheader "to" [ref=e989] [cursor=pointer]
            - columnheader "od" [ref=e990] [cursor=pointer]
            - columnheader "dd" [ref=e991] [cursor=pointer]
            - columnheader "pd" [ref=e992] [cursor=pointer]
            - columnheader "td" [ref=e993] [cursor=pointer]
            - columnheader "T" [ref=e994] [cursor=pointer]
            - columnheader "S" [ref=e995] [cursor=pointer]
            - columnheader "I" [ref=e996] [cursor=pointer]
            - columnheader "25-26" [ref=e997] [cursor=pointer]
            - columnheader "26-27" [ref=e998] [cursor=pointer]
            - columnheader "27-28" [ref=e999] [cursor=pointer]
            - columnheader "28-29" [ref=e1000] [cursor=pointer]
            - columnheader "29-30" [ref=e1001] [cursor=pointer]
            - columnheader "30-31" [ref=e1002] [cursor=pointer]
            - columnheader "Loy" [ref=e1003] [cursor=pointer]
            - columnheader "PFW" [ref=e1004] [cursor=pointer]
            - columnheader "PT" [ref=e1005] [cursor=pointer]
            - columnheader "Sec" [ref=e1006] [cursor=pointer]
            - columnheader "Trd" [ref=e1007] [cursor=pointer]
        - rowgroup [ref=e1008]:
          - row "SG * FA Guard * 26 0 0 0 0 0 0 0 0 0 0 0 0 0 75 65 72 70 70 60 68 65 0 0 0 800 880 960 1040 0 0 0 0 0" [ref=e1009]:
            - cell [ref=e1010]
            - cell "SG" [ref=e1011]
            - cell "* FA Guard *" [ref=e1012]:
              - link "* FA Guard *" [ref=e1013] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=10
                - text: "*"
                - emphasis [ref=e1014]: FA Guard
                - text: "*"
            - cell "26" [ref=e1015]
            - cell "0" [ref=e1016]
            - cell "0" [ref=e1017]
            - cell "0" [ref=e1018]
            - cell "0" [ref=e1019]
            - cell "0" [ref=e1020]
            - cell "0" [ref=e1021]
            - cell "0" [ref=e1022]
            - cell "0" [ref=e1023]
            - cell "0" [ref=e1024]
            - cell "0" [ref=e1025]
            - cell "0" [ref=e1026]
            - cell "0" [ref=e1027]
            - cell "0" [ref=e1028]
            - cell "75" [ref=e1029]
            - cell "65" [ref=e1030]
            - cell "72" [ref=e1031]
            - cell "70" [ref=e1032]
            - cell "70" [ref=e1033]
            - cell "60" [ref=e1034]
            - cell "68" [ref=e1035]
            - cell "65" [ref=e1036]
            - cell "0" [ref=e1037]
            - cell "0" [ref=e1038]
            - cell "0" [ref=e1039]
            - cell "800" [ref=e1040]
            - cell "880" [ref=e1041]
            - cell "960" [ref=e1042]
            - cell "1040" [ref=e1043]
            - cell [ref=e1044]
            - cell [ref=e1045]
            - cell "0" [ref=e1046]
            - cell "0" [ref=e1047]
            - cell "0" [ref=e1048]
            - cell "0" [ref=e1049]
            - cell "0" [ref=e1050]
          - row "SG Extension Vet 30 0 0 0 0 0 0 0 0 0 0 71 0 0 80 70 78 76 75 65 74 0 0 0 0 0 0 0 0 0" [ref=e1051]:
            - cell [ref=e1052]
            - cell "SG" [ref=e1053]
            - cell "Extension Vet" [ref=e1054]:
              - link "Extension Vet" [ref=e1055] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=30
            - cell "30" [ref=e1056]
            - cell "0" [ref=e1057]
            - cell "0" [ref=e1058]
            - cell "0" [ref=e1059]
            - cell "0" [ref=e1060]
            - cell "0" [ref=e1061]
            - cell "0" [ref=e1062]
            - cell "0" [ref=e1063]
            - cell "0" [ref=e1064]
            - cell "0" [ref=e1065]
            - cell "0" [ref=e1066]
            - cell "71" [ref=e1067]
            - cell "0" [ref=e1068]
            - cell "0" [ref=e1069]
            - cell "80" [ref=e1070]
            - cell "70" [ref=e1071]
            - cell "78" [ref=e1072]
            - cell "76" [ref=e1073]
            - cell "75" [ref=e1074]
            - cell "65" [ref=e1075]
            - cell "74" [ref=e1076]
            - cell "0" [ref=e1077]
            - cell "0" [ref=e1078]
            - cell "0" [ref=e1079]
            - cell "0" [ref=e1080]
            - cell [ref=e1081]
            - cell [ref=e1082]
            - cell [ref=e1083]
            - cell [ref=e1084]
            - cell [ref=e1085]
            - cell [ref=e1086]
            - cell "0" [ref=e1087]
            - cell "0" [ref=e1088]
            - cell "0" [ref=e1089]
            - cell "0" [ref=e1090]
            - cell "0" [ref=e1091]
          - row "SG Extension Card Target 30 0 0 0 0 0 0 0 0 0 0 71 0 0 80 70 78 76 75 65 74 0 0 0 0 0 0 0 0 0" [ref=e1092]:
            - cell [ref=e1093]
            - cell "SG" [ref=e1094]
            - cell "Extension Card Target" [ref=e1095]:
              - link "Extension Card Target" [ref=e1096] [cursor=pointer]:
                - /url: modules.php?name=Player&pa=showpage&pid=200000033
            - cell "30" [ref=e1097]
            - cell "0" [ref=e1098]
            - cell "0" [ref=e1099]
            - cell "0" [ref=e1100]
            - cell "0" [ref=e1101]
            - cell "0" [ref=e1102]
            - cell "0" [ref=e1103]
            - cell "0" [ref=e1104]
            - cell "0" [ref=e1105]
            - cell "0" [ref=e1106]
            - cell "0" [ref=e1107]
            - cell "71" [ref=e1108]
            - cell "0" [ref=e1109]
            - cell "0" [ref=e1110]
            - cell "80" [ref=e1111]
            - cell "70" [ref=e1112]
            - cell "78" [ref=e1113]
            - cell "76" [ref=e1114]
            - cell "75" [ref=e1115]
            - cell "65" [ref=e1116]
            - cell "74" [ref=e1117]
            - cell "0" [ref=e1118]
            - cell "0" [ref=e1119]
            - cell "0" [ref=e1120]
            - cell "0" [ref=e1121]
            - cell [ref=e1122]
            - cell [ref=e1123]
            - cell [ref=e1124]
            - cell [ref=e1125]
            - cell [ref=e1126]
            - cell [ref=e1127]
            - cell "0" [ref=e1128]
            - cell "0" [ref=e1129]
            - cell "0" [ref=e1130]
            - cell "0" [ref=e1131]
            - cell "0" [ref=e1132]
    - table [ref=e1135]:
      - rowgroup [ref=e1143]:
        - row "All Other Free Agents" [ref=e1144]:
          - columnheader "All Other Free Agents" [ref=e1145]
        - row "Actions Pos Player Team Age 2ga 2g% fta ft% 3ga 3g% orb drb ast stl tvr blk foul oo do po to od dd pd td T S I 25-26 26-27 27-28 28-29 29-30 30-31 Loy PFW PT Sec Trd" [ref=e1146]:
          - columnheader "Actions" [ref=e1147] [cursor=pointer]:
            - generic [ref=e1148]: Actions
          - columnheader "Pos" [ref=e1149] [cursor=pointer]
          - columnheader "Player" [ref=e1150] [cursor=pointer]
          - columnheader "Team" [ref=e1151] [cursor=pointer]
          - columnheader "Age" [ref=e1152] [cursor=pointer]
          - columnheader "2ga" [ref=e1153] [cursor=pointer]
          - columnheader "2g%" [ref=e1154] [cursor=pointer]
          - columnheader "fta" [ref=e1155] [cursor=pointer]
          - columnheader "ft%" [ref=e1156] [cursor=pointer]
          - columnheader "3ga" [ref=e1157] [cursor=pointer]
          - columnheader "3g%" [ref=e1158] [cursor=pointer]
          - columnheader "orb" [ref=e1159] [cursor=pointer]
          - columnheader "drb" [ref=e1160] [cursor=pointer]
          - columnheader "ast" [ref=e1161] [cursor=pointer]
          - columnheader "stl" [ref=e1162] [cursor=pointer]
          - columnheader "tvr" [ref=e1163] [cursor=pointer]
          - columnheader "blk" [ref=e1164] [cursor=pointer]
          - columnheader "foul" [ref=e1165] [cursor=pointer]
          - columnheader "oo" [ref=e1166] [cursor=pointer]
          - columnheader "do" [ref=e1167] [cursor=pointer]
          - columnheader "po" [ref=e1168] [cursor=pointer]
          - columnheader "to" [ref=e1169] [cursor=pointer]
          - columnheader "od" [ref=e1170] [cursor=pointer]
          - columnheader "dd" [ref=e1171] [cursor=pointer]
          - columnheader "pd" [ref=e1172] [cursor=pointer]
          - columnheader "td" [ref=e1173] [cursor=pointer]
          - columnheader "T" [ref=e1174] [cursor=pointer]
          - columnheader "S" [ref=e1175] [cursor=pointer]
          - columnheader "I" [ref=e1176] [cursor=pointer]
          - columnheader "25-26" [ref=e1177] [cursor=pointer]
          - columnheader "26-27" [ref=e1178] [cursor=pointer]
          - columnheader "27-28" [ref=e1179] [cursor=pointer]
          - columnheader "28-29" [ref=e1180] [cursor=pointer]
          - columnheader "29-30" [ref=e1181] [cursor=pointer]
          - columnheader "30-31" [ref=e1182] [cursor=pointer]
          - columnheader "Loy" [ref=e1183] [cursor=pointer]
          - columnheader "PFW" [ref=e1184] [cursor=pointer]
          - columnheader "PT" [ref=e1185] [cursor=pointer]
          - columnheader "Sec" [ref=e1186] [cursor=pointer]
          - columnheader "Trd" [ref=e1187] [cursor=pointer]
      - rowgroup [ref=e1188]:
        - row "Offer C FA Center FA 30 0 0 0 0 0 0 0 0 0 0 0 0 0 72 63 70 68 68 58 66 63 0 0 0 500 550 600 0 0 0 0 0" [ref=e1189]:
          - cell "Offer" [ref=e1190]:
            - link "Offer" [ref=e1191] [cursor=pointer]:
              - /url: modules.php?name=FreeAgency&pa=negotiate&pid=11
          - cell "C" [ref=e1192]
          - cell "FA Center" [ref=e1193]:
            - link "FA Center" [ref=e1194] [cursor=pointer]:
              - /url: ./modules.php?name=Player&pa=showpage&pid=11
          - cell "FA" [ref=e1195]
          - cell "30" [ref=e1196]
          - cell "0" [ref=e1197]
          - cell "0" [ref=e1198]
          - cell "0" [ref=e1199]
          - cell "0" [ref=e1200]
          - cell "0" [ref=e1201]
          - cell "0" [ref=e1202]
          - cell "0" [ref=e1203]
          - cell "0" [ref=e1204]
          - cell "0" [ref=e1205]
          - cell "0" [ref=e1206]
          - cell "0" [ref=e1207]
          - cell "0" [ref=e1208]
          - cell "0" [ref=e1209]
          - cell "72" [ref=e1210]
          - cell "63" [ref=e1211]
          - cell "70" [ref=e1212]
          - cell "68" [ref=e1213]
          - cell "68" [ref=e1214]
          - cell "58" [ref=e1215]
          - cell "66" [ref=e1216]
          - cell "63" [ref=e1217]
          - cell "0" [ref=e1218]
          - cell "0" [ref=e1219]
          - cell "0" [ref=e1220]
          - cell "500" [ref=e1221]
          - cell "550" [ref=e1222]
          - cell "600" [ref=e1223]
          - cell [ref=e1224]
          - cell [ref=e1225]
          - cell [ref=e1226]
          - cell "0" [ref=e1227]
          - cell "0" [ref=e1228]
          - cell "0" [ref=e1229]
          - cell "0" [ref=e1230]
          - cell "0" [ref=e1231]
        - row "Offer C No Starter FA 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0" [ref=e1232]:
          - cell "Offer" [ref=e1233]:
            - link "Offer" [ref=e1234] [cursor=pointer]:
              - /url: modules.php?name=FreeAgency&pa=negotiate&pid=4040404
          - cell "C" [ref=e1235]
          - cell "No Starter" [ref=e1236]:
            - link "No Starter" [ref=e1237] [cursor=pointer]:
              - /url: ./modules.php?name=Player&pa=showpage&pid=4040404
          - cell "FA" [ref=e1238]
          - cell "0" [ref=e1239]
          - cell "0" [ref=e1240]
          - cell "0" [ref=e1241]
          - cell "0" [ref=e1242]
          - cell "0" [ref=e1243]
          - cell "0" [ref=e1244]
          - cell "0" [ref=e1245]
          - cell "0" [ref=e1246]
          - cell "0" [ref=e1247]
          - cell "0" [ref=e1248]
          - cell "0" [ref=e1249]
          - cell "0" [ref=e1250]
          - cell "0" [ref=e1251]
          - cell "0" [ref=e1252]
          - cell "0" [ref=e1253]
          - cell "0" [ref=e1254]
          - cell "0" [ref=e1255]
          - cell "0" [ref=e1256]
          - cell "0" [ref=e1257]
          - cell "0" [ref=e1258]
          - cell "0" [ref=e1259]
          - cell "0" [ref=e1260]
          - cell "0" [ref=e1261]
          - cell "0" [ref=e1262]
          - cell "0" [ref=e1263]
          - cell [ref=e1264]
          - cell [ref=e1265]
          - cell [ref=e1266]
          - cell [ref=e1267]
          - cell [ref=e1268]
          - cell [ref=e1269]
          - cell "0" [ref=e1270]
          - cell "0" [ref=e1271]
          - cell "0" [ref=e1272]
          - cell "0" [ref=e1273]
          - cell "0" [ref=e1274]
        - row "Offer SF FA Forward Stars 25 0 0 0 0 0 0 0 0 0 0 0 0 0 74 64 71 69 69 59 67 64 0 0 0 400 440 480 520 560 600 0 0 0 0 0" [ref=e1275]:
          - cell "Offer" [ref=e1276]:
            - link "Offer" [ref=e1277] [cursor=pointer]:
              - /url: modules.php?name=FreeAgency&pa=negotiate&pid=12
          - cell "SF" [ref=e1278]
          - cell "FA Forward" [ref=e1279]:
            - link "FA Forward" [ref=e1280] [cursor=pointer]:
              - /url: ./modules.php?name=Player&pa=showpage&pid=12
          - cell "Stars" [ref=e1281]:
            - link "Stars" [ref=e1282] [cursor=pointer]:
              - /url: modules.php?name=Team&op=team&teamid=2
              - generic [ref=e1283]: Stars
          - cell "25" [ref=e1284]
          - cell "0" [ref=e1285]
          - cell "0" [ref=e1286]
          - cell "0" [ref=e1287]
          - cell "0" [ref=e1288]
          - cell "0" [ref=e1289]
          - cell "0" [ref=e1290]
          - cell "0" [ref=e1291]
          - cell "0" [ref=e1292]
          - cell "0" [ref=e1293]
          - cell "0" [ref=e1294]
          - cell "0" [ref=e1295]
          - cell "0" [ref=e1296]
          - cell "0" [ref=e1297]
          - cell "74" [ref=e1298]
          - cell "64" [ref=e1299]
          - cell "71" [ref=e1300]
          - cell "69" [ref=e1301]
          - cell "69" [ref=e1302]
          - cell "59" [ref=e1303]
          - cell "67" [ref=e1304]
          - cell "64" [ref=e1305]
          - cell "0" [ref=e1306]
          - cell "0" [ref=e1307]
          - cell "0" [ref=e1308]
          - cell "400" [ref=e1309]
          - cell "440" [ref=e1310]
          - cell "480" [ref=e1311]
          - cell "520" [ref=e1312]
          - cell "560" [ref=e1313]
          - cell "600" [ref=e1314]
          - cell "0" [ref=e1315]
          - cell "0" [ref=e1316]
          - cell "0" [ref=e1317]
          - cell "0" [ref=e1318]
          - cell "0" [ref=e1319]
        - row "Offer PG Draft Rookie 2026 Stars 20 0 0 0 0 0 0 0 0 0 0 0 0 0 65 55 62 60 60 50 58 55 0 0 0 0 0 0 0 0" [ref=e1320]:
          - cell "Offer" [ref=e1321]:
            - link "Offer" [ref=e1322] [cursor=pointer]:
              - /url: modules.php?name=FreeAgency&pa=negotiate&pid=31
          - cell "PG" [ref=e1323]
          - cell "Draft Rookie 2026" [ref=e1324]:
            - link "Draft Rookie 2026" [ref=e1325] [cursor=pointer]:
              - /url: ./modules.php?name=Player&pa=showpage&pid=31
          - cell "Stars" [ref=e1326]:
            - link "Stars" [ref=e1327] [cursor=pointer]:
              - /url: modules.php?name=Team&op=team&teamid=2
              - generic [ref=e1328]: Stars
          - cell "20" [ref=e1329]
          - cell "0" [ref=e1330]
          - cell "0" [ref=e1331]
          - cell "0" [ref=e1332]
          - cell "0" [ref=e1333]
          - cell "0" [ref=e1334]
          - cell "0" [ref=e1335]
          - cell "0" [ref=e1336]
          - cell "0" [ref=e1337]
          - cell "0" [ref=e1338]
          - cell "0" [ref=e1339]
          - cell "0" [ref=e1340]
          - cell "0" [ref=e1341]
          - cell "0" [ref=e1342]
          - cell "65" [ref=e1343]
          - cell "55" [ref=e1344]
          - cell "62" [ref=e1345]
          - cell "60" [ref=e1346]
          - cell "60" [ref=e1347]
          - cell "50" [ref=e1348]
          - cell "58" [ref=e1349]
          - cell "55" [ref=e1350]
          - cell "0" [ref=e1351]
          - cell "0" [ref=e1352]
          - cell "0" [ref=e1353]
          - cell [ref=e1354]
          - cell [ref=e1355]
          - cell [ref=e1356]
          - cell [ref=e1357]
          - cell [ref=e1358]
          - cell [ref=e1359]
          - cell "0" [ref=e1360]
          - cell "0" [ref=e1361]
          - cell "0" [ref=e1362]
          - cell "0" [ref=e1363]
          - cell "0" [ref=e1364]
        - row "Offer SF DC Utility B Monarchs 27 0 0 0 0 0 0 0 0 0 0 63 0 0 73 63 70 68 68 58 66 0 0 0 0 0 0 0 0 0" [ref=e1365]:
          - cell "Offer" [ref=e1366]:
            - link "Offer" [ref=e1367] [cursor=pointer]:
              - /url: modules.php?name=FreeAgency&pa=negotiate&pid=111
          - cell "SF" [ref=e1368]
          - cell "DC Utility B" [ref=e1369]:
            - link "DC Utility B" [ref=e1370] [cursor=pointer]:
              - /url: ./modules.php?name=Player&pa=showpage&pid=111
          - cell "Monarchs" [ref=e1371]:
            - link "Monarchs" [ref=e1372] [cursor=pointer]:
              - /url: modules.php?name=Team&op=team&teamid=8
              - generic [ref=e1373]: Monarchs
          - cell "27" [ref=e1374]
          - cell "0" [ref=e1375]
          - cell "0" [ref=e1376]
          - cell "0" [ref=e1377]
          - cell "0" [ref=e1378]
          - cell "0" [ref=e1379]
          - cell "0" [ref=e1380]
          - cell "0" [ref=e1381]
          - cell "0" [ref=e1382]
          - cell "0" [ref=e1383]
          - cell "0" [ref=e1384]
          - cell "63" [ref=e1385]
          - cell "0" [ref=e1386]
          - cell "0" [ref=e1387]
          - cell "73" [ref=e1388]
          - cell "63" [ref=e1389]
          - cell "70" [ref=e1390]
          - cell "68" [ref=e1391]
          - cell "68" [ref=e1392]
          - cell "58" [ref=e1393]
          - cell "66" [ref=e1394]
          - cell "0" [ref=e1395]
          - cell "0" [ref=e1396]
          - cell "0" [ref=e1397]
          - cell "0" [ref=e1398]
          - cell [ref=e1399]
          - cell [ref=e1400]
          - cell [ref=e1401]
          - cell [ref=e1402]
          - cell [ref=e1403]
          - cell [ref=e1404]
          - cell "0" [ref=e1405]
          - cell "0" [ref=e1406]
          - cell "0" [ref=e1407]
          - cell "0" [ref=e1408]
          - cell "0" [ref=e1409]
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