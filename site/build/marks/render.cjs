// Renders the SVG sources in this folder to 600 px PNGs (black on transparent); build-marks.py turns them into the brand files.
// Run from site/: node build/marks/render.cjs <out-dir>
const { chromium } = require('playwright'); const fs = require('fs'); const path = require('path');
(async () => { const out = process.argv[2]; const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 600, height: 600 } });
  for (const f of fs.readdirSync(__dirname).filter((n) => n.endsWith('.svg'))) {
    await p.setContent(`<style>html,body{margin:0;background:transparent}</style>${fs.readFileSync(path.join(__dirname, f), 'utf8').replace('<svg ', '<svg width="600" height="600" ')}`);
    await p.screenshot({ path: path.join(out, f.replace('.svg', '.png')), omitBackground: true });
  }
  await b.close(); })();
