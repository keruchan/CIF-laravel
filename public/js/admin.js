const ST=JSON.parse(document.querySelector('#candidate-status-catalogue').textContent);
const candidateStages=JSON.parse(document.querySelector('#candidate-stage-catalogue').textContent);
const candidateStageForStatus=status=>Object.keys(candidateStages).find(key=>candidateStages[key].statuses.includes(status))||'';
const candidateStatusLabel=status=>status==='Initial Interview'?'HR interview':status;
const CANDIDATE_SORT_VERSION=2;
const escapeHtml=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
let candidateRequest=0,candidateTimer,dashboardRequest=0,invitationDashboardScope=null;
const titles={dash:['Dashboard','Overview of all applicants'],list:['Candidates','Search, filter and manage submitted forms'],gen:['Invitations','Manage candidate invitations and access codes'],mrf:['MRF requests','Review approvals and recruitment progress'],requestors:['MRF requestors','Register requestors and manage account access'],staff:['KPI','MRF ownership, fill rates and hiring performance'],reports:['Printables','Print reports or download an Excel workbook'],templates:['Email templates','Create reusable messages for candidate communication'],mail:['Mail management','Send emails and monitor delivery results']};
let view='dash',page=1,per=8,sortK='d',sortD=-1;
const fmt=d=>d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
const cls=s=>s.replace(/[^a-zA-Z0-9]/g,'');
// Remember the destination only; filters and pagination belong to one page visit.
for(const key of ['cif-admin-dashboard','cif-admin-candidates','cif-admin-invitations']){try{sessionStorage.removeItem(key);}catch(error){}}
function savedState(key,fallback){if(key!=='cif-admin-active-view')return fallback;try{return JSON.parse(sessionStorage.getItem(key))||fallback;}catch(error){return fallback;}}
function candidateSortPreference(saved){
 // Reset the previous automatic alphabetical order once; preserve later choices.
 if(saved.sortVersion!==CANDIDATE_SORT_VERSION)return 'd-';
 const restored=saved.sortK?saved.sortK+(saved.sortD===-1?'-':'+'):saved.sort;
 return ['n+','n-','d+','d-','id+','id-'].includes(restored)?restored:'d-';
}
function remember(key,value){if(key!=='cif-admin-active-view')return;try{sessionStorage.setItem(key,JSON.stringify(value));}catch(error){/* URL still preserves the open view when storage is unavailable. */}}
function resetPageFilters(target){
 if(target==='dash'){dashboardRequest++;cifDashboardFilters.reset();dashboardTrendStatus='';dashboardTrendInterval='monthly';selectDashboardTab('recruitment');}
 if(target==='list'){candidateRequest++;clearTimeout(candidateTimer);page=1;sortK='d';sortD=-1;candidateArchived=false;candidateInsight=null;for(const id of ['q','fs','fss','fp'])$('#'+id).value='';syncCandidateStageStatuses();}
 if(target==='gen'){invitationDashboardScope=null;$('#invitation-dashboard-filter').classList.add('hide');invitationRequest++;clearTimeout(invitationTimer);invitationPage=1;invitationSort='created_desc';invitationArchived=false;for(const id of ['invite-search','invite-status','invite-position'])$('#'+id).value='';}
 if(target==='mrf')window.cifMrf?.resetFilters();
 if(target==='requestors')window.cifMrfRequestors?.resetFilters();
 if(target==='staff')window.cifHrStaff?.resetFilters();
 if(target==='reports')window.cifReports?.resetFilters();
 if(['templates','mail'].includes(target))window.cifMail?.resetFilters(target);
}
function go(v,reset=true){if(v==='requestors'&&currentAdmin&&!currentAdmin.can_manage_requestors)v='dash';if(v==='arch'){candidateArchived=true;candidateInsight=null;v='list';}if(!Object.hasOwn(titles,v))v='dash';if(view!==v)resetPageFilters(view);view=v;if(reset)page=1;$$('.nv').forEach(b=>b.classList.toggle('on',b.dataset.v===v));
 ['dash','list','gen','mrf','requestors','staff','reports','templates','mail'].forEach(s=>$('#'+s).classList.toggle('hide',s!==v));
 $('#ttl').textContent=titles[v][0];$('#sub').textContent=titles[v][1];cifSidebar.close();
 const url=new URL(location.href);url.hash=v;url.searchParams.delete('archived');history.replaceState(null,'',url);remember('cif-admin-active-view',v);
 // Start the requested page before background counts on the local PHP server.
 const loading=v==='dash'?dash():v==='gen'?rl():v==='mrf'?cifMrf.load():v==='requestors'?cifMrfRequestors.load():v==='staff'?cifHrStaff.load():v==='reports'?null:v==='templates'?cifMail.loadTemplates():v==='mail'?cifMail.loadMail():table();
 refreshSidebarBadges();return loading;}
