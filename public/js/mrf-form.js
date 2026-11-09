(()=>{
 const query=selector=>document.querySelector(selector),form=query('#mrf-request-form'),endpoint=new URL('../ajax/mrf.php',document.currentScript.src);
 const submissionKey=Array.from(crypto.getRandomValues(new Uint8Array(32)),byte=>byte.toString(16).padStart(2,'0')).join('');
 let submitting=false,submitted=false,printAfterComplete=false;
 let initialValues=null,allowLeave=false,resolveLeave=null;
 const leaveDialog=query('#mrf-leave-confirm'),submitDialog=query('#mrf-submit-confirm'),values=()=>JSON.stringify(Object.fromEntries(new FormData(form).entries()));
 const hasChanges=()=>initialValues!==null&&values()!==initialValues&&!submitted;
 function resetChanges(){
  form.reset();form.querySelectorAll('input,textarea').forEach(field=>field.setCustomValidity(''));
  if(initialValues){for(const [name,value] of Object.entries(JSON.parse(initialValues)))form.elements[name].value=value;}
  conditional();
 }
 function finishLeave(confirmed){const resolve=resolveLeave;resolveLeave=null;leaveDialog.close();if(resolve)resolve(confirmed);}
 query('#mrf-leave-stay').onclick=()=>finishLeave(false);
 query('#mrf-leave-discard').onclick=()=>finishLeave(true);
 leaveDialog.addEventListener('cancel',event=>{event.preventDefault();finishLeave(false);});
 window.cifMrfForm={
  confirmLeave:()=>{
   if(submitting){message('Your request is being submitted. Please wait before leaving this page.');return Promise.resolve(false);}
   if(!hasChanges())return Promise.resolve(true);
   if(resolveLeave)return Promise.resolve(false);
   cifSidebar.close();return new Promise(resolve=>{resolveLeave=resolve;leaveDialog.showModal();});
  },
  allowNavigation:()=>{allowLeave=true;},
  discard:()=>{resetChanges();allowLeave=false;}
 };
 const message=text=>{query('#mrf-form-error').textContent=text;query('#mrf-form-error').hidden=!text;};
 function conditional(){
  const replacement=form.elements.reason.value==='Replacement',field=form.elements.replacement_of;query('#mrf-replacement-field').hidden=!replacement;field.disabled=!replacement;field.required=replacement;query('#mrf-replacement-field [data-optional-label]').hidden=replacement;field.setCustomValidity(replacement&&field.value!==''&&!field.value.trim()?'Please complete this field.':'');
  for(const index of [1,2]){
   const name=form.elements['buddy_'+index+'_name'],phone=form.elements['buddy_'+index+'_contact'],required=Boolean(name.value.trim()||phone.value.trim());
   name.required=phone.required=required;
   for(const input of [name,phone])input.closest('.mrf-field').querySelector('[data-optional-label]').hidden=required;
   name.setCustomValidity(required&&name.value!==''&&!name.value.trim()?'Enter the Ka-APEC buddy’s name.':'');
   phone.setCustomValidity(phone.value!==''&&!/^[1-9][0-9]{2}-[0-9]{3}-[0-9]{4}$/.test(phone.value)?'Enter the remaining 10 digits after the fixed +63 prefix.':'');
  }
 }
 function formatContactInput(field){
  const original=field.value,caret=field.selectionStart??original.length;let digits=original.replace(/\D/g,''),removed=0;
  if(digits.startsWith('63')&&(original.trim().startsWith('+63')||digits.length===12)){digits=digits.slice(2);removed=2;}else if(digits.startsWith('0')){digits=digits.slice(1);removed=1;}
  digits=digits.slice(0,10);field.value=digits.slice(0,3)+(digits.length>3?'-'+digits.slice(3,6):'')+(digits.length>6?'-'+digits.slice(6):'');
  const count=Math.max(0,original.slice(0,caret).replace(/\D/g,'').length-removed),position=Math.min(field.value.length,count+(count>3?1:0)+(count>6?1:0));field.setSelectionRange(position,position);
 }
 form.querySelectorAll('[data-contact-number]').forEach(field=>{
  field.addEventListener('input',()=>formatContactInput(field));
  field.addEventListener('keydown',event=>{const start=field.selectionStart,end=field.selectionEnd;if(start!==end)return;if(event.key==='Backspace'&&start>0&&field.value[start-1]==='-')field.setSelectionRange(start-2,start);if(event.key==='Delete'&&field.value[start]==='-')field.setSelectionRange(start,start+2);});
 });
 form.elements.reason.addEventListener('change',conditional);
 form.addEventListener('input',event=>{const field=event.target;if(field.matches('input[type="text"],textarea')&&field.required)field.setCustomValidity(field.value.trim()?'':'Please complete this field.');conditional();});
 form.addEventListener('change',conditional);
 conditional();
 function accountFields(account){
  const previous=initialValues?JSON.parse(initialValues):null;
  for(const [key,value] of Object.entries({requestor_name:account.name,requestor_position:account.position,department_name:account.department_name,group_name:account.group_name})){
   form.elements[key].value=value||'';if(previous)previous[key]=value||'';
  }
  if(previous)initialValues=JSON.stringify(previous);
  const configured=[account.name,account.position,account.department_name].every(value=>typeof value==='string'&&value.trim());
  query('#mrf-submit').disabled=query('#mrf-submit-print').disabled=!configured;
  if(!configured)message('Your requestor account needs a name, position and department. Contact HR to configure your account before submitting.');
 }
 window.cifMrfForm.updateAccount=accountFields;
 cifReady.then(state=>{if(!state.requestor)throw new Error('Your requestor session has expired. Sign in again to continue.');accountFields(state.requestor);initialValues=values();}).catch(error=>{message(error.message);query('#mrf-submit').disabled=query('#mrf-submit-print').disabled=true;});
 query('#mrf-submit-cancel').onclick=()=>submitDialog.close();
 query('#mrf-submit-confirm-send').onclick=()=>{if(submitting||submitted)return;submitDialog.close();conditional();if(form.reportValidity())send();};
 form.onsubmit=event=>{
  event.preventDefault();if(submitting||submitted)return;conditional();if(!form.reportValidity())return;
  printAfterComplete=event.submitter?.dataset.print==='true';query('#mrf-submit-confirm-send').textContent=printAfterComplete?'Complete & print':'Complete request';
  message('');query('#mrf-submit-summary').textContent=form.elements.position_title.value+' · '+form.elements.headcount.value+' headcount(s) · '+form.elements.department_name.value;submitDialog.showModal();
 };
 async function send(){
  submitting=true;query('#mrf-submit').disabled=query('#mrf-submit-print').disabled=true;query('#mrf-submit').textContent='Completing…';message('');
  try{
   const payload=Object.fromEntries(new FormData(form).entries());form.querySelectorAll('[data-contact-number]').forEach(field=>{payload[field.name]=field.value?'+63-'+field.value:'';});
   const state=await cifReady,response=await fetch(endpoint.href+'?portal=requestor&action=submit',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body:JSON.stringify({form:payload,submission_key:submissionKey})}),data=await response.json();
   if(!response.ok)throw new Error(data.error||'Unable to submit request.');
   submitted=true;submitting=false;location.replace(new URL('mrf.php?id='+data.record.id+'&submitted=1'+(printAfterComplete?'&print=1':''),location.href).href);
  }catch(error){message(error.message);}
  finally{if(!submitted){submitting=false;query('#mrf-submit').disabled=query('#mrf-submit-print').disabled=false;query('#mrf-submit').textContent='Complete request';}}
 }
 window.addEventListener('beforeunload',event=>{if(!allowLeave&&!submitted&&(submitting||hasChanges())){event.preventDefault();event.returnValue='';}});
 // Clear values only after departure really happens, never on a canceled warning.
 window.addEventListener('pagehide',resetChanges);
 window.addEventListener('pageshow',event=>{if(event.persisted){allowLeave=true;location.reload();}});
})();
