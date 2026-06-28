const puppeteer = require('puppeteer-core');
const path = require('path');

const CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const URLBASE = 'file:///D:/laragon/www/billxiot_gps/._mobtest/harness.html';
const OUT = 'D:/laragon/www/billxiot_gps/._mobtest';

const devices = [
  { name: 'galaxyS8_360',  width: 360, height: 740, dsf: 3, q: '?debug=1' },
  { name: 'galaxyS8_360_open', width: 360, height: 740, dsf: 3, q: '?open=1' },
  { name: 'iphoneSE_375',  width: 375, height: 667, dsf: 2, q: '' },
  { name: 'iphone12_390',  width: 390, height: 844, dsf: 3, q: '' },
  { name: 'iphone12_390_open', width: 390, height: 844, dsf: 3, q: '?open=1' },
  { name: 'iphone12_390_footer', width: 390, height: 844, dsf: 3, q: '?footer=1' },
  { name: 'rtl_390_open',  width: 390, height: 844, dsf: 3, q: '?open=1&rtl=1' },
  { name: 'pixel7_412',    width: 412, height: 915, dsf: 2.6, q: '?debug=1' },
  { name: 'ipadMini_768',  width: 768, height: 1024, dsf: 2, q: '' },
  { name: 'ipad_820_two',  width: 820, height: 1180, dsf: 2, q: '?debug=1' },
];

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--hide-scrollbars'],
  });
  for (const d of devices) {
    const page = await browser.newPage();
    await page.emulate({
      viewport: { width: d.width, height: d.height, deviceScaleFactor: d.dsf, isMobile: d.dsf >= 2, hasTouch: d.dsf >= 2 },
      userAgent: 'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Mobile Safari/537.36',
    });
    await page.goto(URLBASE + d.q, { waitUntil: 'networkidle0' });
    await new Promise(r => setTimeout(r, 400));
    const file = path.join(OUT, d.name + '.png');
    await page.screenshot({ path: file });
    const m = await page.evaluate(() => ({
      iw: window.innerWidth,
      mobile: matchMedia('(max-width:768px)').matches,
      coarse: matchMedia('(hover:none) and (pointer:coarse)').matches,
      ctrl: (() => { const e = document.querySelector('.tc-map-controls'); const b = e.getBoundingClientRect(); return Math.round(b.x) + ',' + Math.round(b.right); })(),
      panelX: Math.round(document.querySelector('.tc-panel').getBoundingClientRect().x),
      mapW: Math.round(document.querySelector('.tc-map-area').getBoundingClientRect().width),
    }));
    console.log(`${d.name}: iw=${m.iw} mobile=${m.mobile} coarse=${m.coarse} ctrlX=${m.ctrl} panelX=${m.panelX} mapW=${m.mapW}`);
    await page.close();
  }
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
