(()=>{'use strict';
const form=document.getElementById('plan-bundle-form'),preview=document.getElementById('plans-bundle-preview');
if(!form||!preview)return;
const parseMoney=value=>{
 let v=String(value||'').trim().replace(/^R\$\s*/,'').replace(/\s/g,'');
 if(v.includes(','))v=v.replace(/\./g,'').replace(',','.');
 return /^\d+(?:\.\d{1,2})?$/.test(v)?Number(v):NaN;
};
const fmt=value=>value.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
const render=()=>{
 const f=form.elements,base=parseMoney(f.bundle_monthly_base.value),perPerson=parseMoney(f.bundle_seat_price.value);
 const seats=Number(f.bundle_members.value),semi=Number(f.bundle_semi_discount.value),year=Number(f.bundle_annual_discount.value);
 if(!Number.isFinite(base)||!Number.isFinite(perPerson)||base<=0||perPerson<0||!Number.isFinite(semi)||!Number.isFinite(year)||semi<0||semi>80||year<0||year>80){
  preview.textContent='Informe valores válidos para visualizar as cobranças.';return;
 }
 const monthly=Math.round((base+perPerson*(seats-1))*100);
 const amounts=[['Mensal',monthly],['Semestral',Math.round(monthly*6*(100-semi)/100)],['Anual',Math.round(monthly*12*(100-year)/100)]];
 preview.innerHTML=amounts.map(([name,cents])=>'<div><span>'+name+'</span><strong>'+fmt(cents/100)+'</strong></div>').join('')+'<p>Prévia por período de cobrança. Os valores serão recalculados no servidor antes de salvar.</p>';
};
form.addEventListener('input',render);
form.addEventListener('change',render);
const shop=form.querySelector('input[name="bundle_modules[]"][value="shopping"]'),scan=form.querySelector('input[name="bundle_modules[]"][value="scanner"]');
shop?.addEventListener('change',()=>{if(!shop.checked&&scan)scan.checked=false;});
scan?.addEventListener('change',()=>{if(scan.checked&&shop)shop.checked=true;});
render();
})();