window.addEventListener('hashchange',()=>{if(!$('#app').classList.contains('hide'))go(location.hash.slice(1));});
/* Dashboard summaries and drill-down share the same server classifications. */
const dashboardSaved=savedState('cif-admin-dashboard',{});
let dashboardTrendStatus=ST.includes(dashboardSaved.trend_status)?dashboardSaved.trend_status:'';
let dashboardTrendInterval=['daily','weekly','monthly','yearly'].includes(dashboardSaved.trend_interval)?dashboardSaved.trend_interval:'monthly';
const analysisFields={recruitment:['open_positions','month','position','source'],profile:['sex','age','education','employment'],location:['province','salary']};
const restoredDashboardTab=({demographics:'profile',education:'profile',employment:'location'})[dashboardSaved.tab]||dashboardSaved.tab;
let dashboardTab=Object.hasOwn(analysisFields,restoredDashboardTab)?restoredDashboardTab:'recruitment';
function selectDashboardTab(tab,focus=false){
 if(!Object.hasOwn(analysisFields,tab))return;dashboardTab=tab;
 $$('.dashboard-tabs [role="tab"]').forEach(button=>{const active=button.dataset.analysis===tab;button.setAttribute('aria-selected',String(active));button.tabIndex=active?0:-1;if(active&&focus)button.focus();});
 $$('.dashboard-chart').forEach(panel=>panel.hidden=!analysisFields[tab].includes(panel.dataset.field));
 $('.dashboard-recent').hidden=tab!=='recruitment';$('#dashboard-analysis').setAttribute('aria-labelledby','analysis-'+tab);
 remember('cif-admin-dashboard',{...cifDashboardFilters.read(),trend_status:dashboardTrendStatus,tab});
}
$$('.dashboard-tabs [role="tab"]').forEach(button=>{
 button.onclick=()=>selectDashboardTab(button.dataset.analysis);
 button.onkeydown=event=>{const tabs=$$('.dashboard-tabs [role="tab"]'),index=tabs.indexOf(button);let next;if(event.key==='ArrowRight')next=(index+1)%tabs.length;else if(event.key==='ArrowLeft')next=(index+tabs.length-1)%tabs.length;else if(event.key==='Home')next=0;else if(event.key==='End')next=tabs.length-1;else return;event.preventDefault();selectDashboardTab(tabs[next].dataset.analysis,true);};
});
if(dashboardSaved.position&&Array.from($('#dashboard-position').options).some(option=>option.value===dashboardSaved.position))$('#dashboard-position').value=dashboardSaved.position;
let dashboardScope={...cifDashboardFilters.read(),trend_status:''};
const chartColors=['#147DBB','#00A6A6','#a86b00','#6875c9','#93b5bf','#b5bfcd'];
function timelineLabel(value,interval,long=false){
 if(interval==='yearly')return value;
 const date=new Date(value+(interval==='monthly'?'-01':'')+'T00:00:00');
 const label=date.toLocaleDateString('en-US',{month:long?'long':'short',...(interval!=='monthly'?{day:'numeric'}:{}),year:long?'numeric':'2-digit'});
 return interval==='weekly'?'Week of '+label:label;
}
function drawDashboardChart(panel,field,counts,total,trend){
 if(field==='month'){
  if(!counts.length){panel.classList.add('dashboard-line-panel');panel.querySelector('.dashboard-bars').before(Object.assign(document.createElement('p'),{className:'dashboard-empty',textContent:'No submissions in the selected dates.'}));return;}
  const interval=trend.interval,monthly=interval==='monthly',allFuture=Object.entries(trend.forecast),future=monthly?allFuture:[],totalMonths=counts.length+future.length;
  const spacing={daily:22,weekly:38,monthly:50,yearly:100}[interval],labelEvery=counts.length>24&&!monthly?Math.ceil(100/spacing):1;
  const max=Math.max(1,...counts.map(([,count])=>count),...future.map(([,value])=>value??0)),top=Math.max(4,Math.ceil(max/4)*4),width=Math.max(600,totalMonths*spacing),height=260,left=42,right=24,bottom=48,plotHeight=height-bottom-24;
  const xAt=index=>left+index*(width-left-right)/Math.max(1,totalMonths-1);
  const points=counts.map(([value,count],index)=>({value,count,x:xAt(index),y:24+plotHeight*(1-count/top)}));
  const forecastPoints=future.map(([month,value],index)=>({month,value,x:xAt(counts.length+index),y:value===null?null:24+plotHeight*(1-value/top)}));
  const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox',`0 0 ${width} ${height}`);svg.style.minWidth=width+'px';svg.classList.add('dashboard-line');svg.setAttribute('role','group');svg.setAttribute('aria-label',interval+' candidate submissions. Actual points open candidate records; monthly forecasts are estimates only.');
  const boundary=(xAt(counts.length-1)+xAt(counts.length))/2;
  let content=monthly?`<rect class="forecast-area" x="${boundary}" y="0" width="${width-boundary}" height="${height}"/><line class="forecast-boundary" x1="${boundary}" x2="${boundary}" y1="0" y2="${height-bottom}"/><text x="${boundary+8}" y="12" class="forecast-heading">Forecast</text>`:'';
  for(let tick=0;tick<=4;tick++){const y=24+plotHeight*(1-tick/4);content+=`<line x1="${left}" x2="${width-right}" y1="${y}" y2="${y}" class="line-grid"/><text x="${left-10}" y="${y+4}" text-anchor="end">${top*tick/4}</text>`;}
  content+=`<polyline points="${points.map(point=>point.x+','+point.y).join(' ')}" class="line-series"/>`;
  if(monthly&&trend.forecast_available)content+=`<polyline points="${forecastPoints.map(point=>point.x+','+point.y).join(' ')}" class="line-forecast"/>`;
  forecastPoints.forEach(point=>{const month=new Date(point.month+'-01T00:00:00').toLocaleDateString('en-US',{month:'short',year:'2-digit'});content+=`<text x="${point.x}" y="${height-15}" text-anchor="middle">${month}</text>`;if(point.value!==null)content+=`<g class="forecast-point" aria-label="${point.month}: approximately ${Math.round(point.value)} candidates, forecast only"><title>${point.month}: forecast ${point.value} candidates; not actual submissions</title><circle cx="${point.x}" cy="${point.y}" r="5"/><text x="${point.x}" y="${point.y-12}" text-anchor="middle">~${Math.round(point.value)}</text></g>`;});
  points.forEach((point,index)=>{const label=timelineLabel(point.value,interval),fullLabel=timelineLabel(point.value,interval,true),partial=trend.partial_periods.includes(point.value),showLabel=index%labelEvery===0||index===points.length-1;
   if(showLabel)content+=`<text x="${point.x}" y="${height-15}" text-anchor="middle">${label}${partial?'*':''}</text>`;
   content+=`<g data-period="${point.value}" ${monthly?'data-month="'+point.value+'"':''} role="button" tabindex="${point.count?0:-1}" aria-disabled="${!point.count}" aria-label="${fullLabel}: ${point.count} candidates${partial?', partial period':''}"><title>${fullLabel}: ${point.count} candidates${partial?' (partial period)':''}</title><circle cx="${point.x}" cy="${point.y}" r="10" class="line-hit"/><circle cx="${point.x}" cy="${point.y}" r="${interval==='daily'?3:5}" class="line-point"/>${showLabel?`<text x="${point.x}" y="${point.y-12}" text-anchor="middle" class="line-value">${point.count}</text>`:''}</g>`;
  });
  svg.innerHTML=content;svg.querySelectorAll('[data-period]').forEach(point=>{const activate=()=>{if(point.getAttribute('aria-disabled')!=='true'&&!$('#dash').hasAttribute('aria-busy'))dashboardCandidates(trend.period_field,point.dataset.period);};point.onclick=activate;point.onkeydown=event=>{if(event.key==='Enter'||event.key===' '){event.preventDefault();activate();}};});
  const scroller=document.createElement('div');scroller.className='trend-chart-scroll';scroller.tabIndex=0;scroller.setAttribute('aria-label','Submission timeline; scroll horizontally for all periods');scroller.append(svg);panel.querySelector('.dashboard-bars').before(scroller);panel.classList.add('dashboard-line-panel');
  const legend=document.createElement('div');legend.className='trend-legend';legend.innerHTML=`<span><i class="trend-actual-key"></i>Actual ${interval} submissions</span>${monthly?'<span><i class="trend-average-key"></i>Next 3 months: forecast</span>':''}<strong>${trend.total} actual candidates · ${escapeHtml(trend.status||'All statuses')}</strong>`;scroller.before(legend);
  const forecasts=document.createElement('div');forecasts.className='trend-forecast-cards';forecasts.setAttribute('aria-label','Forecasted candidates for the next three months');
  allFuture.forEach(([month,value])=>{const card=document.createElement('div');card.dataset.forecastMonth=month;const label=new Date(month+'-01T00:00:00').toLocaleDateString('en-US',{month:'long',year:'numeric'});card.innerHTML=`<span>${escapeHtml(label)}</span><b>${value===null?'–':'~'+Math.round(value)}</b><small>${value===null?'Not enough past data':'estimated candidates'}</small>`;forecasts.append(card);});scroller.after(forecasts);
  const note=document.createElement('p');note.className='trend-note';note.textContent='* Some periods may be incomplete. Weeks run Monday–Sunday. '+(!monthly?'Select Monthly to view the estimated trend. ':'')+(trend.forecast_available?'The next 3 months are estimates based on past submissions, for planning only.':'Estimates need at least 2 years of past submissions. Choose a wider date range.')+' Status shows each candidate\'s current stage.';forecasts.after(note);
 }else if(['sex','employment'].includes(field)){
  let cursor=0;const stops=counts.filter(([,count])=>count>0).map(([value,count])=>{const start=cursor;cursor+=total?count/total*100:0;return `${chartColors[counts.findIndex(([label])=>label===value)%chartColors.length]} ${start}% ${cursor}%`;});
  const chart=document.createElement('div');chart.className='dashboard-ring';chart.setAttribute('aria-hidden','true');chart.style.background=total?`conic-gradient(${stops.join(',')})`:'#e3e8f2';chart.innerHTML=`<div><b>${total}</b><span>candidates</span></div>`;panel.querySelector('.dashboard-bars').before(chart);panel.classList.add('dashboard-donut-panel');
 }
}
const dashboardCharts=[
 ['month','Candidate submissions over time','Submissions by date, with estimates for the next 3 months'],
 ['position','Positions applied for','Candidate distribution by role'],
 ['source','Application sources','How candidates learned about APEC Homes'],
 ['education','Educational attainment','Highest reported attainment'],
 ['employment','Current employment','Candidate-reported employment status'],
 ['sex','Sex','Optional candidate response'],
 ['age','Age groups','Calculated from date of birth as of today'],
 ['province','Province','Present-address province'],
 ['salary','Expected monthly salary','PHP salary bands; blanks are not zero'],
];
function dashboardCandidates(field='all',value=''){
 const status=field==='status'?value:['month','day','week','year'].includes(field)?dashboardScope.trend_status||'':'';
 candidateArchived=false;candidateInsight={field,value,range:dashboardScope.range,date_from:dashboardScope.date_from,date_to:dashboardScope.date_to,status};$('#q').value='';$('#fs').value=candidateStageForStatus(status);$('#fss').value='';syncCandidateStageStatuses();
 const position=dashboardScope.position;
 $('#fp').value=Array.from($('#fp').options).some(option=>option.value===position)?position:'';
 clearTimeout(candidateTimer);go('list');
}
function dashboardPending(){invitationArchived=false;$('#invite-search').value='';$('#invite-position').value='';$('#invite-status').value='Pending';invitationPage=1;clearTimeout(invitationTimer);go('gen');}
$('#dashboard-refresh').onclick=()=>cifRefreshPage($('#dashboard-refresh'));
$('#candidates-refresh').onclick=()=>cifRefreshPage($('#candidates-refresh'));
$('#invitations-refresh').onclick=()=>cifRefreshPage($('#invitations-refresh'));
cifDashboardFilters.onChange(dash);
function dashboardListScope(metric,position=dashboardScope.position){return {dashboard_metric:metric,dashboard_range:dashboardScope.range,dashboard_position:position,dashboard_date_from:dashboardScope.date_from,dashboard_date_to:dashboardScope.date_to};}
function dashboardCardSelection(metric,label){
 if(metric.startsWith('candidates_')){dashboardCandidates(metric==='candidates_total'?'all':'outstanding');return;}
 const scope=dashboardListScope(metric);
 if(metric.startsWith('invitations_')){
  invitationDashboardScope=scope;invitationArchived=false;$('#invite-search').value='';$('#invite-status').value='';$('#invite-position').value=dashboardScope.position;invitationPage=1;clearTimeout(invitationTimer);
  $('#invitation-dashboard-filter-label').textContent=label+' | '+cifDashboardCoverageLabel(dashboardScope)+' | '+(dashboardScope.position||'All positions');$('#invitation-dashboard-filter').classList.remove('hide');go('gen');return;
 }
 window.cifMrf.openDashboard(scope,label);go('mrf');
}
function renderDashboardCards(cards){
 const counts=cards.counts;
 const pairs=[
  ['Candidates','#082E82',['candidates_total','Candidates total','Submitted CIFs'],['candidates_outstanding','Candidates outstanding','Still in the hiring process']],
  ['Invitations','#147DBB',['invitations_total','Invitations total','All invitations in the Active list'],['invitations_outstanding','Invitations outstanding','Pending and In Progress']],
  ['Manpower requests','#6875c9',['mrf_total','MRF requests total','Original requests'],['mrf_outstanding','MRF outstanding','Requests still unfinished']],
  ['My assignments','#008f91',['assigned_total','My assigned MRFs total','Openings assigned to me'],['assigned_due','Assigned MRF due',cards.coverage?.all_dates?'All hiring deadlines':cards.coverage?'Due within selected dates':'Due in '+cards.due_month]],
  ['Open positions','#00A6A6',['positions_total','Open positions total','Approved openings to fill'],['positions_progress','Positions in progress','With an active candidate']]
 ];
 const root=$('#stats');root.replaceChildren();root.classList.add('dashboard-paired');
 for(const [group,color,...metrics]of pairs){
  const pair=document.createElement('div');pair.className='dashboard-card-pair';pair.setAttribute('role','group');pair.setAttribute('aria-label',group);
  for(const [metric,label,help]of metrics){
   const button=document.createElement('button');button.type='button';button.className='card stat invitation-stat';button.style.setProperty('--c',color);button.dataset.metric=metric;button.title='View '+label.toLowerCase();
   const pending=metric==='mrf_outstanding'?'<small class="dashboard-awaiting"><strong>'+Number(counts.mrf_pending||0)+'</strong> awaiting approval</small>':metric==='candidates_outstanding'?'<small class="dashboard-awaiting"><strong>'+Number(counts.candidates_screening||0)+'</strong> awaiting screening</small>':'';
   button.innerHTML='<div class="dashboard-count"><b>'+Number(counts[metric]||0)+'</b>'+pending+'</div><span>'+escapeHtml(label)+'</span><small>'+escapeHtml(help)+'</small>';
   button.onclick=()=>dashboardCardSelection(metric,label);pair.append(button);
  }
  root.append(pair);
 }
}

