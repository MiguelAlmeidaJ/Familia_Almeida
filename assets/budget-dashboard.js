(() => {
 const node = document.getElementById('budget-visual-data');
 const donut = document.getElementById('budget-donut');
 const legend = document.getElementById('budget-chart-legend');
 const bars = document.getElementById('budget-allocation-bars');
 if (!node || !donut || !legend || !bars) return;
 let data;
 try { data = JSON.parse(node.textContent); } catch (_) { return; }
 const money = n => Number(n).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
 const segments = [
  {label:'Contas previstas',value:Math.max(0,Number(data.bills)||0),color:'#183f39'},
  {label:'Gastos avulsos',value:Math.max(0,Number(data.spent)||0),color:'#e8a05c'},
  {label:'Saldo projetado',value:Math.max(0,Number(data.available)-Number(data.spent)),color:'#189b7c'}
 ];
 const sum=segments.reduce((s,x)=>s+x.value,0);
 const total=Math.max(Number(data.income)||0,sum);
 const slices=[];let cursor=0;
 segments.forEach((item,i)=>{const degrees=total>0?item.value/total*360:0;slices.push(item.color+' '+cursor+'deg '+(cursor+degrees)+'deg');cursor+=degrees;});
 if(total>sum) slices.push('#dce8ea '+cursor+'deg 360deg');
 donut.style.setProperty('--donut-fill',total>0?'conic-gradient('+slices.join(',')+')':'#e8eff1');
 segments.forEach((item,i)=>{
  const button=document.createElement('button');button.type='button';button.className='budget-legend-row';
  const swatch=document.createElement('span');swatch.className='budget-swatch';swatch.style.background=item.color;
  const label=document.createElement('span');label.textContent=item.label;
  const value=document.createElement('strong');value.textContent=money(item.value);
  button.append(swatch,label,value);
  button.addEventListener('click',()=>{legend.querySelectorAll('button').forEach(b=>b.classList.remove('active'));button.classList.add('active');donut.querySelector('small').textContent=item.label;donut.querySelector('strong').textContent=money(item.value);});
  legend.append(button);
 });
 donut.addEventListener('click',()=>{donut.querySelector('small').textContent='Receitas';donut.querySelector('strong').textContent=money(data.income);legend.querySelectorAll('button').forEach(b=>b.classList.remove('active'));});
 const allocations=Array.isArray(data.allocations)?data.allocations:[];
 const positive=allocations.filter(x=>Number(x.amount)>0);
 if(!positive.length){
  const empty=document.createElement('div');empty.className='budget-chart-empty';empty.textContent='Nenhuma distribuição definida para este mês. Adicione categorias abaixo para acompanhar aqui.';bars.append(empty);return;
 }
 const maximum=Math.max(...positive.map(x=>Number(x.amount)));
 positive.sort((a,b)=>Number(b.amount)-Number(a.amount)).forEach(item=>{
  const amount=Math.max(0,Number(item.amount)||0),spent=Math.max(0,Number(item.spent)||0);
  const button=document.createElement('button');button.type='button';button.className='budget-bar-row';
  const head=document.createElement('span');head.className='budget-bar-heading';
  const name=document.createElement('span');name.textContent=item.category;
  const value=document.createElement('strong');value.textContent=money(amount);
  head.append(name,value);
  const track=document.createElement('span');track.className='budget-bar-track';
  const fill=document.createElement('span');fill.className='budget-bar-fill';fill.style.width=(maximum?amount/maximum*100:0)+'%';track.append(fill);
  const meta=document.createElement('span');meta.className='budget-bar-meta';meta.textContent=money(spent)+' utilizado · '+money(Math.max(0,amount-spent))+' restante';
  button.append(head,track,meta);
  button.addEventListener('click',()=>{bars.querySelectorAll('button').forEach(b=>b.classList.remove('active'));button.classList.add('active');meta.textContent=spent>amount?'Acima do limite em '+money(spent-amount):money(spent)+' utilizado de '+money(amount);});
  bars.append(button);
 });
})();
