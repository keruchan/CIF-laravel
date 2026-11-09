(()=>{
 const el=id=>document.getElementById(id),catalogue=el('candidate-tracking-icons');let previous=null;
 const nextMessages={cif:'Your application has been received. HR will review your information.',initial_interview:'Your application is at the interview phase. HR will contact you about schedules and next steps.',department_interview:'A department interview is part of your application. Please wait for instructions from HR.',lie_detection:'HR has included a Background check assessment in your route. Please wait for their instructions.',job_offer:'Your application is at the job offer phase. Please wait for official communication from HR.',deployment:'Your application is at the deployment phase. HR will confirm the remaining details.'};
 function icon(key){return catalogue.content.querySelector('[data-phase-icon="'+key+'"] svg').cloneNode(true);}
 function date(value){return new Date(value.replace(' ','T')+'Z').toLocaleString('en-US',{timeZone:'Asia/Manila',month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'});}
 function phase(step,compact=false){
  const displayLabel=step.key==='cif'?'SCREENING':step.label;
  const article=document.createElement('article');article.className='tracking-phase'+(compact?' tracking-phase-compact':'');article.dataset.phase=step.key;article.dataset.state=step.state;article.setAttribute('aria-label',displayLabel+' — '+step.status);
  if(['current','under-review'].includes(step.state))article.setAttribute('aria-current','step');
  const circle=document.createElement('span');circle.className='tracking-phase-icon';circle.setAttribute('aria-hidden','true');circle.append(icon(step.key));
  if(step.state==='completed'){const check=document.createElement('span');check.className='tracking-phase-check';check.append(icon('check'));circle.append(check);}
  const label=document.createElement('h3');label.textContent=displayLabel;
  const state=document.createElement('span');state.className='tracking-phase-state';state.textContent=step.status;
  article.append(circle,label,state);
  if(step.updated_at){const updated=document.createElement('time');updated.className='tracking-phase-updated';updated.dateTime=step.updated_at.replace(' ','T')+'Z';updated.title='Philippine time';updated.textContent='Updated '+date(step.updated_at);article.append(updated);}
  return article;
 }
 function render(progress){
  el('application-progress-error').hidden=true;
  const steps=progress.steps.filter(step=>step.state!=='not-required'),assessments=steps.filter(step=>['department_interview','lie_detection'].includes(step.key));
  const signature=JSON.stringify(progress),changed=signature!==previous,first=previous===null;
  if(!changed)return;
  previous=signature;document.body.classList.add('candidate-tracking');
  const schedule=progress.next_schedule;el('tracking-next-schedule').hidden=!schedule;
  el('tracking-next-phase').textContent=schedule?.label||'';
  el('tracking-next-date').textContent=schedule?.scheduled_at?date(schedule.scheduled_at)+' (Philippine time)':'Date and time to be confirmed';
  el('applicant-id').textContent=progress.applicant_id;el('application-position').textContent='Applied position: '+(progress.position||'Not specified');el('application-updated').textContent=progress.updated_at?'Updated '+date(progress.updated_at):'Awaiting HR update';
  el('application-progress-count').textContent=progress.completed+' of '+progress.total+' completed';
  const meter=document.querySelector('.tracking-meter'),value=meter.querySelector('.tracking-meter-value');
  meter.setAttribute('aria-valuemax',progress.total);meter.setAttribute('aria-valuenow',progress.completed);meter.setAttribute('aria-valuetext',progress.completed+' of '+progress.total+' required phases completed');
  if(first)value.style.width='0%';requestAnimationFrame(()=>requestAnimationFrame(()=>{value.style.width=(100*Math.max(0,Math.min(1,progress.completed/progress.total)))+'%';}));
  const complete=progress.status==='Deployed',current=steps.filter(step=>['current','under-review'].includes(step.state)),review=current.some(step=>step.state==='under-review');
  el('application-status').textContent=progress.status;
  el('tracking-current-message').textContent=complete?'All required hiring phases are complete and your application is marked Deployed. HR will provide any remaining instructions.':review?'This is the current status recorded by HR. Please contact HR for clarification or next steps.':current.length>1?'Your required assessments can be completed in either order. HR will contact you with instructions for each assessment.':nextMessages[current[0]?.key]||'HR is reviewing your application and will contact you about the next steps.';
  const statuses=[...new Set(current.map(step=>step.status))];
  el('tracking-current-tag').textContent=complete?steps.find(step=>step.key==='deployment').status:(statuses.join(' / ')||'Under review');
  el('tracking-current-tag').dataset.complete=String(complete);document.querySelector('.tracking-current').dataset.review=String(review);
  const parallel=el('tracking-parallel-statuses');parallel.replaceChildren();if(current.length>1&&statuses.length>1)for(const step of current){const badge=document.createElement('p');badge.textContent=step.label+' · '+step.status;parallel.append(badge);}
  const currentIcon=document.querySelector('.tracking-current-icon');currentIcon.replaceChildren(icon(complete?'check':current[0]?.key||'cif'));
  el('tracking-route-help').textContent=assessments.length===2?'Department interview and Background check may be completed in either order.':assessments.length===1?'Your route includes '+assessments[0].label.toLowerCase()+', as required by HR.':'';
  el('tracking-route-help').hidden=assessments.length===0;
  const route=el('application-progress-stages');route.replaceChildren();
  function node(step,index){const item=document.createElement('li');item.className='tracking-route-node';item.dataset.connected=String(Boolean(step.connected));item.style.setProperty('--phase-delay',Math.min(index*65,300)+'ms');return item;}
  for(const step of steps){
   if(step.key==='lie_detection'&&assessments.some(item=>item.key==='department_interview'))continue;
   const item=node(step,route.children.length);
   if(assessments.length===2&&step.key==='department_interview'){
    item.classList.add('tracking-assessment-node');item.setAttribute('aria-label','Required assessments, in either order');const heading=document.createElement('p');heading.className='tracking-assessment-heading';heading.textContent='Required assessments';const hint=document.createElement('span');hint.textContent='Any order';heading.append(hint);
    const branches=document.createElement('ol');branches.className='tracking-assessment-branches';branches.setAttribute('aria-label','Independent required assessments');
    for(const assessment of assessments){const branch=document.createElement('li');branch.append(phase(assessment,true));branches.append(branch);}item.append(heading,branches);
   }else item.append(phase(step));
   route.append(item);
  }
  [...route.children].forEach((item,index,items)=>{item.dataset.forwardConnected=items[index+1]?.dataset.connected||'false';});
  route.style.setProperty('--route-columns',route.children.length);route.style.setProperty('--route-template',assessments.length===2?'1fr 1fr 2fr 1fr 1fr':'repeat('+route.children.length+',minmax(0,1fr))');route.dataset.assessments=String(assessments.length);
  document.querySelector('.tracking-hero').dataset.complete=String(complete);
 }
 window.cifCandidateTracking={render,reset:()=>{previous=null;document.body.classList.remove('candidate-tracking');}};
})();
