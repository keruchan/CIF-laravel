const assert = require('node:assert/strict');
const cp = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const { chromium } = require('@playwright/test');
const { PNG } = require('pngjs');
const target = path.resolve(__dirname, '../..');
const reference = process.env.CIF_REFERENCE_ROOT || 'C:/xampp/htdocs/cif';
const php = process.env.CIF_TEST_PHP || 'C:/php84/php.exe';
const mysql = 'C:/xampp/mysql/bin/mysql.exe';
const database = 'cif_mrf_test_' + crypto.randomBytes(8).toString('hex');
const dashboardOnly = process.argv.includes('--dashboard-grouping-only');
const label=process.env.CIF_VALIDATION_LABEL||(dashboardOnly?'20261106-dashboard-grouping':'20261106-entry-audit');
if(!/^[a-z0-9-]+$/.test(label))throw new Error('Use a simple validation directory label.');
const output = path.join(target, 'storage/app/migration-validation', label, 'visual');
fs.mkdirSync(output, { recursive: true });
const fixture = operation => cp.execFileSync(php, [path.join(target, 'tests/Fixtures/database.php'), operation, database], { encoding: 'utf8' });
const sql = query => cp.execFileSync(mysql, ['-u', 'root', database, '-e', query], { encoding: 'utf8' });
async function port() { const s=net.createServer(); await new Promise(r=>s.listen(0,'127.0.0.1',r)); const p=s.address().port; await new Promise(r=>s.close(r)); return p; }
async function server(original) {
  const number = await port(), base = 'http://127.0.0.1:' + number;
  const args = ['-S', '127.0.0.1:' + number, '-t', original ? reference : path.join(target, 'public')];
  if (!original) args.push(path.join(__dirname, 'router.php'));
  const process = cp.spawn(php, args, { cwd: original ? reference : target, windowsHide: true, stdio: ['ignore','ignore','pipe'], env: {
    ...global.process.env, CIF_DB_NAME: database, DB_CONNECTION: 'mysql', DB_DATABASE: database,
    DB_URL: '', APP_ENV: 'local', APP_URL: base, SESSION_DRIVER: 'file', CACHE_STORE: 'database',
    SESSION_COOKIE: database, MAIL_MAILER: 'array', CIF_MAIL_ENABLED: 'false',
  }});
  let log=''; process.stderr.on('data',d=>log+=d);
  for(let n=0;n<80;n++){try{const r=await fetch(base+'/ajax/access.php?action=state');if(r.ok)return {process,base};}catch{} await new Promise(r=>setTimeout(r,100));}
  throw new Error('Server did not start: '+log);
}
function difference(a,b) {
  const x=PNG.sync.read(a),y=PNG.sync.read(b);
  if(x.width!==y.width||x.height!==y.height)return {dimensions:false,pixels:1};
  let changed=0;for(let i=0;i<x.data.length;i+=4)if(Math.max(...[0,1,2].map(k=>Math.abs(x.data[i+k]-y.data[i+k])))>16)changed++;
  return {dimensions:true,pixels:changed/(x.width*x.height)};
}
(async()=>{
  let created=false,browser; const servers=[];
  try {
    fixture('create'); created=true;
    const candidate=JSON.parse(cp.execFileSync(php,[path.join(reference,'database/test-candidate-mrf-fixture.php'),database],{encoding:'utf8'}));
    sql("UPDATE department_users SET username=CONCAT(username,'@example.test'); UPDATE invitations SET access_code='TRACK123',code_hash=SHA2('TRACK123',256),redeemed_at=UTC_TIMESTAMP(),submitted_at=UTC_TIMESTAMP() WHERE id="+candidate.id);
    sql("INSERT INTO invitations(email,access_code,code_hash,created_by,position,expires_at,draft_form,draft_saved_at) VALUES ('form@example.test','FORM1234',SHA2('FORM1234',256),1,'Engineering Support Staff',UTC_TIMESTAMP()+INTERVAL 10 DAY,'{}',UTC_TIMESTAMP())");
    sql("INSERT INTO admins(email,name,password_hash,role) SELECT 'visualsuper','Visual Superadmin',password_hash,'superadmin' FROM admins WHERE id=1");
    if(dashboardOnly) {
      // Give the forecasting renderer enough fictional history to draw actual
      // dashed forecast points, rather than testing only an empty forecast area.
      const statements=[],today=new Date();
      for(let month=0;month<36;month++) {
        const date=new Date(Date.UTC(today.getUTCFullYear(),today.getUTCMonth()-36+month,10)).toISOString().slice(0,10);
        for(let index=0;index<1+month%4;index++) {
          const email=`chart-${month}-${index}@example.test`;
          statements.push(`INSERT INTO invitations(email,created_by,expires_at) VALUES ('${email}',1,UTC_TIMESTAMP()+INTERVAL 10 DAY)`);
          statements.push(`INSERT INTO candidates(candidate_number,invitation_id,email,first_name,last_name,position,status,form_data,submitted_at) SELECT CONCAT('CHART',LAST_INSERT_ID()),LAST_INSERT_ID(),'${email}','Chart','Fixture',position,'Screening',form_data,'${date} 02:00:00' FROM candidates WHERE id=${Number(candidate.id)}`);
        }
      }
      cp.execFileSync(mysql,['-u','root',database],{encoding:'utf8',input:statements.join(';')+';'});
    }
    servers.push(await server(true),await server(false));
    browser=await chromium.launch({channel:'msedge',headless:true});
    const clients=[];const errors=[];
    for(const {base} of servers){
      const context=await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
      await context.route('https://fonts.googleapis.com/**',r=>r.abort());
      // Compare both layouts with sending disabled; never read or change the
      // reference's private mail settings to equalize an environment-only note.
      await context.route('**/ajax/email.php?action=catalogue',async route=>{
        const upstream=await route.fetch(),body=await upstream.json();
        await route.fulfill({json:{...body,sending_enabled:false,transport:'Email sending is currently unavailable. Contact your system administrator for help.'}});
      });
      const state=await(await context.request.get(base+'/ajax/access.php?action=state')).json();
      const api=async(endpoint,action,input={})=>{const portal=endpoint==='mrf'?'&portal='+(input.portal||'admin'):'';const r=await context.request.post(base+'/ajax/'+endpoint+'.php?action='+action+portal,{data:input,headers:{'X-CSRF-Token':state.csrf}});const body=await r.json();assert.equal(r.status(),200,action+': '+JSON.stringify(body));return body;};
      await api('access','admin_login',{email:'admin',password:'123'});
      await api('department','login',{email:'dept@example.test',password:'123'});
      await api('superadmin','login',{login:'visualsuper',password:'123'});
      const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
      clients.push({base,context,page,api});
    }
    // Compare actual response contracts and formulas across portals on the same fixture data.
    const comparisons=[['access','candidates',{}],['access','candidate_dashboard',{}],['access','invitations',{}],['access','hr_staff_monitor',{}],['mrf','list',{}],['mrf','list',{portal:'department'}],['mrf','dashboard',{portal:'requestor'}],['mrf-requestors','list',{}],['email','templates',{}],['email','messages',{}],['superadmin','users',{}],['superadmin','overview',{}]];
    const apiResults=[];
    for(const [endpoint,action,input] of comparisons){
      // MRF identifies its portal in the query string; default is admin.
      const [a,b]=await Promise.all(clients.map(c=>c.api(endpoint,action,input)));
      assert.deepEqual(b,a,endpoint+'/'+action+' parity');apiResults.push(endpoint+'/'+action);
    }
    const screens=[
      ['hr-dashboard','/pages/admin/#dash','#stats button'],['candidates','/pages/admin/#list','#tbd .candidate-row'],
      ['invitations','/pages/admin/#gen','#rl tr'],['hr-mrfs','/pages/admin/#mrf','#mrf-rows tr'],
      ['requestors','/pages/admin/#requestors','#requestors-rows tr'],['templates','/pages/admin/#templates','#template-rows tr'],
      ['mail','/pages/admin/#mail','#mail'],['kpi','/pages/admin/#staff','#staff'],
      ['reports','/pages/admin/#reports','#reports-form'],
      ['candidate-record','/pages/admin/record.php?id='+candidate.id+'#stage-cif','#stage-panel-cif'],
      ['department-dashboard','/pages/department/#dashboard','#dashboard'],['department-requests','/pages/department/#mrf','#mrf'],
      ['department-form','/pages/department/mrf-form.php','#mrf-form'],
      ['department-record','/pages/department/mrf.php?id='+candidate.requests.Pending.id,'#mrf-record'],
      ['hr-mrf-record','/pages/admin/mrf.php?id='+candidate.requests.Pending.id,'#mrf-record'],
      ['superadmin-users','/pages/superadmin/','#super-user-rows tr'],
      ['superadmin-audit','/pages/superadmin/#audit','#super-audit-rows tr'],
    ];
    const results=[];
    async function capture(name,url,ready,width){
      const buffers=[];
      for(let i=0;i<clients.length;i++){
        const {page,base}=clients[i];await page.setViewportSize({width,height:1000});
        await page.goto(base+url);await page.locator('#app').waitFor({state:'visible'});
        if(name==='superadmin-audit') {
          const auditButton=page.locator('[data-super-view="audit"]');
          if(!await auditButton.isVisible())await page.locator('#burger').click();
          await auditButton.click();
        }
        if(name==='candidate-form'&&await page.locator('#start-cif').isVisible()){
          if(await page.locator('#start-cif').isDisabled()){
            await page.locator('#read-privacy').click();
            await page.locator('.privacy-dialog-body').evaluate(el=>{el.scrollTop=el.scrollHeight;el.dispatchEvent(new Event('scroll'));});
            await page.locator('#privacy-agree').check();
            await page.locator('#privacy-background-agree').check();
            await page.locator('#accept-privacy').click();await page.locator('#privacy-dialog').waitFor({state:'hidden'});
          }
          await page.locator('#start-cif').click();
        }
        await page.locator(ready).first().waitFor({state:'visible'});
        await page.waitForLoadState('networkidle');
        await page.waitForFunction(()=>![...document.querySelectorAll('[aria-busy="true"]')].some(el=>el.getClientRects().length));
        if(name==='kpi')await page.waitForFunction(()=>!document.querySelector('#hr-monitor-feedback').textContent.startsWith('Loading'));
        await page.evaluate(()=>document.fonts.ready);
        await page.evaluate(()=>scrollTo({top:0,left:0,behavior:'instant'}));
        buffers.push(await page.screenshot({path:path.join(output,name+'-'+width+'-'+(i?'laravel':'original')+'.png'),fullPage:true,animations:'disabled',caret:'hide'}));
      }
      const diff=difference(...buffers);results.push({screen:name,width,...diff});
      console.log(name,width,JSON.stringify(diff));
    }
    const selectedScreens=dashboardOnly?screens.filter(([name])=>name==='hr-dashboard'):screens;
    for(const [name,url,ready] of selectedScreens)await capture(name,url,ready,1440);
    for(const [name,url,ready] of selectedScreens)await capture(name,url,ready,390);
    if(dashboardOnly) {
      const grouping=[];
      for(let i=0;i<clients.length;i++) {
        const {page}=clients[i];
        const settled=()=>page.waitForFunction(()=>!document.querySelector('#dash').hasAttribute('aria-busy'));
        const changing=()=>page.waitForResponse(r=>r.url().includes('action=candidate_dashboard')&&r.request().method()==='POST');
        const choose=async interval=>{const response=changing();await page.locator('#dashboard-period').selectOption(interval);assert.equal((await(await response).json()).analytics.trend.interval,interval);await settled();};
        assert.equal(await page.locator('#dashboard-period').inputValue(),'monthly');
        assert.match(await page.locator('.trend-legend').textContent(),/Actual monthly submissions/);
        assert.equal(await page.locator('.forecast-area').count(),1);
        assert.equal(await page.locator('.line-forecast').count(),1);
        const actualLine=await page.locator('.line-series').getAttribute('points');
        const forecastLine=await page.locator('.line-forecast').getAttribute('points');
        await choose('daily');
        assert.match(await page.locator('.trend-legend').textContent(),/Actual daily submissions/);
        assert.equal(await page.locator('.forecast-area').count(),0);
        assert.equal(await page.locator('.line-forecast').count(),0);
        assert.equal(await page.locator('.trend-forecast-cards [data-forecast-month]').count(),3);
        await choose('monthly');
        assert.equal(await page.locator('.forecast-area').count(),1);
        await choose('daily');
        const reset=changing();await page.locator('#dashboard-filter-reset').click();await reset;await settled();
        assert.equal(await page.locator('#dashboard-period').inputValue(),'monthly');
        assert.equal(await page.locator('#dashboard-from').inputValue(),'');
        assert.equal(await page.locator('#dashboard-to').inputValue(),'');
        assert.equal(await page.locator('.forecast-area').count(),1);
        await choose('daily');await page.reload();await page.locator('#stats button').first().waitFor({state:'visible'});await settled();
        assert.equal(await page.locator('#dashboard-period').inputValue(),'monthly');
        assert.equal(await page.locator('.forecast-area').count(),1);
        grouping.push({implementation:i?'laravel':'original',initial:'monthly',dailyHidesForecastLine:true,monthlyRestoresForecastLine:true,reset:'monthly, all dates',reload:'monthly',actualLine,forecastLine});
      }
      assert.equal(grouping[0].actualLine,grouping[1].actualLine);
      assert.equal(grouping[0].forecastLine,grouping[1].forecastLine);
      assert.deepEqual(errors,[]);
      assert.ok(results.every(r=>r.dimensions&&r.pixels===0));
      fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({apiComparisons:apiResults,screens:results,grouping,errors},null,2));
      console.log('PASS: monthly default, daily/monthly chart and forecast switching, reset and reload match the original; dashboard desktop/mobile screenshots match.');
      return;
    }
    for(const c of clients)await c.api('access','verify',{email:'form@example.test',code:'FORM1234'});
    await capture('candidate-form','/pages/candidate/','#cf',1280);
    await capture('candidate-form','/pages/candidate/','#cf',390);
    for(const c of clients)await c.api('access','verify',{email:'candidate-mrf@example.com',code:'TRACK123'});
    await capture('candidate-tracker','/pages/candidate/','#app',1280);
    await capture('candidate-tracker','/pages/candidate/','#app',390);
    const portal=[];
    for(let i=0;i<clients.length;i++) {
      const {page,base}=clients[i];
      for(const width of [1440,390]) {
        await page.setViewportSize({width,height:1000});await page.goto(base+'/pages/portal/');
        await page.waitForLoadState('networkidle');
        assert.equal(new URL(page.url()).pathname,'/pages/portal/');
        assert.equal(await page.locator('.portal-card').count(),2);
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
        if(i) {
          for(const [name,role] of [['HR Recruitment','admin'],['MRF Requestor','mrf-requestor']]) {
            const link=page.getByRole('link',{name,exact:true});assert.equal(await link.count(),1);
            assert.equal(new URL(await link.getAttribute('href'),page.url()).pathname,'/pages/'+role+'/');
          }
        }
        await page.screenshot({path:path.join(output,'portal-'+width+'-'+(i?'laravel':'original')+'.png'),fullPage:true});
        portal.push({implementation:i?'laravel':'original',width,cards:2});
      }
    }
    for(const width of [1440,390]) {
      const buffers=clients.map((_,i)=>fs.readFileSync(path.join(output,'portal-'+width+'-'+(i?'laravel':'original')+'.png')));
      results.push({screen:'portal',width,...difference(...buffers)});
    }
    const entryRoutes=[];
    for(let i=0;i<clients.length;i++) {
      const {page,base,context}=clients[i];
      for(const entry of ['/', '/index.php', ...(i?['/index.html']:[])]) {
        const response=await context.request.get(base+entry,{maxRedirects:0});
        assert.equal(response.status(),302);
        assert.equal(new URL(response.headers().location,base+'/').pathname,'/pages/candidate/');
        await page.goto(base+entry);
        assert.equal(new URL(page.url()).pathname,'/pages/candidate/');
        await page.locator('#fin').waitFor({state:'visible'});
        entryRoutes.push({implementation:i?'laravel':'original',entry,destination:'/pages/candidate/'});
      }
    }
    assert.deepEqual(errors,[]);
    fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({apiComparisons:apiResults,screens:results,portal,entryRoutes,errors},null,2));
    assert.ok(results.every(r=>r.dimensions&&r.pixels<0.005),'Visual differences exceed the 0.5% review threshold.');
    console.log('PASS: original/Laravel API parity and desktop/mobile visual comparisons.');
  } finally {
    if(browser)await browser.close();for(const s of servers)s.process.kill();if(created)fixture('drop');
  }
})().catch(error=>{console.error(error);process.exitCode=1;});
