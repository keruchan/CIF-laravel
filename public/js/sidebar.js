// Mobile drawer and independent desktop collapse preference for both portals.
(()=>{
 const sidebar=document.getElementById('portal-sidebar'),burger=document.getElementById('burger'),backdrop=document.getElementById('sidebar-backdrop');
 if(!sidebar||!burger||!backdrop)return;
 const mobile=matchMedia('(max-width:900px)'),content=document.querySelector('main .pg'),header=document.querySelector('main header');
 const desktopToggle=sidebar.querySelector('.sidebar-desktop-toggle'),portalPath=location.pathname.replace(/\/[^/]+\.php$/, '/'),storageKey='cif-sidebar-collapsed:'+portalPath;
 let desktopCollapsed=false;
 try{desktopCollapsed=sessionStorage.getItem(storageKey)==='1';}catch(error){/* Collapsing still works without storage. */}
 function animateToggle(button){
  if(matchMedia('(prefers-reduced-motion:reduce)').matches)return;
  button.animate([{transform:'rotate(0deg) scale(1)'},{transform:'rotate(-12deg) scale(.88)'},{transform:'rotate(0deg) scale(1)'}],{duration:220,easing:'ease-out'});
 }
 function syncDesktop(){
  document.body.classList.toggle('sidebar-collapsed',desktopCollapsed);
  if(desktopToggle)desktopToggle.setAttribute('aria-expanded',String(!desktopCollapsed));
  if(!mobile.matches){sidebar.inert=desktopCollapsed;burger.setAttribute('aria-expanded',String(!desktopCollapsed));burger.setAttribute('aria-label',desktopCollapsed?'Show sidebar':'Hide sidebar');}
 }
 function toggleDesktop(button){
  desktopCollapsed=!desktopCollapsed;try{sessionStorage.setItem(storageKey,desktopCollapsed?'1':'0');}catch(error){}
  syncDesktop();animateToggle(desktopCollapsed?burger:desktopToggle||button);
  (desktopCollapsed?burger:desktopToggle||burger).focus();
 }
 function setOpen(open,restoreFocus=false){
  open=Boolean(open&&mobile.matches&&!document.getElementById('app').classList.contains('hide'));
  document.body.classList.toggle('nav',open);burger.setAttribute('aria-expanded',String(open));burger.setAttribute('aria-label',open?'Hide sidebar':'Show sidebar');
  backdrop.hidden=!open;sidebar.inert=mobile.matches&&!open;if(content)content.inert=open;
  syncDesktop();
  if(restoreFocus&&mobile.matches)burger.focus();
 }
 window.cifSidebar={close:(restoreFocus=false)=>setOpen(false,restoreFocus)};
 burger.addEventListener('click',()=>{if(mobile.matches)setOpen(!document.body.classList.contains('nav'));else toggleDesktop(burger);});
 desktopToggle?.addEventListener('click',()=>{if(!mobile.matches)toggleDesktop(desktopToggle);});
 backdrop.addEventListener('click',()=>setOpen(false,true));
 document.addEventListener('keydown',event=>{
  if(!document.body.classList.contains('nav'))return;
  if(event.key==='Escape'){event.preventDefault();setOpen(false,true);return;}
  if(event.key!=='Tab')return;
  const controls=[burger,...sidebar.querySelectorAll('button:not(:disabled),a[href],input:not(:disabled),[tabindex="0"]')].filter(element=>element.getClientRects().length);
  const index=controls.indexOf(document.activeElement),next=event.shiftKey?(index<=0?controls.length-1:index-1):(index+1)%controls.length;
  event.preventDefault();controls[next].focus();
 });
 mobile.addEventListener('change',()=>setOpen(false));
 const updateHeader=()=>document.documentElement.style.setProperty('--mobile-header-height',header.getBoundingClientRect().height+'px');
 new ResizeObserver(updateHeader).observe(header);updateHeader();setOpen(false);
})();
