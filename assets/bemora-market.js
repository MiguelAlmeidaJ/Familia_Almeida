(()=>{
'use strict';
const app=window.BEMORA_MARKET||{};
const picker=document.getElementById('market-list-picker');
picker?.addEventListener('change',()=>{if(picker.value)location.href='/compras?lista='+encodeURIComponent(picker.value);});
const form=document.getElementById('market-scan-form');
if(!form||!app.list)return;
const $=id=>document.getElementById(id);
const video=$('market-video'),canvas=$('market-canvas'),start=$('market-start'),stop=$('market-stop'),capture=$('market-capture');
const placeholder=$('market-placeholder'),status=$('market-ocr-status'),preview=$('market-photo-preview');
 const cameraStep=$('market-camera-step'),reviewStep=$('market-review-step'),scanFeedback=$('market-scan-feedback');
const name=$('market-name'),price=$('market-price'),qty=$('market-qty'),unit=$('market-unit'),match=$('market-match'),tip=$('market-match-tip'),save=$('market-save');
const total=$('market-total'),progress=$('market-purchased'),count=$('market-count'),missing=$('market-missing'),rows=$('market-scan-items');
let stream=null,worker=null,loading=null,list=app.list;
const money=n=>Number(n).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
const clean=s=>(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,' ').trim();
const normalizeName=s=>s.replace(/\s+/g,' ').trim().slice(0,160);
function setStatus(text){status.textContent=text;if(scanFeedback)scanFeedback.textContent=text;}
 function setScanStep(step){const review=step==='review';if(cameraStep)cameraStep.hidden=review;if(reviewStep)reviewStep.hidden=!review;document.querySelectorAll('[data-market-progress]').forEach(el=>{if(el.dataset.marketProgress===step)el.setAttribute('aria-current','step');else el.removeAttribute('aria-current');});if(review)stopCamera();}
const basketTotal=$('market-scan-total'),basketCount=$('market-items-count');
const basketProgress=$('market-progress-track'),basketFill=$('market-progress-fill');
const basketFeedback=$('market-shopping-feedback');
let basketBusy=false;
const batchSize=10;
const morePanel=$('market-load-more'),moreButton=$('market-load-more-button'),moreCount=$('market-load-more-count');
let shownCount=0;
let listObserver=null;
function appendBatch(){
 const from=shownCount;
 const next=Math.min(list.items.length,from+batchSize);
 for(let index=from;index<next;index++)rows.append(buildBasketRow(list.items[index]));
 shownCount=next;
 const pending=list.items.length-shownCount;
 if(morePanel)morePanel.hidden=pending===0;
 if(moreCount)moreCount.textContent=pending>0?'Exibindo '+shownCount+' de '+list.items.length+' produtos':'Todos os produtos exibidos';
}
moreButton?.addEventListener('click',appendBatch);
if(morePanel&&'IntersectionObserver' in window){
 listObserver=new IntersectionObserver(entries=>{
  if(entries.some(entry=>entry.isIntersecting)&&!morePanel.hidden)appendBatch();
 },{rootMargin:'160px 0px',threshold:0});
 listObserver.observe(morePanel);
}
function feedback(message,isError=false){
 basketFeedback.textContent=message;
 basketFeedback.classList.toggle('is-error',isError);
}
async function marketAction(action,item,extra={}){
 if(basketBusy)return;
 if(list.status==='completed'){feedback('Reabra a lista para fazer alterações.',true);return;}
 basketBusy=true;
 feedback('Atualizando a lista…');
 for(const control of rows.querySelectorAll('button'))control.disabled=true;
 try{
  const params=new URLSearchParams({ajax:'1',csrf_token:app.csrf,list_id:String(list.id),item_id:String(item.id),action,...extra});
  const response=await fetch('/compras/acao',{method:'POST',body:params,credentials:'same-origin',headers:{Accept:'application/json'}});
  const data=await response.json();
  if(!response.ok||!data.ok)throw new Error(data.message||'Não foi possível atualizar.');
  list=data.list;
  render();
  feedback(action==='remove_item'?'Produto retirado da lista.':extra.purchased==='1'?'Produto marcado como comprado.':'Compra desfeita. O valor foi retirado do total.');
 }catch(error){
  feedback(error.message||'Erro ao atualizar. Tente novamente.',true);
  for(const control of rows.querySelectorAll('button'))control.disabled=false;
 }finally{basketBusy=false;}
}
function buildBasketRow(item){
 const row=document.createElement('article');
 row.className='market-basket-item'+(item.purchased?' is-purchased':'');
 row.dataset.itemId=String(item.id);
 const main=document.createElement('div');main.className='market-basket-main';
 const icon=document.createElement('span');icon.className='market-basket-check';icon.setAttribute('aria-hidden','true');
 icon.textContent=item.purchased?'✓':'○';
 const desc=document.createElement('div');desc.className='market-basket-description';
 const title=document.createElement('strong');title.textContent=item.name;
 const detail=document.createElement('small');detail.textContent=item.quantity+' '+item.unit+(item.unit_price!==null?' · '+money(item.unit_price)+'/un':' · preço pendente');
 desc.append(title,detail);main.append(icon,desc);
 const amount=document.createElement('strong');amount.className='market-basket-price';
 amount.textContent=item.purchased&&item.unit_price!==null?money(item.unit_price*item.quantity):'Pendente';
 if(item.purchased)amount.classList.add('is-counted');
 const actions=document.createElement('div');actions.className='market-basket-actions';
 if(item.purchased){
  const undo=document.createElement('button');undo.type='button';undo.className='market-basket-undo';
  undo.innerHTML='<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Desfazer compra';
  undo.setAttribute('aria-label','Desmarcar '+item.name+' como comprado');
  undo.disabled=list.status==='completed';
  undo.addEventListener('click',()=>marketAction('toggle_item',item,{purchased:'0'}));
  actions.append(undo);
 }else if(item.unit_price!==null){
  const check=document.createElement('button');check.type='button';check.className='market-basket-check-buy';
  check.innerHTML='<i class="bi bi-check2" aria-hidden="true"></i> Marcar comprado';
  check.setAttribute('aria-label','Marcar '+item.name+' como comprado');
  check.disabled=list.status==='completed';
  check.addEventListener('click',()=>marketAction('toggle_item',item,{purchased:'1'}));
  actions.append(check);
 }else{
  const label=document.createElement('span');label.className='market-basket-waiting';label.textContent='Aguardando preço';
  actions.append(label);
 }
 const remove=document.createElement('button');remove.type='button';remove.className='market-basket-remove';
 remove.innerHTML='<i class="bi bi-trash3" aria-hidden="true"></i> Retirar da lista';
 remove.setAttribute('aria-label','Remover '+item.name+' da lista de compras');
 remove.disabled=list.status==='completed';
 remove.addEventListener('click',()=>{
  if(window.confirm('Retirar "'+item.name+'" da lista? Esta ação remove o produto da compra.'))marketAction('remove_item',item);
 });
 actions.append(remove);
 row.append(main,amount,actions);
 return row;
}
function render(){
 total.textContent=money(list.total);
 progress.textContent=list.purchased;count.textContent=list.count;
 missing.textContent=list.missing_prices?'Há itens sem preço confirmado':'';
 if(basketTotal)basketTotal.textContent=money(list.total);
 if(basketCount)basketCount.textContent=list.purchased+'/'+list.count;
 if(basketProgress){
  basketProgress.setAttribute('aria-valuemax',String(Math.max(1,list.count)));
  basketProgress.setAttribute('aria-valuenow',String(list.purchased));
 }
 if(basketFill)basketFill.style.width=(list.count?list.purchased/list.count*100:0)+'%';
 const previous=match.value;
 match.replaceChildren(new Option('Adicionar como novo produto',''));
 rows.replaceChildren();
 if(!list.items.length){
  const empty=document.createElement('div');empty.className='market-basket-empty';
  empty.textContent='Sua lista está vazia. Escaneie uma etiqueta ou adicione produtos no planejamento.';
  rows.append(empty);
 }
 for(const item of list.items){
  match.add(new Option(item.name+(item.purchased?' · comprado':''),String(item.id)));
 }
 // Render only the visible batch; older rows are discarded after a change
 // to keep DOM size and phone scrolling responsive for larger lists.
 const previouslyVisible=Math.max(batchSize,shownCount);
 shownCount=0;
 while(shownCount<Math.min(previouslyVisible,list.items.length))appendBatch();
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
 if(!navigator.mediaDevices?.getUserMedia){setStatus('Câmera indisponível. Abra o Bemora em HTTPS e permita o acesso à câmera.');return;}
 try{
  stream=await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}}});
  if(scanDialog&&!scanDialog.open){stopCamera();return;}
  video.srcObject=stream;await video.play();placeholder.hidden=true;video.style.visibility='visible';
  capture.disabled=false;stop.disabled=false;start.disabled=true;
  setStatus('Centralize a etiqueta dentro da moldura e pressione Capturar etiqueta.');
 }catch(e){stopCamera();setStatus('Não foi possível acessar a câmera. Verifique a permissão do navegador e tente novamente.');}
});
stop.addEventListener('click',stopCamera);
const scanDialog=document.getElementById('market-scan-dialog');
scanDialog?.addEventListener('close',()=>{stopCamera();setScanStep('camera');});
 $('market-review-back')?.addEventListener('click',()=>{setScanStep('camera');start.click();});
