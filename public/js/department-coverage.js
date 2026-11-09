window.cifRequestorCoverage=prefix=>{
 const form=document.getElementById(prefix+'-coverage-form');if(!form)return null;
 const fields={date_from:'date-from',date_to:'date-to',deadline:'deadline'};
 const node=key=>document.getElementById(prefix+'-'+fields[key]);let changed=()=>{};
 const read=()=>({date_field:'submitted',...Object.fromEntries(Object.keys(fields).filter(key=>node(key)).map(key=>[key,node(key).value]))});
 function valid(){const f=read(),to=node('date_to');to.setCustomValidity((!f.date_from!==!f.date_to)?'Choose both From and To, or clear both.':f.date_from>f.date_to?'To must be on or after From.':'');return form.reportValidity();}
 function preview(){
  const f=read(),target=document.getElementById(prefix==='dashboard'?'dashboard-coverage':'mrf-coverage-preview');
  if(target)target.textContent=(f.date_from&&f.date_to?f.date_from+' to '+f.date_to:'All dates')+' | '+({submitted:'Date submitted',approved:'Date approved',due:'Hiring due date'}[f.date_field])+' | Both dates included, in Philippine time. Results update automatically.';
  form.querySelectorAll('[data-coverage]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.coverage==='all'&&!f.date_from&&!f.date_to)));
 }
 function set(values={}){for(const key of Object.keys(fields))if(node(key))node(key).value=values[key]||'';node('date_to').setCustomValidity('');preview();}
 const iso=d=>d.toISOString().slice(0,10);
 form.onsubmit=event=>event.preventDefault();
 form.addEventListener('change',()=>{if(valid()){preview();changed();}});
 form.querySelectorAll('[data-coverage]').forEach(button=>button.onclick=()=>{
  const today=new Date(new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date())+'T00:00:00Z');
  let from=new Date(today),to=new Date(today);const kind=button.dataset.coverage;
  if(kind==='week'){from.setUTCDate(from.getUTCDate()-(from.getUTCDay()+6)%7);to=new Date(from);to.setUTCDate(to.getUTCDate()+6);}
  if(kind==='month'){from.setUTCDate(1);to=new Date(Date.UTC(today.getUTCFullYear(),today.getUTCMonth()+1,0));}
  if(kind==='year'){from=new Date(Date.UTC(today.getUTCFullYear(),0,1));to=new Date(Date.UTC(today.getUTCFullYear(),11,31));}
  if(kind==='last12'){from=new Date(Date.UTC(today.getUTCFullYear(),today.getUTCMonth()-11,1));to=new Date(Date.UTC(today.getUTCFullYear(),today.getUTCMonth()+1,0));}
  set({...read(),date_from:kind==='all'?'':iso(from),date_to:kind==='all'?'':iso(to)});
  form.querySelectorAll('[data-coverage]').forEach(shortcut=>shortcut.setAttribute('aria-pressed',String(shortcut===button)));changed();
 });
 document.getElementById(prefix+'-coverage-reset').onclick=()=>{set();changed();};
 preview();return {read,set,valid,onChange(callback){changed=callback;}};
};
