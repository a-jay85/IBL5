import { firefox, chromium } from 'playwright';

const URL = 'http://h2h-records-finish.localhost/ibl5/modules.php?name=HeadToHeadRecords';

const VARIANTS = {
  'A-current(width:fit-content)': '',
  'B-inline-block': '.h2h-col-header__text{display:inline-block!important;width:auto!important;}',
  'C-max-content': '.h2h-col-header__text{width:max-content!important;}',
  'D-inline-block+fit': '.h2h-col-header__text{display:inline-block!important;}',
};

async function run(browserType, name) {
  const b = await browserType.launch();
  const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
  await p.goto(URL, { waitUntil: 'load' });
  await p.evaluate(() => {
    const s = document.querySelector('select[name="dimension"]');
    s.value = 'teams';
    s.form.submit();
  });
  await p.waitForSelector('.h2h-col-header--labeled', { timeout: 30000 });

  const out = {};
  for (const [label, css] of Object.entries(VARIANTS)) {
    await p.evaluate((c) => {
      const old = document.getElementById('probe');
      if (old) old.remove();
      if (c) {
        const e = document.createElement('style');
        e.id = 'probe';
        e.textContent = c;
        document.head.appendChild(e);
      }
    }, css);
    const r = await p.evaluate(() => {
      const ths = [...document.querySelectorAll('.h2h-col-header--labeled')].slice(0, 4);
      return ths.map((th) => {
        const sp = th.querySelector('.h2h-col-header__text').getBoundingClientRect();
        const im = th.querySelector('.series-logo-img').getBoundingClientRect();
        return {
          t: th.title,
          delta: +(sp.left + sp.width / 2 - (im.left + im.width / 2)).toFixed(2),
          spanW: +sp.width.toFixed(2),
          spanH: +sp.height.toFixed(2),
          thW: +th.getBoundingClientRect().width.toFixed(2),
        };
      });
    });
    out[label] = r;
  }
  console.log('=====', name, '=====');
  console.log(JSON.stringify(out, null, 1));
  await b.close();
}

await run(firefox, 'FIREFOX');
await run(chromium, 'CHROMIUM');
