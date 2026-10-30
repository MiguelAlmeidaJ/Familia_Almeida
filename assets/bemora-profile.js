(()=>{
'use strict';
const list=document.getElementById('income-sources');
const add=document.getElementById('add-income-source');
const template=document.getElementById('income-source-template');
function renumber(){if(!list)return;[...list.children].forEach((row,i)=>{row.querySelector('.income-source-title strong').textContent='Fonte de renda '+(i+1);});}
add?.addEventListener('click',()=>{if(!list||list.children.length>=12){alert('Máximo de 12 fontes de renda.');return;}list.append(template.content.cloneNode(true));renumber();list.lastElementChild.querySelector('input')?.focus();});
list?.addEventListener('click',e=>{const button=e.target.closest('.source-remove');if(button){button.closest('.income-source-row').remove();renumber();}});
const phone=document.getElementById('profile-phone');
function formatPhone(raw){
 const d=String(raw||'').replace(/\D/g,'').slice(0,11);
 if(!d)return '';
 if(d.length<=2)return '('+d;
 if(d.length<=6)return '('+d.slice(0,2)+') '+d.slice(2);
 if(d.length<=10)return '('+d.slice(0,2)+') '+d.slice(2,6)+'-'+d.slice(6);
 return '('+d.slice(0,2)+') '+d.slice(2,7)+'-'+d.slice(7);
}
if(phone){phone.value=formatPhone(phone.value);phone.addEventListener('input',()=>{phone.value=formatPhone(phone.value);});}
const reais=new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'});
function formatMoney(value){const digits=String(value||'').replace(/\D/g,'').slice(0,12);return digits?reais.format(Number(digits)/100):'';}
function initializeMoney(input){
 const raw=input.value.trim();if(!raw)return;
 const cleaned=raw.replace(/[^\d,.-]/g,'');
 const value=cleaned.includes(',')?Number(cleaned.replace(/\./g,'').replace(',','.')):Number(cleaned);
 if(Number.isFinite(value))input.value=reais.format(value);
}
document.querySelectorAll('input.profile-currency').forEach(initializeMoney);
list?.addEventListener('input',e=>{const input=e.target.closest('input.profile-currency');if(input)input.value=formatMoney(input.value);});
const incomeTotal=document.getElementById('profile-income-total');
const incomeCount=document.getElementById('profile-income-count');
function updateIncomeSummary(){
 if(!list)return;
 const values=[...list.querySelectorAll('.income-source-row input.profile-currency')].map(input=>{
  const clean=input.value.replace(/[^0-9,.-]/g,'');
  const numeric=clean.includes(',')?clean.replace(/\./g,'').replace(',','.'):clean;
  const value=Number(numeric);
  return Number.isFinite(value)?value:0;
 });
 if(incomeTotal)incomeTotal.textContent=reais.format(values.reduce((sum,n)=>sum+n,0));
 if(incomeCount)incomeCount.textContent=values.length+' '+(values.length===1?'fonte':'fontes');
}
list?.addEventListener('input',updateIncomeSummary);
list?.addEventListener('click',event=>{if(event.target.closest('.source-remove'))requestAnimationFrame(updateIncomeSummary);});
add?.addEventListener('click',()=>requestAnimationFrame(updateIncomeSummary));
updateIncomeSummary();
const passwordToggle=document.getElementById('profile-password-toggle');
const passwordDialog=document.getElementById('profile-password-dialog');
const closePassword=()=>{if(passwordDialog?.open)passwordDialog.close();};
passwordToggle?.addEventListener('click',()=>{if(!passwordDialog)return;passwordDialog.showModal();passwordDialog.querySelector('input[name="current_password"]')?.focus();});
document.getElementById('profile-password-close')?.addEventListener('click',closePassword);
document.getElementById('profile-password-cancel')?.addEventListener('click',closePassword);
passwordDialog?.addEventListener('click',e=>{if(e.target===passwordDialog)closePassword();});
const passwordForm=document.getElementById('profile-password-panel');
if(passwordForm){
 const current=passwordForm.querySelector('[name="current_password"]');
 const next=passwordForm.querySelector('[name="new_password"]');
 const confirmInput=passwordForm.querySelector('[name="confirm_password"]');
 const currentMessage=document.getElementById('current-password-feedback');
 const confirmMessage=document.getElementById('password-confirm-feedback');
 const submit=document.getElementById('password-change-submit');
 const strengthFill=document.getElementById('password-strength-fill');
 const strengthLabel=document.getElementById('password-strength-label');
 let verifiedValue=null,checking=false;
 const rules=p=>({
  length:p.length>=8&&p.length<=16,upper:/[A-Z]/.test(p),
  lower:/[a-z]/.test(p),digit:/[0-9]/.test(p),
  special:/[^A-Za-z0-9\\s]/.test(p)
 });
 function update(){
  const conditions=rules(next.value);
  let passed=0;Object.entries(conditions).forEach(([key,valid])=>{
    if(valid)passed++;
    const item=document.querySelector('#password-rules [data-rule="'+key+'"]');
    item?.classList.toggle('passed',valid);
  });
  strengthFill.style.width=(passed*20)+'%';
  strengthFill.dataset.score=String(passed);
  strengthLabel.textContent=!next.value?'Não informada':passed<=2?'Fraca':passed<=4?'Média':'Forte';
  const matches=next.value!==''&&next.value===confirmInput.value;
  confirmMessage.textContent=confirmInput.value?(matches?'As senhas coincidem.':'As senhas não coincidem.'):'';
  confirmMessage.classList.toggle('valid',matches);
  confirmMessage.classList.toggle('invalid',!!confirmInput.value&&!matches);
  submit.disabled=verifiedValue!==current.value||!verifiedValue||checking||passed!==5||!matches;
 }
 async function validateCurrent(){
  const value=current.value;
  verifiedValue=null;
  if(!value){currentMessage.textContent='Informe a senha atual.';update();return;}
  checking=true;currentMessage.textContent='Verificando senha atual...';currentMessage.className='password-validation';update();
  const payload=new FormData();
  payload.append('csrf_token',passwordForm.querySelector('[name="csrf_token"]').value);
  payload.append('action','check_password');
  payload.append('current_password',value);
  try{
   const response=await fetch(passwordForm.action,{method:'POST',body:payload,credentials:'same-origin',cache:'no-store'});
   const result=await response.json();
   if(current.value!==value)return;
   verifiedValue=result.valid?value:null;
   currentMessage.textContent=result.message||(result.valid?'Senha confirmada.':'Senha incorreta.');
   currentMessage.classList.toggle('valid',!!result.valid);
   currentMessage.classList.toggle('invalid',!result.valid);
  }catch{
   if(current.value===value)currentMessage.textContent='Não foi possível verificar a senha. Tente novamente.';
  }finally{checking=false;update();}
 }
 current.addEventListener('blur',validateCurrent);
 current.addEventListener('input',()=>{verifiedValue=null;currentMessage.textContent='Saia do campo para verificar a senha.';currentMessage.className='password-validation';update();});
 next.addEventListener('input',update);
 confirmInput.addEventListener('input',update);
 passwordForm.querySelectorAll('.password-eye').forEach(button=>{
  button.addEventListener('click',()=>{
   const input=button.parentElement.querySelector('input');
   const show=input.type==='password';input.type=show?'text':'password';
   button.setAttribute('aria-pressed',String(show));
   button.setAttribute('aria-label',(show?'Ocultar':'Mostrar')+' senha');
   button.querySelector('i').className=show?'bi bi-eye-slash':'bi bi-eye';
  });
 });
 passwordForm.addEventListener('submit',event=>{
  if(submit.disabled){event.preventDefault();update();}
 });
 passwordDialog?.addEventListener('close',()=>{passwordForm.reset();verifiedValue=null;currentMessage.textContent='Informe a senha atual para validá-la.';currentMessage.className='password-validation';update();});
 update();
}
const choose=document.getElementById('photo-choose');
const camera=document.getElementById('photo-camera');
const picker=document.getElementById('photo-file');
const capture=document.getElementById('photo-capture');
const dialog=document.getElementById('profile-crop-dialog');
const image=document.getElementById('crop-image');
const viewport=document.getElementById('crop-viewport');
const zoom=document.getElementById('crop-zoom');
const form=document.getElementById('profile-avatar-form');
const payload=document.getElementById('avatar-image-data');
let imageUrl=null,scale=1,base=1,x=0,y=0,drag=null;
choose?.addEventListener('click',()=>picker?.click());
camera?.addEventListener('click',()=>capture?.click());
function render(){
 const vw=viewport.clientWidth,vh=viewport.clientHeight,w=image.naturalWidth*scale,h=image.naturalHeight*scale;
 x=Math.max(vw-w,Math.min(0,x));y=Math.max(vh-h,Math.min(0,y));
 image.style.width=w+'px';image.style.height=h+'px';image.style.left=x+'px';image.style.top=y+'px';
}
function loadFile(file){
 if(!file)return;
 if(!file.type.startsWith('image/')||file.size>20*1024*1024){alert('Escolha uma imagem de até 20 MB.');return;}
 if(imageUrl)URL.revokeObjectURL(imageUrl);
 imageUrl=URL.createObjectURL(file);
 image.onload=()=>{
   if(!image.naturalWidth||!image.naturalHeight){alert('Imagem inválida.');return;}
   dialog.showModal();zoom.value='1';
   requestAnimationFrame(()=>{base=Math.max(viewport.clientWidth/image.naturalWidth,viewport.clientHeight/image.naturalHeight);scale=base;x=(viewport.clientWidth-image.naturalWidth*scale)/2;y=(viewport.clientHeight-image.naturalHeight*scale)/2;render();});
 };
 image.onerror=()=>alert('Não foi possível abrir essa imagem.');
 image.src=imageUrl;
}
[picker,capture].forEach(input=>input?.addEventListener('change',()=>{loadFile(input.files?.[0]);input.value='';}));
zoom?.addEventListener('input',()=>{const cx=viewport.clientWidth/2,cy=viewport.clientHeight/2,ratio=(base*Number(zoom.value))/scale;x=cx-(cx-x)*ratio;y=cy-(cy-y)*ratio;scale=base*Number(zoom.value);render();});
viewport?.addEventListener('pointerdown',e=>{drag={id:e.pointerId,x:e.clientX,y:e.clientY,ix:x,iy:y};viewport.setPointerCapture(e.pointerId);});
viewport?.addEventListener('pointermove',e=>{if(!drag||e.pointerId!==drag.id)return;x=drag.ix+e.clientX-drag.x;y=drag.iy+e.clientY-drag.y;render();});
['pointerup','pointercancel'].forEach(type=>viewport?.addEventListener(type,()=>{drag=null;}));
document.getElementById('crop-cancel')?.addEventListener('click',()=>dialog.close());
dialog?.addEventListener('close',()=>{if(imageUrl){URL.revokeObjectURL(imageUrl);imageUrl=null;}});
form?.addEventListener('submit',e=>{
 e.preventDefault();if(!image.naturalWidth)return;
 const canvas=document.createElement('canvas');canvas.width=1080;canvas.height=1080;
 const ctx=canvas.getContext('2d');if(!ctx){alert('Seu navegador não suporta recorte de fotos.');return;}
 ctx.drawImage(image,-x/scale,-y/scale,viewport.clientWidth/scale,viewport.clientHeight/scale,0,0,1080,1080);
 payload.value=canvas.toDataURL('image/jpeg',0.88);
 if(payload.value.length>3000000){alert('Imagem muito grande. Tente outra foto.');return;}
 form.submit();
});
})();