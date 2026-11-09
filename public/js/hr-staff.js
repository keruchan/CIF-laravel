(()=>{
 const root=document.querySelector('#staff');if(!root)return;
 const el=id=>document.getElementById(id),escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
 const ids={period:'hr-kpi-period',date_from:'hr-kpi-from',date_to:'hr-kpi-to',staff_id:'hr-monitor-person'};
 const tableIds={table_search:'hr-kpi-table-search',table_status:'hr-kpi-table-status',table_category:'hr-kpi-table-category'};
 const profileDialog=el('hr-profile-dialog'),placeholder=profileDialog.dataset.placeholder;
 let page=1,request=0,timer,myPhoto='',selectedPhoto='',photoProcessing=false,photoSaving=false,chartData=null,chartMetric='hires',appliedFilters;
 let displayedFilters=null;
 let tableSort='due',tableDirection='asc',tableRequest=0,tableTimer,tablePages=1,tablePending=false;
 const defaults={period:'monthly',date_from:root.dataset.defaultFrom,date_to:root.dataset.defaultTo,staff_id:'0'};
 const number=value=>new Intl.NumberFormat('en-US',{maximumFractionDigits:2}).format(value);
 // Display-only rule: fractions below .10 round down; .10 and above round up.
 function roundDays(value){const whole=Math.floor(value);return whole+((value-whole)+1e-9>=0.1?1:0);}
 const percent=value=>value===null?'—':number(value)+'%',days=value=>value===null?'—':number(roundDays(value))+' days';
 const date=value=>value?new Date(value+'T00:00:00Z').toLocaleDateString('en-US',{timeZone:'UTC',month:'short',day:'numeric',year:'numeric'}):'—';
 const empty=(columns,message)=>'<tr><td class="hr-monitor-empty" colspan="'+columns+'">'+escape(message)+'</td></tr>';
 function readFilters(){return {period:el(ids.period).value,date_from:el(ids.date_from).value,date_to:el(ids.date_to).value,staff_id:Number(el(ids.staff_id).value)};}
 function coveragePreview(){
  const filters=readFilters(),from=filters.date_from,to=filters.date_to;
  el('hr-kpi-filter-error').hidden=true;
  if(!from&&!to){el('hr-kpi-coverage-preview').textContent='Coverage: All dates · '+({weekly:'Weeks run Monday–Sunday',monthly:'Calendar months',yearly:'Calendar years'}[filters.period])+'. Filters update automatically.';return;}
  if(!from||!to||from>to){el('hr-kpi-coverage-preview').textContent='Choose a valid From and To date. Both dates are included.';return;}
  const count=Math.round((Date.parse(to+'T00:00:00Z')-Date.parse(from+'T00:00:00Z'))/86400000)+1;
  el('hr-kpi-coverage-preview').textContent='Coverage: '+date(from)+' – '+date(to)+' · '+number(count)+' calendar days · '+({weekly:'Weeks run Monday–Sunday',monthly:'Calendar months',yearly:'Calendar years'}[filters.period])+'. Both dates included; filters update automatically.';
 }
 function resetFilters(){request++;clearTimeout(timer);resetTableState();displayedFilters=null;page=1;chartMetric='hires';chartData=null;for(const [key,id]of Object.entries(ids))el(id).value=defaults[key];appliedFilters={...defaults,staff_id:0};coveragePreview();}
 function applyFilters(){if(!el('hr-kpi-filter-form').checkValidity()){el('hr-kpi-filter-error').textContent='Choose valid From and To dates. Results will update automatically when the coverage is valid.';el('hr-kpi-filter-error').hidden=false;return;}const filters=readFilters(),limit=new Date(filters.date_from+'T00:00:00Z');limit.setUTCFullYear(limit.getUTCFullYear()+5);let error='';if(!filters.date_from!==!filters.date_to)error='Choose both From and To, or clear both for All dates.';else if(filters.date_from>filters.date_to)error='From must be on or before To.';else if(new Date(filters.date_to+'T00:00:00Z')>=limit)error='Choose coverage of up to five years.';if(error){el('hr-kpi-filter-error').textContent=error;el('hr-kpi-filter-error').hidden=false;return;}appliedFilters=filters;page=1;load();}
 try{sessionStorage.removeItem('cif-hr-monitor');}catch{}
 function metricCells(metric){return '<td>'+metric.needed+'</td><td>'+metric.filled+'</td><td>'+percent(metric.fill_rate)+'</td><td>'+percent(metric.timeliness_rate)+'</td><td>'+days(metric.average_days)+'</td><td>'+metric.hires+'</td>';}
 function mrfRows(rows,newTab=false){return rows.map(row=>'<tr><td><a href="mrf.php?id='+row.id+'"'+(newTab?' target="_blank" rel="noopener"':'')+'>'+escape(row.mrf_number)+'</a><small>'+escape(row.position_title)+' · '+escape(row.department_name)+'</small></td><td>'+escape(row.hr_name||'Unassigned')+'</td><td>'+date(row.date_approved)+'</td><td>'+escape(row.position_category||'—')+'<small>'+escape(row.lead_time_days||'—')+' calendar days</small></td><td>'+date(row.hiring_due_date)+'</td><td><span class="mrf-status" data-status="'+escape(row.status)+'">'+escape(row.status)+'</span>'+(!row.eligible?'<small>Excluded until reopened</small>':'')+'</td><td>'+date(row.first_hire_date)+'</td><td>'+(row.filled?days(row.average_days):days(row.days_open)+'<small>Open since original approval</small>')+'</td></tr>').join('')||empty(8,'No approved MRFs are due in this period for the selected HR member.');}
 function readTableFilters(){const filters={table_sort:tableSort,table_direction:tableDirection};for(const [key,id]of Object.entries(tableIds))filters[key]=el(id).value;return filters;}
 function sortIndicators(){document.querySelectorAll('[data-kpi-sort]').forEach(button=>{const active=button.dataset.kpiSort===tableSort;button.closest('th').setAttribute('aria-sort',active?(tableDirection==='asc'?'ascending':'descending'):'none');button.querySelector('.sort-direction').textContent=active?(tableDirection==='asc'?'↑':'↓'):'↕';});}
 function resetTableState(){tableRequest++;clearTimeout(tableTimer);tablePending=false;page=1;tableSort='due';tableDirection='asc';for(const id of Object.values(tableIds))el(id).value='';sortIndicators();el('hr-kpi-table-feedback').textContent='';}
 function renderTable(data){
  page=data.page;tablePages=data.pages;
  el('hr-kpi-mrfs').innerHTML=data.rows.length||!data.scope_total?mrfRows(data.rows):empty(8,'No MRFs match these table filters within the selected HR/date coverage.');
  const start=data.total?(data.page-1)*data.per_page+1:0,end=Math.min(data.total,data.page*data.per_page);
  el('hr-monitor-page-info').textContent='Showing '+start+'–'+end+' of '+data.total+' MRF files'+(data.total!==data.scope_total?' ('+data.scope_total+' in coverage)':'')+' · Page '+data.page+' of '+data.pages;
  el('hr-monitor-prev').disabled=el('hr-kpi-first').disabled=data.page<=1;el('hr-monitor-next').disabled=el('hr-kpi-last').disabled=data.page>=data.pages;
  const pager=el('hr-kpi-page-numbers');pager.replaceChildren();const first=Math.max(1,Math.min(data.page-2,data.pages-4)),last=Math.min(data.pages,first+4);
  if(first>1){const dots=document.createElement('span');dots.textContent='…';dots.setAttribute('aria-hidden','true');pager.append(dots);}
  for(let index=first;index<=last;index++){const button=document.createElement('button');button.type='button';button.textContent=index;button.dataset.kpiPage=index;button.setAttribute('aria-label','MRF table page '+index);if(index===data.page){button.className='on';button.setAttribute('aria-current','page');}button.onclick=()=>{page=index;loadTable();};pager.append(button);}
  if(last<data.pages){const dots=document.createElement('span');dots.textContent='…';dots.setAttribute('aria-hidden','true');pager.append(dots);}
  el('hr-kpi-table-feedback').textContent=data.total!==data.scope_total?'Table filters are active. KPI cards and charts still show the full HR/date coverage.':'All MRF files within the selected HR/date coverage.';el('hr-kpi-table-feedback').dataset.error='false';sortIndicators();el('hr-kpi-mrfs').removeAttribute('aria-busy');
 }
 async function loadTable(){
  clearTimeout(tableTimer);if(el('hr-monitor-results').getAttribute('aria-busy')==='true'){tablePending=true;tableRequest++;return;}
  const token=++tableRequest,filters={...appliedFilters,...readTableFilters(),page};el('hr-kpi-mrfs').setAttribute('aria-busy','true');el('hr-kpi-table-feedback').textContent='Loading MRF records…';
  for(const id of ['hr-kpi-first','hr-monitor-prev','hr-monitor-next','hr-kpi-last'])el(id).disabled=true;
  try{const data=await cifRequest('hr_staff_monitor',filters);if(token!==tableRequest)return;renderTable(data);}
  catch(error){if(token!==tableRequest)return;el('hr-kpi-table-feedback').textContent=error.message+' Change a table filter or use Reset table to retry.';el('hr-kpi-table-feedback').dataset.error='true';el('hr-kpi-mrfs').innerHTML=empty(8,'MRF records could not be loaded. KPI cards and charts are unchanged.');el('hr-kpi-page-numbers').replaceChildren();el('hr-monitor-page-info').textContent='Table unavailable';}
  finally{if(token===tableRequest)el('hr-kpi-mrfs').removeAttribute('aria-busy');}
 }
 function changedTable(){page=1;loadTable();}
 el(tableIds.table_search).addEventListener('input',()=>{clearTimeout(tableTimer);page=1;tableTimer=setTimeout(loadTable,250);});
 for(const [key,id]of Object.entries(tableIds))if(key!=='table_search')el(id).addEventListener('change',changedTable);
 document.querySelectorAll('[data-kpi-sort]').forEach(button=>{button.onclick=()=>{const key=button.dataset.kpiSort;tableDirection=tableSort===key&&tableDirection==='asc'?'desc':'asc';tableSort=key;sortIndicators();changedTable();};});
 el('hr-kpi-table-reset').onclick=()=>{resetTableState();loadTable();};
 const chartMetrics={fill_rate:{label:'Fill rate',unit:'Percent (%)',color:'#00a6a6',format:percent},timeliness_rate:{label:'Timeliness rate',unit:'Percent (%) · lower is faster',color:'#147dbb',format:percent},average_days:{label:'Average days to fill',unit:'Calendar days',color:'#082e82',format:days},hires:{label:'Number hired',unit:'Hired',color:'#6875c9',format:number}};
 function drawTrend(){
  if(!chartData)return;
  const metric=chartMetrics[chartMetric],points=chartData.trend.points,values=points.map(point=>point[chartMetric]===null?null:(chartMetric==='average_days'?roundDays(point[chartMetric]):point[chartMetric])),valid=values.filter(value=>value!==null);
  const width=Math.max(760,points.length*52+88),height=280,left=64,right=24,top=26,bottom=44,plotWidth=width-left-right,plotHeight=height-top-bottom;
  const maximum=Math.max(1,...valid),step=['hires','average_days'].includes(chartMetric)?Math.max(1,Math.ceil(maximum/4)):maximum/4,max=step*4;
  const x=index=>points.length===1?left+plotWidth/2:left+index*plotWidth/(points.length-1),y=value=>top+plotHeight-(value/max)*plotHeight;
  const selected=points.findIndex(point=>point.selected),selectedX=selected>=0?x(selected):null;
  let svg='<svg viewBox="0 0 '+width+' '+height+'" role="img" aria-labelledby="hr-kpi-svg-title hr-kpi-svg-description" style="--kpi-line-color:'+metric.color+'"><title id="hr-kpi-svg-title">'+escape(metric.label+' · '+chartData.trend.label)+'</title><desc id="hr-kpi-svg-description">MRF due-date trend. '+escape(metric.unit)+'. Missing values are gaps. Exact values are available in View chart values.</desc>';
  if(selectedX!==null)svg+='<rect class="hr-kpi-selected-band" x="'+Math.max(left-12,selectedX-15)+'" y="'+top+'" width="30" height="'+plotHeight+'" rx="8"/>';
  for(let tick=0;tick<=4;tick++){const value=step*tick,py=y(value);svg+='<line class="hr-kpi-gridline" x1="'+left+'" x2="'+(width-right)+'" y1="'+py+'" y2="'+py+'"/><text class="hr-kpi-axis" text-anchor="end" x="'+(left-12)+'" y="'+(py+4)+'">'+escape(number(value))+'</text>';}
  let segment=[];const paths=[];values.forEach((value,index)=>{if(value===null){if(segment.length)paths.push(segment);segment=[];}else segment.push([x(index),y(value)]);});if(segment.length)paths.push(segment);
  for(const path of paths)svg+='<path class="hr-kpi-trend-path" d="'+path.map(([px,py],index)=>(index?'L':'M')+px.toFixed(2)+','+py.toFixed(2)).join(' ')+'"/>';
  points.forEach((point,index)=>{
   svg+='<text class="hr-kpi-axis'+(point.selected?' hr-kpi-axis-selected':'')+'" text-anchor="middle" x="'+x(index)+'" y="'+(height-15)+'">'+escape(point.label)+'</text>';
   if(values[index]===null)return;
   const label=(point.coverage_label||point.key)+' · '+metric.label+': '+metric.format(values[index]);
   svg+='<g class="hr-kpi-point" tabindex="0" role="img" aria-label="'+escape(label)+'" data-kpi-point="'+index+'"><title>'+escape(label)+'</title><circle class="hr-kpi-point-hit" cx="'+x(index)+'" cy="'+y(values[index])+'" r="12"/><circle class="hr-kpi-point-dot" cx="'+x(index)+'" cy="'+y(values[index])+'" r="'+(point.selected?6:4)+'"/></g>';
  });
  if(!valid.length)svg+='<text class="hr-kpi-no-data" text-anchor="middle" x="'+(width/2)+'" y="'+(height/2)+'">No data for this KPI in the selected range</text>';
  el('hr-kpi-line').innerHTML=svg+'</svg>';el('hr-kpi-line').querySelector('svg').style.minWidth=Math.max(560,width*0.78)+'px';el('hr-kpi-trend-unit').textContent=metric.unit;
  document.querySelectorAll('[data-kpi-metric]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.kpiMetric===chartMetric)));
  el('hr-kpi-chart-tooltip').textContent=chartMetric==='timeliness_rate'?'Lower is faster. Values can exceed 100%.':(valid.length?'Hover or focus a point to see its value.':'No targets or valid timing data; gaps are not zero.');
 }
 function drawCharts(data,staff){
  chartData=data;const owner=staff.find(person=>person.id===data.filters.staff_id),scope=owner?owner.name:'All HR members';
  el('hr-kpi-trend-context').textContent=data.trend.label+' · '+scope+' · By MRF due date';drawTrend();
  el('hr-kpi-chart-values').innerHTML=data.trend.points.map(point=>'<tr'+(point.selected?' class="hr-kpi-values-selected"':'')+'><th scope="row">'+escape(point.coverage_label||point.key)+'</th><td>'+percent(point.fill_rate)+'</td><td>'+percent(point.timeliness_rate)+'</td><td>'+days(point.average_days)+'</td><td>'+number(point.hires)+'</td></tr>').join('');
  el('hr-kpi-funnel-context').textContent='Applied in '+data.funnel.period_label+' · '+scope;
  el('hr-kpi-funnel-stages').innerHTML=data.funnel.stages.map((stage,index)=>{
   const share=stage.percent_applied,conversion=stage.percent_previous;
   return '<li><div class="hr-kpi-funnel-label"><span>'+escape(stage.label)+'</span><strong>'+number(stage.count)+'</strong></div><div class="hr-kpi-funnel-track" aria-hidden="true"><div class="hr-kpi-funnel-bar" style="--funnel-width:'+Math.max(0,Math.min(100,share??0))+'%;--funnel-delay:'+(index*50)+'ms;--funnel-color:'+['#147dbb','#117aaa','#087798','#008c98','#00a6a6'][index]+'"></div></div><div class="hr-kpi-funnel-percent"><span>'+percent(share)+' of applicants</span><span>'+(index?percent(conversion)+' from previous step':'Starting point')+'</span></div></li>';
  }).join('');
  if(!data.funnel.total)el('hr-kpi-funnel-context').textContent+=' · No applicants in this period';
 }
 document.querySelector('.hr-kpi-metric-switch').addEventListener('click',event=>{const button=event.target.closest('[data-kpi-metric]');if(!button||!chartMetrics[button.dataset.kpiMetric])return;chartMetric=button.dataset.kpiMetric;drawTrend();});
 function pointDetail(event){const point=event.target.closest('[data-kpi-point]');if(!point||!chartData)return;const record=chartData.trend.points[Number(point.dataset.kpiPoint)],metric=chartMetrics[chartMetric];el('hr-kpi-chart-tooltip').textContent=(record.coverage_label||record.key)+' · '+metric.label+': '+metric.format(record[chartMetric]);}
 el('hr-kpi-line').addEventListener('mouseover',pointDetail);el('hr-kpi-line').addEventListener('focusin',pointDetail);
 async function load(){
  clearTimeout(timer);clearTimeout(tableTimer);coveragePreview();const token=++request,tableToken=++tableRequest,filters={...appliedFilters,...readTableFilters(),page};
  el('hr-profile-open').disabled=true;el('hr-monitor-results').setAttribute('aria-busy','true');el('hr-monitor-feedback').textContent='Loading recruitment KPIs…';el('hr-monitor-feedback').dataset.error='false';
  try{
   const [data,charts]=await Promise.all([cifRequest('hr_staff_monitor',filters),cifRequest('hr_kpi_charts',appliedFilters)]);if(token!==request)return;
   const photos=new Map(data.staff.map(person=>[person.id,person.photo||placeholder])),me=data.staff.find(person=>person.id===data.current_admin_id);
   myPhoto=me?.photo||'';el('hr-profile-open').disabled=!me;
   const draftOwner=el(ids.staff_id).value;el(ids.staff_id).replaceChildren(new Option('All HR members','0'),...data.staff.map(person=>new Option(person.name+' (@'+person.username+')',person.id)));el(ids.staff_id).value=draftOwner;
   const totals=data.totals;el('hr-kpi-due').textContent=number(totals.needed);el('hr-kpi-fill-rate').textContent=percent(totals.fill_rate);el('hr-kpi-fill-count').textContent=totals.filled+' filled / '+totals.needed+' needed';el('hr-kpi-timeliness').textContent=percent(totals.timeliness_rate);el('hr-kpi-average').textContent=days(totals.average_days);el('hr-kpi-hires').textContent=number(totals.hires);drawCharts(charts,data.staff);
   displayedFilters={...data.filters};delete displayedFilters.year;delete displayedFilters.month;
   el('hr-monitor-members').innerHTML=data.metrics.map(person=>'<tr data-member-id="'+person.id+'" title="Filter KPI for '+escape(person.name)+'"><td><div class="hr-member-identity"><img class="hr-profile-avatar" src="'+escape(photos.get(person.id)||placeholder)+'" alt="" width="42" height="42"><div><button class="hr-member-select" type="button" data-hr-id="'+person.id+'" aria-pressed="'+(filters.staff_id===person.id)+'">'+escape(person.name)+'</button><small>@'+escape(person.username)+' · Click to filter</small></div></div></td>'+metricCells(person)+'</tr>').join('')+(data.unassigned_files?'<tr><td><strong>Unassigned</strong><small>Assign an HR owner in the MRF record</small></td>'+metricCells(data.unassigned)+'</tr>':'');
   if(tableToken===tableRequest)renderTable(data);
   const notices=[];
   if(data.unassigned_files)notices.push(data.unassigned_files+' MRF file(s) in this period have no assigned HR owner. They remain in team totals, not individual HR results.');
   if(totals.excluded)notices.push(totals.excluded+' On Hold, Cancelled or Rejected file(s) are excluded. Reopening uses the original approval date; elapsed days do not restart.');
   if(totals.overdue)notices.push(totals.overdue+' eligible MRF file(s) are past due and have no deployed hire.');
   if(data.missing_timing)notices.push(data.missing_timing+' approved file(s) lack approval timing and cannot be placed in a due period. Historical approval dates are not guessed.');
   if(totals.invalid_dates)notices.push(totals.invalid_dates+' hire(s) have missing or earlier-than-approval dates and are excluded from timing averages.');
   if(totals.overfilled)notices.push(totals.overfilled+' file(s) have more than one deployed candidate. Fill rate still counts each file once; the number hired counts all deployments.');
   el('hr-kpi-notices').replaceChildren();for(const notice of notices){const paragraph=document.createElement('p');paragraph.textContent=notice;el('hr-kpi-notices').append(paragraph);}
   const ownerName=data.staff.find(person=>person.id===filters.staff_id)?.name||'All HR members, including unassigned targets';
   el('hr-monitor-feedback').textContent='Showing '+data.coverage.label+' · '+ownerName+(data.coverage.all_dates?'':' · '+data.coverage.days+' calendar days')+'. KPI: MRF due dates. Funnel: application dates.';coveragePreview();
  }catch(error){if(token!==request)return;el('hr-monitor-feedback').textContent=error.message+' Try Refresh to retry.';el('hr-monitor-feedback').dataset.error='true';el('hr-monitor-results').classList.add('hide');}
  finally{if(token===request){el('hr-monitor-results').removeAttribute('aria-busy');if(el('hr-monitor-feedback').dataset.error!=='true'){el('hr-monitor-results').classList.remove('hide');if(tablePending){tablePending=false;loadTable();}}}}
 }
 for(const id of Object.values(ids))el(id).addEventListener('change',()=>{coveragePreview();applyFilters();});
 el('hr-kpi-filter-form').addEventListener('submit',event=>{event.preventDefault();applyFilters();});
 el('hr-kpi-filter-reset').onclick=()=>{resetFilters();load();};
 root.querySelector('.hr-kpi-shortcuts').addEventListener('click',event=>{const shortcut=event.target.closest('[data-kpi-coverage]');if(!shortcut)return;const now=new Date(root.dataset.defaultToday+'T00:00:00Z'),start=new Date(now),end=new Date(now),kind=shortcut.dataset.kpiCoverage;if(kind==='all'){el(ids.date_from).value='';el(ids.date_to).value='';coveragePreview();applyFilters();return;}if(kind==='week'){start.setUTCDate(start.getUTCDate()-((start.getUTCDay()+6)%7));end.setTime(start.getTime());end.setUTCDate(end.getUTCDate()+6);el(ids.period).value='weekly';}else if(kind==='month'){start.setUTCDate(1);end.setUTCMonth(end.getUTCMonth()+1,0);el(ids.period).value='monthly';}else if(kind==='year'){start.setUTCMonth(0,1);end.setUTCMonth(11,31);el(ids.period).value='monthly';}else{start.setUTCDate(1);start.setUTCMonth(start.getUTCMonth()-11);end.setUTCMonth(end.getUTCMonth()+1,0);el(ids.period).value='monthly';}el(ids.date_from).value=start.toISOString().slice(0,10);el(ids.date_to).value=end.toISOString().slice(0,10);applyFilters();});
 el('hr-monitor-members').addEventListener('click',event=>{const row=event.target.closest('[data-member-id]');if(!row||!displayedFilters||el('hr-monitor-results').getAttribute('aria-busy')==='true')return;const owner=Number(row.dataset.memberId);el(ids.staff_id).value=String(owner);appliedFilters={...displayedFilters,staff_id:owner};page=1;load();});
 el('hr-monitor-members').addEventListener('keydown',event=>{const button=event.target.closest('[data-hr-id]');if(button&&!event.repeat&&(event.key==='Enter'||event.key===' ')){event.preventDefault();button.click();}});
 el('hr-kpi-first').onclick=()=>{page=1;loadTable();};el('hr-kpi-last').onclick=()=>{page=tablePages;loadTable();};el('hr-monitor-prev').onclick=()=>{page=Math.max(1,page-1);loadTable();};el('hr-monitor-next').onclick=()=>{page=Math.min(tablePages,page+1);loadTable();};el('hr-monitor-refresh').onclick=()=>cifRefreshPage(el('hr-monitor-refresh'));
 resetFilters();
 function photoControls(){
  const busy=photoProcessing||photoSaving;
  el('hr-profile-preview').src=selectedPhoto||placeholder;
  el('hr-profile-file').disabled=busy;el('hr-profile-cancel').disabled=busy;
  el('hr-profile-remove').disabled=busy||!selectedPhoto;
  el('hr-profile-save').disabled=busy||selectedPhoto===myPhoto;
  el('hr-profile-save').textContent=photoSaving?'Saving…':'Save picture';
  el('hr-profile-status').textContent=photoProcessing?'Preparing your picture…':'';
 }
 function photoError(message=''){el('hr-profile-error').textContent=message;el('hr-profile-error').hidden=!message;}
 el('hr-profile-open').onclick=()=>{selectedPhoto=myPhoto;el('hr-profile-file').value='';photoError();photoControls();profileDialog.showModal();};
 el('hr-profile-cancel').onclick=()=>profileDialog.close();
 profileDialog.addEventListener('cancel',event=>{if(photoProcessing||photoSaving)event.preventDefault();});
 el('hr-profile-remove').onclick=()=>{selectedPhoto='';el('hr-profile-file').value='';photoError();photoControls();};
 el('hr-profile-file').addEventListener('change',async event=>{
  const file=event.target.files[0];if(!file)return;
  photoError();photoProcessing=true;photoControls();
  try{
   selectedPhoto=await cifPrepareHrProfilePhoto(file);
  }catch(error){photoError(error.message||'This picture could not be opened. Choose another photo.');}
  finally{photoProcessing=false;el('hr-profile-file').value='';photoControls();}
 });
 el('hr-profile-form').addEventListener('submit',async event=>{
  event.preventDefault();if(photoSaving||photoProcessing||selectedPhoto===myPhoto)return;
  photoSaving=true;photoError();photoControls();
  try{const result=await cifRequest('hr_profile_photo_save',{photo:selectedPhoto});myPhoto=result.photo;profileDialog.close();toast(result.message);await load();}
  catch(error){photoError(error.message);}
  finally{photoSaving=false;photoControls();}
 });
 window.cifHrStaff={load,resetFilters};
 document.addEventListener('cif:hr-photo-saved',()=>{if(!root.classList.contains('hide'))load();});
})();
