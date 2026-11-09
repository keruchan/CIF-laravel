(()=>{
 const dialog=document.querySelector('#mrf-approval-dialog');if(!dialog)return;
 const el=id=>document.getElementById(id),endpoint=new URL('../ajax/mrf.php',document.currentScript.src);let record=null,callback=null,busy=false,loading=false;
 async function api(action,input){const state=await cifReady,response=await fetch(endpoint.href+'?portal=admin&action='+action,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body:JSON.stringify(input)}),data=await response.json();if(!response.ok)throw new Error(data.error||'Unable to record decision.');return data;}
 function today(){const parts=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date()),get=type=>parts.find(part=>part.type===type).value;return get('year')+'-'+get('month')+'-'+get('day');}
 function dueDate(){
  const value=el('mrf-approval-date').value,days=Number(el('mrf-approval-category').selectedOptions[0]?.dataset.days),date=new Date(value+'T00:00:00Z');
  if(!value||!days||Number.isNaN(date.getTime())||date.toISOString().slice(0,10)!==value){el('mrf-approval-due').textContent='Select an approval date and category to see the hiring due date.';return;}
  date.setUTCDate(date.getUTCDate()+days);el('mrf-approval-due').textContent='Hiring due: '+date.toLocaleDateString('en-US',{timeZone:'UTC',month:'short',day:'numeric',year:'numeric'})+' · '+days+' calendar days';
 }
 function conditions(){const approved=el('mrf-approval-decision').value==='Approved';el('mrf-approval-save').classList.toggle('action-danger',!approved);el('mrf-approval-verified').closest('label').hidden=!approved;el('mrf-approval-verified').required=approved;el('mrf-approval-notes').required=!approved;el('mrf-approval-timing').hidden=!approved;el('mrf-approval-timing').disabled=!approved;el('mrf-approval-date').required=el('mrf-approval-category').required=approved;dueDate();}
 el('mrf-approval-date').oninput=el('mrf-approval-category').onchange=dueDate;
 function close(){if(!busy)dialog.close();}
 el('mrf-approval-close').onclick=el('mrf-approval-cancel').onclick=close;dialog.addEventListener('cancel',event=>{if(busy)event.preventDefault();});el('mrf-approval-decision').onchange=conditions;
 window.cifMrfApproval={open:async(id,decision='Approved',onSaved=null)=>{
  if(busy||loading)return;loading=true;
  try{const [result,options]=await Promise.all([api('detail',{id:Number(id)}),api('hr_options',{})]);record=result.record;if(record.approval_status!=='Pending')throw new Error('This request is already '+record.approval_status+'. Its approval decision is final. Refresh the list to see available actions.');callback=onSaved;el('mrf-approval-form').reset();el('mrf-approval-hr').replaceChildren(new Option('Assign later / automatic',''),...options.staff.map(person=>new Option(person.name,person.id)));el('mrf-approval-date').max=el('mrf-approval-date').value=today();el('mrf-approval-decision').value=decision;el('mrf-approval-notes').value=record.approval_notes||'';el('mrf-approval-error').hidden=true;el('mrf-approval-summary').textContent='Request '+record.batch_number+' · '+record.headcount+' headcount(s) · Awaiting approval. This is a final, one-time decision.';conditions();dialog.showModal();el('mrf-approval-decision').focus();}
  catch(error){window.alert(error.message);}finally{loading=false;}
 }};
 el('mrf-approval-form').onsubmit=async event=>{
  event.preventDefault();if(busy||!record||!event.target.reportValidity())return;if(el('mrf-approval-decision').value==='Denied'&&!el('mrf-approval-notes').value.trim()){el('mrf-approval-error').textContent='Provide the reason for denial.';el('mrf-approval-error').hidden=false;return;}
  busy=true;dialog.querySelectorAll('button,input,select,textarea').forEach(element=>element.disabled=true);el('mrf-approval-save').textContent='Recording…';
  try{const result=await api('approval_update',{id:Number(record.id),approval_revision:Number(record.approval_revision),decision:el('mrf-approval-decision').value,notes:el('mrf-approval-notes').value,confirm_document:el('mrf-approval-verified').checked,...(el('mrf-approval-decision').value==='Approved'?{date_approved:el('mrf-approval-date').value,position_category:el('mrf-approval-category').value,hr_admin_id:el('mrf-approval-hr').value?Number(el('mrf-approval-hr').value):null}:{})});dialog.close();if(callback)await callback(result);}
  catch(error){el('mrf-approval-error').textContent=error.message;el('mrf-approval-error').hidden=false;}
  finally{busy=false;dialog.querySelectorAll('button,input,select,textarea').forEach(element=>element.disabled=false);conditions();el('mrf-approval-save').textContent='Confirm final decision';}
 };
})();
