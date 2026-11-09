(()=>{
 const el=id=>document.getElementById(id),endpoint=new URL('../ajax/mrf-requestors.php',document.currentScript.src);
 let page=1,request=0,timer,archived=false,working=false;
 const dialog=el('requestor-register-dialog'),form=el('requestor-register-form');
 const editDialog=el('requestor-edit-dialog'),editForm=el('requestor-edit-form');let editing=null;
 const editFields={username:'username',name:'name',position:'position',department_name:'department',group_name:'group'};
 function resetPasswordFields(){
  const reset=el('requestor-edit-reset').checked,fields=el('requestor-edit-password-fields');fields.hidden=!reset;fields.disabled=!reset;
  if(!reset){el('requestor-edit-new-password').value='';el('requestor-edit-confirm-password').value='';}
  el('requestor-edit-confirm-password').setCustomValidity('');
 }
 function clearEdit(){editing=null;editForm.reset();el('requestor-edit-error').hidden=true;resetPasswordFields();}
 function openEdit(account){
  if(working)return;clearEdit();editing={id:Number(account.id),revision:Number(account.revision)};
  for(const [key,suffix]of Object.entries(editFields)){const field=el('requestor-edit-'+suffix);field.value=account[key]||'';field.required=key==='name'||key==='username'||Boolean(field.value.trim());}
  editDialog.showModal();el('requestor-edit-name').focus();
 }
 async function api(input){const session=await cifReady,response=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':session.csrf},body:JSON.stringify(input)}),data=await response.json();if(!response.ok)throw new Error(data.error||'Unable to manage requestors.');return data;}
 async function manage(account,action){
  if(working)return;const label={suspend:'Suspend',reactivate:'Reactivate',archive:'Archive',restore:'Restore'}[action];
  if(!window.confirm(label+' '+account.name+'? '+(['suspend','archive'].includes(action)?'This blocks sign-in and existing sessions. Past MRFs will be kept.':action==='restore'?'Their previous Active/Suspended status will be kept.':'They will be able to sign in again.')))return;
  working=true;el('requestors-rows').querySelectorAll('button').forEach(button=>button.disabled=true);
  try{const result=await api({action,id:Number(account.id),revision:Number(account.revision)});window.toast?.(result.message);await load();}catch(error){el('requestors-error').textContent=error.message;el('requestors-error').hidden=false;}finally{working=false;el('requestors-rows').querySelectorAll('button').forEach(button=>button.disabled=false);}
 }
 async function load(){
  const current=++request;el('requestors-rows').setAttribute('aria-busy','true');el('requestors-error').hidden=true;el('requestors-info').textContent='Loading requestors…';
  try{
   const data=await api({action:'list',search:el('requestors-search').value,page,archived});if(current!==request)return;page=data.page;
   const rows=el('requestors-rows');rows.replaceChildren();
   for(const account of data.requestors){
    const row=document.createElement('tr');row.dataset.requestorId=account.id;
    for(const key of ['name','username','department_name','group_name']){const cell=document.createElement('td');cell.textContent=account[key]||'Not provided';if(key==='name'){const position=document.createElement('small');position.textContent=account.position||'Not provided';cell.append(position);}row.append(cell);}
    const status=document.createElement('td'),badge=document.createElement('span');badge.className='requestor-account-status';badge.dataset.status=badge.textContent=account.archived_at?'Archived':account.account_status;status.append(badge);row.append(status);
    const actions=document.createElement('td');actions.className='ac';
    const edit=document.createElement('button');edit.type='button';edit.dataset.requestorAction='edit';edit.textContent='Edit';edit.onclick=()=>openEdit(account);actions.append(edit);
    for(const action of account.archived_at?['restore']:[account.account_status==='Active'?'suspend':'reactivate','archive']){const button=document.createElement('button');button.type='button';button.dataset.requestorAction=action;button.classList.toggle('action-danger',action==='suspend');button.classList.toggle('action-muted',action==='archive');button.textContent={suspend:'Suspend',reactivate:'Reactivate',archive:'Archive',restore:'Restore'}[action];button.onclick=()=>manage(account,action);actions.append(button);}row.append(actions);rows.append(row);
   }
   if(!data.requestors.length){const row=document.createElement('tr'),cell=document.createElement('td');cell.colSpan=6;cell.className='empty';cell.textContent='No requestors match your search.';row.append(cell);rows.append(row);}
   el('requestors-info').textContent=data.total?`Showing ${(page-1)*8+1}–${Math.min(page*8,data.total)} of ${data.total}`:'0 results';
   const pager=el('requestors-pages');pager.replaceChildren();
   const add=(label,target,disabled)=>{const button=document.createElement('button');button.type='button';button.textContent=label;button.disabled=disabled;button.onclick=()=>{page=target;load();};pager.append(button);};
   add('Previous',page-1,page===1);add('Next',page+1,page===data.pages);
  }catch(error){if(current===request){el('requestors-error').textContent=error.message;el('requestors-error').hidden=false;el('requestors-info').textContent='Unable to load requestors.';el('requestors-rows').replaceChildren();el('requestors-pages').replaceChildren();}}
  finally{if(current===request)el('requestors-rows').removeAttribute('aria-busy');}
 }
 el('requestors-search').oninput=()=>{request++;clearTimeout(timer);timer=setTimeout(()=>{page=1;load();},250);};
 el('requestors-refresh').onclick=()=>{clearTimeout(timer);load();};
 function accountView(value){archived=value;page=1;clearTimeout(timer);el('requestors-list-title').textContent=archived?'Archived requestors':'Current requestors';el('requestors-current').setAttribute('aria-pressed',String(!archived));el('requestors-archived').setAttribute('aria-pressed',String(archived));load();}
 el('requestors-current').onclick=()=>accountView(false);el('requestors-archived').onclick=()=>accountView(true);
 function clearRegistration(){form.reset();form.hidden=false;el('requestor-register-result').hidden=true;el('requestor-register-error').hidden=true;el('requestor-created-username').value='';el('requestor-created-password').value='';el('requestor-register-message').textContent='';}
 el('requestors-register').onclick=()=>{if(working)return;clearRegistration();dialog.showModal();};
 el('requestor-register-close').onclick=()=>{if(!working)dialog.close();};dialog.addEventListener('cancel',event=>{if(working)event.preventDefault();});dialog.addEventListener('close',clearRegistration);
 form.onsubmit=async event=>{
  event.preventDefault();if(working||!form.reportValidity())return;working=true;el('requestor-register-error').hidden=true;form.querySelectorAll('button,input').forEach(field=>field.disabled=true);
  try{
   const result=await api({action:'create',email:el('requestor-email').value,name:el('requestor-name').value,position:el('requestor-position').value,department_name:el('requestor-department').value,group_name:el('requestor-group').value});
   form.hidden=true;el('requestor-register-result').hidden=false;el('requestor-created-username').value=result.username;el('requestor-created-password').value=result.default_password;el('requestor-register-message').textContent=result.message;el('requestors-search').value='';accountView(false);
  }catch(error){el('requestor-register-error').textContent=error.message;el('requestor-register-error').hidden=false;}
  finally{working=false;form.querySelectorAll('button,input').forEach(field=>field.disabled=false);}
 };
 el('requestor-edit-reset').onchange=resetPasswordFields;
 el('requestor-edit-confirm-password').oninput=()=>el('requestor-edit-confirm-password').setCustomValidity('');
 el('requestor-edit-new-password').oninput=()=>el('requestor-edit-confirm-password').setCustomValidity('');
 for(const id of ['requestor-edit-close','requestor-edit-cancel'])el(id).onclick=()=>{if(!working)editDialog.close();};
 editDialog.addEventListener('cancel',event=>{if(working)event.preventDefault();});editDialog.addEventListener('close',clearEdit);
 editForm.onsubmit=async event=>{
  event.preventDefault();if(working||!editing)return;
  const reset=el('requestor-edit-reset').checked,confirmation=el('requestor-edit-confirm-password');
  confirmation.setCustomValidity(reset&&confirmation.value!==el('requestor-edit-new-password').value?'The new passwords do not match.':'');
  if(!editForm.reportValidity())return;
  const input={action:'edit',...editing,reset_password:reset,admin_password:el('requestor-edit-admin-password').value,new_password:reset?el('requestor-edit-new-password').value:'',confirm_password:reset?confirmation.value:''};
  for(const [key,suffix]of Object.entries(editFields))input[key]=el('requestor-edit-'+suffix).value;
  working=true;el('requestor-edit-error').hidden=true;editDialog.querySelectorAll('button,input').forEach(field=>field.disabled=true);
  try{const result=await api(input);editDialog.close();window.toast?.(result.message);await load();}
  catch(error){el('requestor-edit-error').textContent=error.message;el('requestor-edit-error').hidden=false;}
  finally{working=false;el('requestor-edit-admin-password').value='';editDialog.querySelectorAll('button,input').forEach(field=>field.disabled=false);resetPasswordFields();}
 };
 function resetFilters(){request++;clearTimeout(timer);page=1;archived=false;el('requestors-search').value='';el('requestors-list-title').textContent='Current requestors';el('requestors-current').setAttribute('aria-pressed','true');el('requestors-archived').setAttribute('aria-pressed','false');}
 window.cifMrfRequestors={load,resetFilters,reset:()=>{resetFilters();dialog.close();clearRegistration();editDialog.close();clearEdit();}};
})();
