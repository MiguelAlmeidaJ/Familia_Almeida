(() => {
'use strict';
const list=document.getElementById('income-sources');
const add=document.getElementById('add-income-source');
const template=document.getElementById('income-source-template');
const renumber=()=>{[...list.children].forEach((row,index)=>{row.querySelector('.income-source-title strong').textContent='Fonte de renda '+(index+1);});};
add?.addEventListener('click',()=>{if(list.children.length>=12){alert('Máximo de 12 fontes de renda.');return;}list.append(template.content.cloneNode(true));renumber();list.lastElementChild.querySelector('input')?.focus();});
list?.addEventListener('click',event=>{const remove=event.target.closest('.source-remove');if(remove){remove.closest('.income-source-row').remove();renumber();}});
// Brazilian phone mask: (DD) 99999-9999 or (DD) 9999-9999.
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
// Currency mask stores a localized string; the server converts it to decimal.
const reais=new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'});
function formatMoney(value){
  const digits=String(value||'').replace(/\D/g,'').slice(0,12);
  return digits?reais.format(Number(digits)/100):'';
}
function normalizeInitialMoney(input){
  const raw=input.value.trim();
  if(!raw)return;
  if(raw.includes('R
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
choose?.addEventListener('click',()=>picker.click());
camera?.addEventListener('click',()=>capture.click());
function render(){const vw=viewport.clientWidth;const vh=viewport.clientHeight;const w=image.naturalWidth*scale,h=image.naturalHeight*scale;x=Math.max(vw-w,Math.min(0,x));y=Math.max(vh-h,Math.min(0,y));image.style.width=w+'px';image.style.height=h+'px';image.style.left=x+'px';image.style.top=y+'px';}
function loadFile(file){if(!file)return;if(!file.type.startsWith('image/')||file.size>20*1024*1024){alert('Escolha uma imagem JPG, PNG ou WEBP de até 20 MB.');return;}
if(imageUrl)URL.revokeObjectURL(imageUrl);
imageUrl=URL.createObjectURL(file);image.onload=()=>{if(!image.naturalWidth||!image.naturalHeight){alert('Imagem inválida.');return;}dialog.showModal();zoom.value='1';requestAnimationFrame(()=>{base=Math.max(viewport.clientWidth/image.naturalWidth,viewport.clientHeight/image.naturalHeight);scale=base;x=(viewport.clientWidth-image.naturalWidth*scale)/2;y=(viewport.clientHeight-image.naturalHeight*scale)/2;render();});};image.onerror=()=>alert('Não foi possível abrir essa imagem.');image.src=imageUrl;}
[picker,capture].forEach(input=>input?.addEventListener('change',()=>{loadFile(input.files?.[0]);input.value='';}));
zoom?.addEventListener('input',()=>{const cx=viewport.clientWidth/2,cy=viewport.clientHeight/2;const ratio=(base*Number(zoom.value))/scale;x=cx-(cx-x)*ratio;y=cy-(cy-y)*ratio;scale=base*Number(zoom.value);render();});
viewport?.addEventListener('pointerdown',e=>{drag={id:e.pointerId,x:e.clientX,y:e.clientY,ix:x,iy:y};viewport.setPointerCapture(e.pointerId);});
viewport?.addEventListener('pointermove',e=>{if(!drag||e.pointerId!==drag.id)return;x=drag.ix+e.clientX-drag.x;y=drag.iy+e.clientY-drag.y;render();});
for(const event of ['pointerup','pointercancel'])viewport?.addEventListener(event,()=>{drag=null;});
document.getElementById('crop-cancel')?.addEventListener('click',()=>dialog.close());
dialog?.addEventListener('close',()=>{if(imageUrl){URL.revokeObjectURL(imageUrl);imageUrl=null;}});
form?.addEventListener('submit',event=>{event.preventDefault();if(!image.naturalWidth)return;
const canvas=document.createElement('canvas');canvas.width=1080;canvas.height=1080;
const ctx=canvas.getContext('2d');if(!ctx){alert('Seu navegador não suporta recorte de fotos.');return;}
const vw=viewport.clientWidth,vh=viewport.clientHeight;
ctx.drawImage(image,(-x/scale),(-y/scale),vw/scale,vh/scale,0,0,1080,1080);
payload.value=canvas.toDataURL('image/jpeg',0.88);
if(payload.value.length>3000000){alert('Imagem muito grande. Tente outra foto.');return;}
form.submit();});
})();)||raw.includes(',')){
    const normalized=raw.replace(/R\$\s*/,'').replace(/\./g,'').replace(',','.');
    const value=Number(normalized);
    if(Number.isFinite(value))input.value=reais.format(value);
  }else{
    const value=Number(raw);
    if(Number.isFinite(value))input.value=reais.format(value);
  }
}
document.querySelectorAll('.profile-currency').forEach(normalizeInitialMoney);
list?.addEventListener('input',event=>{
  const input=event.target.closest('input.profile-currency');
  if(input)input.value=formatMoney(input.value);
});
const passwordToggle=document.getElementById('profile-password-toggle');
const passwordPanel=document.getElementById('profile-password-panel');
passwordToggle?.addEventListener('click',()=>{
 const show=passwordPanel.hidden;
 passwordPanel.hidden=!show;
 passwordToggle.setAttribute('aria-expanded',String(show));
 if(show)passwordPanel.querySelector('input[name="current_password"]')?.focus();
});
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
choose?.addEventListener('click',()=>picker.click());
camera?.addEventListener('click',()=>capture.click());
function render(){const vw=viewport.clientWidth;const vh=viewport.clientHeight;const w=image.naturalWidth*scale,h=image.naturalHeight*scale;x=Math.max(vw-w,Math.min(0,x));y=Math.max(vh-h,Math.min(0,y));image.style.width=w+'px';image.style.height=h+'px';image.style.left=x+'px';image.style.top=y+'px';}
function loadFile(file){if(!file)return;if(!file.type.startsWith('image/')||file.size>20*1024*1024){alert('Escolha uma imagem JPG, PNG ou WEBP de até 20 MB.');return;}
if(imageUrl)URL.revokeObjectURL(imageUrl);
imageUrl=URL.createObjectURL(file);image.onload=()=>{if(!image.naturalWidth||!image.naturalHeight){alert('Imagem inválida.');return;}dialog.showModal();zoom.value='1';requestAnimationFrame(()=>{base=Math.max(viewport.clientWidth/image.naturalWidth,viewport.clientHeight/image.naturalHeight);scale=base;x=(viewport.clientWidth-image.naturalWidth*scale)/2;y=(viewport.clientHeight-image.naturalHeight*scale)/2;render();});};image.onerror=()=>alert('Não foi possível abrir essa imagem.');image.src=imageUrl;}
[picker,capture].forEach(input=>input?.addEventListener('change',()=>{loadFile(input.files?.[0]);input.value='';}));
zoom?.addEventListener('input',()=>{const cx=viewport.clientWidth/2,cy=viewport.clientHeight/2;const ratio=(base*Number(zoom.value))/scale;x=cx-(cx-x)*ratio;y=cy-(cy-y)*ratio;scale=base*Number(zoom.value);render();});
viewport?.addEventListener('pointerdown',e=>{drag={id:e.pointerId,x:e.clientX,y:e.clientY,ix:x,iy:y};viewport.setPointerCapture(e.pointerId);});
viewport?.addEventListener('pointermove',e=>{if(!drag||e.pointerId!==drag.id)return;x=drag.ix+e.clientX-drag.x;y=drag.iy+e.clientY-drag.y;render();});
for(const event of ['pointerup','pointercancel'])viewport?.addEventListener(event,()=>{drag=null;});
document.getElementById('crop-cancel')?.addEventListener('click',()=>dialog.close());
dialog?.addEventListener('close',()=>{if(imageUrl){URL.revokeObjectURL(imageUrl);imageUrl=null;}});
form?.addEventListener('submit',event=>{event.preventDefault();if(!image.naturalWidth)return;
const canvas=document.createElement('canvas');canvas.width=1080;canvas.height=1080;
const ctx=canvas.getContext('2d');if(!ctx){alert('Seu navegador não suporta recorte de fotos.');return;}
const vw=viewport.clientWidth,vh=viewport.clientHeight;
ctx.drawImage(image,(-x/scale),(-y/scale),vw/scale,vh/scale,0,0,1080,1080);
payload.value=canvas.toDataURL('image/jpeg',0.88);
if(payload.value.length>3000000){alert('Imagem muito grande. Tente outra foto.');return;}
form.submit();});
})();