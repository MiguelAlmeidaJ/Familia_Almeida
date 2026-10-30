(()=>{'use strict';
const brl=new Intl.NumberFormat('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
function formatMoney(input){const digits=input.value.replace(/\D/g,'').slice(0,12);input.value=digits?brl.format(Number(digits)/100):'';}
function holiday(year,month,day){
 const key=String(month).padStart(2,'0')+'-'+String(day).padStart(2,'0');
 const fixed=['01-01','04-21','05-01','09-07','10-12','11-02','11-15','11-20','12-25'];
 if(fixed.includes(key))return true;
 // Easter, used to recognize nationwide Good Friday.
 const a=year%19,b=Math.floor(year/100),c=year%100,d=Math.floor(b/4),e=b%4;
 const ff=Math.floor((b+8)/25),g=Math.floor((b-ff+1)/3);
 const h=(19*a+b-d-g+15)%30,i=Math.floor(c/4),k=c%4;
 const l=(32+2*e+2*i-h-k+7)%7,m=Math.floor((a+11*h+22*l)/451);
 const easterMonth=Math.floor((h+l-7*m+114)/31);
 const easterDay=((h+l-7*m+114)%31)+1;
 const goodFriday=new Date(year,easterMonth-1,easterDay-2);
 return month===goodFriday.getMonth()+1&&day===goodFriday.getDate();
}
function dateFor(monthText,mode,day,rule){
 const [year,month]=String(monthText).split('-').map(Number);
 if(!year||!month||month<1||month>12)return null;
 const last=new Date(year,month,0).getDate();
 if(mode!=='business'){
  if(!Number.isInteger(day)||day<1||day>31)return null;
  return new Date(year,month-1,Math.min(day,last));
 }
 const business=[];
 for(let d=1;d<=last;d++){
  const date=new Date(year,month-1,d);
  if(date.getDay()!==0&&date.getDay()!==6&&!holiday(year,month,d))business.push(date);
 }
 if(business.length<5)return null;
 if(rule==='first')return business[0];
 if(rule==='fifth')return business[4];
 if(rule==='penultimate')return business[business.length-2];
 if(rule==='last')return business[business.length-1];
 return null;
}
function init(form){
 if(!form)return;
 const amount=form.querySelector('[name="amount"]'),day=form.querySelector('[name="due_day"]'),mode=form.querySelector('[name="due_mode"]');
 const rule=form.querySelector('[name="business_day_rule"]'),preview=form.querySelector('.bemora-due-preview');
 const fixedWrap=day?.closest('label'),businessWrap=rule?.closest('label');
 const month=form.querySelector('[name="month"]'),firstInstallment=form.querySelector('[name="start_month"]');
 if(amount){amount.addEventListener('input',()=>formatMoney(amount));amount.addEventListener('focus',()=>{if(/^\d+(?:\.\d{1,2})?$/.test(amount.value))amount.value=brl.format(Number(amount.value));});}
 function refresh(){
  const isBusiness=mode?.value==='business';
  if(fixedWrap)fixedWrap.hidden=isBusiness;
  if(businessWrap)businessWrap.hidden=!isBusiness;
  if(day){day.required=!isBusiness;day.disabled=isBusiness;day.setCustomValidity('');}
  if(rule){rule.required=isBusiness;rule.disabled=!isBusiness;}
  const m=firstInstallment && form.querySelector('[name="billing_type"]')?.value==='installment'
    ?firstInstallment.value:month?.value||new Date().toISOString().slice(0,7);
  const n=Number(day?.value);
  const date=dateFor(m,isBusiness?'business':'fixed',n,rule?.value);
  if(!preview)return;
  if(!date){preview.textContent='Selecione um vencimento válido.';return;}
  const label=isBusiness?(rule?.selectedOptions?.[0]?.textContent||'Dia útil'):'Dia '+n;
  preview.textContent=label+' · Vencimento em '+date.toLocaleDateString('pt-BR')+(isBusiness?' (dias úteis nacionais)':'');
 }
 day?.addEventListener('input',()=>{
  const digits=day.value.replace(/\D/g,'').slice(0,3);
  day.value=digits?String(Math.min(31,Number(digits))):'';
  refresh();
 });
 day?.addEventListener('blur',()=>{if(day.value&&Number(day.value)<1)day.value='1';refresh();});
 mode?.addEventListener('change',refresh);
 rule?.addEventListener('change',refresh);
 firstInstallment?.addEventListener('change',refresh);
 month?.addEventListener('change',refresh);
 form.querySelector('[name="billing_type"]')?.addEventListener('change',refresh);
 form.addEventListener('submit',e=>{
  if(mode?.value!=='business'&&(!day||!Number.isInteger(Number(day.value))||Number(day.value)<1||Number(day.value)>31)){
   e.preventDefault();day?.setCustomValidity('Informe um dia entre 1 e 31.');day?.reportValidity();
  }
 });
 form.addEventListener('reset',()=>requestAnimationFrame(refresh));
 refresh();
}
init(document.getElementById('billForm'));
init(document.getElementById('bemora-global-bill-form'));
})();