function renderDashboardOpenPositions(cards){
 const positions=cards.open_positions,total=cards.counts.positions_total,max=Math.max(1,...positions.map(row=>row.count));
 const panel=document.createElement('section');panel.className='card dashboard-chart dashboard-open-positions';panel.dataset.field='open_positions';panel.setAttribute('aria-labelledby','dashboard-open-positions-title');
 panel.innerHTML='<div class="dashboard-open-heading"><div><h3 id="dashboard-open-positions-title">Open positions</h3><p>Openings by position. Select a position to view its manpower requests.</p></div><div class="dashboard-open-total"><b>'+Number(total)+'</b><span>available across '+positions.length+' position'+(positions.length===1?'':'s')+'</span></div></div><div class="dashboard-bars"></div>';
 const bars=panel.querySelector('.dashboard-bars');
 if(!positions.length)bars.innerHTML='<p class="dashboard-empty">No open positions match these filters.</p>';
 for(const {position,count}of positions){
  const button=document.createElement('button');button.type='button';button.className='dashboard-bar';button.dataset.position=position;button.title=position+': '+count+' opening'+(count===1?'':'s')+' available. View manpower requests.';button.setAttribute('aria-label',button.title);
  button.innerHTML='<span class="dashboard-bar-label">'+escapeHtml(position)+'</span><span class="dashboard-bar-track" aria-hidden="true"><i style="width:'+count/max*100+'%"></i></span><span class="dashboard-bar-count">'+Number(count)+'</span>';
  button.onclick=()=>{window.cifMrf.openDashboard(dashboardListScope('positions_total',position),'Open positions');go('mrf');};bars.append(button);
 }
 $('#dashboard-charts').append(panel);
}

