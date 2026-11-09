(()=>{
 const el=id=>document.getElementById(id),dialog=el('candidate-update-dialog');if(!dialog)return;
 const endpoint=new URL('../ajax/email.php',document.currentScript.src);
 let preview=null,requestKey=null,sending=false,changed=false;
 const key=()=>Array.from(crypto.getRandomValues(new Uint8Array(32)),v=>v.toString(16).padStart(2,'0')).join('');
 async function api(action,input){const session=await cifReady;const response=await fetch(endpoint.href+'?action='+action,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':session.csrf},body:JSON.stringify(input)});const data=await response.json();if(!response.ok)throw new Error(data.error||'Unable to update candidate.');return data;}
 function error(text=''){el('candidate-update-error').textContent=text;el('candidate-update-error').hidden=!text;}
 const phaseSelect=el('candidate-update-next-phase'),dateInput=el('candidate-update-next-datetime');
 function scheduleSummary(){
  const label=phaseSelect.selectedOptions[0]?.textContent;
  dateInput.disabled=sending||!phaseSelect.value;
  if(!phaseSelect.value){dateInput.value='';el('candidate-update-schedule-preview').textContent='No next-phase schedule will be included.';return;}
  const when=dateInput.value?new Date(dateInput.value+'+08:00').toLocaleString('en-US',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'})+' (Philippine time)':'Date and time to be confirmed';
  el('candidate-update-schedule-preview').textContent='Next phase: '+label+'\nSchedule: '+when;
 }
 function scheduleFields(){
  const options=preview.next_phase_options||[],saved=preview.progress.next_schedule;
  phaseSelect.replaceChildren(new Option('Choose next phase', ''));
  for(const option of options)phaseSelect.add(new Option(option.label,option.key));
  phaseSelect.value=options.some(o=>o.key===saved?.stage)?saved.stage:options.length===1?options[0].key:'';
  dateInput.value=saved?.scheduled_at&&phaseSelect.value===saved.stage?new Date(new Date(saved.scheduled_at.replace(' ','T')+'Z').getTime()+8*3600000).toISOString().slice(0,16):'';
  el('candidate-update-schedule-fields').hidden=!options.length;scheduleSummary();
 }
 document.addEventListener('click',async event=>{
  const button=event.target.closest('[data-stage-publish],#publish-current-stage');if(!button||sending||busy)return;
  if(window.cifRecordHasUnsavedChanges?.()){showNotification('error','Save changes first','Save the stage changes before updating the candidate.');return;}
  const stage=button.dataset.stagePublish||window.cifRecordTabs.current();button.disabled=true;
  try{
   const result=await api('candidate_update_preview',{candidate_id:recordConfig.id,stage});preview=result.preview;requestKey=key();changed=false;
   el('candidate-update-title').textContent='Update candidate · '+preview.stage_label;
   el('candidate-update-summary').textContent=preview.stage_label+': '+preview.result+' · Candidate phase after release: '+preview.progress.status;
   el('candidate-update-to').value=preview.recipient_email;el('candidate-update-subject').value=preview.subject;el('candidate-update-body').value=preview.body;scheduleFields();error();dialog.showModal();
  }catch(e){showNotification('error','Update not ready',e.message);}finally{button.disabled=false;}
 });
 const close=()=>{if(sending)return;if(changed&&!window.confirm('Discard the unsent email changes?'))return;changed=false;dialog.close();};
 el('candidate-update-cancel').onclick=close;dialog.addEventListener('cancel',e=>{e.preventDefault();close();});
 for(const id of ['candidate-update-subject','candidate-update-body'])el(id).oninput=()=>{changed=true;requestKey=key();};
 phaseSelect.onchange=dateInput.oninput=()=>{changed=true;requestKey=key();scheduleSummary();};
 window.addEventListener('beforeunload',e=>{if(changed||sending){e.preventDefault();e.returnValue='';}});
 el('candidate-update-form').onsubmit=async event=>{
  event.preventDefault();if(sending||!preview||!event.target.reportValidity())return;
  sending=true;busy=true;error();buttonsDisabled(true);dialog.querySelectorAll('button,input,textarea,select').forEach(c=>c.disabled=true);el('candidate-update-submit').textContent='Updating…';
  try{
   const result=await api('candidate_update_send',{candidate_id:recordConfig.id,stage:preview.stage,preview_hash:preview.preview_hash,request_key:requestKey,subject:el('candidate-update-subject').value,body:el('candidate-update-body').value,next_phase:phaseSelect.value,next_datetime:dateInput.value});
   changed=false;dialog.close();showNotification(result.email_status==='Sent'?'success':'warning','Candidate tracker updated',result.message);
  }catch(e){error(e.message+' If the request was interrupted, check the tracker and Mail management before starting another update.');}
  finally{sending=false;busy=false;buttonsDisabled(false);dialog.querySelectorAll('button,input,textarea,select').forEach(c=>c.disabled=false);dateInput.disabled=!phaseSelect.value;el('candidate-update-submit').textContent='Update tracker & send email';}
 };
})();
