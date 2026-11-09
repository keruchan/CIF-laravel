(()=>{
 if(!document.getElementById('mail'))return;
 const el=id=>document.getElementById(id),endpoint=new URL('../ajax/email.php',document.currentScript.src),esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 let mailArchived=false,mailRows=[],archivePending=null,resendPending=null;
 let templates=[],catalogue=null,initializing=null,mailPage=1,listRequest=0,recipientRequest=0,previewRequest=0,recipientTimer,listTimer,editing=null,dirty=false,busy=false,selected=null,preview=null,preparationKey=null,detail=null,lastField=el('template-body');
 const key=()=>Array.from(crypto.getRandomValues(new Uint8Array(32)),b=>b.toString(16).padStart(2,'0')).join('');
 const date=value=>value?new Date(value.replace(' ','T')+'Z').toLocaleString('en-US',{timeZone:'Asia/Shanghai',month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—';
 async function api(action,input={}){const session=await cifReady,response=await fetch(endpoint.href+'?action='+action,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':session.csrf},body:JSON.stringify(input)}),data=await response.json();if(!response.ok)throw new Error(data.error||'Unable to manage email.');return data;}
 function error(id,text=''){el(id).textContent=text;el(id).hidden=!text;}
 function pager(id,page,pages,onPage){const root=el(id);root.replaceChildren();for(const [label,target]of [['Previous',page-1],[page,page],['Next',page+1]]){const button=document.createElement('button');button.type='button';button.textContent=label;button.className='btn g';button.disabled=target<1||target>pages||label===page;button.onclick=()=>onPage(target);root.append(button);}}
 function badge(status){return '<span class="mail-status" data-status="'+esc(status)+'">'+esc(status==='Prepared'?'Not sent':status)+'</span>';}
 async function init(){if(initializing)return initializing;initializing=(async()=>{catalogue=await api('catalogue');updateTransportNotice();for(const id of ['template-category','template-filter-category'])for(const [value,label]of Object.entries(catalogue.categories))el(id).append(new Option(label,value));for(const [name,description]of Object.entries(catalogue.variables)){const button=document.createElement('button');button.type='button';const text='{{'+name+'}}';button.textContent=description;button.title='Add this detail to your message';button.onclick=()=>{lastField.setRangeText(text,lastField.selectionStart,lastField.selectionEnd,'end');lastField.focus();dirty=true;};el('template-variables').append(button);}})();try{await initializing;}catch(e){initializing=null;throw e;}}
 async function getTemplates(){templates=(await api('templates')).templates;return templates;}
 let saved={};
 function remember(){saved={search:el('mail-search').value,status:el('mail-status').value,page:mailPage,archived:mailArchived,template_search:el('template-search').value,category:el('template-filter-category').value,view:el('template-filter-view').value};}
 try{sessionStorage.removeItem('cif-email-filters');}catch{}
 function resetFilters(target){
  if(target==='templates'){el('template-search').value='';el('template-filter-category').value='';el('template-filter-view').value='active';}
  if(target==='mail'){listRequest++;clearTimeout(listTimer);mailPage=1;mailArchived=false;el('mail-search').value='';el('mail-status').value='';}
  remember();
 }
 function renderTemplates(){
  remember();const term=el('template-search').value.toLocaleLowerCase(),archived=el('template-filter-view').value==='archived',category=el('template-filter-category').value,rows=templates.filter(t=>Boolean(Number(t.archived))===archived&&(!category||t.category===category)&&(t.name+' '+t.subject).toLocaleLowerCase().includes(term));
  el('template-rows').innerHTML=rows.map(t=>'<tr><td><strong>'+esc(t.name)+'</strong><small>'+esc(t.subject)+'</small></td><td>'+esc(catalogue.categories[t.category])+'</td><td>'+tableDateTime(t.updated_at)+'<small>'+esc(t.editor||'Starter template')+'</small></td><td>'+(!archived?'<button data-template-edit="'+t.id+'">Edit</button><button data-template-use="'+t.id+'">Use template</button>':'')+'<button data-template-archive="'+t.id+'">'+(archived?'Restore':'Archive')+'</button></td></tr>').join('')||'<tr><td colspan="4">No templates match your search.</td></tr>';
  el('template-rows').querySelectorAll('[data-template-archive]').forEach(button=>button.classList.toggle('action-muted',!archived));
  el('template-info').textContent=rows.length+' matching template(s)';el('template-feedback').textContent='';
 }
 async function loadTemplates(){try{el('template-feedback').textContent='Loading templates…';await init();if(saved.category){el('template-filter-category').value=saved.category;saved.category=null;}await getTemplates();renderTemplates();}catch(e){el('template-feedback').textContent=e.message;}}
 async function loadMail(){
  const request=++listRequest;remember();el('mail-active-view').setAttribute('aria-pressed',String(!mailArchived));el('mail-archived-view').setAttribute('aria-pressed',String(mailArchived));el('mail-list-title').textContent=mailArchived?'Archived emails':'Recent emails';el('mail-feedback').textContent='Loading messages…';el('mail-rows').setAttribute('aria-busy','true');
  try{await init();const data=await api('messages',{search:el('mail-search').value,status:el('mail-status').value,page:mailPage,archived:mailArchived});if(request!==listRequest)return;mailRows=data.messages;mailPage=data.page;remember();el('mail-stats').replaceChildren();for(const [status,count]of Object.entries(data.counts)){const button=document.createElement('button');button.type='button';button.className='card stat';button.style.setProperty('--c',status==='Sent'?'#00a6a6':status==='Failed'?'#b54759':'#147dbb');button.setAttribute('aria-pressed',String(el('mail-status').value===status));const number=document.createElement('b'),label=document.createElement('span');number.textContent=count;label.textContent=status==='Prepared'?'Not sent':status;button.append(number,label);button.onclick=()=>{el('mail-status').value=el('mail-status').value===status?'':status;mailPage=1;loadMail();};el('mail-stats').append(button);}
   el('mail-rows').innerHTML=data.messages.map(m=>'<tr><td><strong>'+esc(m.recipient_name)+'</strong><small>'+esc(m.recipient_email)+'</small></td><td>'+esc(m.subject)+'<small>'+esc(m.template_name)+'</small></td><td>'+badge(m.status)+(Number(m.is_preview)?'<small>Preview record · No email sent</small>':'')+(m.last_error?'<small>'+esc(m.last_error)+'</small>':'')+'</td><td>'+esc(m.creator)+'</td><td>'+tableDateTime(m.created_at)+'</td><td><button data-mail-detail="'+m.id+'">View</button><button data-mail-archive="'+m.id+'">'+(mailArchived?'Restore':'Archive')+'</button></td></tr>').join('')||'<tr><td colspan="6">No messages match your search.</td></tr>';
   el('mail-rows').querySelectorAll('[data-mail-archive]').forEach(button=>button.classList.toggle('action-muted',!mailArchived));
   el('mail-info').textContent=data.total?'Showing '+((data.page-1)*8+1)+'–'+Math.min(data.page*8,data.total)+' of '+data.total:'0 messages';pager('mail-pages',data.page,data.pages,p=>{mailPage=p;loadMail();});el('mail-feedback').textContent='';
   const url=new URL(location.href),candidate=Number(url.searchParams.get('mail_candidate')),invitation=Number(url.searchParams.get('mail_invitation'));if(candidate||invitation){url.searchParams.delete('mail_candidate');url.searchParams.delete('mail_invitation');history.replaceState(null,'',url);await openComposer({kind:candidate?'candidate':'invitation',id:candidate||invitation});}
  }catch(e){if(request===listRequest)el('mail-feedback').textContent=e.message;}finally{if(request===listRequest)el('mail-rows').removeAttribute('aria-busy');}
 }

 function updateTransportNotice(){
  for(const notice of document.querySelectorAll('.mail-notice')){
   const templatesPage=Boolean(notice.closest('#templates'));
   notice.querySelector('strong').textContent=catalogue.sending_enabled?(templatesPage?'Using email templates':'Sending emails'):'Email sending unavailable';
   const paragraphs=notice.querySelectorAll('p');paragraphs[0].textContent=catalogue.sending_enabled
    ?(templatesPage?'Edit a saved message or choose Use template to send it to a candidate.':'Review the recipient and message, then select Submit to send. Check the email status below for the result.')
    :(templatesPage?'You can still edit templates. Contact your system administrator for help with sending emails.':'You can still view past emails. Contact your system administrator for help with sending emails.');
   for(const p of [...paragraphs].slice(1))p.remove();
  }
 }
 function updateSendButton(events=[]){
  const b=el('mail-detail-send'),unresolved=events.at(-1)?.event==='Sending';
  b.textContent=detail.status==='Prepared'?'Send email':'Resend email';
  b.disabled=busy||!catalogue?.sending_enabled||Boolean(detail.archived_at)||Number(detail.is_preview)!==0||unresolved||!['Prepared','Sent','Failed'].includes(detail.status);
  el('mail-transport-help').textContent=detail.archived_at?'Restore this email before resending.':unresolved?'We could not confirm whether this email was sent. Check with the recipient before trying again.':!catalogue?.sending_enabled?'Email sending is currently unavailable. Contact your system administrator for help.':'Resending sends the same message again. Sent does not confirm that the recipient received or read it.';
 }
 el('mail-detail').addEventListener('cancel',e=>{if(busy)e.preventDefault();});
 el('mail-detail-send').onclick=async()=>{
  if(busy||!detail||el('mail-detail-send').disabled)return;
  const resend=detail.status!=='Prepared';
  if(!window.confirm((resend?'Resend this email? The recipient may receive another copy. Check with them first if you are unsure whether it arrived.':'Send this saved email now?')+'\nRecipient: '+detail.recipient_email))return;
  const id=Number(detail.id);
  if(!resendPending||resendPending.id!==id)resendPending={id,revision:Number(detail.revision),request_key:key()};
  busy=true;el('mail-detail-send').disabled=true;
  let completed=false;
  try{const result=await api(resend?'resend':'send',resendPending);completed=true;resendPending=null;el('mail-detail').close();detail=null;toast(result.message);await loadMail();}
  catch(e){toast(e.message);}
  finally{busy=false;if(!completed)await showDetail(id);}
 };
 function closeEditor(){if(busy)return;if(dirty&&!window.confirm('Discard unsaved template changes?'))return;dirty=false;el('template-editor').close();}
 async function editTemplate(id){if(busy)return;await init();editing=templates.find(t=>Number(t.id)===Number(id)&&!Number(t.archived));if(!editing)throw new Error('Choose an existing active template to edit.');el('template-form').reset();el('template-name').value=editing.name;el('template-category').value=editing.category;el('template-subject').value=editing.subject;el('template-body').value=editing.body;el('template-editor-title').textContent='Edit email template';error('template-error');dirty=false;el('template-editor').showModal();el('template-name').focus();}
 el('template-form').oninput=()=>dirty=true;for(const id of ['template-subject','template-body'])el(id).onfocus=()=>lastField=el(id);
 el('template-close').onclick=closeEditor;el('template-editor').addEventListener('cancel',event=>{event.preventDefault();closeEditor();});window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
 el('template-form').onsubmit=async e=>{e.preventDefault();if(busy||!editing)return;busy=true;el('template-form').querySelectorAll('button,input,select,textarea').forEach(c=>c.disabled=true);error('template-error');try{await api('template_save',{id:Number(editing.id),revision:Number(editing.revision),name:el('template-name').value,category:el('template-category').value,subject:el('template-subject').value,body:el('template-body').value});dirty=false;el('template-editor').close();await loadTemplates();toast('Email template saved.');}catch(e){error('template-error',e.message);}finally{busy=false;el('template-form').querySelectorAll('button,input,select,textarea').forEach(c=>c.disabled=false);}};
 el('template-rows').onclick=async event=>{const b=event.target.closest('button');if(!b)return;try{if(b.dataset.templateEdit)await editTemplate(b.dataset.templateEdit);else if(b.dataset.templateUse)await openComposer({templateId:Number(b.dataset.templateUse)});else{const t=templates.find(t=>Number(t.id)===Number(b.dataset.templateArchive));if(!t||!window.confirm((Number(t.archived)?'Restore':'Archive')+' this template? Existing messages will not change.'))return;await api('template_archive',{id:Number(t.id),revision:Number(t.revision),archived:!Number(t.archived)});await loadTemplates();}}catch(e){toast(e.message);}};
 function composerMode(invitation){
  el('mail-composer-options').hidden=invitation;
  el('mail-process-tag').hidden=!invitation;
  el('mail-selected').hidden=invitation;
  el('mail-preview').hidden=invitation;
  el('mail-preview-subject').hidden=invitation;el('mail-preview-body').hidden=invitation;
  el('mail-invitation-editor').hidden=true;
  el('mail-compose-title').textContent=invitation?'Send invitation code':'Send email';
 }
 for(const id of ['mail-edit-subject','mail-edit-body'])el(id).oninput=()=>{preparationKey=key();};
 function invalidate(){++previewRequest;preparationKey=key();preview=null;el('mail-preview-panel').hidden=true;el('mail-invitation-editor').hidden=true;el('mail-prepare').disabled=true;error('mail-compose-error');}
 async function recipients(){const request=++recipientRequest;try{const data=await api('recipients',{kind:el('mail-recipient-kind').value,search:el('mail-recipient-search').value});if(request!==recipientRequest)return;el('mail-recipient-results').replaceChildren();for(const person of data.recipients){const button=document.createElement('button');button.type='button';button.textContent=person.name+' · '+person.email;const small=document.createElement('small');small.textContent=person.reference||'';button.append(small);button.onclick=()=>{selected={kind:el('mail-recipient-kind').value,id:Number(person.id)};el('mail-selected').textContent='Selected: '+person.name+' · '+person.email;el('mail-recipient-results').replaceChildren();invalidate();showPreview();};el('mail-recipient-results').append(button);}if(!data.recipients.length)el('mail-recipient-results').textContent='No registered recipients match your search.';}catch(e){error('mail-compose-error',e.message);}}
 async function openComposer(options={}){if(busy)return;await init();await getTemplates();selected=options.id?{kind:options.kind,id:Number(options.id)}:null;preview=null;preparationKey=key();invalidate();el('mail-template').replaceChildren(new Option('Choose a template',''),...templates.filter(t=>!Number(t.archived)).map(t=>new Option(t.name,t.id)));const initial=options.templateId?templates.find(t=>Number(t.id)===options.templateId):templates.find(t=>t.category===(options.kind==='invitation'?'invitation':'general')&&!Number(t.archived));if(initial)el('mail-template').value=initial.id;composerMode(options.kind==='invitation');el('mail-recipient-kind').value=options.kind||'candidate';el('mail-recipient-search').value='';el('mail-selected').textContent=selected?'Registered recipient selected. Preview to verify the current email address.':'Choose a recipient from the results.';el('mail-recipient-results').replaceChildren();if(selected)await showPreview();else await recipients();options.beforeShow?.();el('mail-composer').showModal();}
 async function showPreview(){invalidate();composerMode(selected?.kind==='invitation');if(selected?.kind==='invitation'){const t=templates.find(t=>t.slug==='invitation'&&!Number(t.archived));if(!t){error('mail-compose-error','The CIF invitation template is unavailable. Restore it in Email templates.');return;}el('mail-template').value=t.id;}const request=previewRequest;if(!selected){error('mail-compose-error','Select a registered recipient from the results.');return;}try{const result=(await api('preview',{template_id:Number(el('mail-template').value),target_kind:selected.kind,target_id:selected.id})).preview;if(request!==previewRequest)return;preview=result;el('mail-preview-to').textContent=preview.recipient_name+' <'+preview.recipient_email+'>';el('mail-preview-subject').textContent=preview.subject;el('mail-preview-body').textContent=preview.body;el('mail-preview-panel').hidden=false;if(selected.kind==='invitation'){el('mail-edit-subject').value=preview.subject;el('mail-edit-body').value=preview.body;el('mail-invitation-editor').hidden=false;}el('mail-prepare').disabled=!catalogue.sending_enabled;if(!catalogue.sending_enabled)error('mail-compose-error','Email sending is currently unavailable. Contact your system administrator for help.');}catch(e){if(request===previewRequest)error('mail-compose-error',e.message);}}
 el('mail-preview').onclick=showPreview;el('mail-template').onchange=()=>{invalidate();if(selected)showPreview();};el('mail-recipient-kind').onchange=()=>{selected=null;invalidate();el('mail-selected').textContent='Choose a recipient from the results.';recipients();};el('mail-recipient-search').oninput=()=>{selected=null;invalidate();el('mail-selected').textContent='Choose a recipient from the results.';++recipientRequest;clearTimeout(recipientTimer);recipientTimer=setTimeout(recipients,250);};
 el('mail-compose').onclick=()=>openComposer().catch(e=>toast(e.message));el('mail-compose-close').onclick=()=>{if(!busy)el('mail-composer').close();};el('mail-composer').addEventListener('cancel',e=>{if(busy)e.preventDefault();});
 el('mail-prepare').onclick=async()=>{
  if(busy||!preview||!catalogue.sending_enabled)return;if(selected?.kind==='invitation'&&(!el('mail-edit-subject').reportValidity()||!el('mail-edit-body').reportValidity()))return;
  busy=true;error('mail-compose-error');el('mail-prepare').textContent='Sending…';
  el('mail-composer').querySelectorAll('button,input,select,textarea').forEach(c=>c.disabled=true);
  try{
   const result=await api('submit',{template_id:preview.template_id,target_kind:preview.target_kind,target_id:preview.target_id,preview_hash:preview.preview_hash,preparation_key:preparationKey,...(selected?.kind==='invitation'?{subject:el('mail-edit-subject').value,body:el('mail-edit-body').value,reviewed_recipient_email:preview.recipient_email}:{})});
   if(result.status!=='Sent'){preview=null;error('mail-compose-error',(result.message||'Email was not sent.')+' See Mail management for details before retrying.');return;}
   el('mail-composer').close();window.cifAdminPage.navigate('mail');toast(result.message);
  }catch(e){error('mail-compose-error',e.message);}
  finally{busy=false;el('mail-composer').querySelectorAll('button,input,select,textarea').forEach(c=>c.disabled=false);el('mail-prepare').textContent='Submit';el('mail-prepare').disabled=!preview||!catalogue.sending_enabled;}
 };
 async function showDetail(id){
  try{
   await init();const data=await api('detail',{id:Number(id)});detail=data.message;
   el('mail-detail-subject').textContent=detail.subject;el('mail-detail-body').textContent=detail.body;
   el('mail-detail-meta').textContent=(detail.archived_at?'Archived · ':'')+detail.status+' · To '+detail.recipient_email+' · Created by '+detail.creator+' · '+date(detail.created_at)+(detail.sent_at?' · Sent '+date(detail.sent_at):'')+(detail.last_error?' · '+detail.last_error:'');
   updateSendButton(data.events);if(!el('mail-detail').open)el('mail-detail').showModal();
  }catch(e){toast(e.message);}
 }
 el('mail-rows').onclick=e=>{
  const view=e.target.closest('[data-mail-detail]');if(view){showDetail(view.dataset.mailDetail);return;}
  const archive=e.target.closest('[data-mail-archive]');if(!archive||busy)return;
  const row=mailRows.find(m=>Number(m.id)===Number(archive.dataset.mailArchive));if(!row)return;
  archivePending={id:Number(row.id),revision:Number(row.revision),archived:!mailArchived};
  el('mail-archive-title').textContent=mailArchived?'Restore email?':'Archive email?';
  el('mail-archive-description').textContent=row.subject+' — '+row.recipient_email+'. '+(mailArchived?'Return this email to the active list?':'Move this email to Archived? Its message and sending result will be kept.');
  el('mail-archive-submit').textContent=mailArchived?'Restore email':'Archive email';el('mail-archive-submit').classList.toggle('action-muted',!mailArchived);error('mail-archive-error');el('mail-archive-confirmation').showModal();
 };
 el('mail-detail-close').onclick=()=>{if(!busy)el('mail-detail').close();};
 el('mail-archive-cancel').onclick=()=>{if(!busy)el('mail-archive-confirmation').close();};
 el('mail-archive-confirmation').addEventListener('cancel',e=>{if(busy)e.preventDefault();});
 el('mail-archive-submit').onclick=async()=>{
  if(busy||!archivePending)return;busy=true;
  el('mail-archive-submit').disabled=true;el('mail-archive-cancel').disabled=true;error('mail-archive-error');
  try{const result=await api('archive',archivePending);el('mail-archive-confirmation').close();await loadMail();toast(result.message);}
  catch(e){error('mail-archive-error',e.message);}
  finally{busy=false;el('mail-archive-submit').disabled=false;el('mail-archive-cancel').disabled=false;}
 };
 el('mail-active-view').onclick=()=>{mailArchived=false;mailPage=1;loadMail();};
 el('mail-archived-view').onclick=()=>{mailArchived=true;mailPage=1;loadMail();};
 for(const id of ['template-search','template-filter-category','template-filter-view'])el(id).addEventListener(id==='template-search'?'input':'change',renderTemplates);el('mail-search').oninput=()=>{mailPage=1;remember();++listRequest;clearTimeout(listTimer);listTimer=setTimeout(loadMail,250);};el('mail-status').onchange=()=>{mailPage=1;loadMail();};el('templates-refresh').onclick=()=>cifRefreshPage(el('templates-refresh'));el('mail-refresh').onclick=()=>cifRefreshPage(el('mail-refresh'));
 resetFilters('templates');resetFilters('mail');
 window.cifMail={loadTemplates,loadMail,resetFilters,openComposer,openInvitation:async email=>{const result=await api('recipients',{kind:'invitation',search:email});const person=result.recipients.find(p=>p.email.toLowerCase()===email.toLowerCase());if(!person)throw new Error('No valid invitation was found. Refresh Invitations.');return openComposer({kind:'invitation',id:Number(person.id)});},reset:()=>{resetFilters('templates');resetFilters('mail');for(const id of ['template-editor','mail-composer','mail-detail','mail-archive-confirmation'])el(id).close();dirty=false;templates=[];selected=null;preview=null;detail=null;el('mail-preview-body').textContent='';el('mail-detail-body').textContent='';el('template-body').value='';},canLeave:()=>!dirty||window.confirm('Discard unsaved email template changes?')};
})();
