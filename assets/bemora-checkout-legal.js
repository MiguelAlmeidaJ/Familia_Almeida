(()=>{
'use strict';
const form=document.getElementById('checkout-form');
const dialog=document.getElementById('checkout-legal-dialog');
if(!form||!dialog)return;
const frame=document.getElementById('checkout-legal-frame');
const title=document.getElementById('checkout-legal-dialog-title');
const description=document.getElementById('checkout-legal-dialog-description');
const confirm=document.getElementById('checkout-legal-confirm');
const full=document.getElementById('checkout-legal-full');
const close=document.getElementById('checkout-legal-close');
const back=document.getElementById('checkout-legal-back');
const checkout=document.getElementById('checkout-submit');
const message=document.getElementById('checkout-legal-message');
const state={terms:false,privacy:false};
const docs={
 terms:{title:'Termos de uso e contratação',description:'Confira as condições do serviço antes de aceitar.',url:'/termos',confirm:'Li e aceito os termos',done:'Termos aceitos'},
 privacy:{title:'Política de privacidade',description:'Conheça o tratamento de seus dados antes de confirmar sua ciência.',url:'/privacidade',confirm:'Li e estou ciente da política',done:'Ciência confirmada'}
};
let current=null;
let lastTrigger=null;
let readingReady=false;
function update(){
 const ready=state.terms&&state.privacy;
 const terms=document.getElementById('checkout-terms-accepted');
 const privacy=document.getElementById('checkout-privacy-acknowledged');
 const general=document.getElementById('checkout-legal-accept');
 terms.value=state.terms?'yes':'';
 privacy.value=state.privacy?'yes':'';
 general.value=ready?'yes':'';
 for(const key of Object.keys(docs)){
  const item=document.querySelector('[data-consent-item="'+key+'"]');
  const status=document.querySelector('[data-consent-status="'+key+'"]');
  const button=document.querySelector('[data-open-legal="'+key+'"]');
  if(!item||!status||!button)continue;
  item.classList.toggle('is-complete',state[key]);
  status.textContent=state[key]?docs[key].done:(key==='terms'?'Aguardando aceite':'Aguardando ciência');
  button.textContent=state[key]?'Revisar documento':'Ler e '+(key==='terms'?'aceitar':'confirmar');
 }
 message.textContent=ready?'Documentos confirmados. Você pode continuar para o pagamento.':'Confirme os dois documentos para liberar o pagamento.';
 message.classList.toggle('is-complete',ready);
 checkout.dataset.legalReady=ready?'yes':'no';
 checkout.dispatchEvent(new Event('checkout-legal-change'));
}
function dismiss(){if(dialog.open)dialog.close();}
for(const button of document.querySelectorAll('[data-open-legal]')){
 button.addEventListener('click',()=>{
  const key=button.dataset.openLegal;
  if(!Object.prototype.hasOwnProperty.call(docs,key))return;
  lastTrigger=button;current=key;readingReady=false;
  title.textContent=docs[key].title;
  description.textContent=docs[key].description;
  confirm.textContent=docs[key].confirm;
  confirm.disabled=true;
  full.href=docs[key].url;
  frame.title=docs[key].title;
  frame.src=docs[key].url+'?embed=1';
  if(typeof dialog.showModal==='function')dialog.showModal();
  else{window.open(docs[key].url,'_blank','noopener,noreferrer');return;}
 });
}
frame.addEventListener('load',()=>{if(!dialog.open||!current)return;readingReady=true;confirm.disabled=false;});
confirm.addEventListener('click',()=>{
 if(!current||!dialog.open||!readingReady)return;
 state[current]=true;
 update();
 dismiss();
});
close.addEventListener('click',dismiss);
back.addEventListener('click',dismiss);
dialog.addEventListener('click',e=>{if(e.target===dialog)dismiss();});
dialog.addEventListener('close',()=>{
 frame.removeAttribute('src');
 current=null;readingReady=false;confirm.disabled=true;
 lastTrigger?.focus();
});
form.addEventListener('submit',e=>{
 if(state.terms&&state.privacy)return;
 e.preventDefault();
 message.textContent='Antes do pagamento, abra e confirme os Termos de Uso e a Política de Privacidade.';
 message.classList.remove('is-complete');
 document.querySelector('.checkout-legal-consents')?.scrollIntoView({behavior:'smooth',block:'center'});
});
update();
})();