async function dash(){
 if(!cifDashboardFilters.valid())return;
 const request=++dashboardRequest,scope={...cifDashboardFilters.read(),trend_status:dashboardTrendStatus};dashboardTrendInterval=scope.trend_interval;
 remember('cif-admin-dashboard',{...scope,tab:dashboardTab});$('#dash').setAttribute('aria-busy','true');$('#dashboard-context').textContent='Loading overview…';
 $$('#stats button, #dashboard-charts button').forEach(button=>button.disabled=true);
 try{
  const result=await cifRequest('candidate_dashboard',scope);if(request!==dashboardRequest||view!=='dash')return;
  const a=result.analytics;dashboardScope=scope;
  $('#dashboard-position').replaceChildren(new Option('All positions',''));a.positions.forEach(position=>$('#dashboard-position').append(new Option(position,position)));
  if(a.positions.includes(scope.position))$('#dashboard-position').value=scope.position;else{$('#dashboard-position').value='';scope.position='';dashboardScope.position='';}
  cifDashboardFilters.preview();$('#dashboard-context').textContent+=' '+a.total+' active candidate'+(a.total===1?'':'s')+' · '+(scope.position||'All positions')+'.';
  renderDashboardCards(result.dashboard_cards);
  $('#dashboard-charts').replaceChildren();
  renderDashboardOpenPositions(result.dashboard_cards);
  dashboardCharts.forEach(([field,title,description])=>{
   const panel=document.createElement('section');panel.className='card dashboard-chart';panel.dataset.field=field;
   const counts=Object.entries(field==='month'?a.trend.series:a.charts[field]),max=Math.max(1,...counts.map(([,count])=>count)),chartTotal=field==='month'?a.trend.total:a.total;
   panel.innerHTML=`<h3>${escapeHtml(title)}</h3><p>${escapeHtml(description)}</p><div class="dashboard-bars"></div>`;
   const bars=panel.querySelector('.dashboard-bars');
   if(field==='month'){
    const controls=document.createElement('div');controls.className='trend-controls';
    const label=document.createElement('label');label.className='trend-status-control';label.textContent='Candidate status';
    const select=document.createElement('select');select.id='trend-status';select.append(new Option('All statuses',''));ST.forEach(status=>select.append(new Option(candidateStatusLabel(status),status)));select.value=dashboardTrendStatus;select.onchange=()=>{dashboardTrendStatus=select.value;dash();};label.append(select);controls.append(label);panel.querySelector('p').after(controls);
   }
   if(!counts.length){bars.innerHTML='<p class="dashboard-empty">No candidates in this selection.</p>';}
   counts.forEach(([value,count],index)=>{
    if(field==='month')return;
    const label=field==='month'?new Date(value+'-01T00:00:00').toLocaleDateString('en-US',{month:'short',year:'numeric'}):value;
    const percentage=chartTotal?Math.round(count/chartTotal*100):0;
    const button=document.createElement('button');button.type='button';button.className='dashboard-bar';button.dataset.value=value;button.disabled=count===0;button.title=`${label}: ${count} candidates (${percentage}%). View matching records.`;
    button.innerHTML=`<span class="dashboard-bar-label">${escapeHtml(label)}</span><span class="dashboard-bar-track" aria-hidden="true"><i style="width:${count/max*100}%"></i></span><span class="dashboard-bar-count">${count}<small>${percentage}%</small></span>`;
    button.style.setProperty('--chart-color',chartColors[index%chartColors.length]);
    button.setAttribute('aria-label',button.title);button.onclick=()=>dashboardCandidates(field,value);bars.append(button);
   });
   drawDashboardChart(panel,field,counts,chartTotal,a.trend);
   if(field==='month')bars.remove();
   $('#dashboard-charts').append(panel);
   if(field==='month'){const scroller=panel.querySelector('.trend-chart-scroll');if(scroller)scroller.scrollLeft=scroller.scrollWidth;}
  });
  $('#dashboard-recent').innerHTML=a.recent.length?a.recent.map(row=>`<tr class="candidate-row" data-dashboard-id="${row.id}" tabindex="0" aria-label="Open CIF for ${escapeHtml(row.name)}"><td><a class="candidate-record-link" href="record.php?id=${row.id}">${escapeHtml(row.name)}</a></td><td>${escapeHtml(row.dimensions.position)}</td><td><span class="bd ${cls(row.dimensions.status)}">${escapeHtml(candidateStatusLabel(row.dimensions.status))}</span></td><td>${tableDateTime(row.submitted_at)}</td></tr>`).join(''):'<tr><td colspan="4" class="empty">No submissions in this selection.</td></tr>';
  selectDashboardTab(dashboardTab);
 }catch(error){if(request===dashboardRequest){$('#dashboard-context').textContent='Unable to load overview. Use Refresh overview to retry.';toast(error.message);}}
 finally{if(request===dashboardRequest){$('#dash').removeAttribute('aria-busy');}}
}
$('#dashboard-recent').onclick=event=>{const row=event.target.closest('[data-dashboard-id]');if(row&&!event.target.closest('a'))view_(Number(row.dataset.dashboardId));};
$('#dashboard-recent').onkeydown=event=>{if(event.key==='Enter'&&event.target.matches('[data-dashboard-id]'))view_(Number(event.target.dataset.dashboardId));};
/* table */
$('#fs').innerHTML='<option value="">All stages</option>'+Object.entries(candidateStages).map(([key,stage])=>`<option value="${key}">${escapeHtml(stage.label)}</option>`).join('');
function syncCandidateStageStatuses(){
 const selected=$('#fss').value,stage=$('#fs').value,choices=stage?candidateStages[stage].decisions:[...new Set(Object.values(candidateStages).flatMap(entry=>entry.decisions))];
 $('#fss').innerHTML='<option value="">All status</option>'+choices.map(value=>`<option>${escapeHtml(value)}</option>`).join('');$('#fss').value=choices.includes(selected)?selected:'';
}
syncCandidateStageStatuses();
['q','fs','fss','fp'].forEach(i=>$('#'+i).oninput=()=>{if(i==='fs')syncCandidateStageStatuses();page=1;candidateRequest++;rememberCandidates();clearTimeout(candidateTimer);candidateTimer=setTimeout(table,i==='q'?250:0)});
function syncSortHeaders(selector,key,ascending,attribute){
 $$(selector).forEach(button=>{
  const active=button.dataset[attribute]===key;
  button.closest('th').setAttribute('aria-sort',active?(ascending?'ascending':'descending'):'none');
  button.querySelector('.sort-direction').textContent=active?(ascending?'↑':'↓'):'↕';
  const label=button.firstChild.textContent.trim();
  button.title=`Sort ${label.toLowerCase()} ${active&&ascending?'descending':'ascending'}`;
 });
}
function candidateSortChanged(){page=1;candidateRequest++;clearTimeout(candidateTimer);table();}
$$('.candidate-sort-header').forEach(button=>button.onclick=()=>{const k=button.dataset.key;sortD=sortK===k?-sortD:1;sortK=k;candidateSortChanged();});
function rememberCandidates(){remember('cif-admin-candidates',{search:$('#q').value,stage:$('#fs').value,stage_status:$('#fss').value,position:$('#fp').value,sort:sortK+(sortD===1?'+':'-'),sortK,sortD,sortVersion:CANDIDATE_SORT_VERSION,archived:candidateArchived,insight:candidateInsight,page});}
function renderCandidateStageCell(candidate){
 return candidate.stages.map(stage=>'<div class="candidate-list-stage" data-stage="'+escapeHtml(stage.key)+'"><span class="bd '+cls(candidate.status)+'">'+escapeHtml(stage.label)+'</span><small class="candidate-stage-decision" data-decision="'+escapeHtml(stage.decision)+'">'+escapeHtml(stage.decision)+'</small></div>').join('');
}
$('#candidate-active-view').onclick=()=>{candidateArchived=false;clearTimeout(candidateTimer);go('list');};
$('#candidate-archived-view').onclick=()=>{candidateInsight=null;candidateArchived=true;clearTimeout(candidateTimer);go('list');};
$('#candidate-insight-clear').onclick=()=>{candidateInsight=null;page=1;table();};
function renderCandidateCards(summary){
 $('#candidate-stats').replaceChildren();
 const colors={'Total':'#082E82','Screening':'#147DBB','Screening Failed':'#a33c4c','Screening Backout':'#a33c4c','Initial Interview':'#a86b00','Department Interview & Background Check':'#6875c9','Department Interview':'#6875c9','Background Check':'#6875c9','Job Offer':'#147DBB','Deployment':'#008f91','Deployed':'#00A6A6','Archived':'#6b7894'};
 const hiddenCards=new Set(['Screening Failed','Screening Backout','Department Interview & Background Check']);
 Object.entries(summary).filter(([label])=>!hiddenCards.has(label)).forEach(([label,count])=>{
  const stage=candidateStageForStatus(label),decision=label==='Deployed'?'Completed':'';
  if(stage&&label!=='Deployed')count=(label==='Deployment'?['Deployment']:candidateStages[stage].statuses).reduce((total,status)=>total+Number(summary[status]||0),0);
  const card=document.createElement('button');card.type='button';card.className='card stat invitation-stat candidate-stat';card.dataset.status=label==='Total'?'':label;
  card.style.setProperty('--c',colors[label]);card.setAttribute('aria-pressed',String(label==='Archived'?candidateArchived:!candidateArchived&&$('#fs').value===stage&&$('#fss').value===decision));
  const displayLabel=label==='Total'?'Active candidates':label==='Deployed'?'Hired':candidateStatusLabel(label);
  card.title=label==='Total'?'Show all active candidates':`Filter ${displayLabel.toLowerCase()} candidates`;
  const number=document.createElement('b');number.textContent=count;const caption=document.createElement('span');caption.textContent=displayLabel;card.append(number,caption);
  card.onclick=()=>{
   candidateInsight=null;
   candidateArchived=label==='Archived';$('#fs').value=stage;$('#fss').value='';syncCandidateStageStatuses();$('#fss').value=decision;
   if(label==='Total'){$('#q').value='';$('#fp').value='';}
   clearTimeout(candidateTimer);go('list');
  };
  $('#candidate-stats').append(card);
 });
}
async function table(){const request=++candidateRequest,a=candidateArchived;$('#candidate-list-title').textContent=a?'Archived candidates':'Active candidates';$('#candidate-active-view').setAttribute('aria-pressed',String(!a));$('#candidate-archived-view').setAttribute('aria-pressed',String(a));syncSortHeaders('.candidate-sort-header',sortK,sortD===1,'key');rememberCandidates();$('#info').textContent='Loading candidates…';$('#tbd').setAttribute('aria-busy','true');
 $('#candidate-insight').classList.toggle('hide',!candidateInsight);if(candidateInsight){const title=candidateInsight.field==='outstanding'?'Outstanding candidates':['day','week','year'].includes(candidateInsight.field)?'Candidate submissions over time':dashboardCharts.find(chart=>chart[0]===candidateInsight.field)?.[1]||'Dashboard selection';const interval={day:'daily',week:'weekly',year:'yearly',month:'monthly'}[candidateInsight.field],label=interval?timelineLabel(candidateInsight.value,interval,true):candidateInsight.value;$('#candidate-insight-label').textContent=`${title}${label?': '+label:''} · ${cifDashboardCoverageLabel(candidateInsight)}`;}
 // Deployment without a specific result shows candidates still awaiting deployment.
 const status=candidateInsight?.status||($('#fs').value==='deployment'&&!$('#fss').value?'Deployment':'');
 try{const result=await cifRequest('candidates',{search:$('#q').value,status,stage:$('#fs').value,stage_status:$('#fss').value,position:$('#fp').value,page,archived:a,insight:candidateInsight,sort:sortK+(sortD===1?'+':'-')});if(request!==candidateRequest||view!=='list'||a!==candidateArchived)return;
 page=result.page;const selected=$('#fp').value||savedState('cif-admin-candidates',{}).position||'';$('#fp').innerHTML='<option value="">All positions</option>'+result.positions.map(value=>`<option>${escapeHtml(value)}</option>`).join('');$('#fp').value=selected;rememberCandidates();setSidebarBadge('#cnt',result.summary.Screening,'candidates awaiting screening');
 renderCandidateCards(result.summary);
 $('#tbd').innerHTML=result.candidates.length?result.candidates.map(x=>`<tr class="candidate-row" data-candidate-id="${Number(x.id)}" tabindex="0" aria-label="Open CIF for ${escapeHtml(x.first_name+' '+x.last_name)}"><td><b>${escapeHtml(x.candidate_number)}</b></td><td class="nm"><b><a class="candidate-record-link" href="record.php?id=${Number(x.id)}">${escapeHtml(x.last_name+', '+x.first_name+(x.middle_name?' '+x.middle_name:''))}</a></b><small>${escapeHtml(x.email)}</small></td><td>${escapeHtml(x.position||'Not provided')}</td><td>${renderCandidateStageCell(x)}${a?'<small> · Archived</small>':''}</td><td>${tableDateTime(x.submitted_at)}</td><td class="ac"><button onclick="printCandidate(${Number(x.id)})">Print</button><button onclick="arc(${Number(x.id)},${!a},this)">${a?'Restore':'Archive'}</button></td></tr>`).join(''):'<tr><td colspan="6" class="empty">No candidates match your search.</td></tr>';
 $('#tbd').querySelectorAll('.ac button:last-child').forEach(button=>button.classList.toggle('action-muted',!a));
 $('#info').textContent=result.total?`Showing ${(page-1)*8+1}–${Math.min(page*8,result.total)} of ${result.total}`:'0 results';
 const start=Math.max(1,Math.min(page-2,result.pages-4)),end=Math.min(result.pages,start+4),numbers=[];if(start>1)numbers.push(1);for(let n=start;n<=end;n++)numbers.push(n);if(end<result.pages)numbers.push(result.pages);
 $('#pn').innerHTML=`<button ${page===1?'disabled':''} onclick="pg(${page-1})">Previous</button>`+numbers.map(n=>`<button class="${n===page?'on':''}" onclick="pg(${n})">${n}</button>`).join('')+`<button ${page===result.pages?'disabled':''} onclick="pg(${page+1})">Next</button>`;
 }catch(error){if(request===candidateRequest){$('#info').textContent='Unable to load candidates.';toast(error.message);}}finally{if(request===candidateRequest)$('#tbd').removeAttribute('aria-busy');}}
