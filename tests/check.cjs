const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const PHP = require('php-parser');
const base = path.resolve(__dirname, '..');
const plugin = path.join(base, 'mac-frontend-motion-editor');
const engine = new PHP.Engine({ parser: { phpVersion: '7.4' } });
function parse(dir) {
  for (const file of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, file.name);
    if (file.isDirectory()) parse(full);
    else if (file.name.endsWith('.php')) engine.parseCode(fs.readFileSync(full, 'utf8'), full);
  }
}
parse(plugin);
parse(__dirname);
console.log('PASS PHP 7.4 syntax (static parser)');
const motion = (animation = '') => Object.fromEntries(['desktop', 'tablet', 'mobile'].map(d => [d, { animation: d === 'desktop' ? animation : '', duration: '', delay: 0 }]));
const fixture = () => ({ docId: 42, nonce: 'test', hash: 'v1', ajaxUrl: 'https://mfme.test/ajax', offUrl: 'https://mfme.test/off', animations: { Fading: { fadeIn: 'Fade In', fadeInUp: 'Fade In Up' } }, elements: {
  parent: { label: 'Container', parent: null, editable: true, motion: motion('fadeIn') },
  heading: { label: 'Widget: heading', parent: 'parent', editable: true, motion: motion() },
  atomic: { label: 'Widget: e-heading', parent: null, editable: false, motion: motion() },
} });
const html = `<html><head><style>body{margin:0;padding:50px;font-family:Arial} .elementor-element{padding:30px;margin:12px;border:1px solid #ccc} .elementor-invisible{visibility:hidden}</style></head><body>
<div data-elementor-id="99"><div class="elementor-element" data-id="heading">Header outside document</div></div>
<main class="elementor-42" data-elementor-id="42"><div class="elementor-element" data-id="parent"><h1 class="elementor-element" data-id="heading">Your next motion moment</h1></div>
<div class="elementor-element" data-id="atomic">Atomic locked</div>
<div data-elementor-id="77"><div class="elementor-element" data-id="heading">Shared template with colliding ID</div></div></main></body></html>`;
(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    const state = fixture();
    let requests = 0, conflict = false;
    await page.route('https://mfme.test/**', async route => {
      if (!route.request().url().endsWith('/ajax')) return route.fulfill({ contentType: 'text/html', body: html });
      requests++;
      const payload = new URLSearchParams(route.request().postData());
      assert.equal(payload.get('hash'), state.hash);
      if (conflict) return route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ success: false, data: { message: 'Page changed, reload before saving.' } }) });
      const info = state.elements[payload.get('element_id')];
      const previous = structuredClone(info.motion);
      info.motion[payload.get('device')].animation = payload.get('animation');
      for (const m of Object.values(info.motion)) { m.duration = payload.get('duration'); m.delay = Number(payload.get('delay')); }
      state.hash = `v${requests + 1}`;
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { hash: state.hash, anim: info.motion, previous } }) });
    });
    await page.goto('https://mfme.test/');
    await page.addStyleTag({ path: path.join(plugin, 'assets/css/motion-editor.css') });
    await page.evaluate(data => { window.MFME_DATA = data; }, fixture());
    await page.addScriptTag({ path: path.join(plugin, 'assets/js/motion-editor.js') });
    const heading = page.locator('main > [data-id="parent"] > [data-id="heading"]');
    const chip = page.locator('.mfme-chip');
    await heading.hover(); await chip.click();
    assert.equal(await page.locator('.mfme-warning').isVisible(), true);
    await page.locator('[data-animation]').selectOption('fadeInUp');
    await page.locator('[data-duration]').selectOption('fast');
    await page.locator('[data-delay]').fill('100');
    await page.locator('[data-replay]').click();
    await page.waitForFunction(() => document.querySelector('h1').classList.contains('fadeInUp'));
    assert.equal(requests, 0, 'Replay must never send a save request');
    await heading.evaluate(el => el.classList.add('unrelated-live-class'));
    await page.locator('[data-close]').click();
    assert.equal(await heading.evaluate(el => el.classList.contains('fadeInUp')), false);
    assert.equal(await heading.evaluate(el => el.classList.contains('unrelated-live-class')), true);
    console.log('PASS preview, delay, restoration and no save');
    await heading.hover(); await chip.click();
    await page.locator('[data-device="tablet"]').click();
    await page.locator('[data-animation]').selectOption('fadeInUp');
    await page.locator('[data-delay]').fill('200');
    await page.locator('[data-apply]').click();
    await page.waitForFunction(() => document.querySelector('.mfme-status').textContent === 'Saved to Elementor.');
    assert.equal(state.elements.heading.motion.tablet.animation, 'fadeInUp');
    assert.equal(state.elements.heading.motion.desktop.animation, '');
    await page.locator('[data-clear]').click();
    await page.waitForFunction(() => window.MFME_DATA.hash === 'v3');
    assert.equal(state.elements.heading.motion.tablet.animation, '');
    await page.locator('[data-undo]').click();
    await page.waitForFunction(() => document.querySelector('.mfme-status').textContent === 'Previous settings restored.');
    assert.equal(state.elements.heading.motion.tablet.animation, 'fadeInUp');
    assert.equal(state.elements.heading.motion.tablet.delay, 200);
    await page.locator('[data-parent]').click();
    assert.match(await page.locator('.mfme-label').textContent(), /Container/);
    console.log('PASS responsive apply, clear, Undo, fresh hash and parent selection');
    await page.locator('[data-close]').click();
    await page.locator('[data-elementor-id="77"] [data-id="heading"]').hover();
    assert.match(await chip.textContent(), /Shared template/);
    assert.equal(await chip.isDisabled(), true);
    await page.locator('[data-id="atomic"]').hover();
    assert.match(await chip.textContent(), /locked/);
    assert.equal(await chip.isDisabled(), true);
    await page.locator('[data-elementor-id="99"] [data-id="heading"]').hover();
    assert.equal(await page.locator('.mfme-outline').isHidden(), true);
    console.log('PASS document boundaries, colliding IDs and Atomic lock');
    await heading.hover(); await chip.click();
    conflict = true;
    await page.locator('[data-apply]').click();
    await page.waitForFunction(() => document.querySelector('.mfme-status').textContent.includes('Page changed'));
    assert.equal(await page.locator('[data-apply]').isDisabled(), true);
    assert.equal(await page.locator('[data-undo]').isDisabled(), true);
    assert.equal(await page.locator('[data-clear]').isDisabled(), true);
    await page.setViewportSize({ width: 390, height: 844 });
    assert.equal(await page.locator('.mfme-panel').evaluate(el => el.getBoundingClientRect().right <= innerWidth), true);
    fs.mkdirSync(path.join(base, 'test-results'), { recursive: true });
    await page.screenshot({ path: path.join(base, 'test-results/editor-mobile.png') });
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.screenshot({ path: path.join(base, 'test-results/editor-desktop.png') });
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('.mfme-panel').isHidden(), true);
    assert.deepEqual(errors, []);
    console.log('PASS conflict lockout, mobile bounds, Escape; zero browser errors');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
