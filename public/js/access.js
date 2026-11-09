function cifFormatInvitationCode(value) {
 const raw=String(value??'').replace(/[\s-]+/g,'').toUpperCase();
 return raw.length===8?raw.slice(0,4)+'-'+raw.slice(4):raw;
}
let cifState;
function cifYearError(field) {
 if(field.value==='')return '';
 if(!/^[0-9]{4}$/.test(field.value))return 'Enter a four-digit year (YYYY).';
 if(Number(field.value)<1900||Number(field.value)>Number(field.dataset.yearMax))return 'Enter a year from 1900 to '+field.dataset.yearMax+'.';
 const from=field.dataset.educationYear==='to'?field.closest('[data-education-years]')?.querySelector('[data-education-year="from"]'):null;
 return from&&/^[0-9]{4}$/.test(from.value)&&Number(field.value)<Number(from.value)?'To year cannot be earlier than From year.':'';
}
function cifRefreshEducationYearRanges(root=document){
 root.querySelectorAll('[data-education-years]').forEach(group=>{
  const stored=group.querySelector('[data-education-years-value]').value.trim(),parts=stored.match(/^(\d{0,4})\s*(?:[-–—/]|to)\s*(\d{0,4})$/i),legacy=stored.match(/^(\d{4})(?:\s*(?:[-–—/]|to)\s*(?:present|current|ongoing))?$/i);
  group.querySelector('[data-education-year="from"]').value=parts?.[1]||legacy?.[1]||'';
  group.querySelector('[data-education-year="to"]').value=parts?.[2]||'';
  group.querySelectorAll('[data-year]').forEach(field=>field.setCustomValidity(cifYearError(field)));
 });
}
document.addEventListener('input',event=>{
 const field=event.target;if(!field.matches('[data-year]'))return;
 const caret=field.selectionStart??field.value.length,position=field.value.slice(0,caret).replace(/[^0-9]/g,'').length;
 field.value=field.value.replace(/[^0-9]/g,'').slice(0,4);
 field.setSelectionRange(Math.min(position,4),Math.min(position,4));field.setCustomValidity(cifYearError(field));
 const group=field.closest('[data-education-years]');if(group){
  const from=group.querySelector('[data-education-year="from"]').value,to=group.querySelector('[data-education-year="to"]').value;
  group.querySelector('[data-education-years-value]').value=from||to?from+'–'+to:'';
  group.querySelectorAll('[data-year]').forEach(input=>input.setCustomValidity(cifYearError(input)));
 }
},true);
cifRefreshEducationYearRanges();
document.querySelectorAll('[data-year]').forEach(field=>field.setCustomValidity(cifYearError(field)));
function cifFinishSessionCheck() {
 document.getElementById('session-loading')?.classList.add('hide');
 if(document.getElementById('app')?.classList.contains('hide'))document.getElementById('login')?.classList.remove('hide');
}
// All explicit refresh controls use the same current-page reload and feedback.
function cifRefreshPage(button) {
 if(!button||button.disabled||button.dataset.refreshing==='true')return;
 const original=button.innerHTML,loading=document.getElementById('session-loading'),caption=loading?.querySelector('p'),originalCaption=caption?.textContent;
 button.dataset.refreshing='true';button.disabled=true;button.setAttribute('aria-busy','true');
 const spinner=document.createElement('span');spinner.className='refresh-spinner';spinner.setAttribute('aria-hidden','true');button.replaceChildren(spinner,document.createTextNode('Refreshing…'));button.classList.add('refreshing-button');
 if(loading){loading.classList.remove('hide');loading.classList.add('session-loading--refresh');if(caption)caption.textContent='Refreshing your current page…';}
 function restore(){button.innerHTML=original;button.disabled=false;button.removeAttribute('aria-busy');delete button.dataset.refreshing;button.classList.remove('refreshing-button');if(loading){loading.classList.add('hide');loading.classList.remove('session-loading--refresh');if(caption)caption.textContent=originalCaption;}}
 // A canceled unsaved-change warning must not leave the page locked.
 window.addEventListener('beforeunload',event=>{if(event.defaultPrevented)setTimeout(restore,0);},{once:true});
 window.addEventListener('pageshow',event=>{if(event.persisted)restore();});
 requestAnimationFrame(()=>requestAnimationFrame(()=>location.reload()));
}
// Resolve against this shared script so nested pages use the same API endpoint.
const cifAccessEndpoint = new URL(document.currentScript.dataset.endpoint || '../ajax/access.php', document.currentScript.src);
const cifReady = fetch(cifAccessEndpoint.href + '?action=state', {credentials:'same-origin', cache:'no-store'})
 .then(async response => {
   const data = await response.json();
   if (!response.ok) throw new Error(data.error || 'Unable to load session.');
   cifState = data;
   return data;
 });
async function cifRequest(action, input = {}) {
 const state = await cifReady;
 const response = await fetch(cifAccessEndpoint.href + '?action=' + encodeURIComponent(action), {
   method:'POST', credentials:'same-origin',
   headers:{'Content-Type':'application/json', 'X-CSRF-Token':state.csrf},
   body:JSON.stringify(input)
 });
 const data = await response.json();
 if (!response.ok) { const error=new Error(data.error || 'Unable to complete request.');error.status=response.status;throw error; }
 return data;
}
