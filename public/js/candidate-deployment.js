(()=>{
 const form=document.querySelector('[data-deployment-form]');if(!form||recordConfig.readOnly)return;
 const endpoint=new URL('../ajax/mrf.php',document.currentScript.src),mrf=document.querySelector('#deployment-mrf-search'),mrfId=document.querySelector('#deployment-mrf-id'),position=document.querySelector('#deployment-position-search');
 const sourceMrf=Number(form.dataset.sourceMrf)||null,sourceNumber=mrf.value;
 let mrfChangeConfirmed=false;
 const changed=()=>{window.cifMarkStageDirty('deployment');form.querySelector('[data-stage-save-state]').textContent='Unsaved changes';};
 form.addEventListener('input',changed);form.addEventListener('change',changed);
 function picker(input,options,loadRows,onChoose,onType){
  let rows=[],active=-1,timer,request=0,controller;
  function close(){clearTimeout(timer);request++;controller?.abort();options.hidden=true;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');active=-1;}
  function layout(){if(options.hidden)return;const box=input.getBoundingClientRect(),bar=form.querySelector('.save-bar').getBoundingClientRect(),below=Math.max(0,Math.min(innerHeight,bar.top)-box.bottom-8),above=Math.max(0,box.top-90),up=below<180&&above>below;input.parentElement.classList.toggle('candidate-mrf-open-up',up);options.style.maxHeight=Math.max(90,Math.min(250,up?above:below))+'px';}
  function note(text){options.replaceChildren();const li=document.createElement('li');li.className='candidate-mrf-note';li.textContent=text;li.setAttribute('role','presentation');options.append(li);}
  function choose(index){const row=rows[index];if(!row)return;onChoose(row);close();input.focus({preventScroll:true});changed();}
  async function load(){
   if(input.readOnly||input.disabled||input.closest('fieldset').disabled)return;
   clearTimeout(timer);const current=++request;controller?.abort();controller=new AbortController();const signal=controller.signal;
   rows=[];active=-1;options.hidden=false;input.setAttribute('aria-expanded','true');input.removeAttribute('aria-activedescendant');options.setAttribute('aria-busy','true');note('Searching…');layout();
   try{const results=await loadRows(input.value.trim(),signal);if(signal.aborted||current!==request)return;rows=results;options.replaceChildren();if(!rows.length)note('No matching registered options. Refine your search.');rows.forEach((row,index)=>{const li=document.createElement('li'),button=document.createElement('button'),title=document.createElement('strong');li.setAttribute('role','presentation');button.type='button';button.id=input.id+'-option-'+index;button.setAttribute('role','option');button.setAttribute('aria-selected','false');title.textContent=row.label;button.append(title);if(row.detail){const detail=document.createElement('span');detail.textContent=row.detail;button.append(detail);}button.addEventListener('mousedown',event=>event.preventDefault());button.onclick=()=>choose(index);li.append(button);options.append(li);});layout();}
   catch(error){if(error.name!=='AbortError'&&current===request)note(error.message);}
   finally{if(current===request)options.removeAttribute('aria-busy');}
  }
  input.addEventListener('input',()=>{onType();close();timer=setTimeout(load,250);});
  input.addEventListener('focus',load);input.addEventListener('click',()=>{if(options.hidden)load();});
  input.addEventListener('keydown',event=>{
   if(event.key==='Escape'){event.preventDefault();close();}
   else if(event.key==='Tab')close();
   else if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();if(options.hidden){load();return;}if(!rows.length)return;active=event.key==='ArrowDown'?(active+1)%rows.length:(active<0?rows.length-1:(active-1+rows.length)%rows.length);options.querySelectorAll('[role="option"]').forEach((button,index)=>{button.setAttribute('aria-selected',String(index===active));if(index===active){input.setAttribute('aria-activedescendant',button.id);button.scrollIntoView({block:'nearest'});}});}
   else if(event.key==='Enter'){event.preventDefault();if(!options.hidden&&active>=0)choose(active);}
  });
  document.addEventListener('pointerdown',event=>{if(!input.parentElement.contains(event.target))close();});window.addEventListener('resize',layout);window.addEventListener('scroll',layout,{passive:true});
  return {close,load};
 }
 function mrfValid(){mrf.setCustomValidity(mrfId.value&&mrf.value.trim()?'':'Choose a registered MRF from the search results.');}
 const mrfPicker=picker(mrf,document.querySelector('#deployment-mrf-options'),async(search,signal)=>{const state=await cifReady;const response=await fetch(endpoint.href+'?portal=admin&action=candidate_options',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body:JSON.stringify({search}),signal}),result=await response.json();if(!response.ok)throw new Error(result.error||'Unable to search MRFs.');return result.requests.map(row=>({...row,label:row.mrf_number+' · '+row.position_title,detail:row.department_name+' · '+row.site_location+' · Approved · Recruitment: '+row.status}));},row=>{mrf.value=row.mrf_number;mrfId.value=String(row.id);mrfValid();},()=>{mrfId.value='';mrfValid();});
 document.querySelector('#deployment-mrf-change').onclick=()=>{if(busy)return;if(!window.confirm('Change the deployment MRF? This changes only the deployment assignment, not the MRF saved in screening or Interview. Continue?'))return;mrfChangeConfirmed=true;mrf.readOnly=false;mrf.focus();mrf.select();};
 const positions=JSON.parse(document.querySelector('#deployment-positions').textContent);
 let selectedPosition=positions.includes(position.value)?position.value:'';
 position.setCustomValidity(position.value&&!selectedPosition?'Choose an official deployment title from the search results.':'');
 const positionPicker=picker(position,document.querySelector('#deployment-position-options'),async search=>positions.filter(title=>title.toLocaleLowerCase().includes(search.toLocaleLowerCase())).map(title=>({label:title})),row=>{position.value=row.label;selectedPosition=row.label;position.setCustomValidity('');},()=>{selectedPosition='';position.setCustomValidity(position.value.trim()?'Choose a registered position title from the search results.':'');});
 const dialog=document.querySelector('#deployment-completion-dialog');
 function validate(complete){
  mrfValid();position.setCustomValidity(position.value.trim()&&position.value!==selectedPosition?'Choose a registered position title from the search results.':'');
  for(const field of [form.elements.date_hired,position])field.required=complete;
  form.elements.employee_id.required=false;
  // Readonly fields are excluded from browser validation, so check the MRF explicitly.
  if(!mrfId.value){showNotification('warning','MRF required','Use Change MRF to select a registered request.');return false;}
  return form.reportValidity();
 }
 async function save(complete){
  if(busy||!validate(complete)||!window.cifConfirmInterviewReload('deployment'))return;
  mrfPicker.close();positionPicker.close();busy=true;buttonsDisabled(true);form.querySelector('[data-stage-save-state]').textContent=complete?'Saving and completing deployment…':'Saving…';
  try{const result=await cifRequest('candidate_deployment_save',{id:recordConfig.id,revision:Number(form.dataset.revision),requirements_revision:Number(form.dataset.requirementsRevision),source_mrf_id:sourceMrf,mrf_id:Number(mrfId.value),mrf_text:mrf.value.trim(),confirm_mrf_change:mrfChangeConfirmed,complete,date_hired:form.elements.date_hired.value,employee_id:form.elements.employee_id.value,position_title:position.value});dirty=false;window.cifClearInterviewDirty();rememberNotification('success',complete?'Deployment completed':'Changes saved',result.notice);location.reload();}
  catch(error){busy=false;buttonsDisabled(false);showNotification('error','Deployment not saved',error.message);form.querySelector('[data-stage-save-state]').textContent='Changes not saved';}
 }
 form.addEventListener('submit',event=>{event.preventDefault();save(false);});
 form.querySelector('[data-stage-finish]').onclick=()=>{if(busy||!validate(true))return;document.querySelector('#deployment-completion-description').textContent='Complete deployment? The CIF and all hiring assessments will become view-only. '+mrf.value+' · '+form.elements.date_hired.value+' · '+(form.elements.employee_id.value.trim()||'Employee ID not provided')+' · '+position.value+(Number(mrfId.value)!==sourceMrf?' The deployment MRF differs from '+(sourceNumber||'the original assignment')+'.':'');dialog.showModal();};
 document.querySelector('#deployment-completion-back').onclick=()=>{dialog.close();form.querySelector('[data-stage-finish]').focus();};
 document.querySelector('#deployment-completion-confirm').onclick=()=>{dialog.close();save(true);};
 mrfValid();
})();
