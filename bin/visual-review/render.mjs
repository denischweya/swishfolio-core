// Swishfolio visual review: screenshots of a freshly built site.
//
// Run by swishfolio-core's ScreenshotRenderer (PHP proc_open, from the
// `wp swishfolio visual-review` command that system cron starts), never
// inside a PHP-FPM request:
//
//   node render.mjs <job.json>
//
// job.json:
//   {
//     "chrome":    "/opt/swish-render/chrome-headless-shell/.../chrome-headless-shell",
//     "modules":   "/opt/swish-render",          // dir holding node_modules/puppeteer-core
//     "out":       "/tmp/swish-vr-xxxx",          // where the JPEGs go
//     "hostMap":   "127.0.0.1",                   // optional: resolve the shot hosts here
//     "timeoutMs": 90000,                         // whole job, browser closed after
//     "shots": [
//       { "name": "home-desktop", "url": "https://x.example/", "width": 1440,
//         "maxHeight": 5120, "scale": 0.5, "mobile": false, "quality": 68 },
//       { "name": "home-mobile", "url": "https://x.example/", "width": 390,
//         "maxHeight": 2400, "scale": 1, "mobile": true, "quality": 68 }
//     ]
//   }
//
// Prints one JSON line on stdout: {"ok":true,"shots":[{name,file,width,height,pageHeight}],"ms":n}
// or {"ok":false,"error":"..."} with exit code 1. A shot that fails is reported
// with "error" and the others still run.

import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const started = Date.now();

function finish(code, payload) {
  process.stdout.write(JSON.stringify(payload) + '\n');
  process.exit(code);
}

async function loadPuppeteer(dir) {
  // puppeteer-core is ESM-only and ESM ignores NODE_PATH, so import it by file.
  const pkgDir = path.join(dir, 'node_modules', 'puppeteer-core');
  const pkg = JSON.parse(fs.readFileSync(path.join(pkgDir, 'package.json'), 'utf8'));
  const entry = (pkg.exports && pkg.exports['.'] && (pkg.exports['.'].import || pkg.exports['.'].default)) || pkg.main;
  const mod = await import(pathToFileURL(path.join(pkgDir, entry)).href);
  return mod.default || mod;
}

const MOBILE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
const DESKTOP_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 SwishfolioVisualReview';

// Hide cookie banners and admin bars, stop motion so a shot shows the settled page.
const SETTLE_CSS = `
  #wpadminbar, #cmplz-cookiebanner-container, .cmplz-cookiebanner, .cmplz-manage-consent { display: none !important; }
  html { margin-top: 0 !important; scroll-behavior: auto !important; }
  *, *::before, *::after { animation-duration: 0s !important; animation-delay: 0s !important; transition-duration: 0s !important; transition-delay: 0s !important; caret-color: transparent !important; }
`;

async function shoot(browser, shot, out, deadline) {
  const page = await browser.newPage();
  try {
    const remaining = () => Math.max(1000, deadline - Date.now());
    await page.setViewport({
      width: shot.width,
      height: shot.mobile ? 844 : 900,
      deviceScaleFactor: 1,
      isMobile: !!shot.mobile,
      hasTouch: !!shot.mobile,
    });
    await page.setUserAgent(shot.mobile ? MOBILE_UA : DESKTOP_UA);
    await page.goto(shot.url, { waitUntil: 'load', timeout: Math.min(45000, remaining()) });
    await page.addStyleTag({ content: SETTLE_CSS }).catch(() => {});
    // Walk down the page so lazy images load and scroll-reveal sections show.
    await page.evaluate(async () => {
      const step = Math.max(400, Math.floor(window.innerHeight * 0.8));
      const max = Math.min(document.documentElement.scrollHeight, 20000);
      for (let y = 0; y < max; y += step) {
        window.scrollTo(0, y);
        await new Promise((r) => setTimeout(r, 90));
      }
      window.scrollTo(0, 0);
      document.querySelectorAll('img[loading="lazy"]').forEach((img) => { img.loading = 'eager'; });
    });
    await page.evaluate(async () => {
      if (document.fonts && document.fonts.ready) {
        await Promise.race([document.fonts.ready, new Promise((r) => setTimeout(r, 3000))]);
      }
      const imgs = Array.from(document.images).filter((i) => !i.complete);
      await Promise.race([
        Promise.all(imgs.map((i) => new Promise((r) => { i.onload = i.onerror = r; }))),
        new Promise((r) => setTimeout(r, 4000)),
      ]);
    });
    await new Promise((r) => setTimeout(r, 400));

    const pageHeight = await page.evaluate(() => Math.ceil(Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0)));
    const height = Math.max(200, Math.min(pageHeight, shot.maxHeight || 5120));
    const scale = shot.scale > 0 && shot.scale <= 1 ? shot.scale : 1;
    const file = path.join(out, `${shot.name.replace(/[^a-z0-9-]/gi, '')}.jpg`);

    await page.screenshot({
      path: file,
      type: 'jpeg',
      quality: Math.max(30, Math.min(90, shot.quality || 68)),
      clip: { x: 0, y: 0, width: shot.width, height, scale },
      captureBeyondViewport: true,
    });

    return { name: shot.name, file, width: Math.round(shot.width * scale), height: Math.round(height * scale), pageHeight };
  } finally {
    await page.close().catch(() => {});
  }
}

