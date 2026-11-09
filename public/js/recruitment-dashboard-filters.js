(()=>{
 const date=value=>new Date(value+'T00:00:00Z').toLocaleDateString('en-US',{timeZone:'UTC',month:'short',day:'numeric',year:'numeric'});
 window.cifDashboardCoverageLabel=scope=>{
  const from=scope.date_from||scope.dashboard_date_from,to=scope.date_to||scope.dashboard_date_to;
  return from&&to?date(from)+' – '+date(to):(scope.range||scope.dashboard_range||'all')==='all'?'All dates':'Last '+(scope.range||scope.dashboard_range)+' days';
 };
 const form=document.querySelector('#dashboard-filter-form');if(!form)return;
 const node=id=>document.getElementById('dashboard-'+id);let changed=()=>{};
 const defaults={position:'',trend_interval:'monthly',date_from:form.dataset.defaultFrom,date_to:form.dataset.defaultTo};
 const read=()=>({range:'all',position:node('position').value,trend_interval:node('period').value,date_from:node('from').value,date_to:node('to').value});
 function valid(){
  const scope=read(),limit=new Date(scope.date_from+'T00:00:00Z');limit.setUTCFullYear(limit.getUTCFullYear()+5);
  let error=!form.checkValidity()||(!scope.date_from!==!scope.date_to)?'Choose both From and To, or clear both for All dates.':scope.date_from>scope.date_to?'From must be on or before To.':new Date(scope.date_to+'T00:00:00Z')>=limit?'Choose coverage of up to five years.':'';
  node('filter-error').textContent=error;node('filter-error').hidden=!error;return !error;
 }
 function preview(){
  if(!valid())return false;const scope=read(),days=Math.round((Date.parse(scope.date_to+'T00:00:00Z')-Date.parse(scope.date_from+'T00:00:00Z'))/86400000)+1;
  node('context').textContent='Coverage: '+window.cifDashboardCoverageLabel(scope)+(scope.date_from?' · '+days+' calendar days':'')+' · '+({daily:'Calendar days',weekly:'Weeks run Monday–Sunday',monthly:'Calendar months',yearly:'Calendar years'}[scope.trend_interval])+(scope.date_from?'. Both dates included; filters update automatically.':'. Filters update automatically.');return true;
 }
 function reset(){node('position').value=defaults.position;node('period').value=defaults.trend_interval;node('from').value=defaults.date_from;node('to').value=defaults.date_to;preview();}
 const apply=()=>{if(preview())changed();};form.onsubmit=event=>event.preventDefault();form.addEventListener('change',apply);
 form.querySelectorAll('[data-dashboard-coverage]').forEach(button=>button.onclick=()=>{
  const today=new Date(form.dataset.today+'T00:00:00Z'),from=new Date(today),to=new Date(today),kind=button.dataset.dashboardCoverage;
  if(kind==='all'){node('from').value='';node('to').value='';apply();return;}
  if(kind==='week'){from.setUTCDate(from.getUTCDate()-(from.getUTCDay()+6)%7);to.setTime(from.getTime());to.setUTCDate(to.getUTCDate()+6);node('period').value='weekly';}
  else if(kind==='month'){from.setUTCDate(1);to.setUTCMonth(to.getUTCMonth()+1,0);node('period').value='monthly';}
  else if(kind==='year'){from.setUTCMonth(0,1);to.setUTCMonth(11,31);node('period').value='monthly';}
  else{from.setUTCDate(1);from.setUTCMonth(from.getUTCMonth()-11);to.setUTCMonth(to.getUTCMonth()+1,0);node('period').value='monthly';}
  node('from').value=from.toISOString().slice(0,10);node('to').value=to.toISOString().slice(0,10);apply();
 });
 node('filter-reset').onclick=()=>{reset();changed();};preview();
 window.cifDashboardFilters={read,valid,preview,reset,onChange(callback){changed=callback;}};
})();