document.getElementById('market-open-camera')?.addEventListener('click',()=>{
 if(!scanDialog||list.status==='completed')return;
 setScanStep('camera');scanDialog.showModal();
 start.click();
});
document.getElementById('market-close-camera')?.addEventListener('click',()=>scanDialog?.close());

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
const marketCamera=document.querySelector('.market-camera');
const guideBox=document.querySelector('.market-guides > div');
const shapes={
 wide:{label:'horizontal',crop:{x:.04,y:.32,w:.92,h:.36}},
 medium:{label:'padrão',crop:{x:.075,y:.30,w:.85,h:.40}},
 square:{label:'quadrada',crop:{x:.19,y:.15,w:.62,h:.70}},
 tall:{label:'vertical',crop:{x:.27,y:.08,w:.46,h:.84}}
};
let currentShape='wide';
for(const button of document.querySelectorAll('[data-market-shape]')){
 button.addEventListener('click',()=>{
  const shape=button.dataset.marketShape;
  if(!Object.prototype.hasOwnProperty.call(shapes,shape))return;
  currentShape=shape;
  marketCamera.dataset.shape=shape;
  for(const other of document.querySelectorAll('[data-market-shape]')){
   const selected=other===button;other.classList.toggle('active',selected);other.setAttribute('aria-pressed',String(selected));
  }
  const hint=$('market-shape-hint');
  if(hint)hint.textContent='Enquadre a etiqueta '+shapes[shape].label+' dentro da marcação; o recorte seguirá exatamente essa área.';
  
 });
}
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
 const lines=text.split(/\r?\n/).map(s=>s.replace(/\s+/g,' ').trim()).filter(Boolean);
 // A common Brazilian label contains different prices for varejo/atacado.
 // Do not treat a barcode or tiny fractional quantity as a product price.
 const candidates=[];
 let context='';
 for(let i=0;i<lines.length;i++){
  const line=lines[i];
  if(/\b(varejo|atacado|clube|promocional|oferta)\b/i.test(line))context=line;
  const matchAll=[...line.matchAll(/(?:R\s*\$\s*)?(\d{1,4}[,.]\s*\d{2})(?!\d)/g)];
  for(const match of matchAll){
   const n=Number(match[1].replace(/\s/g,'').replace(',','.'));
   if(!Number.isFinite(n)||n<.50||n>=10000)continue;
   const before=line.slice(Math.max(0,match.index-30),match.index);
   const after=line.slice(match.index+match[0].length,match.index+match[0].length+25);
   const surroundings=(before+' '+after+' '+context).toLowerCase();
   if(/\b(?:c[oó]d(?:igo)?|ean|fabrica|validade|cep|cpf|cnpj)\b/.test(before.toLowerCase()))continue;
   const nearest=(before.match(/(?:varejo|atacado|clube)[^a-záéíóúãõç]*$/i)||[])[0]||'';
   const category=/atacado/i.test(nearest)?'Atacado':/varejo/i.test(nearest)?'Varejo':/clube/i.test(nearest)?'Clube':/atacado/i.test(line)&&!/(?:varejo|clube)/i.test(line)?'Atacado':/varejo/i.test(line)&&!/(?:atacado|clube)/i.test(line)?'Varejo':'';
   const weight=(/R\s*\$/i.test(line)?4:0)+(/pre[cç]o|varejo|atacado/i.test(surroundings)?2:0)+(n>=1?1:0);
   candidates.push({amount:n,label:category,weight,idx:i});
  }
 }
 // Merge the same amount across OCR fragments, keeping the best label.
 const grouped=new Map();
 for(const c of candidates){const key=c.amount.toFixed(2);if(!grouped.has(key)||grouped.get(key).weight<c.weight)grouped.set(key,c);}
 const options=[...grouped.values()].sort((a,b)=>b.weight-a.weight||a.idx-b.idx).slice(0,5);
 const bad=/^(?:R\s*\$|pre[cç]o|varejo|atacado|desconto|promo[cç][aã]o|oferta|clube|total|leve|pague|mercado|supermercado|validade|c[oó]digo|ean|unit[aá]rio|unidades)/i;
 const names=lines.map((line,index)=>{
  const value=line.replace(/(?:R\s*\$)?\s*\d{1,4}[,.]\s*\d{2}/g,'').replace(/[|*_=]+/g,' ').replace(/\s+/g,' ').trim();
  const letters=(value.match(/[a-záàâãéêíóôõúç]/gi)||[]).length;
  const digits=(value.match(/\d/g)||[]).length;
  const hasPackage=/\b(?:\d+\s*(?:kg|g|ml|l|un|und|pct)|tipo\s*\d+)\b/i.test(value);
  const badLine=bad.test(value)||/\b(?:R\$|CNPJ|ATACADO|VAREJO|LEVE|PAGUE|PROMO|COD|OFERTA|PRECO)\b/i.test(value);
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
 const display=video.getBoundingClientRect();
 const guide=guideBox.getBoundingClientRect();
 if(!vw||!vh||!display.width||!display.height||!guide.width||!guide.height)throw new Error('Câmera sem imagem disponível.');
 // The displayed camera uses object-fit:cover. Match the exact visible guide,
 // including all responsive layouts and selected aspect ratios.
 const scale=Math.max(display.width/vw,display.height/vh);
 const offsetX=(vw*scale-display.width)/2,offsetY=(vh*scale-display.height)/2;
 const left=(guide.left-display.left+offsetX)/scale;
 const top=(guide.top-display.top+offsetY)/scale;
 const width=guide.width/scale,height=guide.height/scale;
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
cropCanvas?.addEventListener('pointerdown',e=>{
 if(!uploadImage)return;
 cropCanvas.setPointerCapture(e.pointerId);dragOrigin=point(e);cropRect={x:dragOrigin.x,y:dragOrigin.y,w:.001,h:.001};
 drawCropEditor();
});
cropCanvas?.addEventListener('pointermove',e=>{
 if(!dragOrigin)return;
 const p=point(e);
 cropRect={x:Math.min(dragOrigin.x,p.x),y:Math.min(dragOrigin.y,p.y),w:Math.abs(p.x-dragOrigin.x),h:Math.abs(p.y-dragOrigin.y)};
 drawCropEditor();
});
function finishCrop(){dragOrigin=null;if(cropRect.w<.08||cropRect.h<.05){cropRect={...shapes[currentShape].crop};drawCropEditor();}}
cropCanvas?.addEventListener('pointerup',finishCrop);
cropCanvas?.addEventListener('pointercancel',finishCrop);
function productQuality(value){
 if(!value)return -100;
 const letters=(value.match(/[a-záàâãéêíóôõúç]/gi)||[]).length;
 const digitCount=(value.match(/\d/g)||[]).length;
 const tokens=value.split(/\s+/).filter(Boolean);
 const coherent=tokens.filter(token=>/[a-záéíóúãõç]{3,}/i.test(token)).length;
 const suspicious=tokens.filter(token=>/[^a-záàâãéêíóôõúç0-9-]/i.test(token)).length;
 const packageHint=/\b(?:\d+\s*(?:kg|g|ml|l|un|und|pct)|tipo\s*\d+)\b/i.test(value);
 return coherent*3+Math.min(letters,40)*.12+(packageHint?9:0)-digitCount*.12-suspicious*2;
}
function betterName(existing,candidate){
 if(!candidate)return existing;
 if(!existing)return candidate;
 return productQuality(candidate)>productQuality(existing)?candidate:existing;
}
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
 recognizing=true;save.disabled=true;capture.disabled=true;
 name.value='';price.value='';setPriceChoices([]);
 try{
  setupOutput(cropped);
  const reader=await getWorker();
  setStatus('Lendo apenas o recorte da etiqueta. Aguarde…');
  const result=await reader.recognize(enhance(cropped));
  let parsed=parseLabel(result.data.text||'');
  // Large price digits can dominate OCR. Read the product-name band separately
  // and prefer its result when the label header is clearer.
  const topBand=cropFrom(cropped,0,0,1,.38);
  const header=await reader.recognize(enhance(topBand));
  const topParsed=parseLabel(header.data.text||'');
  parsed.product=betterName(parsed.product,topParsed.product);
  if(!parsed.product||!parsed.options.length){
   const backup=await reader.recognize(cropped);
   const alternative=parseLabel(backup.data.text||'');
   parsed.product=betterName(parsed.product,alternative.product);
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
 }catch(error){setStatus('Não foi possível ler a etiqueta. Preencha os campos manualmente ou escaneie novamente.');}
 finally{recognizing=false;save.disabled=list.status==='completed';capture.disabled=!stream;setScanStep('review');}
}
capture.addEventListener('click',()=>{
 if(!stream||!video.videoWidth||recognizing)return;
 try{
  const cropped=cropVisibleGuide();

  recognize(cropped);
 }catch(error){setStatus('Não foi possível capturar a etiqueta. Reposicione a câmera e tente novamente.');}
});
form.addEventListener('submit',async e=>{
 e.preventDefault();
 if(list.status==='completed'){setStatus('Reabra a lista antes de registrar compras.');return;}
 if(basketBusy){setStatus('Aguarde a atualização do carrinho antes de confirmar outro produto.');return;}
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
  if(scanDialog?.open)scanDialog.close();
  if(window.matchMedia('(max-width: 750px)').matches){$('market-shopping-items')?.scrollIntoView({behavior:'smooth',block:'start'});}
 }catch(error){setStatus(error.message||'Erro ao salvar produto.');}
 finally{save.disabled=false;}
});
render();
})();
