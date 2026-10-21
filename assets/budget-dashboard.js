(() => {
 const json = document.getElementById('budget-visual-data');
 const donut = document.getElementById('budget-donut');
 const legend = document.getElementById('budget-chart-legend');
 if (!json || !donut || !legend) return;
 let d;
 try { d=JSON.parse(json.textContent); } catch (_) { return; }
 const brl=n=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(n);
 const income=Math.max(0,Number(d.income)||0);
 const parts=[
  {label:'Contas previstas',amount:Math.max(0,Number(d.bills)||0),color:'#173e36'},
  {label:'Gastos avulsos',amount:Math.max(0,Number(d.spent)||0),color:'#efa85a'},
  {label:'Saldo projetado',amount:Math.max(0,Number(d.remaining)||0),color:'#159b7d'}
 ];
 const sum=parts.reduce((a,b)=>a+b.amount,0);
 const scale=Math.max(income,sum);
 const segments=[];
 let cursor=0;
 for(const item of parts) {
  const width=scale ? item.amount/scale*360:0;
  if(width>0) segments.push(item.color+' '+cursor+'deg '+(cursor+width)+'deg');
  cursor+=width;
 }
 if(scale>sum) segments.push('#e4ecee '+cursor+'deg 360deg');
 donut.style.setProperty('--donut-fill',segments.length?'conic-gradient('+segments.join(',')+')':'#e8eef0');
 const labelNode=document.getElementById('donut-focus-label'),valueNode=document.getElementById('donut-focus-value'),shareNode=document.getElementById('donut-focus-share');
 function focus(item,button) {
  labelNode.textContent=item.label;
  valueNode.textContent=brl(item.amount);
  shareNode.textContent=income>0?(100*item.amount/income).toLocaleString('pt-BR',{maximumFractionDigits:1})+'% da receita':'sem receita prevista';
  legend.querySelectorAll('button').forEach(x=>x.classList.toggle('active',x===button));
 }
 parts.forEach(item=>{
  const btn=document.createElement('button');btn.type='button';btn.className='dashboard-legend-button';
  const dot=document.createElement('span');dot.className='dashboard-legend-dot';dot.style.background=item.color;
  const name=document.createElement('span');name.className='dashboard-legend-name';name.textContent=item.label;
  const small=document.createElement('small');small.textContent=income>0?(item.amount/income*100).toLocaleString('pt-BR',{maximumFractionDigits:1})+'% do previsto':'Sem percentual';
  name.append(small);
  const val=document.createElement('strong');val.textContent=brl(item.amount);
  btn.append(dot,name,val);btn.addEventListener('click',()=>focus(item,btn));legend.append(btn);
 });
 focus(parts[2],legend.children[2]);
})();
