// Exercises the real Apache rewrite engine with a disposable public directory.
// A text front controller tests routing only; PHP execution is tested separately.
const assert = require('node:assert/strict');
const cp = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');

const root = path.resolve(__dirname, '../..');
const apache = process.env.CIF_TEST_APACHE || 'C:/xampp/apache/bin/httpd.exe';
if (!fs.existsSync(apache)) throw new Error('Set CIF_TEST_APACHE to the installed Apache binary.');
const serverRoot = path.resolve(path.dirname(apache), '..');
const fixture = fs.mkdtempSync(path.join(os.tmpdir(), 'cif-apache-rewrite-'));
const publicDir = path.join(fixture, 'public');
const posix = value => value.replaceAll('\\', '/');

(async () => {
  let server;
  try {
    fs.mkdirSync(publicDir);
    fs.copyFileSync(path.join(root, 'public/.htaccess'), path.join(publicDir, '.htaccess'));
    fs.writeFileSync(path.join(publicDir, 'index.php'), 'Recruitment rewrite fixture');
    const reserve = net.createServer();
    await new Promise(resolve => reserve.listen(0, '127.0.0.1', resolve));
    const port = reserve.address().port;
    await new Promise(resolve => reserve.close(resolve));
    const config = path.join(fixture, 'httpd.conf');
    fs.writeFileSync(config, `ServerRoot "${posix(serverRoot)}"
Listen 127.0.0.1:${port}
ServerName localhost
PidFile "${posix(path.join(fixture, 'httpd.pid'))}"
ErrorLog "${posix(path.join(fixture, 'error.log'))}"
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule dir_module modules/mod_dir.so
LoadModule rewrite_module modules/mod_rewrite.so
DocumentRoot "${posix(publicDir)}"
DirectoryIndex index.php
<Directory "${posix(publicDir)}">
  AllowOverride All
  Require all granted
</Directory>
`);
    cp.execFileSync(apache, ['-t', '-f', config], { windowsHide: true, stdio: 'pipe' });
    server = cp.spawn(apache, ['-X', '-f', config], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    let log = ''; server.stderr.on('data', chunk => log += chunk);
    const base = `http://127.0.0.1:${port}`;
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt++) {
      try { if ((await fetch(base + '/')).ok) { ready = true; break; } } catch {}
      if (server.exitCode !== null) break;
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert.ok(ready, 'Isolated Apache failed to start: ' + log);
    let checks = 0;
    for (const prefix of ['', '/installation-prefix']) {
      for (const portal of ['candidate', 'admin', 'portal', 'mrf-requestor', 'superadmin']) {
        const response = await fetch(base + prefix + '/pages/' + portal + '/?from=bookmark', { redirect: 'manual' });
        assert.equal(response.status, 200, portal + ': Apache must preserve the directory slash');
        assert.equal(await response.text(), 'Recruitment rewrite fixture');
        checks++;
      }
    }
    const ordinary = await fetch(base + '/unrelated/', { redirect: 'manual' });
    assert.equal(ordinary.status, 301, 'Keep the existing rule for unrelated URLs');
    assert.equal(new URL(ordinary.headers.get('location'), base).pathname, '/unrelated');
    console.log(`PASS: ${checks} portal rewrite checks preserve trailing slashes; ordinary redirects remain intact. Isolated Apache only; no user server configuration changed.`);
  } finally {
    if (server && server.exitCode === null) {
      const exited = new Promise(resolve => server.once('exit', resolve));
      server.kill(); await exited;
    }
    // Delete only the exact files created in this generated temporary directory.
    for (const name of ['.htaccess', 'index.php']) {
      const file = path.join(publicDir, name); if (fs.existsSync(file)) fs.unlinkSync(file);
    }
    if (fs.existsSync(publicDir)) fs.rmdirSync(publicDir);
    for (const name of ['httpd.conf', 'httpd.pid', 'error.log']) {
      const file = path.join(fixture, name); if (fs.existsSync(file)) fs.unlinkSync(file);
    }
    fs.rmdirSync(fixture);
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
