(()=>{
 const tabs=[...document.querySelectorAll('[data-stage-tab]')],panels=[...document.querySelectorAll('[data-record-panel]')],stageDirty=new Set();
 const keys=tabs.map(tab=>tab.dataset.stageTab);
 let selectedStage=recordConfig.screeningPassed?'initial_interview':'cif';
 function refreshActions(){const initial=selectedStage==='initial_interview';document.querySelector('#save-cif').textContent=initial?'Save CIF & HR assessment':'Save CIF';document.querySelector('#save-cif').disabled=recordConfig.readOnly||busy||(initial&&!recordConfig.screeningPassed);document.querySelector('#complete-interview').disabled=recordConfig.readOnly||busy||!recordConfig.screeningPassed;}
 function show(stage,updateUrl=true){
  if(!keys.includes(stage))stage=recordConfig.screeningPassed?'initial_interview':'cif';selectedStage=stage;
  tabs.forEach(tab=>{const active=tab.dataset.stageTab===stage;tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;});
  panels.forEach(panel=>panel.classList.toggle('hide',panel.dataset.recordPanel!==stage));
  document.querySelectorAll('[data-hr-credit]').forEach(credit=>credit.classList.toggle('hide',credit.dataset.hrCredit!==stage));
  editor.classList.toggle('hide',!['cif','initial_interview'].includes(stage));
  document.querySelector('#complete-interview').classList.toggle('hide',stage!=='initial_interview');
  document.querySelector('#complete-screening').classList.toggle('hide',stage!=='cif');refreshActions();
  document.querySelectorAll('.section-nav a').forEach(link=>{const matching=link.hash==='#hr-review'?stage==='initial_interview':stage==='cif'&&link.hash===location.hash;link.classList.toggle('section-active',matching);});
  if(updateUrl)history.replaceState(null,'','#stage-'+stage);
 }
 function fromHash(){const hash=location.hash.slice(1);show(hash.startsWith('stage-')?hash.slice(6):(hash.startsWith('section-')||hash==='missing-information'?'cif':(hash==='hr-review'?'initial_interview':selectedStage)),false);}
 window.cifRecordTabs={show,refreshActions,current:()=>selectedStage};
 tabs.forEach((tab,index)=>{
  tab.addEventListener('click',()=>show(tab.dataset.stageTab));
  tab.addEventListener('keydown',event=>{let next;if(event.key==='ArrowRight')next=(index+1)%tabs.length;else if(event.key==='ArrowLeft')next=(index+tabs.length-1)%tabs.length;else if(event.key==='Home')next=0;else if(event.key==='End')next=tabs.length-1;else return;event.preventDefault();tabs[next].focus();show(tabs[next].dataset.stageTab);});
 });
 document.querySelector('.section-nav').addEventListener('click',event=>{const link=event.target.closest('a');if(!link)return;show(link.hash==='#hr-review'?'initial_interview':'cif',false);});
 window.addEventListener('hashchange',fromHash);fromHash();
 let nextStage=null;try{nextStage=sessionStorage.getItem(`cif-next-stage-${recordConfig.id}`);sessionStorage.removeItem(`cif-next-stage-${recordConfig.id}`);}catch{}
 if(keys.includes(nextStage)&&location.hash==='#stage-'+nextStage){const reveal=()=>requestAnimationFrame(()=>{const panel=panels.find(panel=>panel.dataset.recordPanel===nextStage),card=panel.matches('.editor-card')?panel:panel.querySelector('.editor-card'),heading=panel.querySelector('h2');(card||panel).scrollIntoView({block:'start'});if(heading){heading.tabIndex=-1;heading.focus({preventScroll:true});}});if(document.readyState==='complete')reveal();else window.addEventListener('load',reveal,{once:true});}
 window.cifConfirmInterviewReload=(savingStage=null)=>{
  const other=[...stageDirty].filter(stage=>stage!==savingStage);if(savingStage&&dirty)other.push('CIF / HR interview');
  return !other.length||window.confirm('This saves only the current form. Unsaved changes in other tabs will be lost after refresh. Continue?');
 };
 window.cifClearInterviewDirty=()=>stageDirty.clear();
 window.cifRecordHasUnsavedChanges=()=>dirty||stageDirty.size>0;
 window.addEventListener('beforeunload',event=>{if(stageDirty.size){event.preventDefault();event.returnValue='';}});
 const finishDialog=document.querySelector('#stage-completion-dialog');let finishingForm=null;
 async function saveStage(form,complete=false){
  if(recordConfig.readOnly||busy||!form.reportValidity()||!window.cifConfirmInterviewReload(form.dataset.interviewForm))return;
  const values=Object.fromEntries(new FormData(form));busy=true;buttonsDisabled(true);
  const offer=form.dataset.interviewForm==='job_offer';
  form.querySelector('[data-stage-save-state]').textContent=complete?(offer?'Saving and finishing job offer…':'Saving and finishing interview…'):'Saving…';
  try{const result=await cifRequest('candidate_stage_save',{id:recordConfig.id,stage:form.dataset.interviewForm,revision:Number(form.dataset.revision),requirements_revision:Number(form.dataset.requirementsRevision),complete,...values});stageDirty.clear();dirty=false;rememberNotification('success',complete?(offer?'Job offer finished':'Interview completed'):'Changes saved',result.notice);reloadCandidateAfterSave(result);}
  catch(error){showNotification('error',complete?(offer?'Job offer not finished':'Interview not finished'):'Changes not saved',error.message);form.querySelector('[data-stage-save-state]').textContent='Changes not saved';busy=false;buttonsDisabled(false);}
 }
 document.querySelectorAll('[data-interview-form]').forEach(form=>{
  const stage=form.dataset.interviewForm;
  const changed=()=>{if(recordConfig.readOnly)return;stageDirty.add(stage);form.querySelector('[data-stage-save-state]').textContent='Unsaved changes';};form.addEventListener('input',changed);form.addEventListener('change',changed);
  form.addEventListener('submit',event=>{event.preventDefault();saveStage(form,false);});
  form.querySelector('[data-stage-finish]').addEventListener('click',()=>{
   if(recordConfig.readOnly||busy||!form.reportValidity())return;finishingForm=form;
   const label=form.closest('[data-record-panel]').querySelector('h2').textContent;
   document.querySelector('#stage-completion-title').textContent='Finish '+label;
   document.querySelector('#stage-completion-description').textContent=stage==='job_offer'?'Finish the job offer? The current response and remarks will be saved together. Accepted means the applicant accepted the offer.':'Confirm that this interview is finished? Your current decision and remarks will be saved together. No separate save is needed.';
   document.querySelector('#stage-completion-help').textContent=stage==='job_offer'?'Pending Decision is allowed. Only a finished offer with an Accepted response opens deployment.':'Pending Decision is allowed. Only a finished interview with a Passed decision advances the roadmap.';
   document.querySelector('#stage-completion-confirm').textContent=stage==='job_offer'?'Finish Job Offer':'Finish Interview';
   document.querySelector('#stage-completion-decision').textContent=form.elements.decision.value;
   document.querySelector('#stage-completion-result-label').textContent='Decision:';
   finishDialog.showModal();
  });
 });
 window.cifMarkStageDirty=stage=>stageDirty.add(stage);
 document.querySelector('#stage-completion-back').onclick=()=>{finishDialog.close();finishingForm?.querySelector('[data-stage-finish]').focus();};
 document.querySelector('#stage-completion-confirm').onclick=()=>{finishDialog.close();if(finishingForm)saveStage(finishingForm,true);};
 const oldCanSignOut=window.cifAdminPage.canSignOut;window.cifAdminPage.canSignOut=()=>oldCanSignOut()&&(!stageDirty.size||window.confirm('You have unsaved hiring-stage changes. Sign out without saving?'));
 const oldOnSignOut=window.cifAdminPage.onSignOut;window.cifAdminPage.onSignOut=()=>{stageDirty.clear();oldOnSignOut();};
})();
