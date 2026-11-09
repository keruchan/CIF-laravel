window.cifPhotoPending=0;
document.querySelectorAll('[data-photo-widget]').forEach(widget=>{
 const value=widget.querySelector('[data-photo-value]'),preview=widget.querySelector('[data-photo-preview]'),error=widget.querySelector('[data-photo-error]'),remove=widget.querySelector('[data-photo-remove]'),choose=widget.querySelector('[data-photo-upload]');
 const buttons=[...widget.querySelectorAll('button')];
 function changed(photo){value.value=photo;preview.hidden=!photo;if(photo)preview.src=photo;else preview.removeAttribute('src');remove.disabled=!photo;remove.hidden=!photo;value.dispatchEvent(new Event('input',{bubbles:true}));value.dispatchEvent(new Event('change',{bubbles:true}));}
 choose.onclick=()=>widget.querySelector('[data-photo-file]').click();
 widget.querySelector('[data-photo-camera]').onclick=()=>widget.querySelector('[data-photo-capture]').click();
 remove.onclick=()=>{error.hidden=true;changed('');choose.focus();};
 widget.querySelectorAll('input[type="file"]').forEach(input=>input.onchange=async()=>{
  const file=input.files[0];if(!file)return;error.hidden=true;
  window.cifPhotoPending++;input.disabled=true;buttons.forEach(button=>button.disabled=true);widget.setAttribute('aria-busy','true');
  let url;
  try{
   if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>10*1024*1024)throw new Error('Choose a JPEG, PNG or WebP picture under 10 MB.');
   url=URL.createObjectURL(file);const image=new Image();await new Promise((resolve,reject)=>{image.onload=resolve;image.onerror=()=>reject(new Error('This picture could not be opened. Try a JPEG, PNG or WebP photo.'));image.src=url;});
   const scale=Math.min(1,1200/Math.max(image.naturalWidth,image.naturalHeight)),canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(image.naturalWidth*scale));canvas.height=Math.max(1,Math.round(image.naturalHeight*scale));
   const context=canvas.getContext('2d');context.fillStyle='#fff';context.fillRect(0,0,canvas.width,canvas.height);context.drawImage(image,0,0,canvas.width,canvas.height);
   const photo=canvas.toDataURL('image/jpeg',0.85);if(photo.length>2800000)throw new Error('The resized image is too large. Please choose a smaller picture.');changed(photo);
  }catch(reason){error.textContent=reason.message;error.hidden=false;input.value='';}
  finally{if(url)URL.revokeObjectURL(url);input.value='';window.cifPhotoPending--;input.disabled=false;buttons.forEach(button=>button.disabled=false);remove.disabled=!value.value;widget.removeAttribute('aria-busy');}
 });
});