(async () => {
  const jobFile = process.argv[2];
  if (!jobFile) {
    finish(1, { ok: false, error: 'usage: node render.mjs <job.json>' });
  }

  let job;
  try {
    job = JSON.parse(fs.readFileSync(jobFile, 'utf8'));
  } catch (e) {
    finish(1, { ok: false, error: 'unreadable job: ' + e.message });
  }

  const deadline = started + (job.timeoutMs || 90000);
  // Own deadline, so the browser is closed before PHP has to kill anything.
  const watchdog = setTimeout(() => finish(1, { ok: false, error: 'renderer timed out' }), Math.max(5000, deadline - started + 2000));
  watchdog.unref();

  let puppeteer;
  try {
    puppeteer = await loadPuppeteer(job.modules);
  } catch (e) {
    finish(1, { ok: false, error: 'puppeteer-core not found under ' + job.modules + ': ' + e.message });
  }

  const args = [
    '--no-sandbox', // www-data cannot use the user-namespace sandbox on Ubuntu 24 (AppArmor); only our own sites are loaded.
    '--disable-dev-shm-usage',
    '--disable-gpu',
    '--hide-scrollbars',
    '--mute-audio',
    '--no-first-run',
    '--ignore-certificate-errors', // the origin answers with a Cloudflare origin certificate
    '--font-render-hinting=none',
  ];
  if (job.hostMap) {
    const hosts = [...new Set(job.shots.map((s) => new URL(s.url).hostname))];
    args.push('--host-resolver-rules=' + hosts.map((h) => `MAP ${h} ${job.hostMap}`).join(', '));
  }

  let browser;
  try {
    browser = await puppeteer.launch({
      executablePath: job.chrome,
      headless: /headless-shell/.test(job.chrome) ? 'shell' : true,
      args,
      acceptInsecureCerts: true,
      protocolTimeout: 60000,
      handleSIGTERM: true,
      handleSIGINT: true,
    });
  } catch (e) {
    finish(1, { ok: false, error: 'chrome failed to start: ' + e.message });
  }

  fs.mkdirSync(job.out, { recursive: true });
  const shots = [];
  for (const shot of job.shots) {
    if (Date.now() > deadline - 3000) {
      shots.push({ name: shot.name, error: 'skipped: out of time' });
      continue;
    }
    try {
      shots.push(await shoot(browser, shot, job.out, deadline));
    } catch (e) {
      shots.push({ name: shot.name, error: String(e && e.message ? e.message : e).slice(0, 300) });
    }
  }
  await browser.close().catch(() => {});

  const ok = shots.some((s) => s.file);
  finish(ok ? 0 : 1, ok ? { ok, shots, ms: Date.now() - started } : { ok, error: 'no shot succeeded', shots, ms: Date.now() - started });
})().catch((e) => finish(1, { ok: false, error: String(e && e.message ? e.message : e) }));
