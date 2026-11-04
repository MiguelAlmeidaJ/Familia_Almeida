(()=>{'use strict';
const postal=document.getElementById('address-postal');
const lookupButton=document.getElementById('address-lookup');
const status=document.getElementById('address-lookup-status');
const street=document.getElementById('address-street');
const state=document.getElementById('address-state');
const city=document.getElementById('address-city');
const manual=document.getElementById('address-city-manual');
const cityStatus=document.getElementById('address-city-status');
if(!postal||!lookupButton||!street||!state||!city||!manual)return;
let citiesRequest=0,postalRequest=0,loadingCities=false;
const normalize=v=>String(v||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
const digits=v=>String(v||'').replace(/\D/g,'');
function setMessage(node,message,error=false){node.textContent=message;node.classList.toggle('is-error',error);}
function showManual(message){
 city.replaceChildren(new Option('Consulta indisponível',''));
 city.disabled=true;city.required=false;
 manual.hidden=false;manual.disabled=false;manual.required=true;
 setMessage(cityStatus,message,true);
}
function hideManual(){
 manual.hidden=true;manual.disabled=true;manual.required=false;
 city.required=true;
}
function populateCities(cities,preferred=''){
 city.replaceChildren(new Option('Selecione sua cidade',''));
 const unique=new Set();
 for(const item of cities){
  const name=typeof item?.nome==='string'?item.nome.trim():'';
  if(!name||unique.has(name))continue;
  unique.add(name);city.add(new Option(name,name));
 }
 city.disabled=false;city.required=true;
 hideManual();
 const wanted=normalize(preferred);
 if(wanted){
  const match=[...city.options].find(o=>normalize(o.value)===wanted);
  if(match)city.value=match.value;
 }
 setMessage(cityStatus,city.value?'Cidade localizada e selecionada.':'Selecione sua cidade na lista.');
}
async function loadCities(uf,preferred=''){
 const request=++citiesRequest;
 city.replaceChildren(new Option('Carregando cidades...',''));
 city.disabled=true;loadingCities=true;hideManual();
 if(!uf){loadingCities=false;city.replaceChildren(new Option('Selecione primeiro um estado',''));setMessage(cityStatus,'Escolha o estado para carregar os municípios.');return;}
 setMessage(cityStatus,'Buscando municípios no IBGE...');
 try{
  const response=await fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados/'+encodeURIComponent(uf)+'/municipios?orderBy=nome',{cache:'default'});
  if(!response.ok)throw new Error('IBGE indisponível');
  const items=await response.json();
  if(!Array.isArray(items)||items.length===0)throw new Error('Sem cidades');
  if(request!==citiesRequest)return;
  populateCities(items,preferred);
 }catch(error){if(request!==citiesRequest)return;showManual('Não foi possível carregar a lista do IBGE. Informe sua cidade manualmente.');}
 finally{if(request===citiesRequest)loadingCities=false;}
}
async function lookupPostal(){
 const cep=digits(postal.value);
 if(cep.length!==8){setMessage(status,'Digite os 8 números do CEP.',true);postal.focus();return;}
 const request=++postalRequest;
 postal.value=cep.slice(0,5)+'-'+cep.slice(5);
 lookupButton.disabled=true;
 setMessage(status,'Consultando o CEP...');
 try{
  const response=await fetch('https://viacep.com.br/ws/'+cep+'/json/',{cache:'no-store'});
  if(!response.ok)throw new Error('Erro de conexão');
  const data=await response.json();
  if(request!==postalRequest)return;
  if(data.erro===true||data.erro==='true'){setMessage(status,'CEP não localizado. Preencha o endereço, estado e cidade manualmente.',true);return;}
  if(typeof data.logradouro==='string'&&data.logradouro.trim())street.value=data.logradouro.trim();
  if(typeof data.uf==='string'&&[...state.options].some(o=>o.value===data.uf)){
   state.value=data.uf;
   await loadCities(data.uf,typeof data.localidade==='string'?data.localidade:'');
  }
  setMessage(status,'Endereço consultado. Confirme os dados e complete o que faltar.');
  if(!street.value.trim())street.focus();
 }catch(error){if(request===postalRequest)setMessage(status,'Consulta indisponível. Preencha o endereço, estado e cidade manualmente.',true);}
 finally{if(request===postalRequest)lookupButton.disabled=false;}
}
postal.addEventListener('input',()=>{
 const n=digits(postal.value).slice(0,8);
 postal.value=n.length>5?n.slice(0,5)+'-'+n.slice(5):n;
 postalRequest++;lookupButton.disabled=false;
 setMessage(status,n.length===8?'Clique em Buscar CEP para preencher o endereço.':'Informe os 8 números do CEP.');
});
postal.addEventListener('blur',()=>{if(digits(postal.value).length===8)lookupPostal();});
lookupButton.addEventListener('click',lookupPostal);
state.addEventListener('change',()=>loadCities(state.value));
window.bemoraAddressValidate=()=>{
 const controls=[postal,street,state];
 for(const control of controls){
  if(!control.checkValidity()){control.reportValidity();control.focus();return false;}
 }
 if(digits(postal.value).length!==8){setMessage(status,'CEP inválido. Informe 8 dígitos.',true);postal.focus();return false;}
 if(loadingCities){setMessage(cityStatus,'Aguarde o carregamento das cidades.',true);return false;}
 const activeCity=manual.disabled?city:manual;
 if(!activeCity.checkValidity()||!activeCity.value.trim()){
  activeCity.reportValidity();activeCity.focus();return false;
 }
 return true;
};
})();