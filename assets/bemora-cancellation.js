document.addEventListener('DOMContentLoaded',()=>{
 const dialog=document.getElementById('bemora-cancel-dialog');
 const open=document.getElementById('bemora-cancel-open');
 if(!dialog||!open)return;
 const close=()=>dialog.close();
 open.addEventListener('click',()=>dialog.showModal());
 document.getElementById('bemora-cancel-close')?.addEventListener('click',close);
 document.getElementById('bemora-cancel-back')?.addEventListener('click',close);
 dialog.addEventListener('click',event=>{if(event.target===dialog)close()});
 const form=dialog.querySelector('form');
 form?.addEventListener('submit',()=>{const btn=form.querySelector('button[type="submit"]');if(btn){btn.disabled=true;btn.textContent='Confirmando cancelamento…'}});
});