const $=s=>document.querySelector(s),S=[...document.querySelectorAll('.step')],labels=S.map(section=>section.dataset.label);
let i=0,submitting=false,exiting=false,activeCandidate=null,formDirty=false;
let privacyAccepted=false,acceptingPrivacy=false,privacyReadToEnd=false;
let privacyChoices={data_privacy:false,background:false};
let separatePermanentAddress={};
let draftRevision=0,draftGeneration=0,savedDraftGeneration=0,draftTimer=null,savingDraft=null,draftConflict=false,draftActive=false,restoringDraft=false,lastSavedPhoto='',progressTimer=null;
function formatContactInput(field){
 const original=field.value,caret=field.selectionStart??original.length;
 let digits=original.replace(/\D/g,''),removed=0;
 if(digits.startsWith('63')&&(original.trim().startsWith('+63')||digits.length===12)){digits=digits.slice(2);removed=2;}
 else if(digits.startsWith('0')){digits=digits.slice(1);removed=1;}
 digits=digits.slice(0,10);
 field.value=digits.slice(0,3)+(digits.length>3?'-'+digits.slice(3,6):'')+(digits.length>6?'-'+digits.slice(6):'');
 const count=Math.max(0,original.slice(0,caret).replace(/\D/g,'').length-removed);
 const position=Math.min(field.value.length,count+(count>3?1:0)+(count>6?1:0));
 field.setSelectionRange(position,position);
}
$('#cf').querySelectorAll('[data-contact-number]').forEach(field=>{
 field.addEventListener('input',()=>formatContactInput(field));
 field.addEventListener('keydown',event=>{
  const start=field.selectionStart,end=field.selectionEnd;
  if(start!==end)return;
  if(event.key==='Backspace'&&start>0&&field.value[start-1]==='-')field.setSelectionRange(start-2,start);
  if(event.key==='Delete'&&field.value[start]==='-')field.setSelectionRange(start,start+2);
 });
});
const addressParts=['house_number','street','purok','barangay','municipality','province'];
const addressInput=(group,part)=>$('[name="'+group+'_'+part+'"]');
function copyPresentAddress(){
 addressParts.forEach(part=>{addressInput('permanent',part).value=addressInput('present',part).value;});
}
$('#same-address').addEventListener('change',()=>{
 const same=$('#same-address').checked;
 addressParts.forEach(part=>{
  const permanent=addressInput('permanent',part);
  if(same)separatePermanentAddress[part]=permanent.value;
  else permanent.value=separatePermanentAddress[part]||'';
  permanent.readOnly=same;
 });
 if(same)copyPresentAddress();
});
addressParts.forEach(part=>['input','change'].forEach(event=>addressInput('present',part).addEventListener(event,()=>{if($('#same-address').checked)copyPresentAddress();})));
$('#steps').innerHTML=labels.map((label,index)=>'<div class="st"><b>'+(index+1)+'</b><span>'+label+'</span></div>').join('');
$('#cd').oninput=event=>{
 const field=event.target,caret=field.selectionStart??field.value.length;
 const lettersBefore=field.value.slice(0,caret).replace(/[^a-z0-9]/gi,'').length;
 const raw=field.value.replace(/[^a-z0-9]/gi,'').toUpperCase().slice(0,8);
 field.value=raw.length>4?raw.slice(0,4)+'-'+raw.slice(4):raw;
 const position=Math.min(field.value.length,lettersBefore+(lettersBefore>4?1:0));
 field.setSelectionRange(position,position);
};
$('#cd').onblur=e=>{e.target.value=cifFormatInvitationCode(e.target.value);};
function formValues(){
 const form={};
 const phones=new Set([...$('#cf').querySelectorAll('[data-contact-number]')].map(field=>field.name));
 for(const [name,value] of new FormData($('#cf'))){const saved=phones.has(name)&&value?'+63-'+value:value;const match=name.match(/^(\w+)\[(\d+)\]\[(\w+)\]$/);if(match){const [,group,index,key]=match;form[group]??=[];form[group][Number(index)]??={};form[group][Number(index)][key]=saved;}else form[name]=saved;}
 form.consent=$('#ok').checked;form.background_consent=privacyChoices.background;
 form.data_privacy_consent=privacyChoices.data_privacy;
 form.same_as_present_address=$('#same-address').checked;
 form.separate_permanent_address=separatePermanentAddress;
 return form;
}
function hydrateForm(form){
 $('#cf').reset();
 separatePermanentAddress={};addressParts.forEach(part=>addressInput('permanent',part).readOnly=false);
 $('#cf').querySelectorAll('[name]').forEach(field=>{
  const match=field.name.match(/^(\w+)\[(\d+)\]\[(\w+)\]$/);
  const value=match?form[match[1]]?.[Number(match[2])]?.[match[3]]:form[field.name];
  if(typeof value==='string')field.value=value;
 });
 cifRefreshEducationYearRanges($('#cf'));
 $('#ok').checked=form.consent===true;
 $('#same-address').checked=form.same_as_present_address===true;
 addressParts.forEach(part=>{separatePermanentAddress[part]=form.separate_permanent_address?.[part]||addressInput('permanent',part).value;addressInput('permanent',part).readOnly=$('#same-address').checked;});
 if($('#same-address').checked)copyPresentAddress();
 $('#cf').querySelectorAll('[data-contact-number]').forEach(formatContactInput);
 window.cifPositionPickers.refresh($('#cf'));
 $('#cf').querySelectorAll('[data-photo-widget]').forEach(widget=>{
  const value=widget.querySelector('[data-photo-value]').value,preview=widget.querySelector('[data-photo-preview]'),remove=widget.querySelector('[data-photo-remove]');
  preview.hidden=!value;if(value)preview.src=value;else preview.removeAttribute('src');remove.hidden=!value;remove.disabled=!value;
 });
}
window.addEventListener('beforeunload',event=>{if(formDirty||window.cifPhotoPending||submitting){event.preventDefault();event.returnValue='';}});
function openCandidate(candidate){
 clearTimeout(draftTimer);clearInterval(progressTimer);activeCandidate=candidate;formDirty=false;draftActive=false;draftConflict=false;
 privacyAccepted=candidate.privacy_accepted===true;privacyChoices=candidate.privacy_choices||{data_privacy:false,background:false};refreshPrivacyStatus();
 $('#login').classList.add('hide');$('#app').classList.remove('hide');
 if(candidate.mode==='tracking'){showApplicationProgress(candidate.progress);return;}
 $('#app>.top').append($('.candidate-exit-actions'));
 window.cifCandidateTracking.reset();
 restoringDraft=true;draftRevision=candidate.draft.revision;draftGeneration=savedDraftGeneration=0;lastSavedPhoto=candidate.draft.form?.id_photo||'';
 hydrateForm({contact_email:candidate.email,...candidate.draft.form});
 $('#applicant-id').textContent='';
 $('#applied-position').textContent=candidate.position||'Not specified by HR';
 const position=$('[name="desired_position"]'),available=JSON.parse(position.dataset.positionOptions);position.value=available.includes(candidate.position)?candidate.position:position.value;position.readOnly=available.includes(candidate.position);window.cifPositionPickers.set(position,position.value);
 if(!position.readOnly)$('#applied-position').textContent='Choose an available applied position in Personal Information';
 $('#login').classList.add('hide');$('#app').classList.remove('hide');
 $('#fin').classList.add('hide');draftActive=candidate.draft.saved_at!==null;
 if(draftActive&&privacyAccepted)show(Math.min(S.length-1,Math.max(0,candidate.draft.step)));else showIntroduction();
 setDraftStatus(candidate.draft.saved_at?'Progress restored · Saved '+candidateDate(candidate.draft.saved_at):'Progress saves automatically as you type.');restoringDraft=false;
}
function showIntroduction(){
 $('#candidate-intro').classList.remove('hide');$('#cf').classList.add('hide');$('#steps').classList.add('hide');
 $('#intro-title').focus({preventScroll:true});scrollTo({top:0,behavior:'smooth'});
}
function refreshPrivacyStatus(){
 $('#start-cif').disabled=!privacyAccepted;
 $('#privacy-status').textContent=privacyAccepted?'Privacy consent and background authorization recorded. You can now start your CIF.':'Please read the notice and select both consent and authorization checkboxes before starting your CIF.';
 $('#privacy-status').classList.toggle('accepted',privacyAccepted);
}
function refreshPrivacyReadGate(){
 if(!$('#privacy-dialog').open)return;
 const body=$('.privacy-dialog-body');
 if(body.clientHeight>0&&body.scrollHeight-body.scrollTop-body.clientHeight<=4)privacyReadToEnd=true;
 $('#privacy-agree').disabled=!privacyReadToEnd||acceptingPrivacy;
 $('#privacy-background-agree').disabled=!privacyReadToEnd||acceptingPrivacy;
 $('#accept-privacy').disabled=!privacyReadToEnd||acceptingPrivacy;
 $('#privacy-read-hint').textContent=privacyReadToEnd?'You have reached the end. Choose your consent and authorization, then select Continue. Both are required to start your CIF.':'Scroll to the bottom of the notice to enable the checkboxes and Continue.';
}
$('.privacy-dialog-body').addEventListener('scroll',refreshPrivacyReadGate,{passive:true});
new ResizeObserver(refreshPrivacyReadGate).observe($('.privacy-dialog-body'));
$('#read-privacy').onclick=()=>{
 privacyReadToEnd=false;$('#privacy-agree').checked=privacyChoices.data_privacy;$('#privacy-background-agree').checked=privacyChoices.background;
 $('#privacy-agree').disabled=true;$('#privacy-background-agree').disabled=true;$('#accept-privacy').disabled=true;$('#privacy-error').hidden=true;
 $('#privacy-dialog').showModal();$('.privacy-dialog-body').scrollTop=0;$('#privacy-title').focus();
 refreshPrivacyReadGate();
};
$('#close-privacy').onclick=()=>$('#privacy-dialog').close();
$('#privacy-dialog').addEventListener('close',()=>$('#read-privacy').focus());
$('#accept-privacy').onclick=async()=>{
 if(acceptingPrivacy||!privacyReadToEnd)return;
 const agreed=$('#privacy-agree').checked;
 const background=$('#privacy-background-agree').checked;
 if(privacyChoices.data_privacy===agreed&&privacyChoices.background===background){$('#privacy-dialog').close();return;}
 acceptingPrivacy=true;$('#accept-privacy').disabled=true;$('#close-privacy').disabled=true;$('#privacy-agree').disabled=true;$('#privacy-background-agree').disabled=true;$('#privacy-error').hidden=true;
 try{
  const result=await cifRequest('candidate_privacy_accept',{accepted:agreed,background_authorized:background,version:$('#privacy-dialog').dataset.version,revision:draftRevision});
  draftRevision=result.revision;privacyChoices={data_privacy:agreed,background};privacyAccepted=agreed&&background;refreshPrivacyStatus();$('#privacy-dialog').close();
 }catch(error){$('#privacy-error').textContent=error.message;$('#privacy-error').hidden=false;}
 finally{acceptingPrivacy=false;$('#close-privacy').disabled=false;refreshPrivacyReadGate();}
};
$('#privacy-dialog').addEventListener('cancel',event=>{if(acceptingPrivacy)event.preventDefault();});
$('#start-cif').onclick=()=>{if(!privacyAccepted)return;show(0);markDraftChanged();const title=S[0].querySelector('h3');title.tabIndex=-1;title.focus({preventScroll:true});};
$('#lf').onsubmit=async e=>{
 e.preventDefault();const button=e.submitter||$('#lf button');button.disabled=true;
 try{const result=await cifRequest('verify',{email:$('#em').value,code:$('#cd').value});$('#lerr').classList.add('hide');openCandidate(result.candidate);}
 catch(error){$('#lerr').textContent=error.message;$('#lerr').classList.remove('hide');}
 finally{button.disabled=false;}
};
cifReady.then(state=>{if(state.candidate)openCandidate(state.candidate);}).catch(error=>{$('#lerr').textContent=error.message+' Please refresh the page to retry.';$('#lerr').classList.remove('hide');}).finally(cifFinishSessionCheck);
function show(n){
 if(!privacyAccepted){showIntroduction();return;}
 const previousStep=i;
 $('#candidate-intro').classList.add('hide');$('#cf').classList.remove('hide');$('#steps').classList.remove('hide');
 i=n;S.forEach((section,index)=>section.classList.toggle('hide',index!==n));
 document.querySelectorAll('.st').forEach((step,index)=>{step.classList.toggle('on',index===n);step.classList.toggle('done',index<n);});
 $('#form-error').classList.add('hide');$('#nx').textContent=n===S.length-1?'Submit form':'Next';refreshSubmitButton();scrollTo({top:0,behavior:'smooth'});
 if(n!==previousStep)markDraftChanged();
}
function candidateDate(value){return new Date(value.replace(' ','T')+'Z').toLocaleString('en-US',{timeZone:'Asia/Shanghai',month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'});}
function setDraftStatus(text,retry=false){$('#draft-save-status').textContent=text;$('#draft-save-retry').hidden=!retry;$('#draft-save-retry').textContent=draftConflict?'Reload saved progress':'Retry save';}
function markDraftChanged(){
 if(restoringDraft||submitting||!privacyAccepted||activeCandidate?.mode!=='form')return;draftActive=true;draftGeneration++;formDirty=true;
 clearTimeout(draftTimer);if(draftConflict)return;setDraftStatus('Saving your progress…');draftTimer=setTimeout(()=>saveDraft(),500);
}
async function saveDraft(){
 clearTimeout(draftTimer);if(savingDraft)return savingDraft;
 if(!draftActive||draftConflict||submitting||activeCandidate?.mode!=='form'||draftGeneration===savedDraftGeneration)return;
 if(window.cifPhotoPending){draftTimer=setTimeout(()=>saveDraft(),500);return;}
 const generation=draftGeneration,form=formValues(),photo=form.id_photo||'',preservePhoto=photo===lastSavedPhoto;if(preservePhoto)delete form.id_photo;
 const body=JSON.stringify({revision:draftRevision,step:i,form,preserve_photo:preservePhoto});setDraftStatus('Saving your progress…');
 savingDraft=(async()=>{
  try{
   const state=await cifReady,response=await fetch(cifAccessEndpoint.href+'?action=candidate_draft',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body,keepalive:new TextEncoder().encode(body).byteLength<60000}),result=await response.json();
   if(!response.ok){const error=new Error(result.error||'Unable to save progress.');error.status=response.status;throw error;}
   draftRevision=result.revision;lastSavedPhoto=photo;savedDraftGeneration=generation;formDirty=draftGeneration!==savedDraftGeneration;
   setDraftStatus(formDirty?'Saving your latest changes…':'All progress saved · '+candidateDate(result.saved_at));
  }catch(error){
   if(error.status===409)draftConflict=true;formDirty=true;setDraftStatus(error.message+(draftConflict?'':' Keep this page open and retry; your last confirmed save is retained.'),true);
  }finally{savingDraft=null;if(!draftConflict&&!submitting&&draftGeneration!==generation)draftTimer=setTimeout(()=>saveDraft(),300);}
 })();return savingDraft;
}
$('#draft-save-retry').onclick=()=>{if(draftConflict){if(window.confirm('Reload the saved progress from the server? Unsaved changes in this tab will be discarded.')){formDirty=false;location.reload();}}else saveDraft();};
document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='hidden')saveDraft();});window.addEventListener('pagehide',()=>saveDraft());
function showApplicationProgress(progress){
 formDirty=false;clearTimeout(draftTimer);clearInterval(progressTimer);draftActive=false;
 // The tracker hides the form header, so keep Exit beside its refresh control.
 $('.tracking-header-actions').append($('.candidate-exit-actions'));
 $('#candidate-intro').classList.add('hide');$('#cf').classList.add('hide');$('#steps').classList.add('hide');$('#fin').classList.remove('hide');
 window.cifCandidateTracking.render(progress);
 progressTimer=setInterval(async()=>{if(document.visibilityState!=='visible')return;try{const result=await cifRequest('candidate_progress');if(activeCandidate?.mode==='tracking')showApplicationProgress(result.candidate.progress);}catch(error){clearInterval(progressTimer);const message=$('#application-progress-error');message.textContent=error.message+' Use Refresh progress to reconnect.';message.hidden=false;}},60000);
}
$('#application-refresh').onclick=()=>cifRefreshPage($('#application-refresh'));
$('#candidate-exit').onclick=async()=>{
 if(exiting)return;
 const button=$('#candidate-exit'),errorMessage=$('#candidate-exit-error');errorMessage.hidden=true;
 if(submitting||window.cifPhotoPending){errorMessage.textContent=submitting?'Please wait for your form to finish submitting.':'Please wait for your picture to finish processing, then select Exit again.';errorMessage.hidden=false;return;}
 exiting=true;button.disabled=true;button.textContent='Exiting...';$('#app').inert=true;
 try{
  clearTimeout(draftTimer);
  if(savingDraft)await savingDraft;
  if(activeCandidate?.mode==='form'&&formDirty){
   await saveDraft();
   if(formDirty)throw new Error('Your latest changes could not be saved. Please check your progress below before trying Exit again.');
  }
  await cifRequest('candidate_logout');
  clearTimeout(draftTimer);clearInterval(progressTimer);activeCandidate=null;draftActive=false;formDirty=false;
  location.reload();
 }catch(error){
  errorMessage.textContent=error.message;errorMessage.hidden=false;
  $('#app').inert=false;exiting=false;button.disabled=false;button.textContent='Exit';button.focus();
 }
};
function prepareValidation(){
 $('#cf').querySelectorAll('.record-row').forEach(row=>{
  const fields=[...row.querySelectorAll('input,textarea,select')];
  const anchor=fields.find(field=>field.name.endsWith('['+row.dataset.anchor+']'));
  if(anchor){
   anchor.required=fields.some(field=>field.value.trim()!=='');
   const optional=anchor.labels[0]?.querySelector('[data-optional-label]');
   if(optional)optional.classList.toggle('hide',anchor.required);
  }
 });
 $('#cf').querySelectorAll('input,select,textarea').forEach(field=>{
  if(field.hasAttribute('data-position-picker'))return;
  const whitespaceOnly=field.required&&field.type!=='checkbox'&&field.value!==''&&field.value.trim()==='';
  const invalidContact=field.matches('[data-contact-number]')&&field.value!==''&&!/^[1-9][0-9]{2}-[0-9]{3}-[0-9]{4}$/.test(field.value);
  const yearError=field.matches('[data-year]')?cifYearError(field):'';
  field.setCustomValidity(whitespaceOnly?'Please complete this field.':invalidContact?'Enter the remaining 10 digits after +63.':yearError);
  if(field.checkValidity())field.classList.remove('err');
 });
}
function firstInvalid(){
 return [...$('#cf').querySelectorAll('input,select,textarea')].find(field=>!field.checkValidity());
}
function refreshSubmitButton(){
 prepareValidation();
 const missing=firstInvalid(),last=i===S.length-1;
 $('#nx').disabled=submitting||(last&&Boolean(missing));$('#bk').disabled=submitting;
 $('#submit-hint').classList.toggle('hide',!last||!missing);
 $('#review-fields').disabled=submitting;
 if(missing)$('#submit-hint-text').textContent='Complete or correct the fields in '+missing.closest('.step').dataset.label+' before submitting.';
}
$('#cf').addEventListener('input',()=>{refreshSubmitButton();markDraftChanged();});
$('#cf').addEventListener('change',()=>{refreshSubmitButton();markDraftChanged();});
$('#cf').onsubmit=event=>{event.preventDefault();if(i===S.length-1&&!$('#nx').disabled)$('#nx').click();};
function valid(allSteps=false){
 prepareValidation();
 let first;
 (allSteps?S:[S[i]]).forEach(section=>section.querySelectorAll('input,select,textarea').forEach(field=>{
  const bad=!field.checkValidity();field.classList.toggle('err',bad);if(bad)first=first||field;
 }));
 if(first){
  const step=S.indexOf(first.closest('.step'));if(step!==i)show(step);
  first.focus();first.reportValidity();
 }
 return !first;
}
$('#review-fields').onclick=()=>valid(true);
$('#bk').onclick=()=>i===0?showIntroduction():show(i-1);
$('#nx').onclick=async()=>{
 if(submitting||exiting)return;
 if(window.cifPhotoPending){$('#form-error').textContent='Please wait for the ID picture to finish processing.';$('#form-error').classList.remove('hide');return;}
 if(!valid(i===S.length-1))return;if(i<S.length-1)return show(i+1);
 if(activeCandidate?.submitted)return;
 submitting=true;refreshSubmitButton();
 let locked=[];
 try{
  clearTimeout(draftTimer);if(savingDraft)await savingDraft;if(draftConflict)throw new Error('Reload the saved progress before submitting. Another tab or device updated this application.');
  if(!valid(true))return;
  const form=formValues();
  locked=[...$('#cf').querySelectorAll('input,select,textarea,button')].map(field=>[field,field.disabled]);locked.forEach(([field])=>field.disabled=true);
  const result=await cifRequest('submit',{...form,draft_revision:draftRevision});
  activeCandidate.submitted=true;activeCandidate.applicant_id=result.candidate_number;formDirty=false;
  openCandidate(result.candidate);
 }catch(error){if(error.status===409){draftConflict=true;setDraftStatus(error.message,true);}$('#form-error').textContent=error.message;$('#form-error').classList.remove('hide');}finally{locked.forEach(([field,disabled])=>field.disabled=disabled);submitting=false;refreshSubmitButton();if(activeCandidate?.mode==='form'&&formDirty&&!draftConflict)draftTimer=setTimeout(()=>saveDraft(),500);}
};
