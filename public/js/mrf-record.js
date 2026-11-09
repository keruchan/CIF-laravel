(()=>{
 const query=selector=>document.querySelector(selector),wrap=query('.mrf-record-wrap');
 query('#mrf-print').onclick=()=>window.print();
 cifReady.catch(()=>{if(query('#mrf-manage-error')){query('#mrf-manage-error').textContent='Unable to check your session. Refresh this page before saving.';query('#mrf-manage-error').hidden=false;}});
 if(wrap.dataset.portal!=='admin')return;
 query('#mrf-funnel-refresh')?.addEventListener('click',()=>cifRefreshPage(query('#mrf-funnel-refresh')));
 const endpoint=new URL('../ajax/mrf.php',document.currentScript.src),form=query('#mrf-management-form'),dialog=query('#mrf-status-confirm');let busy=false,pending=null;
 window.cifAdminPage={
  show:()=>{query('#ttl').textContent='MRF requests';query('#sub').textContent='Review approvals and recruitment progress';document.querySelectorAll('.nv[data-v]').forEach(button=>{const active=button.dataset.v==='mrf';button.classList.toggle('on',active);if(active)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current');});cifSidebar.close();refreshSidebarBadges();},
  navigate:view=>{location.href=new URL('./#'+view,location.href).href;},
  onSignOut:()=>{pending=null;dialog.close();}
 };
 const message=(selector,text)=>{query(selector).textContent=text;query(selector).hidden=!text;};
 const assignmentForm=query('#mrf-assignment-form');
 function renderAssignment(assignment){
  if(!assignmentForm)return;assignmentForm.dataset.revision=assignment.revision;query('#mrf-assigned-hr').value=assignment.hr_admin_id||'';const history=query('#mrf-assignment-history-list');history.replaceChildren();
  assignment.history.forEach(entry=>{const row=document.createElement('div');row.className='mrf-history-entry';row.textContent=(entry.previous_name||'Unassigned')+' → '+(entry.name||'Unassigned')+' · '+entry.actor+'\n'+new Date(entry.created_at.replace(' ','T')+'Z').toLocaleString()+' · '+entry.source+'\n'+entry.reason;history.append(row);});if(!assignment.history.length)history.textContent='No HR assignment recorded yet.';
 }
 assignmentForm?.addEventListener('submit',async event=>{
  event.preventDefault();if(busy)return;busy=true;message('#mrf-assignment-error','');message('#mrf-assignment-feedback','');assignmentForm.querySelectorAll('button,input,select,textarea').forEach(element=>element.disabled=true);
  try{
   const state=await cifReady,response=await fetch(endpoint.href+'?portal=admin&action=assignment_update',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body:JSON.stringify({id:Number(wrap.dataset.mrfId),assignment_revision:Number(assignmentForm.dataset.revision),hr_admin_id:query('#mrf-assigned-hr').value?Number(query('#mrf-assigned-hr').value):null,reason:query('#mrf-assignment-reason').value})}),data=await response.json();if(!response.ok)throw new Error(data.error||'Unable to save assignment.');
   renderAssignment(data.record.hr_assignment);query('#mrf-assignment-reason').value='';message('#mrf-assignment-feedback',data.message);
  }catch(error){message('#mrf-assignment-error',error.message);}
  finally{busy=false;assignmentForm.querySelectorAll('button,input,select,textarea').forEach(element=>element.disabled=false);}
 });
 query('#mrf-record-approval')?.addEventListener('click',()=>{if(!busy)window.cifMrfApproval.open(wrap.dataset.mrfId,'Approved',()=>location.reload());});
 query('#mrf-record-refresh').onclick=()=>{if(!busy)cifRefreshPage(query('#mrf-record-refresh'));};
 query('#mrf-confirm-cancel').onclick=()=>{pending=null;dialog.close();};
 dialog.addEventListener('cancel',()=>{pending=null;});
 query('#mrf-confirm-save').onclick=()=>{dialog.close();if(pending){const input=pending;pending=null;save(input);}};
 form.onsubmit=event=>{
  event.preventDefault();if(busy)return;message('#mrf-manage-error','');message('#mrf-manage-success','');
  const input={id:Number(wrap.dataset.mrfId),revision:Number(wrap.dataset.revision),status:query('#mrf-manage-status').value,hr_notes:query('#mrf-hr-notes').value};
  const previousStatus=query('#mrf-record-status').textContent,reopening=['On Hold','Cancelled'].includes(previousStatus)&&input.status==='In Progress';
  if((['Rejected','On Hold','Cancelled'].includes(input.status)||reopening)&&!input.hr_notes.trim()){message('#mrf-manage-error',reopening?'Add a reason in HR notes before resuming or reinstating this MRF.':'Add a reason in HR notes before setting this request to '+input.status+'.');query('#mrf-hr-notes').focus();return;}
  if((['Rejected','Completed','On Hold','Cancelled'].includes(input.status)||reopening)&&input.status!==previousStatus){pending=input;query('#mrf-confirm-description').textContent=(reopening?(previousStatus==='Cancelled'?'Reinstate':'Resume')+' this MRF file? It will return to In Progress and become available for recruitment again. Management approval and the original hiring due date stay unchanged.':`Set this MRF file to ${input.status}?`)+` This changes only this headcount file, not its siblings, and is visible to the requestor.`+(['On Hold','Cancelled'].includes(input.status)?' It cannot be used for recruitment while this status applies. The original hiring due date stays unchanged.'+(input.status==='Cancelled'?' Use Reinstate to return it to In Progress later.':' Use Resume to return it to In Progress later.'):'');dialog.showModal();return;}
  save(input);
 };
 async function save(input){
  busy=true;form.querySelectorAll('button,input,select,textarea').forEach(element=>element.disabled=true);query('#mrf-manage-save').textContent='Saving…';
  try{
   const state=await cifReady,response=await fetch(endpoint.href+'?portal=admin&action=update',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body:JSON.stringify(input)}),data=await response.json();if(!response.ok)throw new Error(data.error||'Unable to save request.');
   wrap.dataset.revision=data.record.revision;const badge=query('#mrf-record-status');badge.textContent=data.record.status;badge.dataset.status=data.record.status;query('#mrf-hr-notes').value=data.record.hr_notes||'';query('#mrf-manage-status').value=data.record.status;
   const progress=query('#mrf-manage-status');progress.replaceChildren();
   for(const status of ['Pending','In Progress','Completed','Rejected','On Hold','Cancelled']){if(data.record.status==='Cancelled'&&!['Cancelled','In Progress'].includes(status))continue;if(['On Hold','Cancelled'].includes(status)&&(data.record.approval_status!=='Approved'||data.record.status==='Completed'))continue;const label=status==='In Progress'&&['On Hold','Cancelled'].includes(data.record.status)?status+(data.record.status==='Cancelled'?' — Reinstate':' — Resume'):status;progress.add(new Option(label,status,false,status===data.record.status));}
   const history=query('#mrf-history-list');history.replaceChildren();data.record.history.forEach(entry=>{const element=document.createElement('div');element.className='mrf-history-entry';const date=new Date(entry.created_at.replace(' ','T')+'Z').toLocaleString();element.textContent=`${entry.previous_status} → ${entry.status} · ${entry.admin_name}\n${date}\n${entry.hr_notes||''}`;history.append(element);});if(!data.record.history.length)history.textContent='No HR changes yet.';
   renderAssignment(data.record.hr_assignment);message('#mrf-manage-success',data.message);message('#mrf-manage-error','');
   refreshSidebarBadges();
  }catch(error){message('#mrf-manage-error',error.message);}
  finally{busy=false;form.querySelectorAll('button,input,select,textarea').forEach(element=>element.disabled=false);query('#mrf-manage-save').textContent='Save changes';}
 }
})();
