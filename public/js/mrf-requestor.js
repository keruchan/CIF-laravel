const requestorQuery=selector=>document.querySelector(selector);
let requestorAccount,requestorReady=false,toastTimer,requestorCurrentView=null;
const requestorPage=requestorQuery('#app').dataset.requestorPage||'portal';
const requestorViews={dashboard:['Dashboard','Your manpower requests at a glance.'],mrf:['Manpower requests','Manpower Request Form (MRF).']};
function requestorView(){
 if(!requestorReady)return;
 const requested=location.hash.slice(1),view=requestorPage!=='portal'?requestorPage:Object.hasOwn(requestorViews,requested)?requested:'dashboard';
 if(requestorPage==='portal'&&requestorCurrentView!==view)window.cifMrf?.resetFilters();
 requestorCurrentView=view;
 if(requestorPage==='portal'&&requested!==view)history.replaceState(null,'','#'+view);
 document.querySelectorAll('[data-page]').forEach(page=>page.classList.toggle('hide',page.id!==view));
 const menuView=view.startsWith('mrf-')?'mrf':view;
 document.querySelectorAll('.nv[data-view]').forEach(button=>{button.classList.toggle('on',button.dataset.view===menuView);if(button.dataset.view===menuView)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current');});
 const pageTitles=view==='mrf-form'?['New manpower request','Fill out the form and submit your request to HR.']:view==='mrf-record'?['Manpower request '+requestorQuery('#app').dataset.requestNumber,'View request details, status and HR updates.']:requestorViews[view];
 requestorQuery('#ttl').textContent=pageTitles[0];requestorQuery('#sub').textContent=pageTitles[1];cifSidebar.close();
 if(requestorPage==='portal'&&view==='mrf')cifMrf.load();
 if(requestorPage==='portal'&&view==='dashboard')window.cifRequestorDashboard?.load();
}
function requestorOpen(account){
 requestorAccount=account;requestorReady=true;requestorQuery('#profile-name').textContent=account.name;requestorQuery('#profile-username').textContent=account.username;
 window.cifMrfForm?.updateAccount(account);
 requestorQuery('#login').classList.add('hide');requestorQuery('#app').classList.remove('hide');requestorView();
}
function requestorSignedOut(){
 window.cifRequestorDashboard?.reset();requestorReady=false;requestorAccount=null;requestorCurrentView=null;window.cifMrf?.resetFilters();cifSidebar.close();requestorQuery('#requestor-settings').close();requestorQuery('#app').classList.add('hide');requestorQuery('#login').classList.remove('hide');requestorQuery('#password').value='';requestorQuery('#username').focus();
}
function requestorMessage(selector,message){const element=requestorQuery(selector);element.textContent=message;element.hidden=!message;}
function requestorToast(message){clearTimeout(toastTimer);requestorMessage('#requestor-toast',message);toastTimer=setTimeout(()=>requestorMessage('#requestor-toast',''),5000);}
document.querySelectorAll('[data-view]').forEach(button=>button.addEventListener('click',async()=>{if(requestorPage!=='portal'){if(window.cifMrfForm&&!await cifMrfForm.confirmLeave())return;window.cifMrfForm?.allowNavigation();location.href=new URL('./#'+button.dataset.view,location.href).href;return;}location.hash=button.dataset.view;requestorView();}));
window.addEventListener('hashchange',requestorView);
window.addEventListener('pagehide',()=>{if(requestorPage==='portal')window.cifMrf?.resetFilters();});
window.addEventListener('pageshow',event=>{if(event.persisted&&requestorReady){requestorCurrentView=null;requestorView();}});
requestorQuery('#requestor-login').onsubmit=async event=>{
 event.preventDefault();const button=requestorQuery('#login-submit');button.disabled=true;requestorMessage('#login-error','');
 try{const data=await cifRequest('login',{email:requestorQuery('#username').value,password:requestorQuery('#password').value});requestorQuery('#password').value='';requestorOpen(data.requestor);}
 catch(error){requestorMessage('#login-error',error.message);}finally{button.disabled=false;}
};
requestorQuery('#out').onclick=async()=>{
 const button=requestorQuery('#out');button.disabled=true;
 try{if(window.cifMrfForm&&!await cifMrfForm.confirmLeave())return;await cifRequest('logout');window.cifMrfForm?.discard();requestorSignedOut();requestorToast('You have been signed out.');}catch(error){requestorToast(error.message);}finally{button.disabled=false;}
};
const requestorDialog=requestorQuery('#requestor-settings');
requestorQuery('#requestor-settings-open').onclick=()=>{
 cifSidebar.close();requestorQuery('#settings-form').reset();requestorQuery('#settings-username').value=requestorAccount.username;requestorQuery('#settings-name').value=requestorAccount.name;requestorQuery('#settings-position').value=requestorAccount.position||'';requestorQuery('#settings-department').value=requestorAccount.department_name||'';requestorQuery('#settings-group').value=requestorAccount.group_name||'';requestorMessage('#settings-error','');requestorMessage('#settings-feedback','');requestorDialog.showModal();
};
let requestorSaving=false;
for(const selector of ['#settings-close','#settings-cancel'])requestorQuery(selector).onclick=()=>{if(!requestorSaving)requestorDialog.close();};
requestorDialog.addEventListener('cancel',event=>{if(requestorSaving)event.preventDefault();});
requestorQuery('#settings-form').onsubmit=async event=>{
 event.preventDefault();if(requestorSaving)return;requestorSaving=true;requestorMessage('#settings-error','');requestorMessage('#settings-feedback','');requestorDialog.querySelectorAll('button').forEach(button=>button.disabled=true);
 try{
  const data=await cifRequest('settings_save',{group_name:requestorQuery('#settings-group').value,current_password:requestorQuery('#settings-current').value,new_password:requestorQuery('#settings-new').value,confirm_password:requestorQuery('#settings-confirm').value});
  requestorOpen(data.requestor);for(const selector of ['#settings-current','#settings-new','#settings-confirm'])requestorQuery(selector).value='';requestorMessage('#settings-feedback',data.message);requestorToast(data.message);
 }catch(error){if(error.status===401){requestorSignedOut();requestorMessage('#login-error',error.message);}else requestorMessage('#settings-error',error.message);}
 finally{requestorSaving=false;requestorDialog.querySelectorAll('button').forEach(button=>button.disabled=false);}
};
cifReady.then(state=>{if(state.requestor)requestorOpen(state.requestor);}).catch(()=>requestorMessage('#login-error','Unable to check your session. Please refresh the page and try again.')).finally(cifFinishSessionCheck);
