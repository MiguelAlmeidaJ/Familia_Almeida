(()=>{'use strict';const dialog=document.getElementById('bill-dialog');const form=document.getElementById('billForm');if(!dialog||!form)return;
dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close();});
form.addEventListener('submit',e=>{if(!form.checkValidity())return;const type=document.getElementById('bill-type').value;const value=Number(document.getElementById('bill-amount').value||0);if(type!=='variable'&&value<=0){e.preventDefault();alert('Informe um valor maior que zero.');return;}const button=form.querySelector('[type="submit"]');if(button){button.disabled=true;button.textContent='Salvando...';}});
document.querySelectorAll('.fixed-reference-row').forEach(row=>{const date=row.dataset.dueDate;if(date&&date<new Date().toISOString().slice(0,10)&&!row.classList.contains('is-paid'))row.classList.add('is-overdue');});
})();
