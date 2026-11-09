(()=>{
 const roots=[...document.querySelectorAll('[data-candidate-mrf]')];if(!roots.length)return;
 const endpoint=new URL('../ajax/mrf.php',document.currentScript.src),pickers=new Map();
 window.cifCandidateMrf={validate:(scope='initial_interview')=>pickers.get(scope)?.validate()??true};
 roots.forEach(root=>{
 const prefix=root.dataset.mrfPrefix,scope=root.dataset.mrfScope,required=root.dataset.mrfRequired==='true',el=suffix=>document.getElementById(prefix+'-'+suffix);
 const search=el('search'),hidden=el('id'),options=el('options'),selected=el('selected'),clear=el('clear'),error=el('error');let timer,request=0,controller,active=-1,rows=[],attempted=false;
 function validity(){
  const empty=!hidden.value&&!search.value.trim(),invalid=(required||!empty)&&(!hidden.value||!search.value.trim()),text=required?'MRF is required. Choose an approved MRF from the available options.':'Choose an approved MRF from the results, or clear the field to leave it blank.';
  search.setCustomValidity(invalid?text:'');clear.disabled=!hidden.value&&!search.value.trim();
  error.textContent=invalid&&attempted?text:'';error.hidden=!error.textContent;
  if(invalid&&attempted)search.setAttribute('aria-invalid','true');else search.removeAttribute('aria-invalid');
  const mark=el('required');if(mark)mark.hidden=!invalid;
  return !invalid;
 }
 search.addEventListener('invalid',()=>{attempted=true;validity();});
 pickers.set(scope,{
  validate:()=>{attempted=true;if(validity())return true;window.cifRecordTabs?.show(scope);search.scrollIntoView({block:'center'});search.reportValidity();return false;},
  set:row=>{hidden.value=String(row.id);search.value=row.mrf_number;selected.textContent=`${row.mrf_number} · ${row.position_title} · ${row.department_name} · Approved · Recruitment: ${row.status}`;validity();collapse();},
  clear:()=>{hidden.value='';search.value='';selected.textContent='No MRF selected';validity();collapse();}
 });
 function collapse(){options.hidden=true;search.setAttribute('aria-expanded','false');search.removeAttribute('aria-activedescendant');search.parentElement.classList.remove('candidate-mrf-open-up');active=-1;}
 function positionOptions(){
  if(options.hidden)return;const box=search.getBoundingClientRect(),viewport=window.visualViewport,top=(viewport?.offsetTop||0)+90,bottom=(viewport?.offsetTop||0)+(viewport?.height||innerHeight),saveBar=document.querySelector('.save-bar').getBoundingClientRect();
  const below=Math.max(0,Math.min(bottom,saveBar.top)-box.bottom-8),above=Math.max(0,box.top-top-8),up=below<180&&above>below;
  search.parentElement.classList.toggle('candidate-mrf-open-up',up);options.style.maxHeight=Math.max(90,Math.min(innerWidth<=560?220:280,up?above:below))+'px';
 }
 function change(){hidden.dispatchEvent(new Event('change',{bubbles:true}));}
 function clearValue(){const changed=hidden.value!=='';hidden.value='';selected.textContent='No MRF selected';clear.disabled=true;for(const [key,picker]of pickers)if(key!==scope)picker.clear();if(changed)change();}
 function setActive(index){const buttons=[...options.querySelectorAll('[role="option"]')];active=index;buttons.forEach((button,number)=>button.setAttribute('aria-selected',String(number===active)));if(buttons[active]){search.setAttribute('aria-activedescendant',buttons[active].id);buttons[active].scrollIntoView({block:'nearest'});}else search.removeAttribute('aria-activedescendant');}
 function choose(index){const row=rows[index];if(!row)return;for(const picker of pickers.values())picker.set(row);search.focus({preventScroll:true});change();}
 function note(text){options.replaceChildren();const item=document.createElement('li');item.className='candidate-mrf-note';item.setAttribute('role','presentation');item.textContent=text;options.append(item);}
 async function load(){
  const current=++request;controller?.abort();controller=new AbortController();const signal=controller.signal;
  rows=[];active=-1;search.removeAttribute('aria-activedescendant');options.hidden=false;search.setAttribute('aria-expanded','true');options.setAttribute('aria-busy','true');note('Searching registered MRFs…');positionOptions();
  try{
   const state=await cifReady;if(signal.aborted)return;
   const response=await fetch(endpoint.href+'?portal=admin&action=candidate_options',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body:JSON.stringify({search:search.value.trim()}),signal}),result=await response.json();
   if(!response.ok)throw new Error(result.error||'Unable to search requests.');if(current!==request)return;rows=result.requests;options.replaceChildren();
   if(!rows.length)note('No approved, available MRF matches. Pending approval and Denied requests cannot be selected.');
   rows.forEach((row,index)=>{const item=document.createElement('li'),button=document.createElement('button'),title=document.createElement('strong'),detail=document.createElement('span');item.setAttribute('role','presentation');button.type='button';button.id=prefix+'-option-'+index;button.setAttribute('role','option');button.setAttribute('aria-selected','false');title.textContent=`${row.mrf_number} · ${row.position_title}`;detail.textContent=`${row.department_name} · ${row.site_location} · Approved · Recruitment: ${row.status}`;button.append(title,detail);button.addEventListener('mousedown',event=>event.preventDefault());button.onclick=()=>choose(index);item.append(button);options.append(item);});
   if(result.more){const item=document.createElement('li');item.className='candidate-mrf-note';item.setAttribute('role','presentation');item.textContent='Showing 20 matches. Refine your search for more specific results.';options.append(item);}
  }catch(error){if(error.name!=='AbortError'&&current===request)note(error.message+' Click the search field to retry.');}
  finally{if(current===request)options.removeAttribute('aria-busy');}
 }
 search.addEventListener('input',()=>{clearValue();validity();clearTimeout(timer);request++;controller?.abort();rows=[];active=-1;search.removeAttribute('aria-activedescendant');options.hidden=false;search.setAttribute('aria-expanded','true');options.setAttribute('aria-busy','true');note('Searching registered MRFs…');positionOptions();timer=setTimeout(load,250);});
 search.addEventListener('focus',()=>{clearTimeout(timer);if(search.getBoundingClientRect().bottom>document.querySelector('.save-bar').getBoundingClientRect().top-16)search.scrollIntoView({block:'center'});load();});
 search.addEventListener('click',()=>{if(options.hidden){clearTimeout(timer);load();}});
 search.addEventListener('keydown',event=>{
  if(event.key==='Escape'){event.preventDefault();clearTimeout(timer);request++;controller?.abort();collapse();}
  else if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();if(options.hidden){load();return;}if(rows.length)setActive(event.key==='ArrowDown'?(active+1)%rows.length:(active<0?rows.length-1:(active-1+rows.length)%rows.length));}
  else if(event.key==='Enter'){event.preventDefault();if(!options.hidden&&active>=0)choose(active);}
  else if(event.key==='Tab'){clearTimeout(timer);request++;controller?.abort();collapse();}
 });
 clear.onclick=()=>{clearTimeout(timer);clearValue();search.value='';validity();collapse();search.focus();};
 document.addEventListener('pointerdown',event=>{if(!root.contains(event.target)){clearTimeout(timer);request++;controller?.abort();collapse();}});
 validity();
 window.addEventListener('resize',positionOptions);window.addEventListener('scroll',positionOptions,{passive:true});window.visualViewport?.addEventListener('resize',positionOptions);window.visualViewport?.addEventListener('scroll',positionOptions);
 });
})();
