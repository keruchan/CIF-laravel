// Runs a current reference browser scenario against Laravel in its disposable DB.
// Reference fixtures restrict create/drop to cif_mrf_test_<random hex> databases.
const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const Module = require('node:module');
const target = path.resolve(__dirname, '../..');
const reference = process.env.CIF_REFERENCE_ROOT || 'C:/xampp/htdocs/cif';
const scenario = process.argv[2];
if (!/^check-[a-z-]+$/.test(scenario || '')) throw new Error('Choose a reference check scenario.');
const file = path.join(reference, 'database', scenario + '.cjs');
const originalSpawn = cp.spawn;
const originalExec = cp.execFileSync;
cp.execFileSync = function (command, args, options) {
  if (/php(?:\.exe)?$/i.test(command)) command = process.env.CIF_TEST_PHP || 'C:/php84/php.exe';
  if (args[0]?.endsWith('test-mrf-fixture.php')) args = [path.join(target, 'tests/Fixtures/database.php'), ...args.slice(1)];
  if (args[0]?.endsWith('test-superadmin-inspect.php')) args = [path.join(target, 'tests/Fixtures/superadmin-inspect.php'), ...args.slice(1)];
  if (args[0]?.endsWith('setup-hr-email.php') && process.env.CIF_COMPARE_ORIGINAL !== '1') {
    args = [path.join(target, 'tests/Fixtures/seed-email.php'), ...args.slice(1)];
  }
  if (args[0]?.endsWith('setup-superadmin.php')) {
    args = [path.join(target, 'artisan'), 'recruitment:setup-superadmin', ...args.slice(1)];
    options = { ...options, cwd: target, env: { ...options?.env, APP_ENV: 'local', DB_CONNECTION: 'mysql', DB_DATABASE: options?.env?.CIF_DB_NAME, DB_URL: '' } };
  }
  return originalExec(command, args, options);
};
cp.spawn = function (command, args, options = {}) {
  const serverIndex = args.indexOf('-S');
  if (serverIndex < 0) return originalSpawn(command, args, options);
  const database = options.env?.CIF_DB_NAME;
  if (!/^cif_mrf_test_[a-f0-9]{16}$/.test(database || '')) throw new Error('A disposable fixture database is required.');
  if (options.env.CIF_PDFTOTEXT && !fs.existsSync(options.env.CIF_PDFTOTEXT)) {
    options.env.CIF_PDFTOTEXT = 'C:/Program Files/Git/ucrt64/bin/pdftotext.exe';
  }
  if (process.env.CIF_COMPARE_ORIGINAL === '1') return originalSpawn(process.env.CIF_TEST_PHP || 'C:/php84/php.exe', args, options);
  return originalSpawn(process.env.CIF_TEST_PHP || 'C:/php84/php.exe', [
    ...args.slice(0, serverIndex), '-S', args[serverIndex + 1], '-t', path.join(target, 'public'), path.join(__dirname, 'router.php'),
  ], {
    ...options, cwd: target, windowsHide: true,
    env: { ...options.env, APP_ENV: 'local', APP_URL: 'http://' + args[serverIndex + 1],
      DB_CONNECTION: 'mysql', DB_DATABASE: database, DB_URL: '',
      SESSION_DRIVER: 'file', SESSION_COOKIE: database, CACHE_STORE: 'database',
      MAIL_MAILER: 'array', CIF_MAIL_ENABLED: 'false',
    },
  });
};
let code = fs.readFileSync(file, 'utf8');
if (scenario === 'check-superadmin') process.env.CIF_FIXTURE_DEFER_AUDIT = '1';
// Older scenarios predate the email-only Department sign-in. Their fixtures
// keep legacy usernames, so give those fictional accounts valid emails.
if (!code.includes('dept@example.test')) {
  code = code.replace(/(created=true;)/, '$1\nexecFileSync("C:/xampp/mysql/bin/mysql.exe",["-u","root",' +
    (/\bdb\s*=\s*['"]cif_mrf_test_/.test(code) ? 'db' : 'database') +
    ',"-e","UPDATE department_users SET username=CONCAT(username,\'@example.test\')"]);');
  code = code.replace(/(['"])(otherdept|unconfigured|dept)\1/g, "'$2@example.test'");
}
// Laravel's session CSRF rejection is 419. Role-permission failures remain 403.
code = code.replace(/(assert\.equal\([^;\n]*(?:'invalid'|null|undefined)[^;\n]*\.status),403/g, '$1,419');
code = code.replace('assert.equal(deniedCsrf.status,403)', 'assert.equal(deniedCsrf.status,419)');
if (scenario === 'check-superadmin') {
  code = code.replace('assert.equal(deniedCsrf.status,419);', 'assert.equal(deniedCsrf.status,419);' +
    'const csrfAudit=await sup("audit",{category:"Access",outcome:"Failed"});' +
    'assert.ok(csrfAudit.events.some(event=>event.entity_id==="users"&&JSON.parse(event.after_json||"{}").http_status===419&&event.actor_role==="Superadmin"),"Rejected CSRF request is audited with its validated role");');
}
if (scenario === 'check-mrf-import') {
  code = code.replace('created=true;', 'created=true;execFileSync("C:/xampp/mysql/bin/mysql.exe",["-u","root",database,"-e","UPDATE department_users SET group_name=\'Systems\' WHERE id=1"]);');
  // Exercise anonymous authorization with that browser's own CSRF token.
  code = code.replace('upload(anonymous,docx)', 'upload(anonymous,docx,(await state(anonymous)).csrf)');
  code = code.replace("call(anonymous,'save',{token:pdfFields.data.token,owner_id:1,form:expected.form})",
    "call(anonymous,'save',{token:pdfFields.data.token,owner_id:1,form:expected.form},(await state(anonymous)).csrf)");
}
if (scenario === 'check-recruitment-reports') {
  // Native CSRF runs before controller authentication. Use the anonymous
  // browser's own token when exercising the report authorization boundary.
  code = code.replace("const hs=await state(hr),rs=await state(requestor,'mrf-requestor');",
    "const hs=await state(hr),rs=await state(requestor,'mrf-requestor'),as=await state(anonymous);");
  code = code.replace('report(anonymous,data.input)', 'report(anonymous,data.input,as.csrf)');
  code = code.replace("report(hr,data.input,'wrong')).status(),403", "report(hr,data.input,'wrong')).status(),419");
}
if (process.env.CIF_TEST_DEBUG) code = code.replace(/return \{status:response.status\(\),data:await response.json\(\)\}/g,
  'const diagnostic=await response.json();if(response.status()>=400)console.log(response.url(),response.status(),diagnostic);return {status:response.status(),data:diagnostic}');
if (process.env.CIF_TEST_DEBUG && scenario === 'check-mrf-import') {
  code = code.replace("errors=[];page.on('pageerror'", "errors=[];page.on('response',async r=>{if(r.status()>=400&&r.url().includes('/ajax/'))console.log('Upload diagnostic',r.status(),await r.text());});page.on('pageerror'");
  code = code.replace('serverLog+=chunk', "(serverLog+=chunk,String(chunk).includes('CIF MRF import')&&console.error(String(chunk)))");
}
code = code.replace(/\}finally\{if\(browser\)/g, "}catch(error){console.error('Scenario assertion failed:',error);throw error;}finally{if(browser)");
process.argv[2] = require.resolve('@playwright/test');
const compiled = new Module(file, module);
compiled.filename = file;
compiled.paths = Module._nodeModulePaths(path.dirname(file));
compiled._compile(code, file);
