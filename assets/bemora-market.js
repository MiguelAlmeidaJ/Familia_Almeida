(()=>{
'use strict';
const app=window.BEMORA_MARKET||{};
const picker=document.getElementById('market-list-picker');
picker?.addEventListener('change',()=>{if(picker.value)location.href='/compras?lista='+encodeURIComponent(picker.value);});
const form=document.getElementById('market-scan-form');
if(!form||!app.list)return;
const $=id=>document.getElementById(id);
const video=$('market-video'),canvas=$('market-canvas'),start=$('market-start'),stop=$('market-stop'),capture=$('market-capture');
const placeholder=$('market-placeholder'),status=$('market-ocr-status'),photo=$('market-photo'),preview=$('market-photo-preview');
const name=$('market-name'),price=$('market-price'),qty=$('market-qty'),unit=$('market-unit'),match=$('market-match'),tip=$('market-match-tip'),save=$('market-save');
const total=$('market-total'),progress=$('market-purchased'),count=$('market-count'),missing=$('market-missing'),rows=$('market-scan-items');
let stream=null,worker=null,loading=null,list=app.list;
const money=n=>Number(n).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
const clean=s=>(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim();
const normalizeName=s=>s.replace(/\s+/g,' ').trim().slice(0,160);
function setStatus(text){status.textContent=text;}
function render(){
 total.textContent=money(list.total);
 progress.textContent=list.purchased;count.textContent=list.count;
 missing.textContent=list.missing_prices?'Há itens sem preço confirmado':'';
 const previous=match.value;
 match.replaceChildren(new Option('Adicionar como novo produto',''));
 rows.replaceChildren();
 for(const item of list.items){
  match.add(new Option(item.name+(item.purchased?' · comprado':''),String(item.id)));
  const row=document.createElement('div');row.className='market-scan-row'+(item.purchased?' done':'');
  const label=document.createElement('span');label.textContent=item.name+' · '+item.quantity+' '+item.unit;
  const value=document.createElement('strong');value.textContent=item.purchased&&item.unit_price!==null?money(item.unit_price*item.quantity):'Pendente';
  row.append(label,value);rows.append(row);
 }
 if([...match.options].some(x=>x.value===previous))match.value=previous;
}
function offerMatch(){
 const query=clean(name.value);
 if(!query)return;
 const scored=list.items.map(item=>{
  const candidate=clean(item.name);
  const overlap=query.split(' ').filter(token=>token.length>2&&candidate.split(' ').includes(token)).length;
  return {item,score:query===candidate?100:(query.includes(candidate)||candidate.includes(query)?80:overlap*15)};
 }).sort((a,b)=>b.score-a.score);
 const selected=scored[0];
 if(selected&&selected.score>=30){match.value=String(selected.item.id);qty.value=String(selected.item.quantity);unit.value=selected.item.unit;tip.textContent='Produto semelhante encontrado: '+selected.item.name+'. Confira se é realmente o mesmo antes de confirmar.';}
 else{match.value='';tip.textContent='Produto não encontrado na lista. Você pode adicioná-lo como novo.';}
}
name.addEventListener('change',offerMatch);
match.addEventListener('change',()=>{
 const selected=list.items.find(x=>String(x.id)===match.value);
 if(selected){qty.value=selected.quantity;unit.value=selected.unit;tip.textContent='Este produto será marcado como comprado; confirme nome, quantidade e preço.';}
 else tip.textContent='O produto será adicionado à lista como comprado.';
});
function stopCamera(){
 if(stream){for(const track of stream.getTracks())track.stop();stream=null;}
 video.srcObject=null;video.style.visibility='hidden';placeholder.hidden=false;capture.disabled=true;stop.disabled=true;start.disabled=false;
}
start.addEventListener('click',async()=>{
 if(!navigator.mediaDevices?.getUserMedia){setStatus('Câmera indisponível neste navegador. Use a opção de selecionar uma foto.');return;}
 try{
  stream=await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}}});
  video.srcObject=stream;await video.play();placeholder.hidden=true;video.style.visibility='visible';
  capture.disabled=false;stop.disabled=false;start.disabled=true;
  setStatus('Centralize a etiqueta dentro da moldura e pressione Capturar etiqueta.');
 }catch(e){stopCamera();setStatus('Não foi possível acessar a câmera. Verifique a permissão ou selecione uma foto da etiqueta.');}
});
stop.addEventListener('click',stopCamera);
window.addEventListener('pagehide',()=>{stopCamera();if(worker)worker.terminate();});
async function getWorker(){
 if(worker)return worker;
 if(loading)return loading;
 loading=(async()=>{
  setStatus('Carregando o reconhecimento de texto. A primeira utilização pode demorar um pouco…');
  if(!window.Tesseract){
   await new Promise((resolve,reject)=>{
    const script=document.createElement('script');script.src='https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
    script.onload=resolve;script.onerror=()=>reject(new Error('Falha ao carregar OCR. Verifique a conexão.'));document.head.append(script);
   });
  }
  worker=await window.Tesseract.createWorker('por');
  return worker;
 })();
 try{return await loading;}finally{loading=null;}
}
// The visible guide and the actual cropped pixels use the same geometry.
const GUIDE={left:.075,top:.30,width:.85,height:.40};
const cropEditor=$('market-crop-editor'),cropCanvas=$('market-crop-canvas'),cropApply=$('market-crop-apply');
const priceOptions=$('market-price-options'),priceButtons=$('market-price-buttons');
let uploadImage=null,cropRect={x:.08,y:.28,w:.84,h:.43},dragOrigin=null,recognizing=false;
function setPriceChoices(options){
 priceButtons.replaceChildren();
 const prices=options.filter(v=>Number.isFinite(v.amount)&&v.amount>0);
 priceOptions.hidden=prices.length<2;
 for(const item of prices){
  const button=document.createElement('button');
  button.type='button';
  button.className='market-price-choice';
  button.textContent=(item.label?item.label+' · ':'')+money(item.amount);
  button.addEventListener('click',()=>{
   price.value=item.amount.toFixed(2);
   for(const el of priceButtons.children)el.classList.toggle('selected',el===button);
   setStatus('Preço selecionado. Confira a quantidade e confirme somente depois de conferir a etiqueta.');
  });
  priceButtons.append(button);
 }
}
function parseLabel(text){
 const lines=text.split(/\\r?\\n/).map(s=>s.replace(/\\s+/g,' ').trim()).filter(Boolean);
 // A common Brazilian label contains different prices for varejo/atacado.
 // Do not treat a barcode or tiny fractional quantity as a product price.
 const candidates=[];
 let context='';
 for(let i=0;i<lines.length;i++){
  const line=lines[i];
  if(/\\b(varejo|atacado|clube|promocional|oferta)\\b/i.test(line))context=line;
  const matchAll=[...line.matchAll(/(?:R\\s*\\$\\s*)?(\\d{1,4}[,.]\\s*\\d{2})(?!\\d)/g)];
  for(const match of matchAll){
   const n=Number(match[1].replace(/\\s/g,'').replace(',','.'));
   if(!Number.isFinite(n)||n<.50||n>=10000)continue;
   const before=line.slice(Math.max(0,match.index-30),match.index);
   const after=line.slice(match.index+match[0].length,match.index+match[0].length+25);
   const surroundings=(before+' '+after+' '+context).toLowerCase();
   if(/\\b(?:c[oó]d(?:igo)?|ean|fabrica|validade|cep|cpf|cnpj)\\b/.test(before.toLowerCase()))continue;
   const nearest=(before.match(/(?:varejo|atacado|clube)[^a-záéíóúãõç]*$/i)||[])[0]||'';
   const category=/atacado/i.test(nearest)?'Atacado':/varejo/i.test(nearest)?'Varejo':/clube/i.test(nearest)?'Clube':/atacado/i.test(line)&&!/(?:varejo|clube)/i.test(line)?'Atacado':/varejo/i.test(line)&&!/(?:atacado|clube)/i.test(line)?'Varejo':'';
   const weight=(/R\\s*\\$/i.test(line)?4:0)+(/pre[cç]o|varejo|atacado/i.test(surroundings)?2:0)+(n>=1?1:0);
   candidates.push({amount:n,label:category,weight,idx:i});
  }
 }
 // Merge the same amount across OCR fragments, keeping the best label.
 const grouped=new Map();
 for(const c of candidates){const key=c.amount.toFixed(2);if(!grouped.has(key)||grouped.get(key).weight<c.weight)grouped.set(key,c);}
 const options=[...grouped.values()].sort((a,b)=>b.weight-a.weight||a.idx-b.idx).slice(0,5);
 const bad=/^(?:R\\s*\\$|pre[cç]o|varejo|atacado|desconto|promo[cç][aã]o|oferta|clube|total|leve|pague|mercado|supermercado|validade|c[oó]digo|ean|unit[aá]rio|unidades)/i;
 const names=lines.map((line,index)=>{
  const value=line.replace(/(?:R\\s*\\$)?\\s*\\d{1,4}[,.]\\s*\\d{2}/g,'').replace(/[|*_=]+/g,' ').replace(/\\s+/g,' ').trim();
  const letters=(value.match(/[a-záàâãéêíóôõúç]/gi)||[]).length;
  const digits=(value.match(/\\d/g)||[]).length;
  const hasPackage=/\\b(?:\\d+\\s*(?:kg|g|ml|l|un|und|pct)|tipo\\s*\\d+)\\b/i.test(value);
  const badLine=bad.test(value)||/\\b(?:R\\$|CNPJ|ATACADO|VAREJO|LEVE|PAGUE|PROMO|COD|OFERTA|PRECO)\\b/i.test(value);
  const score=(hasPackage?8:0)+Math.min(letters,30)/5-digits/5-index*.9;
  return {value,score,badLine,letters};
 }).filter(v=>!v.badLine&&v.letters>=6&&v.value.length>=7&&v.value.length<=90);
 names.sort((a,b)=>b.score-a.score);
 const chosen=names[0];
 // Refuse low-quality OCR garbage rather than silently fabricating a label.
 const product=chosen&&chosen.score>=2.5?normalizeName(chosen.value):'';
 return {product,options};
}
function setupOutput(source){
 canvas.width=source.width;canvas.height=source.height;
 canvas.getContext('2d',{willReadFrequently:true}).drawImage(source,0,0);
 const url=canvas.toDataURL('image/jpeg',.92);
 preview.src=url;preview.hidden=false;
}
function cropFrom(source,x,y,w,h){
 const sw=source.videoWidth||source.naturalWidth||source.width;
 const sh=source.videoHeight||source.naturalHeight||source.height;
 const left=Math.max(0,Math.floor(x*sw)),top=Math.max(0,Math.floor(y*sh));
 const width=Math.max(1,Math.min(sw-left,Math.floor(w*sw)));
 const height=Math.max(1,Math.min(sh-top,Math.floor(h*sh)));
 const output=document.createElement('canvas');
 output.width=width;output.height=height;
 output.getContext('2d').drawImage(source,left,top,width,height,0,0,width,height);
 return output;
}
// object-fit:cover crops the camera's actual image before it reaches the screen.
// Convert the guide from display coordinates to the real video pixels.
function cropVisibleGuide(){
 const vw=video.videoWidth,vh=video.videoHeight;
 const rect=video.getBoundingClientRect();
 if(!vw||!vh||!rect.width||!rect.height)throw new Error('Câmera sem imagem disponível.');
 const scale=Math.max(rect.width/vw,rect.height/vh);
 const renderedW=vw*scale,renderedH=vh*scale;
 const offX=(renderedW-rect.width)/2,offY=(renderedH-rect.height)/2;
 const left=(rect.width*GUIDE.left+offX)/scale;
 const top=(rect.height*GUIDE.top+offY)/scale;
 const width=rect.width*GUIDE.width/scale;
 const height=rect.height*GUIDE.height/scale;
 return cropFrom(video,left/vw,top/vh,width/vw,height/vh);
}
function drawCropEditor(){
 if(!uploadImage)return;
 const w=cropCanvas.width,h=cropCanvas.height;
 const ctx=cropCanvas.getContext('2d');
 ctx.clearRect(0,0,w,h);ctx.drawImage(uploadImage,0,0,w,h);
 ctx.fillStyle='rgba(0,0,0,.35)';
 const x=cropRect.x*w,y=cropRect.y*h,cw=cropRect.w*w,ch=cropRect.h*h;
 ctx.fillRect(0,0,w,y);ctx.fillRect(0,y,x,ch);ctx.fillRect(x+cw,y,w-(x+cw),ch);ctx.fillRect(0,y+ch,w,h-(y+ch));
 ctx.strokeStyle='#abf7c7';ctx.lineWidth=Math.max(2,w/220);
 ctx.strokeRect(x,y,cw,ch);
}
function point(e){
 const rect=cropCanvas.getBoundingClientRect();
 return {x:Math.max(0,Math.min(1,(e.clientX-rect.left)/rect.width)),y:Math.max(0,Math.min(1,(e.clientY-rect.top)/rect.height))};
}
cropCanvas.addEventListener('pointerdown',e=>{
 if(!uploadImage)return;
 cropCanvas.setPointerCapture(e.pointerId);dragOrigin=point(e);cropRect={x:dragOrigin.x,y:dragOrigin.y,w:.001,h:.001};
 drawCropEditor();
});
cropCanvas.addEventListener('pointermove',e=>{
 if(!dragOrigin)return;
 const p=point(e);
 cropRect={x:Math.min(dragOrigin.x,p.x),y:Math.min(dragOrigin.y,p.y),w:Math.abs(p.x-dragOrigin.x),h:Math.abs(p.y-dragOrigin.y)};
 drawCropEditor();
});
function finishCrop(){dragOrigin=null;if(cropRect.w<.08||cropRect.h<.05){cropRect={x:.08,y:.28,w:.84,h:.43};drawCropEditor();}}
cropCanvas.addEventListener('pointerup',finishCrop);
cropCanvas.addEventListener('pointercancel',finishCrop);
function enhance(source){
 const output=document.createElement('canvas');
 // Upscale small labels so printed characters remain readable.
 const multiplier=Math.min(3,Math.max(1,Math.ceil(1400/source.width)));
 output.width=Math.min(2600,Math.round(source.width*multiplier));
 output.height=Math.min(1800,Math.round(source.height*multiplier));
 const ctx=output.getContext('2d',{willReadFrequently:true});
 ctx.imageSmoothingEnabled=true;ctx.imageSmoothingQuality='high';
 ctx.drawImage(source,0,0,output.width,output.height);
 const image=ctx.getImageData(0,0,output.width,output.height);
 const pixels=image.data;
 for(let i=0;i<pixels.length;i+=4){
  const lum=.299*pixels[i]+.587*pixels[i+1]+.114*pixels[i+2];
  const value=Math.max(0,Math.min(255,(lum-128)*1.65+132));
  pixels[i]=pixels[i+1]=pixels[i+2]=value;
 }
 ctx.putImageData(image,0,0);
 return output;
}
async function recognize(cropped){
 if(recognizing)return;
 recognizing=true;save.disabled=true;capture.disabled=true;cropApply.disabled=true;
 name.value='';price.value='';setPriceChoices([]);
 try{
  setupOutput(cropped);
  const reader=await getWorker();
  setStatus('Lendo apenas o recorte da etiqueta. Aguarde…');
  const result=await reader.recognize(enhance(cropped));
  let parsed=parseLabel(result.data.text||'');
  // Try original colors if enhanced recognition is incomplete.
  if(!parsed.product||!parsed.options.length){
   const backup=await reader.recognize(cropped);
   const alternative=parseLabel(backup.data.text||'');
   if(!parsed.product)parsed.product=alternative.product;
   if(!parsed.options.length)parsed.options=alternative.options;
  }
  if(parsed.product)name.value=parsed.product;
  setPriceChoices(parsed.options);
  if(parsed.options.length===1)price.value=parsed.options[0].amount.toFixed(2);
  // Multiple retail/wholesale prices must never be auto-selected.
  offerMatch();
  if(!parsed.product||!parsed.options.length)setStatus('A leitura ficou incompleta. Ajuste o recorte ou preencha nome e preço manualmente.');
  else if(parsed.options.length>1)setStatus('Foram encontrados vários preços. Selecione o valor correto (varejo ou atacado) e revise o nome.');
  else setStatus('Recorte lido. Confira o nome, preço e quantidade antes de confirmar.');
 }catch(error){setStatus('Não foi possível ler este recorte. Tente outra foto ou preencha os campos manualmente.');}
 finally{recognizing=false;save.disabled=list.status==='completed';capture.disabled=!stream;cropApply.disabled=false;}
}
capture.addEventListener('click',()=>{
 if(!stream||!video.videoWidth||recognizing)return;
 try{
  const cropped=cropVisibleGuide();
  cropEditor.hidden=true;
  recognize(cropped);
 }catch(error){setStatus('Não foi possível recortar a câmera. Tente selecionar uma foto.');}
});
photo.addEventListener('change',async()=>{
 const file=photo.files?.[0];if(!file)return;
 if(!file.type.startsWith('image/')||file.size>12*1024*1024){setStatus('Escolha uma imagem de até 12 MB.');return;}
 try{
  const bitmap=await createImageBitmap(file,{imageOrientation:'from-image'});
  const longest=Math.max(bitmap.width,bitmap.height);
  const scale=Math.min(1,1400/longest);
  const display=document.createElement('canvas');display.width=Math.round(bitmap.width*scale);display.height=Math.round(bitmap.height*scale);
  display.getContext('2d').drawImage(bitmap,0,0,display.width,display.height);
  bitmap.close();
  uploadImage=display;
  cropCanvas.width=display.width;cropCanvas.height=display.height;
  cropRect={x:.08,y:.28,w:.84,h:.43};
  cropEditor.hidden=false;preview.hidden=true;
  drawCropEditor();
  setStatus('Arraste o dedo para selecionar somente a etiqueta e toque em Ler área selecionada.');
 }catch(error){setStatus('Não foi possível abrir a imagem. Tente uma foto em JPG ou PNG.');}
});
cropApply.addEventListener('click',()=>{
 if(!uploadImage||recognizing)return;
 recognize(cropFrom(uploadImage,cropRect.x,cropRect.y,cropRect.w,cropRect.h));
});
form.addEventListener('submit',async e=>{
 e.preventDefault();
 if(list.status==='completed'){setStatus('Reabra a lista antes de registrar compras.');return;}
 if(!form.reportValidity())return;
 const selected=list.items.find(x=>String(x.id)===match.value);
 const params=new URLSearchParams({ajax:'1',csrf_token:app.csrf,list_id:String(list.id),
 action:selected?'update_item':'add_item',name:name.value.trim(),quantity:qty.value,unit:unit.value,price:price.value,
 purchased:'1',price_source:'label'});
 if(selected)params.set('item_id',String(selected.id));
 save.disabled=true;setStatus('Salvando produto confirmado…');
 try{
  const res=await fetch('/compras/acao',{method:'POST',body:params,credentials:'same-origin',headers:{Accept:'application/json'}});
  const data=await res.json();
  if(!res.ok||!data.ok)throw new Error(data.message||'Não foi possível salvar.');
  list=data.list;render();
  name.value='';price.value='';qty.value='1';unit.value='un';match.value='';
  setStatus('Produto e preço salvos. Você pode continuar escaneando a próxima etiqueta.');
 }catch(error){setStatus(error.message||'Erro ao salvar produto.');}
 finally{save.disabled=false;}
});
render();
})();