const pg=n=>{page=n;table()};
$('#tbd').addEventListener('click',event=>{
 if(event.target.closest('a,button,input,select,textarea,.ac')||window.getSelection()?.toString())return;
 const row=event.target.closest('[data-candidate-id]');if(!row)return;
 if(event.ctrlKey||event.metaKey){window.open(new URL('record.php?id='+row.dataset.candidateId,location.href).href,'_blank','noopener');return;}
 view_(Number(row.dataset.candidateId));
});
$('#tbd').addEventListener('keydown',event=>{
 if(event.target.matches('[data-candidate-id]')&&event.key==='Enter'){event.preventDefault();view_(Number(event.target.dataset.candidateId));}
});
const archiveDialog=$('#archive-confirmation');
let archivePending=null,archiveBusy=false;
function arc(id,v,button,kind='candidate'){
 if(archiveBusy||archiveDialog.open)return;
 archivePending={id,archived:v,button,kind};
 const row=button?.closest('tr');
 $('#archive-confirmation-title').textContent=(v?'Archive ':'Restore ')+kind+'?';
 $('#archive-confirmation-description').textContent=kind==='invitation'?(v?'This invitation will move to Archived. Its code, usage history and any submitted Applicant ID will be preserved. Archiving does not revoke an unexpired, unsubmitted code or archive the candidate’s CIF.':'This invitation will return to the active list. Its code expiry, usage history and any submitted Applicant ID will remain unchanged. Restoring does not reactivate an expired or submitted code.'):(v?'This record will move to Archived and leave the active candidates list. All CIF information will be kept, and you can restore it later.':'This record will return to the active candidates list. Its saved CIF information and interview status will remain unchanged.');
 $('#archive-confirmation-submit').textContent=(v?'Archive ':'Restore ')+kind;
 $('#archive-confirmation-submit').classList.toggle('action-muted',v);
 $('#archive-confirmation-icon').textContent=v?'↗':'↩';
 archiveDialog.dataset.action=v?'archive':'restore';
 $('#archive-candidate-name').textContent=kind==='invitation'?(row?.cells[0]?.textContent||'Invitation'):row?.querySelector('.nm b')?.textContent||'Candidate record';
 $('#archive-candidate-id').textContent=kind==='invitation'?row?.cells[2]?.textContent||`Invitation #${id}`:row?.querySelector('td:first-child')?.textContent||`Record ${id}`;
 $('#archive-candidate-email').textContent=kind==='invitation'?`Position: ${row?.cells[1]?.textContent||'Not specified'}`:row?.querySelector('.nm small')?.textContent||'';
 $('#archive-confirmation-error').hidden=true;
 archiveDialog.showModal();
}
$('#archive-confirmation-cancel').onclick=()=>archiveDialog.close();
archiveDialog.addEventListener('cancel',event=>{if(archiveBusy)event.preventDefault();});
archiveDialog.addEventListener('close',()=>{archivePending=null;});
$('#archive-confirmation-submit').onclick=async()=>{
 if(archiveBusy||!archivePending)return;
 const pending=archivePending;archiveBusy=true;
 $('#archive-confirmation-submit').disabled=true;$('#archive-confirmation-cancel').disabled=true;
 $('#archive-confirmation-submit').textContent=pending.archived?'Archiving…':'Restoring…';
 try{
  await cifRequest(pending.kind==='invitation'?'invitation_archive':'candidate_archive',{id:pending.id,archived:pending.archived});
  archiveDialog.close();await (pending.kind==='invitation'?rl():table());
  toast((pending.kind==='invitation'?'Invitation':'Candidate')+(pending.archived?' archived. Information preserved.':' restored to the active list.'));
  $(pending.kind==='invitation'?'#invite-search':'#q').focus({preventScroll:true});
 }catch(error){$('#archive-confirmation-error').textContent=error.message;$('#archive-confirmation-error').hidden=false;}
 finally{archiveBusy=false;$('#archive-confirmation-submit').disabled=false;$('#archive-confirmation-cancel').disabled=false;$('#archive-confirmation-submit').textContent=(pending.archived?'Archive ':'Restore ')+pending.kind;}
};
function view_(id){location.href=new URL('record.php?id='+id,location.href).href;}
function printCandidate(id){window.open(new URL('record.php?id='+id+'&print=1',location.href).href,'_blank','noopener');}
const closeD=()=>document.body.classList.remove('open');$('#ov').onclick=closeD;
/* Database-backed invitations; retrieval is restricted to authenticated HR. */
let invitationPage=1, invitationRequest=0, invitationTimer;
const invitationSaved=savedState('cif-admin-invitations',{});
let invitationArchived=invitationSaved.archived===true;
$('#invite-search').value=invitationSaved.search||'';$('#invite-status').value=['Pending','In Progress','Submitted','Expired'].includes(invitationSaved.status)?invitationSaved.status:'';invitationPage=Math.max(1,Number(invitationSaved.page)||1);
if(invitationSaved.position&&Array.from($('#invite-position').options).some(option=>option.value===invitationSaved.position))$('#invite-position').value=invitationSaved.position;
let invitationSort=['created_desc','created_asc','expires_desc','expires_asc'].includes(invitationSaved.sort)?invitationSaved.sort:'created_desc';
const candidateSaved=savedState('cif-admin-candidates',{});
let candidateInsight=candidateSaved.insight&&typeof candidateSaved.insight==='object'?candidateSaved.insight:null;
let candidateArchived=false;
if(candidateArchived)candidateInsight=null;
$('#q').value=candidateSaved.search||'';$('#fs').value=candidateSaved.stage||'';syncCandidateStageStatuses();$('#fss').value=candidateSaved.stage_status||'';if(candidateSaved.position&&Array.from($('#fp').options).some(option=>option.value===candidateSaved.position))$('#fp').value=candidateSaved.position;const candidateSort=candidateSortPreference(candidateSaved);sortK=candidateSort.slice(0,-1);sortD=candidateSort.endsWith('+')?1:-1;page=Math.max(1,Number(candidateSaved.page)||1);
function rememberInvitations(){remember('cif-admin-invitations',{search:$('#invite-search').value,status:$('#invite-status').value,position:$('#invite-position').value,sort:invitationSort,archived:invitationArchived,page:invitationPage,...(invitationDashboardScope||{})});}
$('#invite-active-view').onclick=()=>{invitationArchived=false;invitationFilterChanged();};
$('#invite-archived-view').onclick=()=>{invitationDashboardScope=null;$('#invitation-dashboard-filter').classList.add('hide');invitationArchived=true;invitationFilterChanged();};
function invitationFilterChanged(){invitationPage=1;invitationRequest++;clearTimeout(invitationTimer);rl();}
['invite-status','invite-position'].forEach(id=>$('#'+id).onchange=invitationFilterChanged);
$$('.invite-sort-header').forEach(button=>button.onclick=()=>{const field=button.dataset.date;invitationSort=invitationSort===field+'_asc'?field+'_desc':field+'_asc';invitationFilterChanged();});
let inviteBusy=false;
function openInvite(){
 $('#generate-form').reset();$('#invite-error').classList.add('hide');
 $('#gb').disabled=false;
 $('#invite-form').showModal();$('#ge').focus();
}
$('#invite-open').onclick=()=>openInvite();
$('#invite-close').onclick=()=>{if(!inviteBusy)$('#invite-form').close();};
$('#invite-form').addEventListener('cancel',event=>{if(inviteBusy)event.preventDefault();});
$('#invite-form').addEventListener('click',event=>{if(event.target!==$('#invite-form'))return;const rect=event.target.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)event.target.close();});
$('#invite-form').addEventListener('close',()=>$('#invite-open').focus());
$('#invitation-dashboard-filter-clear').onclick=()=>{invitationDashboardScope=null;$('#invitation-dashboard-filter').classList.add('hide');invitationFilterChanged();};
$('#invite-search').oninput=()=>{invitationPage=1;rememberInvitations();invitationRequest++;clearTimeout(invitationTimer);invitationTimer=setTimeout(rl,250);};
$('#generate-form').onsubmit=async event=>{
 event.preventDefault();if(inviteBusy||!window.cifPositionPickers.validate($('#generate-form'))||!$('#generate-form').reportValidity())return;
 const button=$('#gb');inviteBusy=true;button.disabled=true;$('#invite-error').classList.add('hide');
 try{
  const result=await cifRequest('generate',{email:$('#ge').value,position:$('#gp').value});
  $('#invite-search').value='';$('#invite-status').value='';$('#invite-position').value='';invitationSort='created_desc';invitationArchived=false;invitationPage=1;clearTimeout(invitationTimer);
  await rl();await window.cifMail.openComposer({kind:'invitation',id:Number(result.id),beforeShow:()=>$('#invite-form').close()});
 }catch(error){$('#invite-error').textContent=error.message;$('#invite-error').classList.remove('hide');}finally{inviteBusy=false;button.disabled=false;}
};
function invitationEmail(invitation){
 window.cifMail.openComposer({kind:'invitation',id:Number(invitation.id)}).catch(error=>toast(error.message));
}
const invitationDate=value=>value?new Date(value.replace(' ','T')+'Z').toLocaleString('en-US',{timeZone:'Asia/Shanghai',month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit'}):'—';
function tableDateTime(value){
 if(!value)return '—';
 const timestamp=new Date(value.replace(' ','T')+'Z');if(Number.isNaN(timestamp.getTime()))return '—';
 const date=timestamp.toLocaleDateString('en-US',{timeZone:'Asia/Shanghai',month:'short',day:'numeric',year:'numeric'});
 const time=timestamp.toLocaleTimeString('en-US',{timeZone:'Asia/Shanghai',hour:'numeric',minute:'2-digit'});
 return `<time class="table-date-time" datetime="${timestamp.toISOString()}"><span class="date-line">${escapeHtml(date)} </span><span class="time-line">${escapeHtml(time)}</span></time>`;
}
async function rl(){
 rememberInvitations();
 if(invitationDashboardScope){const scope=invitationDashboardScope;$('#invitation-dashboard-filter-label').textContent=(scope.dashboard_metric==='invitations_outstanding'?'Outstanding invitations':'Invitations total')+' | '+cifDashboardCoverageLabel(scope)+' | '+($('#invite-position').value||'All positions');}
 const request=++invitationRequest;
 $('#rl').setAttribute('aria-busy','true');$('#invite-info').textContent='Loading invitations…';
 try{
  const result=await cifRequest('invitations',{search:$('#invite-search').value,status:$('#invite-status').value,position:$('#invite-position').value,sort:invitationSort,archived:invitationArchived,page:invitationPage,...(invitationDashboardScope||{})});
  if(request!==invitationRequest)return;
  invitationPage=result.page;rememberInvitations();const list=$('#rl');list.replaceChildren();
  setSidebarBadge('#invite-cnt',result.summary.Pending,'pending invitations');
  $('#invitation-list-title').textContent=invitationArchived?'Archived invitations':'Recent invitations';
  $('#invite-active-view').setAttribute('aria-pressed',String(!invitationArchived));$('#invite-archived-view').setAttribute('aria-pressed',String(invitationArchived));
  const selectedPosition=$('#invite-position').value;$('#invite-position').replaceChildren(new Option('All applied positions',''));
  for(const position of result.positions)$('#invite-position').append(new Option(position,position));
  $('#invite-position').value=selectedPosition;
  const [dateKey,dateDirection]=invitationSort.split('_');syncSortHeaders('.invite-sort-header',dateKey,dateDirection==='asc','date');
  $('#invite-stats').replaceChildren();
  Object.entries(result.summary).forEach(([label,count])=>{const card=document.createElement('button');card.type='button';card.className='card stat invitation-stat';card.dataset.status=label==='Total'?'':label;card.setAttribute('aria-pressed',String(label==='Archived'?invitationArchived:!invitationArchived&&$('#invite-status').value===card.dataset.status));card.title=label==='Total'?'Show all active invitations':`Filter ${label.toLowerCase()} invitations`;card.style.setProperty('--c',label==='Pending'?'#00A6A6':label==='Archived'?'#6b7894':'#147DBB');const number=document.createElement('b');number.textContent=count;const caption=document.createElement('span');caption.textContent=label==='Total'?'Active invitations':label;card.append(number,caption);card.onclick=()=>{invitationArchived=label==='Archived';$('#invite-status').value=label==='Archived'?'':card.dataset.status;if(label==='Total'){$('#invite-search').value='';$('#invite-position').value='';}invitationFilterChanged();};$('#invite-stats').append(card);});
  if(!result.invitations.length){const row=document.createElement('tr'),cell=document.createElement('td');cell.colSpan=7;cell.className='empty';cell.textContent='No invitations match your search.';row.append(cell);list.append(row);}
  result.invitations.forEach(invitation=>{
   const row=document.createElement('tr');
   [invitation.email,invitation.position||'—',invitation.access_code?cifFormatInvitationCode(invitation.access_code):'Not available',invitation.status,invitationDate(invitation.created_at),invitationDate(invitation.expires_at)].forEach((value,index)=>{
    const cell=document.createElement('td');const text=document.createElement(index===3?'span':'div');text.textContent=value;
    if(index===2)text.className='invitation-code';if(index===3)text.className='bd '+cls(invitation.status);
    if(index===5&&(invitation.redeemed_at||invitation.submitted_at)){cell.textContent='No expiry';cell.title='This code has already been used.';}else if(index===4||index===5)cell.innerHTML=tableDateTime(index===4?invitation.created_at:invitation.expires_at);else cell.append(text);row.append(cell);
   });
   const actions=document.createElement('td');actions.className='ac';
   const submitted=Boolean(invitation.submitted_at);
   const email=document.createElement('button');email.textContent='Send email';email.disabled=invitationArchived||submitted||!invitation.access_code||Boolean(Number(invitation.code_expired));email.title=submitted?'This CIF has already been submitted':'Review and send an email';email.onclick=()=>invitationEmail(invitation);
   const archive=document.createElement('button');archive.textContent=invitationArchived?'Restore':'Archive';archive.classList.toggle('action-muted',!invitationArchived);archive.onclick=()=>arc(Number(invitation.id),!invitationArchived,archive,'invitation');
   actions.append(email,archive);row.append(actions);list.append(row);
  });
  $('#invite-info').textContent=result.total?`Showing ${(result.page-1)*result.per_page+1}–${Math.min(result.page*result.per_page,result.total)} of ${result.total}`:'0 results';
  const pager=$('#invite-pages');pager.replaceChildren();
  const addPage=(label,target,disabled=false)=>{const button=document.createElement('button');button.textContent=label;button.disabled=disabled;button.classList.toggle('on',target===result.page&&typeof label==='number');if(target===result.page&&typeof label==='number')button.setAttribute('aria-current','page');button.onclick=()=>{invitationPage=target;clearTimeout(invitationTimer);rl();};pager.append(button);};
  addPage('Previous',result.page-1,result.page===1);
  const start=Math.max(1,Math.min(result.page-2,result.pages-4)),end=Math.min(result.pages,start+4);
  if(start>1)addPage(1,1);for(let p=start;p<=end;p++)addPage(p,p);if(end<result.pages)addPage(result.pages,result.pages);
  addPage('Next',result.page+1,result.page===result.pages);
 }catch(error){if(request===invitationRequest){$('#invite-info').textContent='Unable to load invitations. Change a filter or reopen Invitations to retry.';toast(error.message);}}
 finally{if(request===invitationRequest)$('#rl').removeAttribute('aria-busy');}
}
window.cifAdminPage={
 show:()=>{const saved=location.hash.slice(1)||savedState('cif-admin-active-view','dash');resetPageFilters(saved);go(saved,false);},
 navigate:target=>{resetPageFilters(target);go(target);},
 canSignOut:()=>window.cifMrfImport?.canLeave()??true,
 onSignOut:()=>{resetPageFilters(view);window.cifMrf?.reset();window.cifMrfImport?.reset();window.cifMail?.reset();window.cifMrfRequestors?.reset();$('#invite-form').close();closeD();}
};
window.addEventListener('pagehide',()=>resetPageFilters(view));
window.addEventListener('pageshow',event=>{if(event.persisted&&!$('#app').classList.contains('hide')){resetPageFilters(view);go(view,false);}});
