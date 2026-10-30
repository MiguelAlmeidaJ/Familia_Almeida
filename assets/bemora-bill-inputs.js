(()=>{'use strict';
const brl=new Intl.NumberFormat('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
function formatMoney(input){const digits=input.value.replace(/\D/g,'').slice(0,12);input.value=digits?brl.format(Number(digits)/100):'';}
function setup(form){if(!form)return;const amount=form.querySelector('input[name="amount"]'),day=form.querySelector('[name="due_day"]'),mode=form.querySelector('[name="due_mode"]'),rule=form.querySelector('[name="business_day_rule"]'),business=null,preview=form.querySelector('.bemora-due-preview'),month=form.querySelector('[name="month"]');
 if(amount){amount.addEventListener('input',()=>formatMoney(amount));amount.addEventListener('focus',()=>{if(amount.value&&/^\d+(?:\.\d{1,2})?$/.test(amount.value))amount.value=brl.format(Number(amount.value));});}
 if(day){day.addEventListener('input',()=>{const value=day.value.replace(/\D/g,'').slice(0,3);day.value=value?String(Math.min(31,Number(value))):'';updatePreview();});day.addEventListener('blur',()=>{if(day.value&&Number(day.value)<1)day.value='1';updatePreview();});}
 function updatePreview(){
  const isBusiness=mode?.value==='business';
  const ruleLabel=rule?.selectedOptions?.[0]?.textContent||'';
  const wrap=rule?.closest('.bemora-business-rule');
  if(wrap)wrap.hidden=!isBusiness;
  if(day){day.closest('label').hidden=isBusiness;day.required=!isBusiness;}
  if(rule)rule.required=isBusiness;
  if(!preview)return;
  const monthInput=form.querySelector('[name="start_month"]');
  const activeMonth=monthInput?.value||month?.value||new Date().toISOString().slice(0,7);
  const [year,m]=activeMonth.split('-').map(Number);
  if(!year||!m)return;
  if(isBusiness){preview.textContent='Vencimento: '+ruleLabel+' de cada mês. Finais de semana e feriados nacionais são desconsiderados.';return;}
  const n=Number(day?.value);
  if(!Number.isInteger(n)||n<1||n>31){preview.textContent='Informe um dia entre 1 e 31.';return;}
  const last=new Date(year,m,0).getDate(),date=new Date(year,m-1,Math.min(n,last));
  preview.textContent='Vencimento previsto: '+date.toLocaleDateString('pt-BR')+(n>last?' · ajustado ao último dia do mês':'');
 }
 mode?.addEventListener('change',updatePreview);
 rule?.addEventListener('change',updatePreview);
 form.querySelector('[name="start_month"]')?.addEventListener('change',updatePreview);
 month?.addEventListener('change',updatePreview);
 updatePreview();
 form.addEventListener('submit',e=>{if(mode?.value!=='business'&&(!day||!Number.isInteger(Number(day.value))||Number(day.value)<1||Number(day.value)>31)){e.preventDefault();day?.setCustomValidity('Informe um dia entre 1 e 31.');day?.reportValidity();return;}day?.setCustomValidity('');});
}
setup(document.getElementById('billForm'));setup(document.getElementById('bemora-global-bill-form'));
})();