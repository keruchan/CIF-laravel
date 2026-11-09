(()=>{
 const editor=document.querySelector('#cif-editor'),scale=document.querySelector('#hr-score-scale');
 if(!editor||!scale)return;
 const bands=JSON.parse(scale.textContent);
 function refresh(){editor.querySelectorAll('[data-hr-score]').forEach(field=>{
  field.setCustomValidity(field.value!==''&&!/^\d{1,3}(?:\.\d{1,2})?$/.test(field.value)?'Enter a score from 0 to 100, with up to two decimal places.':'');
  const output=document.getElementById(field.id+'-rating'),value=Number(field.value);
  const band=field.value.trim()!==''&&field.validity.valid?bands.find(item=>value>=item.min):null;
  output.textContent=band?band.rating+'/5 · '+band.label:field.value===''?'Not scored':'Enter 0–100';
  output.dataset.rating=band?String(band.rating):'';
 });}
 refresh();editor.addEventListener('input',refresh);editor.addEventListener('change',refresh);
})();
