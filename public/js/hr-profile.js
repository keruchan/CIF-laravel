// Shared image preparation for HR settings and Staff Monitoring.
async function cifPrepareHrProfilePhoto(file){
 if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>10*1024*1024)throw new Error('Choose a JPEG, PNG or WebP picture up to 10 MB.');
 const objectUrl=URL.createObjectURL(file);
 try{
  const image=new Image();image.src=objectUrl;await image.decode();
  const scale=Math.min(1,512/Math.max(image.naturalWidth,image.naturalHeight)),canvas=document.createElement('canvas');
  canvas.width=Math.max(1,Math.round(image.naturalWidth*scale));canvas.height=Math.max(1,Math.round(image.naturalHeight*scale));
  const context=canvas.getContext('2d');context.fillStyle='#ffffff';context.fillRect(0,0,canvas.width,canvas.height);context.drawImage(image,0,0,canvas.width,canvas.height);
  return canvas.toDataURL('image/jpeg',.86);
 }finally{URL.revokeObjectURL(objectUrl);}
}
(()=>{
 const form=document.getElementById('admin-photo-form');if(!form)return;
 const el=id=>document.getElementById('admin-photo-'+id),placeholder=form.dataset.placeholder;
 let saved='',selected='',loaded=false,loading=false,processing=false,saving=false,sequence=0,owner=null;
 const busy=()=>processing||saving;
 function controls(){
  const disabled=loading||busy()||adminSettingsBusy||!loaded;
  el('preview').src=selected||placeholder;el('file').disabled=disabled;
  for(const id of ['admin-settings-close','admin-settings-cancel','admin-settings-save'])document.getElementById(id).disabled=busy()||adminSettingsBusy;
 }
 function error(message=''){el('error').textContent=message;el('error').hidden=!message;}
 function reset(){sequence++;saved=selected='';loaded=loading=processing=saving=false;owner=null;form.reset();error();el('status').textContent='Your picture saves automatically when selected.';controls();}
 async function open(){
  reset();const token=sequence;owner=currentAdmin?.id;loading=true;controls();el('status').textContent='Loading your picture…';
  try{
   const result=await cifRequest('hr_profile_photo');if(token!==sequence)return;
   if(result.admin_id!==owner||currentAdmin?.id!==owner)throw new Error('Your account changed. Close settings and sign in again.');
   saved=selected=result.photo;loaded=true;el('status').textContent='Your picture saves automatically when selected.';
  }catch(reason){if(token===sequence){error(reason.message);el('status').textContent='Close and reopen settings to try again.';}}
  finally{if(token===sequence){loading=false;controls();}}
 }
 el('file').addEventListener('change',async event=>{
  const file=event.target.files[0];if(!file||!loaded||busy()||adminSettingsBusy)return;
  const token=sequence;processing=true;error();controls();el('status').textContent='Preparing your picture…';
  try{
   const photo=await cifPrepareHrProfilePhoto(file);if(token!==sequence)return;
   if(owner!==currentAdmin?.id)throw new Error('Your account changed. Close settings and sign in again.');
   selected=photo;processing=false;saving=true;controls();el('status').textContent='Saving your picture…';
   const result=await cifRequest('hr_profile_photo_save',{photo:selected});if(token!==sequence)return;
   saved=selected=result.photo;el('status').textContent=result.message;toast(result.message);
   document.dispatchEvent(new CustomEvent('cif:hr-photo-saved',{detail:{adminId:owner}}));
  }catch(reason){if(token===sequence){selected=saved;error(reason.message||'This picture could not be opened. Choose another photo.');el('status').textContent='Your picture was not saved. Choose a picture to try again.';}}
  finally{if(token===sequence){processing=saving=false;el('file').value='';controls();}}
 });
 form.addEventListener('submit',event=>event.preventDefault());
 window.cifHrSettingsPhoto={open,reset,busy,controls};
})();
