(()=>{
 const root=document.querySelector('#reports');if(!root)return;
 const el=id=>document.getElementById(id),form=el('reports-form'),error=el('reports-error'),feedback=el('reports-feedback');let busy=false;
 function preview(){el('reports-coverage').textContent=(el('reports-from').value||el('reports-to').value?(el('reports-from').value||'Choose From')+' to '+(el('reports-to').value||'Choose To'):'All dates')+' · '+el('reports-scope').selectedOptions[0].textContent;}
 function reset(){if(busy)return;form.reset();error.hidden=true;feedback.hidden=true;preview();}
 root.addEventListener('change',preview);el('reports-reset').onclick=reset;el('reports-refresh').onclick=()=>cifRefreshPage(el('reports-refresh'));
 el('reports-select-all').onclick=()=>{root.querySelectorAll('[name=report]').forEach(c=>c.checked=true);};
 root.querySelectorAll('[data-report-coverage]').forEach(button=>button.onclick=()=>{
  const start=new Date(root.dataset.today+'T00:00:00Z'),end=new Date(start),kind=button.dataset.reportCoverage;
  if(kind==='all'){el('reports-from').value='';el('reports-to').value='';}else{
   start.setUTCDate(1);end.setUTCMonth(end.getUTCMonth()+1,0);
   if(kind==='year'){start.setUTCMonth(0,1);end.setUTCMonth(11,31);}if(kind==='last12')start.setUTCMonth(start.getUTCMonth()-11);
   el('reports-from').value=start.toISOString().slice(0,10);el('reports-to').value=end.toISOString().slice(0,10);
  }preview();
 });
 form.onsubmit=async event=>{
  event.preventDefault();if(busy)return;error.hidden=true;feedback.hidden=true;
  const format=event.submitter?.value||'xlsx',input={format,reports:[...root.querySelectorAll('[name=report]:checked')].map(c=>c.value),scope:el('reports-scope').value,archives:el('reports-archives').value,date_from:el('reports-from').value,date_to:el('reports-to').value};
  if(!input.reports.length){error.textContent='Select at least one report.';error.hidden=false;return;}
  if((!input.date_from!==!input.date_to)||input.date_from>input.date_to){error.textContent='Choose both dates in order, or clear both for All dates.';error.hidden=false;return;}
  const printWindow=format==='print'?window.open('about:blank','_blank'):null;
  if(format==='print'&&!printWindow){error.textContent='Allow pop-ups for this site to open print preview.';error.hidden=false;return;}
  if(printWindow){printWindow.opener=null;printWindow.document.title='Preparing report';printWindow.document.body.textContent='Preparing your reports…';}
  busy=true;form.setAttribute('aria-busy','true');const controls=[...form.querySelectorAll('button,input,select')];controls.forEach(c=>c.disabled=true);feedback.textContent='Preparing your reports…';feedback.hidden=false;
  try{
   const state=await cifReady,response=await fetch(new URL('../../ajax/reports.php',location.href),{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':state.csrf},body:JSON.stringify(input)});
   if(!response.ok){const result=await response.json();throw new Error(result.error||'Unable to prepare your report.');}
   if(format==='print'){const html=await response.text();if(printWindow.closed)throw new Error('The print preview window was closed. Please try again.');printWindow.document.open();printWindow.document.write(html);printWindow.document.close();}
   else{const blob=await response.blob(),url=URL.createObjectURL(blob),a=document.createElement('a');a.href=url;a.download='APEC-Homes-Reports-'+root.dataset.today+'.xlsx';document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),30000);}
   feedback.textContent=format==='print'?'Your print preview is ready in the new tab.':'Your Excel download is ready.';
  }catch(e){if(printWindow&&!printWindow.closed)printWindow.close();feedback.hidden=true;error.textContent=e.message||'Unable to prepare your report. Please try again.';error.hidden=false;}
  finally{busy=false;form.removeAttribute('aria-busy');controls.forEach(c=>c.disabled=false);}
 };
 window.cifReports={resetFilters:reset};preview();
})();
