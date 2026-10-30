(()=>{'use strict';
const raw=document.getElementById('reports-data');if(!raw)return;
let rows;try{rows=JSON.parse(raw.textContent);}catch{return;}if(!Array.isArray(rows)||!rows.length)return;
const money=v=>new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(v)||0);
const compact=v=>new Intl.NumberFormat('pt-BR',{notation:'compact',maximumFractionDigits:1}).format(v);
const monthly=m=>{const [y,mm]=m.split('-');return mm+'/'+y.slice(2);};
const series={income:{title:'Receitas',color:'#0e967b',get:r=>Number(r.income)||0},costs:{title:'Contas + gastos',color:'#f0ac61',get:r=>(Number(r.bills)||0)+(Number(r.expenses)||0)},balance:{title:'Saldo',color:'#174d43',get:r=>Number(r.balance)||0}};
const enabled=new Set(Object.keys(series));
const svg=document.getElementById('reports-trend-chart');
const selectedNode=document.getElementById('reports-selected-month');
const tableRows=[...document.querySelectorAll('[data-report-month]')];
const donut=document.getElementById('reports-donut');
const donutLegend=document.getElementById('reports-donut-legend');
let selected=rows.length-1;
const ns='http://www.w3.org/2000/svg';
function make(tag,attrs={},text){const node=document.createElementNS(ns,tag);Object.entries(attrs).forEach(([k,v])=>node.setAttribute(k,String(v)));if(text!==undefined)node.textContent=text;svg.appendChild(node);return node;}
function select(index){
 selected=Math.max(0,Math.min(rows.length-1,index));const r=rows[selected];
 if(selectedNode)selectedNode.textContent=monthly(r.month)+' · Receitas '+money(r.income)+' · Contas '+money(r.bills)+' · Gastos '+money(r.expenses)+' · Saldo '+money(r.balance);
 tableRows.forEach(row=>row.classList.toggle('reports-active-row',row.dataset.reportMonth===r.month));
 draw();renderDonut();
}
function draw(){
 if(!svg)return;svg.replaceChildren();
 const W=760,H=310,L=65,R=22,T=23,B=45;
 const values=rows.flatMap(r=>[0,...[...enabled].map(key=>series[key].get(r))]);
 const min=Math.min(0,...values),max=Math.max(1,...values),range=max-min;
 const X=i=>L+i*(W-L-R)/Math.max(1,rows.length-1),Y=v=>T+(max-v)/range*(H-T-B);
 for(let n=0;n<=4;n++){const val=min+n*range/4,y=Y(val);make('line',{x1:L,y1:y,x2:W-R,y2:y,class:'reports-gridline'});make('text',{x:L-8,y:y+4,'text-anchor':'end',class:'reports-axis-label'},compact(val));}
 rows.forEach((r,i)=>{make('text',{x:X(i),y:H-12,'text-anchor':'middle',class:'reports-axis-label'},monthly(r.month));});
 if(enabled.size){
  for(const key of enabled){const def=series[key];make('polyline',{points:rows.map((r,i)=>X(i)+','+Y(def.get(r))).join(' '),class:'reports-line',stroke:def.color});rows.forEach((r,i)=>{make('circle',{cx:X(i),cy:Y(def.get(r)),r:selected===i?6:4,fill:def.color,class:'reports-chart-dot'});});}
 }
 const x=X(selected);make('line',{x1:x,y1:T,x2:x,y2:H-B,stroke:'#91b7a7','stroke-dasharray':'4 5','stroke-width':1.5,'pointer-events':'none'});
 rows.forEach((r,i)=>{
  const hit=make('rect',{x:Math.max(L,X(i)-(W-L-R)/Math.max(1,rows.length-1)/2),y:T,width:Math.min(65,(W-L-R)/Math.max(1,rows.length-1)),height:H-B-T,fill:'transparent',tabindex:0,role:'button','aria-label':'Ver dados de '+monthly(r.month),class:'reports-hit-month'});
  hit.addEventListener('click',()=>select(i));hit.addEventListener('focus',()=>select(i));hit.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();select(i);}});
  const tt=document.createElementNS(ns,'title');tt.textContent=monthly(r.month)+' · '+money(r.income)+' em receitas · '+money(r.balance)+' de saldo';hit.appendChild(tt);
 });
}
document.querySelectorAll('.reports-series-toggle').forEach(btn=>btn.addEventListener('click',()=>{const key=btn.dataset.series;if(!series[key])return;enabled.has(key)?enabled.delete(key):enabled.add(key);btn.setAttribute('aria-pressed',String(enabled.has(key)));draw();}));
const parts=[['bills','Contas previstas','#174d43'],['expenses','Gastos avulsos','#f0ac61'],['balance','Saldo projetado','#119a7d']];
let highlighted='balance';
function renderDonut(){
 if(!donut)return;const r=rows[selected];
 const values=parts.map(([key])=>Math.max(0,Number(r[key])||0));
 const total=values.reduce((a,b)=>a+b,0);
 let cursor=0;const stops=[];
 parts.forEach(([key,,color],i)=>{const extent=total>0?values[i]/total*100:0;stops.push(color+' '+cursor+'% '+(cursor+extent)+'%');cursor+=extent;});
 donut.style.background=total>0?'conic-gradient('+stops.join(',')+')':'#e8f0e9';
 donut.querySelector('small').textContent=parts.find(x=>x[0]===highlighted)?.[1]||'Saldo projetado';
 donut.querySelector('strong').textContent=money(r[highlighted]);
 donutLegend?.querySelectorAll('button').forEach(btn=>{const key=btn.dataset.part;const i=parts.findIndex(p=>p[0]===key);btn.classList.toggle('is-active',key===highlighted);const strong=btn.querySelector('strong');if(strong)strong.textContent=money(r[key]);btn.title=(total?Number(100*values[i]/total).toFixed(1):'0')+'% da composição';});
}
donutLegend?.querySelectorAll('button').forEach(btn=>btn.addEventListener('click',()=>{highlighted=btn.dataset.part;renderDonut();}));
svg?.addEventListener('keydown',e=>{if(e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();select(selected+(e.key==='ArrowRight'?1:-1));}});
select(selected);
})();