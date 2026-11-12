// Fictional fixture only: no development records and no outbound mail.
const assert = require('node:assert/strict');
const cp = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const { chromium } = require('@playwright/test');
const target = path.resolve(__dirname, '../..');
const php = process.env.CIF_TEST_PHP || 'C:/php84/php.exe';
const database = 'cif_mrf_test_' + crypto.randomBytes(8).toString('hex');
const label = process.env.CIF_VALIDATION_LABEL || '20261112-privacy-revision';
if (!/^[a-z0-9-]+$/.test(label)) throw new Error('Use a simple validation directory label.');
const output = path.join(target, 'storage/app/migration-validation', label);
const fixture = action => cp.execFileSync(php, [path.join(target, 'tests/Fixtures/database.php'), action, database], { encoding: 'utf8' });
(async () => {
  let created = false, server, browser;
  const errors = [];
  try {
    fixture('create'); created = true;
    fs.mkdirSync(output, { recursive: true });
    const listener = net.createServer();
    await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
    const port = listener.address().port;
    await new Promise(resolve => listener.close(resolve));
    const base = 'http://127.0.0.1:' + port;
    server = cp.spawn(php, ['-S', '127.0.0.1:' + port, '-t', path.join(target, 'public'), path.join(__dirname, 'router.php')], {
      cwd: target, windowsHide: true, stdio: 'ignore', env: {
        ...process.env, APP_ENV: 'local', APP_URL: base, DB_CONNECTION: 'mysql', DB_DATABASE: database, DB_URL: '',
        SESSION_DRIVER: 'file', SESSION_COOKIE: database, SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'database',
        MAIL_MAILER: 'array', CIF_MAIL_ENABLED: 'false', CIF_PRIVACY_DPO_EMAIL: 'careers@apechomes.com',
      },
    });
    for (let i = 0; i < 100; i++) {
      try { if ((await fetch(base + '/ajax/access.php?action=state')).ok) break; } catch {}
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    browser = await chromium.launch({ channel: 'msedge', headless: true });
    for (const width of [1440, 390]) {
      const context = await browser.newContext({ viewport: { width, height: 950 }, reducedMotion: 'reduce' });
      await context.route('https://fonts.googleapis.com/**', route => route.abort());
      const page = await context.newPage();
      page.on('pageerror', error => errors.push(error.message));
      const state = await (await context.request.get(base + '/ajax/access.php?action=state')).json();
      const api = async (action, data) => {
        const response = await context.request.post(base + '/ajax/access.php?action=' + action, { data, headers: { 'X-CSRF-Token': state.csrf } });
        return { status: response.status(), body: await response.json() };
      };
      assert.equal((await api('admin_login', { email: 'admin', password: '123' })).status, 200);
      const email = 'privacy-' + width + '@example.test';
      const invite = await api('generate', { email, position: 'Engineering Support Staff' });
      assert.equal(invite.status, 200);
      await page.goto(base + '/pages/candidate/');
      await page.locator('#em').fill(email); await page.locator('#cd').fill(invite.body.code);
      await page.locator('#lf button').click();
      await page.locator('#candidate-intro').waitFor({ state: 'visible' });
      assert.equal(await page.locator('#start-cif').isDisabled(), true);
      assert.equal(await page.locator('#cf').isVisible(), false);
      const assertBlockedInFreshBrowser = async () => {
        const otherContext = await browser.newContext({ viewport: { width, height: 950 } });
        await otherContext.route('https://fonts.googleapis.com/**', route => route.abort());
        const otherPage = await otherContext.newPage();
        try {
          await otherPage.goto(base + '/pages/candidate/');
          await otherPage.locator('#em').fill(email); await otherPage.locator('#cd').fill(invite.body.code);
          await otherPage.locator('#lf button').click();
          await otherPage.locator('#candidate-intro').waitFor({ state: 'visible' });
          assert.equal(await otherPage.locator('#start-cif').isDisabled(), true);
          assert.equal(await otherPage.locator('#cf').isVisible(), false);
          assert.equal(await otherPage.locator('#cf').evaluate(el => el.inert), true);
        } finally { await otherContext.close(); }
      };
      await page.reload(); await page.locator('#candidate-intro').waitFor({ state: 'visible' });
      assert.equal(await page.locator('#start-cif').isDisabled(), true);
      await assertBlockedInFreshBrowser();
      await page.locator('#candidate-exit').click();
      await page.locator('#login').waitFor({ state: 'visible' });
      await page.locator('#em').fill(email); await page.locator('#cd').fill(invite.body.code);
      await page.locator('#lf button').click(); await page.locator('#candidate-intro').waitFor({ state: 'visible' });
      assert.equal(await page.locator('#start-cif').isDisabled(), true);
      await page.screenshot({ path: path.join(output, 'intro-' + width + '.png'), fullPage: true });
      const readToEnd = async () => {
        assert.equal(await page.locator('#privacy-agree').isDisabled(), true);
        assert.equal(await page.locator('#accept-privacy').isDisabled(), true);
        await page.locator('.privacy-dialog-body').evaluate(el => { el.scrollTop = el.scrollHeight; el.dispatchEvent(new Event('scroll')); });
        await page.waitForFunction(() => !document.querySelector('#privacy-agree').disabled);
        assert.equal(await page.locator('#accept-privacy').isEnabled(), true);
      };
      await page.locator('#read-privacy').click();
      assert.equal(await page.locator('#accept-privacy').isDisabled(), true);
      assert.equal(await page.locator('#privacy-agree').isDisabled(), true);
      assert.equal(await page.locator('#accept-privacy').textContent(), 'Continue');
      assert.equal(await page.locator('#privacy-agree').isChecked(), false);
      assert.match(await page.locator('.privacy-dialog-body').textContent(), /two \(2\) years/);
      const noticeText = await page.locator('.privacy-dialog-body').textContent();
      assert.match(noticeText, /The New Apec Development Corporation and its affiliates/);
      assert.match(noticeText, /Academic & Employment History:.*transcript of records/);
      assert.match(noticeText, /Human Resources team, department hiring managers, and authorized interviewers/);
      assert.match(noticeText, /You may inquire/);
      assert.match(noticeText, /prior approval or go-signal/);
      assert.doesNotMatch(noticeText, /transcripts of records|file a complaint|Future Career Opportunities/);
      assert.equal(await page.locator('.privacy-dialog-body input[type="checkbox"]').count(), 2);
      for (const title of await page.locator('.privacy-dialog-body h3').allTextContents()) assert.equal(title, title.toUpperCase());
      assert.equal(await page.locator('.privacy-dialog-body a[href="mailto:careers@apechomes.com"]').count(), 1);

      const bounds = await page.locator('#privacy-dialog').boundingBox();
      assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width + 1);
      const scrollable = await page.locator('.privacy-dialog-body').evaluate(el => el.scrollHeight > el.clientHeight);
      assert.equal(scrollable, true);
      await page.screenshot({ path: path.join(output, 'dialog-' + width + '.png'), fullPage: true });
      await page.locator('.privacy-dialog-body').evaluate(el => { el.scrollTop = (el.scrollHeight - el.clientHeight) / 2; el.dispatchEvent(new Event('scroll')); });
      assert.equal(await page.locator('#privacy-agree').isDisabled(), true);
      await page.screenshot({ path: path.join(output, 'notice-middle-' + width + '.png'), fullPage: true });
      await page.locator('.privacy-dialog-body').focus();
      await page.keyboard.press('Control+End');
      await page.waitForFunction(() => !document.querySelector('#privacy-agree').disabled);
      assert.equal(await page.locator('#accept-privacy').isEnabled(), true);
      await page.screenshot({ path: path.join(output, 'opening-authorizations-' + width + '.png'), fullPage: true });
      await page.keyboard.press('Escape');
      assert.equal(await page.locator('#start-cif').isDisabled(), true);
      assert.equal(await page.locator('#read-privacy').evaluate(el => el === document.activeElement), true);
      await page.locator('#read-privacy').click();
      await readToEnd();
      await page.locator('#accept-privacy').click();
      await page.locator('#privacy-dialog').waitFor({ state: 'hidden' });
      assert.equal(await page.locator('#start-cif').isDisabled(), true);
      await page.locator('#read-privacy').click();
      await readToEnd();
      await page.locator('#privacy-agree').check(); await page.locator('#privacy-background-agree').check();
      // A failed acceptance must leave the form gated, with a useful error.
      await page.route('**/ajax/access.php?action=candidate_privacy_accept', route => route.fulfill({
        status: 503, contentType: 'application/json', body: JSON.stringify({ error: 'Temporary save failure. Please retry.' }),
      }));
      await page.locator('#accept-privacy').click();
      await page.locator('#privacy-error').waitFor({ state: 'visible' });
      assert.equal(await page.locator('#start-cif').isDisabled(), true);
      await page.unroute('**/ajax/access.php?action=candidate_privacy_accept');
      await page.locator('#accept-privacy').click();
      await page.locator('#privacy-dialog').waitFor({ state: 'hidden' });
      assert.equal(await page.locator('#start-cif').isEnabled(), true);
      // Accepting the notice alone must not count as starting the form on reload.
      await page.reload(); await page.locator('#candidate-intro').waitFor({ state: 'visible' });
      assert.equal(await page.locator('#cf').isVisible(), false);
      // A second page already editing this applicant must lock when consent is
      // withdrawn elsewhere; its confirmed saved answers must remain intact.
      const siblingPage = await context.newPage();
      await siblingPage.goto(base + '/pages/candidate/');
      await siblingPage.locator('#start-cif').click();
      await siblingPage.locator('#cf').waitFor({ state: 'visible' });
      await siblingPage.locator('[name="fn"]').fill('Sibling');
      await siblingPage.waitForFunction(() => document.querySelector('#draft-save-status').textContent.startsWith('All progress saved'));
      await page.bringToFront(); await page.evaluate(() => recheckPrivacy());
      await page.locator('#read-privacy').click();
      await readToEnd();
      assert.equal(await page.locator('#privacy-agree').isChecked(), true);
      const withdrawal = page.waitForResponse(response => response.url().includes('action=candidate_privacy_accept') && response.request().method() === 'POST');
      await page.locator('#privacy-agree').uncheck();
      assert.equal(await page.locator('#start-cif').isDisabled(), true, 'Unchecking must lock Start immediately, even before Continue or reload.');
      assert.equal((await withdrawal).status(), 200);
      await siblingPage.evaluate(() => window.dispatchEvent(new Event('focus')));
      await siblingPage.locator('#candidate-intro').waitFor({ state: 'visible' });
      assert.equal(await siblingPage.locator('#cf').isVisible(), false);
      assert.equal(await siblingPage.locator('#cf').evaluate(el => el.inert), true);
      await siblingPage.close();
      // Do not click Continue: a clean browser must still see the withdrawal.
      await page.reload(); await page.locator('#candidate-intro').waitFor({ state: 'visible' });
      await assertBlockedInFreshBrowser();
      assert.equal(await page.locator('#start-cif').isDisabled(), true);
      await page.reload(); await page.locator('#candidate-intro').waitFor({ state: 'visible' });
      assert.equal(await page.locator('#start-cif').isDisabled(), true);
      await page.locator('#read-privacy').click();
      await readToEnd();
      assert.equal(await page.locator('#privacy-agree').isChecked(), false);
      await page.locator('#privacy-agree').check(); await page.locator('#privacy-background-agree').check(); await page.locator('#accept-privacy').click();
      await page.locator('#privacy-dialog').waitFor({ state: 'hidden' });
      await page.locator('#start-cif').click();
      await page.locator('#cf').waitFor({ state: 'visible' });
      assert.equal(await page.locator('[name="fn"]').inputValue(), 'SIBLING');
      await page.locator('[name="fn"]').fill('Privacy');
      await page.waitForFunction(() => document.querySelector('#draft-save-status').textContent.startsWith('All progress saved'));
      await page.reload();
      await page.locator('#cf').waitFor({ state: 'visible' });
      assert.equal(await page.locator('[name="fn"]').inputValue(), 'PRIVACY');
      assert.equal(await page.locator('#privacy-dialog').isVisible(), false);
      await page.evaluate(() => show(document.querySelectorAll('.step').length - 1));
      const final = page.locator('.step').last();
      assert.equal(await final.locator('input[type="checkbox"]').count(), 1);
      assert.equal(await page.locator('#ok').getAttribute('required'), '');
      assert.equal(await page.locator('#future-opportunities-ok').count(), 0);
      assert.equal(await page.locator('#background-ok').count(), 0);
      assert.doesNotMatch(await final.textContent(), /Future Career Opportunities|DATA PRIVACY CONSENT/);
      await page.screenshot({ path: path.join(output, 'final-certification-' + width + '.png'), fullPage: true });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      // Consent belongs to the invitation, not the browser or another candidate.
      const other = await api('generate', { email: 'other-' + email, position: 'Engineering Support Staff' });
      await api('verify', { email: 'other-' + email, code: other.body.code });
      await page.reload(); await page.locator('#candidate-intro').waitFor({ state: 'visible' });
      assert.equal(await page.locator('#start-cif').isDisabled(), true);
      await context.close();
    }
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(output, 'results.json'), JSON.stringify({
      widths: [1440, 390], checks: ['checkbox and Continue disabled until bottom; Start requires acceptance', 'partial scroll stays locked; keyboard scroll to end unlocks', 'read gate resets on every reopening', 'full notice scrolls within viewport', 'Escape cancels and restores focus',
        'Continue without agreement keeps Start disabled', 'failed acceptance stays gated', 'retry saves acceptance',
        'reload after acceptance stays on welcome until Start', 'uncheck and Continue revokes acceptance across reload', 'reaccept enables Start',
        'draft and acceptance survive reload', 'both authorizations in opening notice; final step only original certification', 'future opportunities removed',
        'fresh unsigned applicant stays gated after reload/logout and in a separate browser', 'unchecking persists without Continue',
        'existing editing tab locks after withdrawal; confirmed answers survive reacceptance',
        'no cross-applicant consent', 'no horizontal overflow'], errors,
    }, null, 2));
    console.log('PASS: desktop/mobile applicant privacy consent, cancellation, retry, persistence and applicant isolation.');
  } finally {
    if (browser) await browser.close();
    if (server) { server.kill(); await new Promise(resolve => server.exitCode !== null ? resolve() : server.once('exit', resolve)); }
    if (created) fixture('drop');
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
