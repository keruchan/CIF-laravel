// Compare warm HTTP timings on disposable fictional data. Never sends email.
const assert=require('node:assert/strict');
const cp=require('node:child_process');
const fs=require('node:fs');
const path=require('node:path');
const net=require('node:net');
const crypto=require('node:crypto');
const {chromium}=require('@playwright/test');
const root=path.resolve(__dirname,'../..');
const php=process.env.CIF_TEST_PHP||'C:/php84/php.exe';
const reference=process.env.CIF_REFERENCE_ROOT||'C:/xampp/htdocs/cif';
const database='cif_mrf_test_'+crypto.randomBytes(8).toString('hex');
const output=path.join(root,'storage/app/migration-validation/20261106-performance');
const fixture=operation=>cp.execFileSync(php,[path.join(root,'tests/Fixtures/database.php'),operation,database],{stdio:'pipe'});
const median=values=>[...values].sort((a,b)=>a-b)[Math.floor(values.length/2)];
const opcacheLoaded=cp.execFileSync(php,['-r',"echo extension_loaded('Zend OPcache')?'yes':'no';"],{encoding:'utf8'}).trim()==='yes';
async function port(){const s=net.createServer();await new Promise(r=>s.listen(0,'127.0.0.1',r));const n=s.address().port;await new Promise(r=>s.close(r));return n;}
async function run(original,opcache){
 const number=await port(),base='http://127.0.0.1:'+number;
 const args=opcache?[...(!opcacheLoaded?['-d','zend_extension=php_opcache.dll']:[]),'-d','opcache.enable=1','-d','opcache.enable_cli=1','-d','opcache.revalidate_freq=0']:['-d','opcache.enable=0','-d','opcache.enable_cli=0'];
 args.push('-S','127.0.0.1:'+number,'-t',original?reference:path.join(root,'public'));
 if(!original)args.push(path.join(__dirname,'router.php'));
 const server=cp.spawn(php,args,{cwd:original?reference:root,windowsHide:true,stdio:['ignore','ignore','pipe'],env:{...process.env,APP_ENV:'local',APP_URL:base,CIF_DB_NAME:database,DB_CONNECTION:'mysql',DB_DATABASE:database,DB_URL:'',SESSION_DRIVER:'file',SESSION_COOKIE:database,CACHE_STORE:'database',MAIL_MAILER:'array',CIF_MAIL_ENABLED:'false'}});
 let log='';server.stderr.on('data',d=>log+=d);
 const cookies=new Map();let csrf;
 const headers=()=>({'Cookie':[...cookies].map(([k,v])=>k+'='+v).join('; '),'Content-Type':'application/json',...(csrf?{'X-CSRF-Token':csrf}:{})});
 async function request(url,input){
  const start=performance.now();const r=await fetch(base+url,{method:input?'POST':'GET',headers:headers(),body:input?JSON.stringify(input):undefined});
  for(const c of r.headers.getSetCookie()){const pair=c.split(';')[0],i=pair.indexOf('=');cookies.set(pair.slice(0,i),pair.slice(i+1));}
  const body=await r.text();assert.equal(r.status,200,url+': '+body.slice(0,150));
  return {ms:performance.now()-start,body};
 }
 try{
  let ready=false;
  for(let n=0;n<100;n++){try{csrf=JSON.parse((await request('/ajax/access.php?action=state')).body).csrf;ready=true;break;}catch{await new Promise(r=>setTimeout(r,100));}}
  assert.ok(ready,log);
  await request('/ajax/access.php?action=admin_login',{email:'admin',password:'123'});
  const endpoints=[['html','/pages/admin/',null],['state','/ajax/access.php?action=state',null],['badges','/ajax/access.php?action=admin_badges',{}],['dashboard','/ajax/access.php?action=candidate_dashboard',{range:'all',trend_interval:'monthly',date_from:'',date_to:''}],['candidates','/ajax/access.php?action=candidates',{}],['invitations','/ajax/access.php?action=invitations',{}],['mrfs','/ajax/mrf.php?portal=admin&action=list',{}],['kpi','/ajax/access.php?action=hr_staff_monitor',{}],['templates','/ajax/email.php?action=templates',{}]];
  const timings={};
  for(const [name,url,input] of endpoints){await request(url,input);const samples=[];for(let i=0;i<5;i++)samples.push(Number((await request(url,input)).ms.toFixed(2)));timings[name]={median_ms:median(samples),samples_ms:samples};}
  const browser=await chromium.launch({channel:'msedge',headless:true});
  const navigation={};
  try {
   const context=await browser.newContext({viewport:{width:1440,height:1000}});
   await context.route('https://fonts.googleapis.com/**',route=>route.abort());
   const state=await(await context.request.get(base+'/ajax/access.php?action=state')).json();
   const login=await context.request.post(base+'/ajax/access.php?action=admin_login',{data:{email:'admin',password:'123'},headers:{'X-CSRF-Token':state.csrf}});assert.equal(login.status(),200);
   const page=await context.newPage();
   await page.goto(base+'/pages/admin/#dash');await page.locator('#stats button').first().waitFor({state:'visible'});
   await page.waitForFunction(()=>!document.querySelector('#dash').hasAttribute('aria-busy'));
   for(const [view,action] of [['list','candidates'],['gen','invitations'],['dash','candidate_dashboard']]) {
    const samples=[];
    for(let i=0;i<3;i++) {
     const response=page.waitForResponse(r=>r.url().includes('action='+action)&&r.request().method()==='POST');
     const start=performance.now();await page.locator('.nv[data-v="'+view+'"]').click();await(await response).body();
     await page.evaluate(()=>new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r))));samples.push(Number((performance.now()-start).toFixed(2)));
    }
    navigation[view]={median_ms:median(samples),samples_ms:samples};
   }
  }finally{await browser.close();}
  return {runtime:original?'vanilla-php':'laravel',opcache,timings,navigation};
 }finally{if(server.exitCode===null){const exited=new Promise(r=>server.once('exit',r));server.kill();await exited;}}
}
(async()=>{
 let created=false;
 try{
  fixture('create');created=true;
  const candidate=JSON.parse(cp.execFileSync(php,[path.join(reference,'database/test-candidate-mrf-fixture.php'),database],{encoding:'utf8'}));
  const statements=[];
  for(let i=0;i<999;i++){
   const email='benchmark-'+i+'@example.test';
   statements.push(`INSERT INTO invitations(email,created_by,expires_at) VALUES ('${email}',1,UTC_TIMESTAMP()+INTERVAL 10 DAY)`);
   statements.push(`INSERT INTO candidates(candidate_number,invitation_id,email,first_name,last_name,position,status,form_data,submitted_at) SELECT CONCAT('BENCH',LAST_INSERT_ID()),LAST_INSERT_ID(),'${email}','Benchmark','Applicant',position,'Screening',form_data,UTC_TIMESTAMP()-INTERVAL ${i%900} DAY FROM candidates WHERE id=${Number(candidate.id)}`);
  }
  cp.execFileSync('C:/xampp/mysql/bin/mysql.exe',['-u','root',database],{input:statements.join(';')+';',stdio:['pipe','ignore','pipe']});
  const results=[];
  for(const [original,opcache] of [[false,false],[false,true],[true,true]]){
   const result=await run(original,opcache);results.push(result);console.log(JSON.stringify({runtime:result.runtime,opcache,median_ms:Object.fromEntries(Object.entries(result.timings).map(([key,value])=>[key,value.median_ms])),navigation_median_ms:Object.fromEntries(Object.entries(result.navigation).map(([key,value])=>[key,value.median_ms]))}));
  }
  fs.mkdirSync(output,{recursive:true});fs.writeFileSync(path.join(output,'http-benchmark.json'),JSON.stringify({fictional_candidates:1000,samples_per_endpoint:5,results},null,2));
 }finally{if(created)fixture('drop');}
})().catch(error=>{console.error(error);process.exitCode=1;});
