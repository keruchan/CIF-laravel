(()=>{
 const pickers=new WeakMap();
 document.querySelectorAll('[data-position-picker]').forEach(input=>{
  const titles=JSON.parse(input.dataset.positionOptions),options=document.getElementById(input.getAttribute('aria-controls'));
  let selected=titles.includes(input.value)?input.value:'',rows=[],active=-1,choosing=false;
  function valid(){const message=input.value.trim()&&input.value!==selected?'Choose an available applied position from the search results.':(input.required&&!input.value.trim()?'Choose an applied position.':'');input.setCustomValidity(message);return !message;}
  function close(){options.hidden=true;input.setAttribute('aria-expanded','false');input.removeAttribute('aria-activedescendant');active=-1;}
  function layout(){if(options.hidden)return;const box=input.getBoundingClientRect(),bottom=(window.visualViewport?.height||innerHeight),below=bottom-box.bottom-16,above=box.top-90,up=below<180&&above>below;input.parentElement.classList.toggle('position-picker-up',up);options.style.maxHeight=Math.max(90,Math.min(260,up?above:below))+'px';}
  function set(value,notify=false){input.value=value;selected=titles.includes(value)?value:'';valid();close();if(notify){choosing=true;input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}));choosing=false;}}
  function choose(index){if(!rows[index])return;set(rows[index],true);input.focus({preventScroll:true});}
  function show(){
   if(input.disabled||input.readOnly||input.closest('fieldset[disabled]'))return;
   const search=input.value===selected?'':input.value.trim().toLocaleLowerCase();rows=titles.filter(title=>title.toLocaleLowerCase().includes(search));options.replaceChildren();active=-1;input.removeAttribute('aria-activedescendant');options.hidden=false;input.setAttribute('aria-expanded','true');
   if(!rows.length){const note=document.createElement('li');note.className='position-picker-note';note.setAttribute('role','presentation');note.textContent='No available position matches. Choose a title from the official list.';options.append(note);}
   rows.forEach((title,index)=>{const item=document.createElement('li'),button=document.createElement('button');item.setAttribute('role','presentation');button.type='button';button.id=input.id+'-option-'+index;button.setAttribute('role','option');button.setAttribute('aria-selected','false');button.textContent=title;button.addEventListener('mousedown',event=>event.preventDefault());button.onclick=()=>choose(index);item.append(button);options.append(item);});layout();
  }
  input.addEventListener('input',()=>{if(choosing)return;selected='';valid();show();});input.addEventListener('focus',show);input.addEventListener('click',()=>{if(options.hidden)show();});
  input.addEventListener('keydown',event=>{if(event.key==='Escape'){event.preventDefault();close();}else if(event.key==='Tab')close();else if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();if(options.hidden){show();return;}if(!rows.length)return;active=event.key==='ArrowDown'?(active+1)%rows.length:(active<0?rows.length-1:(active-1+rows.length)%rows.length);options.querySelectorAll('[role="option"]').forEach((button,index)=>{button.setAttribute('aria-selected',String(index===active));if(index===active){input.setAttribute('aria-activedescendant',button.id);button.scrollIntoView({block:'nearest'});}});}else if(event.key==='Enter'&&!options.hidden){event.preventDefault();if(active>=0)choose(active);}});
  document.addEventListener('pointerdown',event=>{if(!input.parentElement.contains(event.target))close();});input.form?.addEventListener('reset',()=>queueMicrotask(()=>set(input.value)));input.form?.addEventListener('submit',valid,true);window.addEventListener('resize',layout);window.addEventListener('scroll',layout,{passive:true});pickers.set(input,{set,valid});valid();
 });
 window.cifPositionPickers={set:(input,value)=>pickers.get(input)?.set(value),refresh:root=>(root||document).querySelectorAll('[data-position-picker]').forEach(input=>pickers.get(input).set(input.value)),validate:root=>[...(root||document).querySelectorAll('[data-position-picker]')].map(input=>pickers.get(input).valid()).every(Boolean)};
})();
