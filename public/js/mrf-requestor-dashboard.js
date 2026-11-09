(()=>{
 const root=document.querySelector('#dashboard');if(!root)return;
 const endpoint=new URL('../ajax/mrf.php?portal=requestor&action=dashboard',document.currentScript.src);
 const query=id=>root.querySelector('#dashboard-'+id);let generation=0,displayedFilters={};
 const coverage=window.cifRequestorCoverage('dashboard');
 const open=filters=>window.cifMrf.openFiltered({...displayedFilters,...filters});
 const element=(tag,text,className)=>{const node=document.createElement(tag);node.textContent=text;if(className)node.className=className;return node;};
 const date=value=>new Date(value+'T00:00:00Z').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric',timeZone:'UTC'});
 const colors={Pending:'#147dbb',Approved:'#00a6a6',Denied:'#b32d3a','In Progress':'#147dbb',Completed:'#00a6a6',Rejected:'#b32d3a','On Hold':'#8b6400',Cancelled:'#6b7894'};
 function statCard(label,count,color,action,help){
  const card=element('button','','card stat');card.type='button';card.style.setProperty('--c',color);card.onclick=action;
  card.append(element('b',count),element('span',label));if(help)card.append(element('small',help));return card;
 }
 function breakdown(id,values){
  const target=query(id);target.replaceChildren();
  const total=Object.values(values).reduce((sum,count)=>sum+Number(count),0);
  for(const [name,count] of Object.entries(values)){
   const label=name==='Pending'&&id==='approval'?'Awaiting approval':name;
   const row=element('button','','requestor-chart-row');row.type='button';row.onclick=()=>open(id==='approval'?{approval:name}:{status:name});
   const caption=element('span','','requestor-chart-caption');caption.append(element('span',label),element('strong',count));
   const track=element('span','','requestor-chart-track'),bar=element('span','','requestor-chart-bar');
   bar.style.width=(total?Number(count)/total*100:0)+'%';bar.style.backgroundColor=colors[name]||'#082e82';track.setAttribute('aria-hidden','true');track.append(bar);
   row.append(caption,track);target.append(row);
  }
 }
 function records(id,rows,empty){
  const target=query(id);target.replaceChildren();
  if(!rows.length){target.append(element('p',empty,'requestor-empty'));return;}
  for(const row of rows){
   const link=element('a','','requestor-record');link.href='mrf.php?id='+encodeURIComponent(row.id);
   const detail=element('div','');detail.append(element('strong',row.mrf_number+' · '+row.position_title),element('small',row.site_location));
   const meta=element('div','','requestor-record-meta');
   meta.append(element('span',id==='deadlines'?'Due '+date(row.hiring_due_date):date(row.created_at.slice(0,10))),element('small',id==='deadlines'?row.status:(row.approval_status==='Pending'?'Awaiting approval':row.approval_status)+' · '+row.status));
   link.append(detail,meta);target.append(link);
  }
 }
 async function load(){
  if(!coverage.valid())return;const filters=coverage.read();
  const current=++generation;query('refresh').disabled=true;query('error').hidden=true;query('loading').hidden=false;query('content').hidden=true;
  try{
   const state=await cifReady,response=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body:JSON.stringify(filters)}),data=await response.json();
   if(!response.ok)throw new Error(data.error||'Unable to load the dashboard.');if(current!==generation)return;
   displayedFilters={...filters};query('coverage').textContent=(filters.date_from?date(filters.date_from)+' | '+date(filters.date_to):'All dates')+' | '+({submitted:'Date submitted',approved:'Date approved',due:'Hiring due date'}[filters.date_field])+' | Click a card or status to view matching requests.';
   const metrics=query('metrics');metrics.replaceChildren();
   for(const [label,value,help,color,filters] of [['Total requests',data.total,'Submitted by this account','#082e82',{}],['Headcount requested',data.headcount,'Within selected coverage','#147dbb',{}],['All outstanding',data.outstanding,'All hiring deadlines still open','#00a6a6',{deadline:'outstanding'}],['Overdue requests',data.overdue,'Approved and unfinished','#b32d3a',{deadline:'overdue'}],['Due within 7 days',data.upcoming,'Includes today','#8b6400',{deadline:'upcoming'}]]){
    metrics.append(statCard(label,value,color,()=>open(filters),help));
   }
   breakdown('approval',data.approval,data.total);breakdown('progress',data.progress,data.total);
   records('deadlines',data.deadlines,'No outstanding hiring deadlines.');records('recent',data.recent,'No requests submitted yet. Create a new request to get started.');query('content').hidden=false;
  }catch(error){if(current===generation){query('error').textContent=error.message;query('error').hidden=false;}}
  finally{if(current===generation){query('refresh').disabled=false;query('loading').hidden=true;}}
 }
 coverage.onChange(load);
 query('open-deadlines').onclick=()=>open({deadline:'outstanding'});
 query('open-recent').onclick=()=>open({});
 query('refresh').onclick=load;
 window.cifRequestorDashboard={load,reset(){generation++;query('content').hidden=true;query('metrics').replaceChildren();query('recent').replaceChildren();query('deadlines').replaceChildren();}};
})();
