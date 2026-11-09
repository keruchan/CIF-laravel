const deptQuery=selector=>document.querySelector(selector);
let departmentAccount,departmentReady=false,toastTimer,departmentCurrentView=null;
const departmentPage=deptQuery('#app').dataset.departmentPage||'portal';
const departmentViews={dashboard:['Dashboard','Your manpower requests at a glance.'],mrf:['Manpower requests','Manpower Request Form (MRF).']};
function departmentView(){
 if(!departmentReady)return;
 const requested=location.hash.slice(1),view=departmentPage!=='portal'?departmentPage:Object.hasOwn(departmentViews,requested)?requested:'dashboard';
 if(departmentPage==='portal'&&departmentCurrentView!==view)window.cifMrf?.resetFilters();
 departmentCurrentView=view;
 if(departmentPage==='portal'&&requested!==view)history.replaceState(null,'','#'+view);
 document.querySelectorAll('[data-page]').forEach(page=>page.classList.toggle('hide',page.id!==view));
 const menuView=view.startsWith('mrf-')?'mrf':view;
 document.querySelectorAll('.nv[data-view]').forEach(button=>{button.classList.toggle('on',button.dataset.view===menuView);if(button.dataset.view===menuView)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current');});
 const pageTitles=view==='mrf-form'?['New manpower request','Fill out the form and submit your request to HR.']:view==='mrf-record'?['Manpower request '+deptQuery('#app').dataset.requestNumber,'View request details, status and HR updates.']:departmentViews[view];
 deptQuery('#ttl').textContent=pageTitles[0];deptQuery('#sub').textContent=pageTitles[1];cifSidebar.close();
 if(departmentPage==='portal'&&view==='mrf')cifMrf.load();
 if(departmentPage==='portal'&&view==='dashboard')window.cifDepartmentDashboard?.load();
}
function departmentOpen(account){
 departmentAccount=account;departmentReady=true;deptQuery('#profile-name').textContent=account.name;deptQuery('#profile-username').textContent=account.username;
 window.cifMrfForm?.updateAccount(account);
 deptQuery('#login').classList.add('hide');deptQuery('#app').classList.remove('hide');departmentView();
}
function departmentSignedOut(){
 window.cifDepartmentDashboard?.reset();departmentReady=false;departmentAccount=null;departmentCurrentView=null;window.cifMrf?.resetFilters();cifSidebar.close();deptQuery('#department-settings').close();deptQuery('#app').classList.add('hide');deptQuery('#login').classList.remove('hide');deptQuery('#password').value='';deptQuery('#username').focus();
}
function departmentMessage(selector,message){const element=deptQuery(selector);element.textContent=message;element.hidden=!message;}
function departmentToast(message){clearTimeout(toastTimer);departmentMessage('#department-toast',message);toastTimer=setTimeout(()=>departmentMessage('#department-toast',''),5000);}
document.querySelectorAll('[data-view]').forEach(button=>button.addEventListener('click',async()=>{if(departmentPage!=='portal'){if(window.cifMrfForm&&!await cifMrfForm.confirmLeave())return;window.cifMrfForm?.allowNavigation();location.href=new URL('./#'+button.dataset.view,location.href).href;return;}location.hash=button.dataset.view;departmentView();}));
window.addEventListener('hashchange',departmentView);
window.addEventListener('pagehide',()=>{if(departmentPage==='portal')window.cifMrf?.resetFilters();});
window.addEventListener('pageshow',event=>{if(event.persisted&&departmentReady){departmentCurrentView=null;departmentView();}});
deptQuery('#department-login').onsubmit=async event=>{
 event.preventDefault();const button=deptQuery('#login-submit');button.disabled=true;departmentMessage('#login-error','');
 try{const data=await cifRequest('login',{email:deptQuery('#username').value,password:deptQuery('#password').value});deptQuery('#password').value='';departmentOpen(data.department);}
 catch(error){departmentMessage('#login-error',error.message);}finally{button.disabled=false;}
};
deptQuery('#out').onclick=async()=>{
 const button=deptQuery('#out');button.disabled=true;
 try{if(window.cifMrfForm&&!await cifMrfForm.confirmLeave())return;await cifRequest('logout');window.cifMrfForm?.discard();departmentSignedOut();departmentToast('You have been signed out.');}catch(error){departmentToast(error.message);}finally{button.disabled=false;}
};
const departmentDialog=deptQuery('#department-settings');
deptQuery('#department-settings-open').onclick=()=>{
 cifSidebar.close();deptQuery('#settings-form').reset();deptQuery('#settings-username').value=departmentAccount.username;deptQuery('#settings-name').value=departmentAccount.name;deptQuery('#settings-position').value=departmentAccount.position||'';deptQuery('#settings-department').value=departmentAccount.department_name||'';deptQuery('#settings-group').value=departmentAccount.group_name||'';departmentMessage('#settings-error','');departmentMessage('#settings-feedback','');departmentDialog.showModal();
};
let departmentSaving=false;
for(const selector of ['#settings-close','#settings-cancel'])deptQuery(selector).onclick=()=>{if(!departmentSaving)departmentDialog.close();};
departmentDialog.addEventListener('cancel',event=>{if(departmentSaving)event.preventDefault();});
deptQuery('#settings-form').onsubmit=async event=>{
 event.preventDefault();if(departmentSaving)return;departmentSaving=true;departmentMessage('#settings-error','');departmentMessage('#settings-feedback','');departmentDialog.querySelectorAll('button').forEach(button=>button.disabled=true);
 try{
  const data=await cifRequest('settings_save',{group_name:deptQuery('#settings-group').value,current_password:deptQuery('#settings-current').value,new_password:deptQuery('#settings-new').value,confirm_password:deptQuery('#settings-confirm').value});
  departmentOpen(data.department);for(const selector of ['#settings-current','#settings-new','#settings-confirm'])deptQuery(selector).value='';departmentMessage('#settings-feedback',data.message);departmentToast(data.message);
 }catch(error){if(error.status===401){departmentSignedOut();departmentMessage('#login-error',error.message);}else departmentMessage('#settings-error',error.message);}
 finally{departmentSaving=false;departmentDialog.querySelectorAll('button').forEach(button=>button.disabled=false);}
};
cifReady.then(state=>{if(state.department)departmentOpen(state.department);}).catch(()=>departmentMessage('#login-error','Unable to check your session. Please refresh the page and try again.')).finally(cifFinishSessionCheck);
