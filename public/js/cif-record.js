const recordConfig=JSON.parse(document.querySelector('#record-config').textContent);
const editor=document.querySelector('#cif-editor'),message=document.querySelector('#record-message'),editState=document.querySelector('#edit-state');
let dirty=false,busy=false;
const mainDirtyScopes=new Set();editor.addEventListener('input',event=>{const panel=event.target.closest('[data-record-panel]');if(panel)mainDirtyScopes.add(panel.dataset.recordPanel);});editor.addEventListener('change',event=>{const panel=event.target.closest('[data-record-panel]');if(panel)mainDirtyScopes.add(panel.dataset.recordPanel);});
for(const [inlineId,dialogId] of [['initial-decision-inline','initial-decision'],['initial-decision-remarks-inline','initial-decision-remarks']]){
 const inline=document.getElementById(inlineId),dialog=document.getElementById(dialogId);
 inline.addEventListener('input',()=>{dialog.value=inline.value;});inline.addEventListener('change',()=>{dialog.value=inline.value;});
 const fromDialog=()=>{if(inline.value!==dialog.value){inline.value=dialog.value;inline.dispatchEvent(new Event('input',{bubbles:true}));}};
 dialog.addEventListener('input',fromDialog);dialog.addEventListener('change',fromDialog);
}
function revealCifRecordField(field){const panel=field.closest('[data-record-panel]');if(panel)window.cifRecordTabs?.show(panel.dataset.recordPanel);}
function candidatePortalUrl(view){const url=new URL('./#'+view,location.href);if(view==='list')url.searchParams.set('archived',recordConfig.archived?'1':'0');return url.href;}
window.cifAdminPage={
 show:()=>{document.querySelector('#ttl').textContent='Candidates';document.querySelector('#sub').textContent='Search, filter and manage submitted forms';document.querySelectorAll('.nv[data-v]').forEach(button=>{const active=button.dataset.v==='list';button.classList.toggle('on',active);if(active)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current');});cifSidebar.close();refreshSidebarBadges();},
 navigate:view=>{location.href=candidatePortalUrl(view);},
 canSignOut:()=>{if(busy){message.textContent='Please wait for the current CIF request to finish before signing out.';return false;}return !dirty||window.confirm('You have unsaved CIF changes. Sign out without saving? Your changes will be lost.');},
 onSignOut:()=>{dirty=false;location.replace(candidatePortalUrl('list'));}
};
const notification=document.querySelector('#record-notification');
let notificationTimer;
function dismissNotification(){clearTimeout(notificationTimer);notificationTimer=undefined;notification.hidden=true;}
function showNotification(type,title,body){
 clearTimeout(notificationTimer);
 notification.dataset.type=type;notification.hidden=false;
 document.querySelector('#notification-icon').textContent=type==='success'?'✓':type==='warning'?'!':'×';
 document.querySelector('#notification-title').textContent=title;
 document.querySelector('#notification-body').textContent=body;
 notificationTimer=setTimeout(dismissNotification,5000);
}
function rememberNotification(type,title,body){try{sessionStorage.setItem(`cif-status-notice-${recordConfig.id}`,JSON.stringify({type,title,body}));}catch{showNotification(type,title,body);}}
function reloadCandidateAfterSave(result){
 const next=result.next_stage;
 if(['cif','initial_interview','department_interview','lie_detection','job_offer','deployment'].includes(next)){
  history.replaceState(null,'','#stage-'+next);
  try{sessionStorage.setItem(`cif-next-stage-${recordConfig.id}`,next);}catch{}
 }
 location.reload();
}
document.querySelector('#notification-dismiss').onclick=()=>{dismissNotification();document.querySelector('#save-cif').focus({preventScroll:true});};
try{const notice=sessionStorage.getItem(`cif-status-notice-${recordConfig.id}`);if(notice){sessionStorage.removeItem(`cif-status-notice-${recordConfig.id}`);let saved;try{saved=JSON.parse(notice);}catch{saved={type:'warning',title:'Changes saved — review needed',body:notice};}showNotification(saved.type,saved.title,saved.body);}}catch{}
const assessmentFields=[...editor.querySelectorAll('[data-assessment-required]')];
const assessmentNotice=document.querySelector('#hr-missing-notice');
const assessmentAttemptKey=`cif-assessment-attempt-${recordConfig.id}`;
let assessmentAttempted=false;
try{assessmentAttempted=sessionStorage.getItem(assessmentAttemptKey)==='1';sessionStorage.removeItem(assessmentAttemptKey);}catch{}
function refreshAssessment(){
 const missing=assessmentFields.filter(field=>!field.value.trim());
 const overall=document.querySelector('#hr-completion');
 overall.textContent=missing.length?`${assessmentFields.length-missing.length}/${assessmentFields.length} answered`:'Assessment complete';
 overall.classList.toggle('complete',!missing.length);
 const summaries=[];
 document.querySelectorAll('.hr-group').forEach(group=>{
  const fields=[...group.querySelectorAll('[data-assessment-required]')],empty=fields.filter(field=>!field.value.trim());
  const badge=group.querySelector('.section-completion');
  badge.textContent=!fields.length?'Optional':empty.length?`${fields.length-empty.length}/${fields.length} · ${empty.length} missing`:'✓ Complete';
  badge.classList.toggle('complete',fields.length>0&&!empty.length);
  const requiredMark=group.querySelector('summary .required-mark');if(requiredMark)requiredMark.hidden=!empty.length;
  if(empty.length)summaries.push(`${group.dataset.sectionLabel}: ${empty.length}`);
 });
 assessmentNotice.textContent=missing.length?`${assessmentAttempted?'Assessment incomplete. ':''}${missing.length} required answers have no value (${summaries.join('; ')}).`:'All required HR assessment answers are complete.';
 assessmentNotice.classList.toggle('complete',!missing.length);
 assessmentFields.forEach(field=>{
  field.closest('.editor-field').querySelector('label .required-mark').hidden=Boolean(field.value.trim());
  const highlight=assessmentAttempted&&!field.value.trim();
  field.classList.toggle('assessment-missing',highlight);
  if(highlight)field.setAttribute('aria-invalid','true');else field.removeAttribute('aria-invalid');
 });
 return missing;
}
refreshAssessment();
editor.addEventListener('input',refreshAssessment);
function refreshHrChoices(){editor.querySelectorAll('[data-hr-choice]').forEach(select=>{
 const detail=select.closest('.editor-field').querySelector('.hr-answer-details');
 const show=select.value==='Reported';
 detail.hidden=!show;detail.querySelector('textarea').disabled=!show;
});}
refreshHrChoices();
function markHrChoiceEdited(event){
 const wrapper=event.target.closest('.editor-field');
 const select=wrapper?.querySelector('[data-hr-choice]');
 if(select){select.dataset.choiceEdited='true';const note=wrapper.querySelector('.saved-answer-note');if(note)note.hidden=true;}
}
editor.addEventListener('input',markHrChoiceEdited);
editor.addEventListener('change',markHrChoiceEdited);
editor.addEventListener('change',()=>{refreshHrChoices();refreshAssessment();dirty=true;editState.textContent='Unsaved changes';message.textContent='';});
const missingLinks=document.querySelector('#candidate-missing-links');
function refreshCandidateMissing(){
 const groups=[];
 editor.querySelectorAll('.editor-card:not(.hr-card)').forEach(section=>{
  const fields=[...section.querySelectorAll('[data-candidate-label]')].filter(field=>{
   if(field.value.trim())return false;
   const record=field.closest('.editor-record');
   return !record||[...record.querySelectorAll('[data-candidate-label]')].some(input=>input.value.trim());
  });
  if(fields.length)groups.push({section,fields});
 });
 missingLinks.replaceChildren();
 let count=0;
 for(const {section,fields} of groups){
  const group=document.createElement('div'),heading=document.createElement('h3'),links=document.createElement('div');
  heading.textContent=`${section.querySelector('h2').textContent} · ${fields.length}`;
  group.className='missing-info-group';links.className='missing-info-links';
  for(const field of fields){
   const button=document.createElement('button');button.type='button';button.dataset.missingTarget=field.id;
   let label=field.dataset.candidateLabel;
   const record=field.closest('.editor-record');
   if(record)label=`${record.closest('.editor-record-group').querySelector('h3').textContent} — ${record.querySelector('summary').firstChild.textContent.trim()}: ${label}`;
   button.textContent=label+(field.dataset.candidateRequired==='true'?'':' (optional)');
   links.append(button);count++;
  }
  group.append(heading,links);missingLinks.append(group);
 }
 const badge=document.querySelector('#candidate-missing-count');
 badge.textContent=count?`${count} blank fields`:'No missing information';badge.classList.toggle('complete',!count);
}
missingLinks.addEventListener('click',event=>{
 const button=event.target.closest('[data-missing-target]');if(!button)return;
 const field=document.getElementById(button.dataset.missingTarget);if(!field)return;revealCifRecordField(field);
 for(let parent=field.parentElement;parent;parent=parent.parentElement){if(parent.tagName==='DETAILS')parent.open=true;}
 field.scrollIntoView({block:'center',behavior:'smooth'});(field.matches('[data-photo-value]')?field.closest('[data-photo-widget]').querySelector('[data-photo-upload]'):field).focus({preventScroll:true});
 const wrapper=field.closest('.editor-field');wrapper.classList.remove('missing-field-target');
 requestAnimationFrame(()=>wrapper.classList.add('missing-field-target'));
});
refreshCandidateMissing();editor.addEventListener('input',refreshCandidateMissing);editor.addEventListener('change',refreshCandidateMissing);
editor.addEventListener('input',()=>{dirty=true;editState.textContent='Unsaved changes';message.textContent='';});
window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
function buttonsDisabled(disabled){document.querySelectorAll('.record-actions button,#publish-current-stage,[data-stage-publish],#save-cif,#complete-interview,#complete-screening,[data-stage-save],[data-stage-finish],#stage-completion-confirm,#deployment-completion-confirm,#screening-completion-confirm').forEach(button=>button.disabled=disabled||button.hasAttribute('data-prerequisite-blocked')||button.hasAttribute('data-deployment-locked'));if(!disabled)window.cifRecordTabs?.refreshActions();}
function savedFirst(){if(window.cifPhotoPending){message.textContent='Wait for the ID picture to finish processing before continuing.';return false;}if(dirty){message.textContent='Save your CIF changes before archiving or printing.';return false;}return true;}
async function update(action,input){if(busy||!savedFirst())return;busy=true;buttonsDisabled(true);try{await cifRequest(action,{id:recordConfig.id,...input});rememberNotification('success',action==='candidate_archive'?(input.archived?'Candidate archived':'Candidate restored'):'Status updated',action==='candidate_archive'?(input.archived?'The candidate record has been moved to Archived. No information was deleted.':'The candidate record is available in the active candidates list again.'):`Application status changed to ${input.status}.`);location.reload();}catch(error){message.textContent=error.message;showNotification('error','Update not completed',error.message);busy=false;buttonsDisabled(false);}}
document.querySelector('#archive-record').onclick=()=>update('candidate_archive',{archived:!recordConfig.archived});
document.querySelector('#prepare-record-email').onclick=()=>{if(savedFirst())location.href='./?mail_candidate='+recordConfig.id+'#mail';};
document.querySelector('#print-record').onclick=()=>{if(savedFirst())window.print();};
const completionDialog=document.querySelector('#completion-dialog');
function completionReview(){
 if(busy)return;
 window.cifRecordTabs?.show('initial_interview');
 if(window.cifCandidateMrf&&!cifCandidateMrf.validate())return;
 assessmentAttempted=true;refreshAssessment();
 const missing=[...editor.querySelectorAll('[data-candidate-required="true"],[data-assessment-required]')].filter(field=>!field.value.trim());
 editor.querySelectorAll('[data-record-anchor]').forEach(record=>{
  const fields=[...record.querySelectorAll('[data-candidate-label]')];
  const anchor=fields.find(field=>field.name===record.dataset.recordAnchor);
  if(anchor&&!anchor.value.trim()&&fields.some(field=>field.value.trim()))missing.push(anchor);
 });
 const list=document.querySelector('#completion-missing');list.replaceChildren();
 for(const field of missing){const item=document.createElement('li'),button=document.createElement('button');button.type='button';button.textContent=field.dataset.candidateLabel||field.closest('.editor-field').querySelector('label').textContent.replace('*','').trim();button.onclick=()=>{completionDialog.close();revealCifRecordField(field);for(let parent=field.parentElement;parent;parent=parent.parentElement){if(parent.tagName==='DETAILS')parent.open=true;}field.scrollIntoView({block:'center'});field.focus({preventScroll:true});};item.append(button);list.append(item);}
 document.querySelector('#completion-description').textContent=missing.length?`${missing.length} answers are missing. Fill the items below before finishing the interview. Optional blanks do not block completion. You can still use Save for incomplete work.`:'Confirm that the interview is finished? Your current changes and decision will be saved together. Candidate status will update automatically from the roadmap. No separate save is needed.';
 document.querySelector('#completion-confirm').disabled=missing.length>0;
 document.querySelector('#completion-dialog .interview-decision-fields').hidden=missing.length>0;
 document.querySelector('#initial-decision').value=document.querySelector('#initial-decision-inline').value;
 document.querySelector('#initial-decision-remarks').value=document.querySelector('#initial-decision-remarks-inline').value;
 completionDialog.showModal();
}
document.querySelector('#completion-back').onclick=()=>completionDialog.close();
document.querySelector('#complete-interview').onclick=completionReview;
document.querySelector('#completion-confirm').onclick=()=>{completionDialog.close();saveEditor(true,'initial_interview');};
editor.onsubmit=event=>{event.preventDefault();saveEditor(false);};
const screeningDialog=document.querySelector('#screening-completion-dialog'),screeningDecision=document.querySelector('#screening-decision');
function screeningWarning(){const missing=[...document.querySelectorAll('#stage-panel-cif [data-candidate-required="true"]')].filter(field=>!field.value.trim());document.querySelectorAll('#stage-panel-cif [data-record-anchor]').forEach(record=>{const fields=[...record.querySelectorAll('[data-candidate-label]')],anchor=fields.find(field=>field.name===record.dataset.recordAnchor);if(anchor&&!anchor.value.trim()&&fields.some(field=>field.value.trim()))missing.push(anchor);});const warning=document.querySelector('#screening-completion-warning');warning.textContent=missing.length?`Warning: ${missing.length} required CIF answers are missing. You can still complete screening as Passed, Failed or Backout. Verify and fill the missing information with the candidate during HR interview.`:'A Passed decision opens HR interview. Failed or Backout keeps it blocked.';warning.classList.toggle('screening-missing-warning',missing.length>0);}
document.querySelector('#complete-screening').onclick=()=>{if(busy)return;window.cifRecordTabs.show('cif');if(window.cifCandidateMrf&&!cifCandidateMrf.validate('cif'))return;screeningDecision.value='';screeningWarning();screeningDialog.showModal();};
document.querySelector('#screening-completion-back').onclick=()=>screeningDialog.close();
document.querySelector('#screening-completion-confirm').onclick=()=>{if(!screeningDecision.reportValidity())return;screeningDialog.close();saveEditor(true,'cif');};
async function saveEditor(complete=false,scope=window.cifRecordTabs?.current()||'cif'){
 if(recordConfig.readOnly)return;
 if(window.cifPhotoPending){showNotification('warning','Picture still processing','Wait for the ID picture to finish processing before saving.');return;}
 if(busy)return;
 if(window.cifCandidateMrf&&!cifCandidateMrf.validate(scope)){window.cifRecordTabs?.show(scope);return;}
 if(scope==='cif'&&mainDirtyScopes.has('initial_interview')&&!window.confirm('Save CIF only? Unsaved HR assessment changes will be lost after refresh. Continue?'))return;
 if(window.cifConfirmInterviewReload&&!window.cifConfirmInterviewReload())return;
 assessmentAttempted=true;refreshAssessment();
 const invalid=[...editor.querySelectorAll(scope==='cif'?'#stage-panel-cif input,#stage-panel-cif select,#stage-panel-cif textarea':'input,select,textarea')].find(field=>!field.checkValidity());
 if(invalid){revealCifRecordField(invalid);for(let parent=invalid.parentElement;parent;parent=parent.parentElement){if(parent.tagName==='DETAILS')parent.open=true;}invalid.reportValidity();return;}
 const form={},hr={},hrDetails={};
 for(const [name,value] of new FormData(editor)){
  if(['interview_decision','interview_remarks'].includes(name)||name.startsWith('assessment_'))continue;
  const hrMatch=name.match(/^hr\[(\w+)\]$/),detailMatch=name.match(/^hr_details\[(\w+)\]$/),recordMatch=name.match(/^(\w+)\[(\d+)\]\[(\w+)\]$/);
  if(hrMatch){hr[hrMatch[1]]=value;}else if(detailMatch){hrDetails[detailMatch[1]]=value;}else if(recordMatch){const [,group,index,key]=recordMatch;form[group]??=[];form[group][Number(index)]??={};form[group][Number(index)][key]=value;}else form[name]=value;
 }
 editor.querySelectorAll('[data-original-answer]').forEach(select=>{if(select.dataset.choiceEdited!=='true'){hr[select.dataset.hrChoice]=select.dataset.originalAnswer;delete hrDetails[select.dataset.hrChoice];}});
 const mrfPrefix=scope==='cif'?'screening-mrf':'candidate-mrf',mrfSearch=document.querySelector('#'+mrfPrefix+'-search'),mrfId=document.querySelector('#'+mrfPrefix+'-id');
 busy=true;buttonsDisabled(true);editState.textContent=complete?(scope==='cif'?'Saving and completing screening…':'Saving and finishing interview…'):'Saving…';
 try{const result=await cifRequest('candidate_save',{id:recordConfig.id,revision:recordConfig.revision,scope,screening_decision:complete&&scope==='cif'?screeningDecision.value:null,form,hr,hr_details:hrDetails,complete,assessment_requirements:{department_required:document.querySelector('#assessment-department').checked,ldt_required:document.querySelector('#assessment-ldt').checked},decision:document.querySelector('#initial-decision-inline').value,decision_remarks:document.querySelector('#initial-decision-remarks-inline').value,mrf_text:mrfSearch.value.trim(),mrf_id:mrfId.value?Number(mrfId.value):null});dirty=false;window.cifClearInterviewDirty?.();try{sessionStorage.setItem(assessmentAttemptKey,'1');}catch{}rememberNotification(result.missing?.length?'warning':'success',complete?(scope==='cif'?'Screening completed':'Interview completed'):'Changes saved',result.notice||'Changes saved.');reloadCandidateAfterSave(result);}
 catch(error){message.textContent=error.message;showNotification('error',complete?(scope==='cif'?'Screening not completed':'Interview not finished'):'Changes not saved',error.message);editState.textContent='Changes not saved';busy=false;buttonsDisabled(false);}
}
if(recordConfig.autoPrint)window.addEventListener('load',async()=>{try{const state=await cifReady;if(state.admin)window.print();}catch(error){/* Session errors are shown by the portal. */}});
