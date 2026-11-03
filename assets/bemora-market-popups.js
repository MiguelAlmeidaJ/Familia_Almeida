(()=>{
'use strict';
const $=id=>document.getElementById(id);
const productDialog=$('market-product-dialog');
const productForm=$('market-add-product-form');
const productFeedback=$('market-product-feedback');
if(productDialog&&productForm){
 const opener=$('market-open-product');
 const cancel=$('market-product-cancel');
 const close=$('market-close-product');
 const saveClose=$('market-product-save-close');
 const saveMore=$('market-product-save-continue');
 const name=productForm.querySelector('[name="name"]');
 let saving=false,modified=false;
 const dismiss=()=>{
  if(saving)return;
  productDialog.close();
  if(modified)location.reload();
 };
 opener?.addEventListener('click',()=>{productDialog.showModal();name?.focus();});
 cancel?.addEventListener('click',dismiss);
 close?.addEventListener('click',dismiss);
 productDialog.addEventListener('cancel',event=>{if(saving){event.preventDefault();return;}if(modified){event.preventDefault();dismiss();}});
 productDialog.addEventListener('close',()=>{if(modified)location.reload();else opener?.focus();});
 async function addProduct(keepOpen){
  if(saving||!productForm.reportValidity())return;
  saving=true;
  saveClose.disabled=true;saveMore.disabled=true;cancel.disabled=true;
  productFeedback.textContent='Salvando produto…';
  try{
   const body=new URLSearchParams(new FormData(productForm));
   body.set('ajax','1');
   const response=await fetch('/compras/acao',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body});
   const data=await response.json();
   if(!response.ok||!data.ok)throw new Error(data.message||'Não foi possível adicionar o produto.');
   modified=true;
   if(keepOpen){
    productForm.reset();
    const quantity=productForm.querySelector('[name="quantity"]');
    if(quantity)quantity.value='1';
    productFeedback.textContent='Produto salvo! Você pode adicionar o próximo.';
    name?.focus();
   }else{productDialog.close();location.reload();}
  }catch(error){
   productFeedback.textContent=error.message||'Não foi possível salvar o produto.';
  }finally{
   saving=false;saveClose.disabled=false;saveMore.disabled=false;cancel.disabled=false;
  }
 }
 saveClose?.addEventListener('click',()=>addProduct(false));
 saveMore?.addEventListener('click',()=>addProduct(true));
 productForm.addEventListener('submit',event=>{event.preventDefault();addProduct(false);});
}
const finishDialog=$('market-finish-dialog');
const finishOpen=$('market-open-finish');
const finishForm=$('market-finish-form');
if(finishDialog&&finishOpen&&finishForm){
 finishOpen.addEventListener('click',()=>{
  if(finishOpen.disabled)return;
  const value=$('market-total')?.textContent||'R$ 0,00';
  const total=$('market-finish-total');
  if(total)total.textContent=value;
  finishDialog.showModal();
 });
 for(const button of finishDialog.querySelectorAll('[data-close-market-dialog]')){
  button.addEventListener('click',()=>finishDialog.close());
 }
 finishForm.addEventListener('submit',event=>{
  const raw=$('market-total')?.textContent||'';
  const amount=Number(raw.replace(/[^0-9,]/g,'').replace(',','.'));
  if(!Number.isFinite(amount)||amount<=0){
   event.preventDefault();
   window.alert('Marque ao menos um produto comprado com preço válido antes de finalizar.');
   return;
  }
  if(!window.confirm('Confirmar a finalização e registrar '+raw+' como gasto de supermercado?')){
   event.preventDefault();return;
  }
  const button=$('market-finish-confirm');
  if(button){button.disabled=true;button.textContent='Registrando gasto…';}
 });
}
})();
