const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
let badgeRequest=0;
function setSidebarBadge(selector,count,label){const badge=$(selector);badge.textContent=count;badge.classList.remove('badge-loading');badge.classList.add('badge-loaded');badge.removeAttribute('aria-busy');badge.setAttribute('aria-label',`${count} ${label}`);}
async function refreshSidebarBadges(){
 const request=++badgeRequest;
 ['#cnt','#invite-cnt','#mrf-cnt'].forEach(selector=>{const badge=$(selector);badge.textContent='–';badge.classList.remove('badge-loaded');badge.classList.add('badge-loading');badge.setAttribute('aria-busy','true');badge.setAttribute('aria-label','Loading count');});
 try{const counts=await cifRequest('admin_badges');if(request!==badgeRequest||$('#app').classList.contains('hide'))return;setSidebarBadge('#cnt',counts.submitted,'candidates awaiting screening');setSidebarBadge('#invite-cnt',counts.pending,'pending invitations');setSidebarBadge('#mrf-cnt',counts.mrf_pending,'manpower files awaiting approval');}
 catch(error){if(request===badgeRequest){['#cnt','#invite-cnt','#mrf-cnt'].forEach(selector=>{const badge=$(selector);if(!badge.classList.contains('badge-loading'))return;badge.textContent='–';badge.classList.remove('badge-loading');badge.removeAttribute('aria-busy');badge.setAttribute('aria-label','Count unavailable');});}}
}
$$('.nv[data-v]').forEach(button=>button.onclick=()=>{if(!window.cifAdminPage?.navigate){toast('Page scripts did not load correctly. Refresh the page to load the latest update.');return;}window.cifAdminPage.navigate(button.dataset.v);});
let currentAdmin=null,adminSettingsBusy=false;
function renderAdminProfile(admin){currentAdmin=admin;document.querySelector('.nv[data-v="requestors"]')?.classList.toggle('hide',!admin.can_manage_requestors);$('#admin-profile-name').textContent=admin.name;$('#admin-profile-username').textContent=admin.email+' · Settings';}
function openAdmin(admin){
 $('#login').classList.add('hide');$('#app').classList.remove('hide');renderAdminProfile(admin);
 if(!window.cifAdminPage?.show){
  let notice=$('#admin-script-error');
  if(!notice){notice=document.createElement('p');notice.id='admin-script-error';notice.setAttribute('role','alert');notice.style.cssText='padding:16px;border:1px solid #e8bd73;border-radius:12px;background:#fff8e6;color:#713f12';const button=document.createElement('button');button.type='button';button.className='btn g';button.textContent='Reload page';button.onclick=()=>location.reload();notice.append(document.createTextNode('The page scripts did not load correctly. Reload to get the latest update. You can still sign out. '),button);($('#app main')||$('#app')).append(notice);}
  return;
 }
 $('#admin-script-error')?.remove();window.cifAdminPage.show();
}
$('#admin-settings-open').onclick=()=>{
 if(!currentAdmin)return;$('#admin-settings-form').reset();$('#admin-settings-name').value=currentAdmin.name;$('#admin-settings-username').value=currentAdmin.email;$('#admin-settings-error').hidden=true;$('#admin-settings-feedback').hidden=true;
 cifSidebar.close();$('#admin-settings').showModal();$('#admin-settings-name').focus();
 window.cifHrSettingsPhoto?.open();
};
['admin-settings-close','admin-settings-cancel'].forEach(id=>$('#'+id).onclick=()=>{if(!adminSettingsBusy&&!window.cifHrSettingsPhoto?.busy())$('#admin-settings').close();});
$('#admin-settings').onclose=()=>{if($('#admin-settings').open)return;$('#admin-settings-form').reset();$('#admin-settings-error').hidden=true;$('#admin-settings-feedback').hidden=true;window.cifHrSettingsPhoto?.reset();};
$('#admin-settings').oncancel=event=>{if(adminSettingsBusy||window.cifHrSettingsPhoto?.busy())event.preventDefault();};
$('#admin-settings-form').onsubmit=async event=>{
 event.preventDefault();if(adminSettingsBusy||window.cifHrSettingsPhoto?.busy())return;
 const error=$('#admin-settings-error'),feedback=$('#admin-settings-feedback');error.hidden=true;feedback.hidden=true;
 const next=$('#admin-settings-new').value,confirmation=$('#admin-settings-confirm').value;
 if(next!==confirmation){error.textContent='The new passwords do not match.';error.hidden=false;$('#admin-settings-confirm').focus();return;}
 if(next&&(Array.from(next).length<12||new TextEncoder().encode(next).length>72)){error.textContent='Use at least 12 characters and no more than 72 bytes for the new password.';error.hidden=false;$('#admin-settings-new').focus();return;}
 const input={name:$('#admin-settings-name').value,current_password:$('#admin-settings-current').value,new_password:next,confirm_password:confirmation};
 adminSettingsBusy=true;$('#admin-settings-form').setAttribute('aria-busy','true');$('#admin-settings-save').textContent='Saving…';$$('#admin-settings-form button, #admin-settings-form input, #admin-settings-close').forEach(control=>control.disabled=true);window.cifHrSettingsPhoto?.controls();
 try{const result=await cifRequest('admin_settings_save',input);renderAdminProfile(result.admin);$('#admin-settings-name').value=result.admin.name;['admin-settings-current','admin-settings-new','admin-settings-confirm'].forEach(id=>$('#'+id).value='');feedback.textContent=result.message;feedback.hidden=false;}
 catch(reason){error.textContent=reason.message;error.hidden=false;$('#admin-settings-current').value='';}
 finally{adminSettingsBusy=false;$('#admin-settings-form').removeAttribute('aria-busy');$('#admin-settings-save').textContent='Save changes';$$('#admin-settings-form button, #admin-settings-form input, #admin-settings-close').forEach(control=>control.disabled=false);window.cifHrSettingsPhoto?.controls();}
};
$('#lf').onsubmit=async e=>{e.preventDefault();const button=e.submitter||$('#lf button');button.disabled=true;try{const result=await cifRequest('admin_login',{email:$('#ae').value,password:$('#ap').value});$('#ae_err').classList.add('hide');$('#ap').value='';openAdmin(result.admin);}catch(error){$('#ae_err').textContent=error.message;$('#ae_err').classList.remove('hide');}finally{button.disabled=false;}};
$('#out').onclick=async()=>{try{const page=window.cifAdminPage;if(page?.canSignOut&&!await page.canSignOut())return;await cifRequest('admin_logout');cifSidebar.close();currentAdmin=null;$('#admin-settings').close();page?.onSignOut?.();$('#ap').value='';$('#app').classList.add('hide');$('#login').classList.remove('hide');}catch(error){toast(error.message);}};

document.addEventListener('DOMContentLoaded',()=>{cifReady.then(state=>{if(state.admin)openAdmin(state.admin);}).catch(error=>{$('#ae_err').textContent=error.message+' Please refresh the page to retry.';$('#ae_err').classList.remove('hide');}).finally(cifFinishSessionCheck);});
function toast(t){const e=$('#toast');e.textContent=t;e.classList.add('on');setTimeout(()=>e.classList.remove('on'),2200)}
