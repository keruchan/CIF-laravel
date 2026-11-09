(()=>{
 const script=document.currentScript,portal=script.dataset.portal,endpoint=new URL('../ajax/mrf.php',script.src),query=selector=>document.querySelector(selector);
 const coverage=portal==='requestor'?window.cifRequestorCoverage('mrf'):null;let pendingFilters=null,dashboardSelection=null;
 const statuses=['Pending','In Progress','Completed','Rejected','On Hold','Cancelled'];
 const storageKey='cif-mrf-list:'+portal;let page=1,sort='desc',request=0,timer;
 try{sessionStorage.removeItem(storageKey);}catch(error){}
 function resetFilters(){dashboardSelection=null;query('#mrf-dashboard-filter')?.classList.add('hide');coverage?.set();request++;clearTimeout(timer);page=1;sort='desc';query('#mrf-search').value='';query('#mrf-filter-status').value='';query('#mrf-filter-approval').value='';resetStatusAction();}
 async function api(action,input){
  const state=await cifReady,response=await fetch(endpoint.href+'?portal='+portal+'&action='+action,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body:JSON.stringify(input)}),data=await response.json();
  if(!response.ok)throw new Error(data.error||'Unable to complete request.');return data;
 }
 const message=(selector,text)=>{query(selector).textContent=text;query(selector).hidden=!text;};
 const statusDialog=portal==='admin'?query('#mrf-action-dialog'):null;let statusPending=null,statusBusy=false,statusLoading=false,statusRequest=0;
 const statusActions={
  hold:{status:'On Hold',label:'Hold',title:'Put MRF on hold?',confirm:'Confirm hold',help:'Recruitment will pause for this file. Use Resume when it is ready to continue.'},
  cancel:{status:'Cancelled',label:'Cancel',title:'Cancel MRF?',confirm:'Confirm cancellation',help:'Recruitment will stop for this file. You can reinstate it later to return it to In Progress.'},
  resume:{status:'In Progress',label:'Resume',title:'Resume MRF?',confirm:'Confirm resume',help:'This file will move from On Hold to In Progress and become available for recruitment again.'},
  reinstate:{status:'In Progress',label:'Reinstate',title:'Reinstate MRF?',confirm:'Confirm reinstatement',help:'This cancelled file will be reopened, set to In Progress and become available for recruitment again.'}
 };
 function availableStatusActions(status){return status==='Completed'?[]:status==='Cancelled'?['reinstate']:status==='On Hold'?['resume','cancel']:['hold','cancel'];}
 function resetStatusAction(){statusRequest++;statusPending=null;statusDialog?.close();}
 async function openStatusAction(id,action){
  if(!statusDialog||statusBusy||statusLoading)return;statusLoading=true;const current=++statusRequest;
  try{
   const {record}=await api('detail',{id:Number(id)});if(current!==statusRequest)return;
   if(record.approval_status!=='Approved')throw new Error('Only approved requests can be held, cancelled, resumed or reinstated. Refresh the list.');
   if(record.status==='Completed')throw new Error('This MRF is completed. Hold and Cancel are no longer available. Refresh the list.');
   if(!availableStatusActions(record.status).includes(action))throw new Error('This MRF status has changed. Refresh the list to see the available actions.');
   const config=statusActions[action];statusPending={id:Number(record.id),revision:Number(record.revision),status:config.status};query('#mrf-action-form').reset();message('#mrf-action-error','');
   query('#mrf-action-title').textContent=config.title;
   query('#mrf-action-summary').textContent=record.mrf_number+' · '+record.position_title+' · Current status: '+record.status;
   query('#mrf-action-help').textContent=config.help+' Only this headcount file is affected. Management approval and the original hiring due date stay unchanged.';
   query('#mrf-action-save').textContent=config.confirm;query('#mrf-action-save').classList.toggle('action-danger',action==='cancel');statusDialog.showModal();query('#mrf-action-reason').focus();
  }catch(error){if(current===statusRequest)message('#mrf-list-error',error.message);}
  finally{statusLoading=false;}
 }
 if(statusDialog){
  query('#mrf-action-close').onclick=()=>{if(!statusBusy)resetStatusAction();};
  statusDialog.addEventListener('cancel',event=>{if(statusBusy)event.preventDefault();else resetStatusAction();});
  query('#mrf-action-form').onsubmit=async event=>{
   event.preventDefault();if(statusBusy||!statusPending||!event.target.reportValidity())return;
   const reason=query('#mrf-action-reason').value.trim();if(!reason){message('#mrf-action-error','Please provide a reason.');query('#mrf-action-reason').focus();return;}
   const input={...statusPending,hr_notes:reason},current=statusRequest;statusBusy=true;message('#mrf-action-error','');
   statusDialog.querySelectorAll('button,textarea').forEach(element=>element.disabled=true);
   try{
    const result=await api('update',input);if(current!==statusRequest)return;
    resetStatusAction();await load();refreshSidebarBadges();toast(result.message);
   }catch(error){if(current===statusRequest)message('#mrf-action-error',error.message);}
   finally{statusBusy=false;statusDialog.querySelectorAll('button,textarea').forEach(element=>element.disabled=false);}
  };
 }
 const recordUrl=(id,print=false)=>new URL('mrf.php?id='+id+(print?'&print=1':''),location.href).href;
 function cell(text,small){const element=document.createElement('td');element.textContent=text;if(small){const detail=document.createElement('small');detail.textContent=small;element.append(detail);}return element;}
 function renderSummaryCards(data,input){
  const stats=query('#mrf-stats');stats.replaceChildren();
  Object.entries(data.approval_summary).forEach(([label,count])=>{const button=document.createElement('button');button.type='button';button.className='card stat';button.style.setProperty('--c',label==='Approved'?'#00a6a6':label==='Denied'?'#b32d3a':'#147dbb');button.setAttribute('aria-pressed',String(!input.status&&input.approval===(label==='Total'?'':label)));const number=document.createElement('b'),caption=document.createElement('span');number.textContent=count;caption.textContent=label==='Total'?'All requests':label==='Pending'?'Awaiting approval':label;button.append(number,caption);button.onclick=()=>{query('#mrf-filter-approval').value=label==='Total'?'':label;query('#mrf-filter-status').value='';changed();};stats.append(button);});
  const completed=document.createElement('button');completed.type='button';completed.className='card stat';completed.style.setProperty('--c','#00a6a6');completed.setAttribute('aria-pressed',String(input.status==='Completed'&&!input.approval));
  const number=document.createElement('b'),caption=document.createElement('span');number.textContent=data.summary.Completed;caption.textContent='Completed';completed.append(number,caption);
  completed.onclick=()=>{query('#mrf-filter-status').value='Completed';query('#mrf-filter-approval').value='';changed();};stats.append(completed);
 }
 async function load(){
  if(pendingFilters){const filters=pendingFilters;pendingFilters=null;coverage?.set(filters);query('#mrf-filter-status').value=filters.status||'';query('#mrf-filter-approval').value=filters.approval||'';page=1;}
  if(coverage&&!coverage.valid())return;
  const current=++request;query('#mrf-rows').setAttribute('aria-busy','true');message('#mrf-list-error','');
  const input={search:query('#mrf-search').value,status:query('#mrf-filter-status').value,approval:query('#mrf-filter-approval').value,sort,page,...(coverage?.read()||{}),...(dashboardSelection||{})};
  try{
   const data=await api('list',input);if(current!==request)return;page=data.page;
   if(portal==='admin')setSidebarBadge('#mrf-cnt',data.approval_summary.Pending,'manpower files awaiting approval');
   renderSummaryCards(data,input);
   const rows=query('#mrf-rows');rows.replaceChildren();
   if(!data.requests.length){const row=document.createElement('tr'),empty=cell('No manpower requests match your filters.');empty.colSpan=portal==='requestor'?8:7;empty.className='empty';row.append(empty);rows.append(row);}
   data.requests.forEach(record=>{
    const row=document.createElement('tr');row.dataset.id=record.id;row.tabIndex=0;row.setAttribute('aria-label','Open manpower request '+record.mrf_number);
    row.append(cell(record.mrf_number),cell(record.position_title,record.site_location),cell(record.requestor_name,record.department_name));
    if(portal==='requestor')row.append(cell(record.headcount));
    const approvalCell=cell(''),approvalBadge=document.createElement('span');approvalBadge.className='mrf-status mrf-approval-status';approvalBadge.dataset.status=record.approval_status;approvalBadge.textContent=record.approval_status==='Pending'?'Awaiting approval':record.approval_status;approvalCell.append(approvalBadge);if(record.approval_status==='Approved'&&record.hiring_due_date){const due=document.createElement('small');due.textContent='Hiring due: '+new Date(record.hiring_due_date+'T00:00:00Z').toLocaleDateString('en-US',{timeZone:'UTC',month:'short',day:'numeric',year:'numeric'});due.title='Category '+record.position_category+' · '+record.lead_time_days+' calendar days from management approval on '+record.date_approved;approvalCell.append(due);}row.append(approvalCell);
    const status=cell(''),badge=document.createElement('span');badge.className='mrf-status';badge.dataset.status=record.status;badge.textContent=record.status;status.append(badge);row.append(status);
    const date=new Date(record.created_at.replace(' ','T')+'Z'),dateCell=cell(date.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})),time=document.createElement('time');dateCell.classList.add('mrf-created');time.dateTime=date.toISOString();time.textContent=date.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});dateCell.append(time);row.append(dateCell);
    const actions=cell(''),actionButtons=document.createElement('div'),print=document.createElement('a');actionButtons.className='mrf-row-actions';print.className='btn g';print.textContent='Print';print.href=recordUrl(record.id,true);print.target='_blank';print.rel='noopener';
    actionButtons.append(print);
    if(portal==='admin'&&record.approval_status==='Pending')for(const decision of ['Approved','Denied']){const button=document.createElement('button');button.type='button';button.className='btn g'+(decision==='Denied'?' action-danger':'');button.textContent=decision==='Approved'?'Approve':'Deny';button.dataset.approvalAction=decision;button.onclick=()=>window.cifMrfApproval.open(record.id,decision,async result=>{await load();refreshSidebarBadges();toast(result.message);});actionButtons.append(button);}
    if(portal==='admin'&&record.approval_status==='Approved')for(const action of availableStatusActions(record.status)){const config=statusActions[action],button=document.createElement('button');button.type='button';button.className='btn g'+(action==='cancel'?' action-danger':'');button.textContent=config.label;button.dataset.statusAction=config.status;button.dataset.mrfAction=action;button.onclick=()=>openStatusAction(record.id,action);actionButtons.append(button);}
    actions.append(actionButtons);row.append(actions);
    const open=event=>{if(event.target.closest('a,button'))return;if(event.ctrlKey||event.metaKey)window.open(recordUrl(record.id),'_blank','noopener');else location.href=recordUrl(record.id);};
    row.onclick=open;row.onkeydown=event=>{if(event.target===row&&(event.key==='Enter'||event.key===' ')){event.preventDefault();open(event);}};rows.append(row);
   });
   query('#mrf-info').textContent=data.total?`Showing ${(data.page-1)*8+1}–${Math.min(data.page*8,data.total)} of ${data.total}`:'0 results';
   const pager=query('#mrf-pages');pager.replaceChildren();const add=(label,target,disabled=false)=>{const button=document.createElement('button');button.type='button';button.textContent=label;button.disabled=disabled;if(typeof label==='number'&&target===data.page){button.className='on';button.setAttribute('aria-current','page');}button.onclick=()=>{page=target;load();};pager.append(button);};
   add('Previous',data.page-1,data.page===1);const start=Math.max(1,Math.min(data.page-2,data.pages-4)),end=Math.min(data.pages,start+4);if(start>1)add(1,1);for(let index=start;index<=end;index++)add(index,index);if(end<data.pages)add(data.pages,data.pages);add('Next',data.page+1,data.page===data.pages);
   query('#mrf-sort-date').closest('th').setAttribute('aria-sort',sort==='asc'?'ascending':'descending');query('#mrf-sort-date .sort-direction').textContent=sort==='asc'?'↑':'↓';
  }catch(error){if(current===request){message('#mrf-list-error',error.message);query('#mrf-info').textContent='Unable to load requests. Please retry.';}}
  finally{if(current===request)query('#mrf-rows').removeAttribute('aria-busy');}
 }
 function changed(){page=1;clearTimeout(timer);load();}
 query('#mrf-search').addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(changed,250);});query('#mrf-filter-status').onchange=query('#mrf-filter-approval').onchange=changed;query('#mrf-sort-date').onclick=()=>{sort=sort==='asc'?'desc':'asc';changed();};
 query('#mrf-refresh').onclick=()=>{
  clearTimeout(timer);cifRefreshPage(query('#mrf-refresh'));
 };
 if(portal==='admin')query('#mrf-dashboard-filter-clear').onclick=()=>{dashboardSelection=null;query('#mrf-dashboard-filter').classList.add('hide');changed();};
 coverage?.onChange(changed);
 resetFilters();
 window.cifMrf={load,reset:resetStatusAction,resetFilters,openDashboard(scope,label){resetFilters();dashboardSelection=scope;query('#mrf-dashboard-filter-label').textContent=label+' | '+cifDashboardCoverageLabel(scope)+' | '+(scope.dashboard_position||'All positions')+(scope.dashboard_metric.startsWith('assigned_')?' | My assigned headcounts.':scope.dashboard_metric.startsWith('mrf_')?' | Each row is one headcount from the selected requests.':' | One row per open headcount.');query('#mrf-dashboard-filter').classList.remove('hide');},openFiltered(filters){pendingFilters=filters;location.hash='mrf';requestorView();}};
})();
