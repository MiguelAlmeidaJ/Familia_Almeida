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
function extract(text){
 const lines=text.split(/\r?\n/).map(s=>s.replace(/\s+/g,' ').trim()).filter(Boolean);
 let detectedPrice='';
 const candidates=[];
 for(let i=0;i<lines.length;i++){
  const line=lines[i];
  const matches=[...line.matchAll(/(?:R\$\s*)?(\d{1,5}[.,]\d{2})(?!\d)/g)];
  for(const m of matches){
   const normalized=m[1].replace(',','.');
   const amount=Number(normalized);
   if(amount>0&&amount<100000)candidates.push({amount,line,index:i,priority:/(?:R\$|pre[cç]o|unit[aá]rio|por\s+un|total)/i.test(line)?2:1});
  }
 }
 candidates.sort((a,b)=>b.priority-a.priority||b.index-a.index);
 if(candidates[0])detectedPrice=candidates[0].amount.toFixed(2);
 const productLines=lines.filter(l=>!/(?:^\d+[,\.]\d{2}$|R\$|pre[cç]o|unit[aá]rio|total|c[oó]digo|ean|promo|oferta|clube)/i.test(l))
   .filter(l=>/[a-záéíóúãõç]{3,}/i.test(l)).filter(l=>l.length>=4&&l.length<=100);
 const label=productLines.sort((a,b)=>b.replace(/[^a-záéíóúãõç ]/gi,'').length-a.replace(/[^a-záéíóúãõç ]/gi,'').length)[0]||'';
 return {name:normalizeName(label),price:detectedPrice};
}
async function recognize(source){
 save.disabled=true;capture.disabled=true;
 try{
  const reader=await getWorker();
  setStatus('Lendo a etiqueta… Centralize a foto e confira o resultado antes de salvar.');
  const result=await reader.recognize(source);
  const extracted=extract(result.data.text||'');
  if(extracted.name)name.value=extracted.name;
  if(extracted.price)price.value=extracted.price;
  offerMatch();
  if(!extracted.name||!extracted.price)setStatus('Leitura parcial. Preencha ou corrija nome e preço manualmente.');
  else setStatus('Leitura concluída. Confira se o preço é unitário (e não valor por kg ou promoção) e confirme.');
 }catch(e){setStatus('Não foi possível reconhecer a etiqueta. Ajuste a imagem ou preencha os campos manualmente.');}
 finally{save.disabled=list.status==='completed';capture.disabled=!stream;}
}
capture.addEventListener('click',()=>{
 if(!stream||!video.videoWidth)return;
 const w=video.videoWidth,h=video.videoHeight;
 // The guide is 85% wide and 40% high, centered. Crop only the intended label area.
 canvas.width=Math.round(w*.85);canvas.height=Math.round(h*.4);
 canvas.getContext('2d',{willReadFrequently:true}).drawImage(video,w*.075,h*.3,w*.85,h*.4,0,0,canvas.width,canvas.height);
 const url=canvas.toDataURL('image/jpeg',.92);
 preview.src=url;preview.hidden=false;
 recognize(canvas);
});
photo.addEventListener('change',()=>{
 const file=photo.files?.[0];if(!file)return;
 if(!file.type.startsWith('image/')||file.size>12*1024*1024){setStatus('Escolha uma imagem de até 12 MB.');return;}
 const url=URL.createObjectURL(file);preview.src=url;preview.hidden=false;
 recognize(file).finally(()=>URL.revokeObjectURL(url));
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
