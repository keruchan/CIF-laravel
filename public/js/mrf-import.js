(()=>{
 const query=selector=>document.querySelector(selector),dialog=query('#mrf-upload-dialog');if(!dialog)return;
 const endpoint=new URL('../ajax/mrf-import.php',document.currentScript.src),fileForm=query('#mrf-upload-file-form'),review=query('#mrf-upload-review'),form=query('#mrf-import-request-form');
 let busy=false,token=null,lastField=null,numberConflict=null;
 const message=(selector,text)=>{query(selector).textContent=text;query(selector).hidden=!text;};
 function working(value){busy=value;dialog.setAttribute('aria-busy',String(value));dialog.querySelectorAll('button').forEach(button=>button.disabled=value);query('#mrf-upload-file').disabled=value;query('#mrf-upload-recognize').textContent=value?'Recognizing…':'Recognize fields';query('#mrf-import-submit').textContent=value?'Saving…':'Save uploaded request';}
 async function request(action,input,multipart=false){
  const state=await cifReady,headers={'X-CSRF-Token':state.csrf};if(!multipart)headers['Content-Type']='application/json';
  const response=await fetch(endpoint.href+'?action='+action,{method:'POST',credentials:'same-origin',headers,body:multipart?input:JSON.stringify(input)}),data=await response.json();
  if(!response.ok){const error=new Error(data.error||'Unable to process this upload.');error.existingId=data.existing_id;error.numberConflict=data.number_conflict;throw error;}return data;
 }
 function showNumberConflict(conflict){numberConflict=conflict||null;query('#mrf-upload-confirm-new').checked=false;query('#mrf-upload-number-conflict').hidden=!numberConflict;if(numberConflict){query('#mrf-upload-number-conflict-text').textContent='MRF '+numberConflict.source_number+' is already taken. Check the existing MRF before continuing. If this is a different request, a new unique MRF number will be generated.';query('#mrf-upload-number-conflict-link').href=new URL('mrf.php?id='+numberConflict.id,location.href).href;}}
 function reset(){token=null;lastField=null;numberConflict=null;query('#mrf-upload-number-conflict').hidden=true;query('#mrf-upload-confirm-new').checked=false;fileForm.reset();form.reset();fileForm.hidden=false;review.hidden=true;query('#mrf-upload-existing').hidden=true;query('#mrf-upload-discard-confirm').hidden=true;message('#mrf-upload-error','');message('#mrf-import-form-error','');query('#mrf-upload-source').textContent='';query('#mrf-upload-warnings').replaceChildren();conditional();}
 function close(){if(busy)return;if(token){query('#mrf-upload-discard-confirm').hidden=false;query('#mrf-upload-keep').focus();}else{dialog.close();reset();}}
 query('#mrf-upload-open').onclick=()=>{reset();cifSidebar.close();dialog.showModal();};query('#mrf-upload-close').onclick=close;
 dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
 form.addEventListener('focusin',event=>{if(event.target.matches('input,select,textarea'))lastField=event.target;});
 query('#mrf-upload-keep').onclick=()=>{query('#mrf-upload-discard-confirm').hidden=true;(lastField||query('#mrf-import-owner')).focus();};
 query('#mrf-upload-discard').onclick=async()=>{if(busy)return;working(true);try{await request('discard',{token});token=null;dialog.close();reset();}catch(error){message('#mrf-upload-error',error.message);}finally{working(false);}};
 fileForm.onsubmit=async event=>{
  event.preventDefault();if(busy)return;const file=query('#mrf-upload-file').files[0];if(!file)return;
  if(file.size>2097152){message('#mrf-upload-error','Choose a file no larger than 2 MB.');return;}
  const body=new FormData();body.append('document',file);working(true);message('#mrf-upload-error','');query('#mrf-upload-existing').hidden=true;
  try{
   const result=await request('extract',body,true);token=result.token;
   showNumberConflict(result.number_conflict);
   for(const [key,value] of Object.entries(result.form)){const field=form.elements[key];if(!field)continue;field.value=field.hasAttribute('data-contact-number')?String(value).replace(/^\+63-?/,''):String(value);field.setCustomValidity('');}
   window.cifPositionPickers.refresh(form);
   const owner=query('#mrf-import-owner');owner.replaceChildren(new Option('Select MRF requestor',''));for(const account of result.accounts)owner.append(new Option(`${account.department_name||'Unconfigured department'} · ${account.group_name||'No group'} · ${account.name} (${account.username})`,account.id));owner.value=result.owner_id||'';
   query('#mrf-upload-summary').textContent=`${result.filename} · ${result.recognized.length} fields recognized`;
   const warnings=[...result.warnings];if(result.missing.length)warnings.unshift('Missing required information: '+result.missing.join(', ')+'.');
   query('#mrf-upload-warnings').replaceChildren(...warnings.map(text=>{const item=document.createElement('li');item.textContent=text;return item;}));
   query('#mrf-upload-source').textContent=result.text;fileForm.hidden=true;review.hidden=false;conditional();owner.focus();
  }catch(error){message('#mrf-upload-error',error.message);if(error.existingId){const link=query('#mrf-upload-existing');link.href=new URL('mrf.php?id='+error.existingId,location.href).href;link.hidden=false;}}
  finally{working(false);}
 };
 function conditional(){
  const replacement=form.elements.reason.value==='Replacement',field=form.elements.replacement_of;query('#mrf-import-replacement-field').hidden=!replacement;field.disabled=!replacement;field.required=replacement;query('#mrf-import-replacement-field [data-optional-label]').hidden=replacement;
  for(const index of [1,2]){const name=form.elements['buddy_'+index+'_name'],phone=form.elements['buddy_'+index+'_contact'],required=Boolean(name.value.trim()||phone.value.trim());name.required=phone.required=required;for(const input of [name,phone])input.closest('.mrf-field').querySelector('[data-optional-label]').hidden=required;}
  form.querySelectorAll('[name],#mrf-import-owner').forEach(field=>field.closest('.mrf-field')?.classList.toggle('mrf-import-missing',field.required&&!field.disabled&&!String(field.value).trim()));
 }
 form.addEventListener('input',event=>{
  const field=event.target;if(field.hasAttribute('data-contact-number')){let digits=field.value.replace(/\D/g,'');if(digits.startsWith('63')&&(field.value.startsWith('+63')||digits.length===12))digits=digits.slice(2);else if(digits.startsWith('0'))digits=digits.slice(1);digits=digits.slice(0,10);field.value=digits.slice(0,3)+(digits.length>3?'-'+digits.slice(3,6):'')+(digits.length>6?'-'+digits.slice(6):'');}
  if(field.matches('input[type="text"],textarea')&&field.required)field.setCustomValidity(field.value.trim()?'':'Please complete this field.');conditional();
 });form.addEventListener('change',conditional);
 form.onsubmit=async event=>{
  event.preventDefault();if(busy||!token)return;conditional();if(numberConflict&&!query('#mrf-upload-confirm-new').checked){message('#mrf-import-form-error','This MRF number already exists. Check the existing MRF and confirm this is a different request.');query('#mrf-upload-number-conflict').scrollIntoView({block:'center'});query('#mrf-upload-confirm-new').focus();return;}if(!form.reportValidity())return;
  const payload=Object.fromEntries(new FormData(form).entries());form.querySelectorAll('[data-contact-number]').forEach(field=>payload[field.name]=field.value?'+63-'+field.value:'');
  working(true);message('#mrf-import-form-error','');
  try{const result=await request('save',{token,owner_id:Number(query('#mrf-import-owner').value),form:payload,confirm_new_request:query('#mrf-upload-confirm-new').checked});token=null;dialog.close();reset();location.href=new URL('mrf.php?id='+result.record.id+'&imported=1',location.href).href;}
  catch(error){message('#mrf-import-form-error',error.message);if(error.numberConflict){showNumberConflict(error.numberConflict);query('#mrf-upload-number-conflict').scrollIntoView({block:'center'});}else if(error.existingId){message('#mrf-upload-error',error.message);const link=query('#mrf-upload-existing');link.href=new URL('mrf.php?id='+error.existingId,location.href).href;link.hidden=false;query('#mrf-upload-error').scrollIntoView({block:'nearest'});}else query('#mrf-import-form-error').scrollIntoView({block:'nearest'});}
  finally{working(false);}
 };
 window.addEventListener('beforeunload',event=>{if(token||busy){event.preventDefault();event.returnValue='';}});
 window.cifMrfImport={reset:()=>{if(!busy){dialog.close();reset();}},canLeave:()=>!busy};
 conditional();
})();
