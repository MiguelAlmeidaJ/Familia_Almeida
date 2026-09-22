'use client';

import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

const money = (v) => new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(v||0));
const monthName = (m) => {
  const parts=m.split('-').map(Number);
  return new Intl.DateTimeFormat('pt-BR',{month:'long',year:'numeric'}).format(new Date(parts[0],parts[1]-1,1));
};

export default function Dashboard(){
  const router=useRouter();
  const [month,setMonth]=useState(new Date().toISOString().slice(0,7));
  const [data,setData]=useState(null);
  const [user,setUser]=useState(null);
  const [error,setError]=useState('');
  const [loading,setLoading]=useState(true);

  const load=useCallback(async()=>{
    setLoading(true);
    const responses=await Promise.all([
      fetch('/api/finance?month='+month,{cache:'no-store'}),
      fetch('/api/auth/me',{cache:'no-store'})
    ]);
    if(responses[0].status===401||responses[1].status===401){router.replace('/login');return;}
    const finance=await responses[0].json();
    const me=await responses[1].json();
    if(!responses[0].ok)setError(finance.error||'Erro ao carregar dados.');
    else setData(finance);
    if(responses[1].ok)setUser(me.user);
    setLoading(false);
  },[month,router]);

  useEffect(()=>{load();},[load]);

  async function api(method,payload){
    setError('');
    const res=await fetch('/api/finance',{method,headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
    const body=await res.json().catch(()=>({}));
    if(res.status===401){router.replace('/login');return false;}
    if(!res.ok){setError(body.error||'Não foi possível salvar.');return false;}
    await load(); return true;
  }

  function changeMonth(delta){
    const p=month.split('-').map(Number);
    const d=new Date(p[0],p[1]-1+delta,1);
    setMonth(d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0'));
  }

  async function addTransaction(preset){
    const type=preset||prompt('Tipo: income, expense, investment ou debt','expense');
    if(!type)return;
    const description=prompt('Descrição'); if(!description)return;
    const category=prompt('Categoria',type==='investment'?'Investimento':'Mercado'); if(!category)return;
    const amount=Number(prompt('Valor')); if(!(amount>0))return;
    const date=prompt('Data (AAAA-MM-DD)',month+'-01'); if(!date)return;
    await api('POST',{action:'transaction',type,description,category,amount,date});
  }

  async function addBill(){
    const name=prompt('Nome da conta'); if(!name)return;
    const amount=Number(prompt('Valor mensal','0')||0);
    const dueDay=Number(prompt('Dia do vencimento','10')||10);
    await api('POST',{action:'bill',name,amount,dueDay});
  }

  async function addGoal(){
    const category=prompt('Categoria da meta'); if(!category)return;
    const limit=Number(prompt('Limite mensal','0')||0);
    await api('POST',{action:'goal',category,limit});
  }

  async function addDebt(){
    const name=prompt('Nome da dívida'); if(!name)return;
    const total=Number(prompt('Valor total')); if(!(total>0))return;
    const paid=Number(prompt('Valor já pago','0')||0);
    await api('POST',{action:'debt',name,total,paid});
  }

  async function setInvestmentGoal(){
    const value=Number(prompt('Meta mensal de investimento',String(data?.investmentGoal||0)));
    if(Number.isNaN(value))return;
    await api('PATCH',{action:'investment-goal',value});
  }

  async function logout(){
    await fetch('/api/auth/logout',{method:'POST'});
    router.replace('/login');
  }

  if(loading&&!data)return <div className="loading-screen"><div className="loader-mark">FA</div><p>Organizando as finanças…</p></div>;
  if(!data)return null;

  const debtPending=data.debts.reduce((s,d)=>s+Math.max(0,d.total_amount-d.paid_amount),0);
  const balance=(data.totals.income||0)-(data.totals.expense||0)-(data.totals.investment||0)-(data.totals.debt||0);
  const paidBills=data.bills.filter(b=>b.paid).length;

  return <div className="shell">
    <aside className="sidebar">
      <div className="brand"><div className="brandmark">FA</div><div className="brandcopy"><b>FAMÍLIA</b><strong>ALMEIDA</strong><small>FINANÇAS</small></div></div>
      <div className="side-note"><small>PROPÓSITO</small><p>Dar nome a cada real para construir liberdade com intenção.</p></div>
      <button className="logout" onClick={logout}>Sair da conta</button>
    </aside>
    <main>
      <header className="topbar"><div className="who"><small>LOGADO COMO</small><strong>{user?.name||'Família Almeida'}</strong></div><div className="privacy"><i/>Banco PostgreSQL conectado</div></header>
      <div className="content">
        <div className="hero">
          <div><p className="eyebrow">PLANEJAMENTO FINANCEIRO</p><h1>Para onde nosso dinheiro está indo?</h1><p className="sub">Acompanhe o mês e ajuste as prioridades da família.</p></div>
          <div className="month"><button onClick={()=>changeMonth(-1)}>‹</button><div className="label"><small>MÊS DE REFERÊNCIA</small><strong>{monthName(month)}</strong></div><button onClick={()=>changeMonth(1)}>›</button></div>
        </div>
        {error&&<div className="alert">{error}<button onClick={()=>setError('')}>×</button></div>}
        <div className="grid4">
          <Stat label="Entradas" value={data.totals.income} icon="↗"/>
          <Stat label="Saídas" value={(data.totals.expense||0)+(data.totals.debt||0)} icon="↘"/>
          <Stat label="Saldo projetado" value={balance} icon="◌"/>
          <Stat label="Dívidas pendentes" value={debtPending} icon="▤"/>
        </div>

        <div className="actions">
          <button onClick={()=>addTransaction('income')}>+ Entrada</button>
          <button onClick={()=>addTransaction('expense')}>+ Gasto</button>
          <button onClick={()=>addTransaction('investment')}>+ Investimento</button>
          <button onClick={addBill}>+ Conta fixa</button>
          <button onClick={addGoal}>+ Meta</button>
          <button onClick={addDebt}>+ Dívida</button>
        </div>

        <div className="split equal">
          <section className="card">
            <div className="card-head"><div><p className="eyebrow">CONTAS DO MÊS</p><h2>{paidBills} de {data.bills.length} contas pagas</h2></div></div>
            <div className="bill-list">{data.bills.map(b=><div className="bill" key={b.id}>
              <button className={'check '+(b.paid?'done':'')} onClick={()=>api('PATCH',{action:'bill-paid',id:b.id,month,paid:!b.paid})}>{b.paid?'✓':''}</button>
              <div className="due"><small>DIA</small><strong>{String(b.due_day).padStart(2,'0')}</strong></div>
              <div className="billinfo"><strong>{b.name}</strong><span>Vence dia {b.due_day}</span></div>
              <div className="billamt"><strong>{money(b.amount)}</strong></div>
            </div>)}</div>
          </section>

          <section className="card investment">
            <div><p className="eyebrow">CONSTRUIR O FUTURO</p><h2>Meta de investimento</h2><p>O investimento entra no orçamento antes de virar sobra.</p></div>
            <div className="goal-number"><strong>{money(data.totals.investment)}</strong><span>de {money(data.investmentGoal)}</span></div>
            <Progress current={data.totals.investment} total={data.investmentGoal}/>
            <button className="lightbtn" onClick={setInvestmentGoal}>Editar meta mensal</button>
          </section>
        </div>

        <div className="split equal">
          <section className="card">
            <div className="card-head"><div><p className="eyebrow">METAS DE GASTOS</p><h2>Limites por categoria</h2></div></div>
            <div className="goal-list">{data.goals.map(g=><div className="goal" key={g.id}><div><strong>{g.category}</strong><span>{money(g.spent)} de {money(g.monthly_limit)}</span><Progress current={g.spent} total={g.monthly_limit}/></div></div>)}</div>
          </section>
          <section className="card">
            <div className="card-head"><div><p className="eyebrow">DÍVIDAS</p><h2>Pendências</h2></div></div>
            <div className="goal-list">{data.debts.length?data.debts.map(d=><div className="goal" key={d.id}><div><strong>{d.name}</strong><span>{money(Math.max(0,d.total_amount-d.paid_amount))} pendente</span><Progress current={d.paid_amount} total={d.total_amount}/></div><button className="iconbtn" onClick={()=>api('DELETE',{action:'debt',id:d.id})}>🗑</button></div>):<Empty text="Nenhuma dívida cadastrada."/ >}</div>
          </section>
        </div>

        <section className="card recent">
          <div className="card-head"><div><p className="eyebrow">MOVIMENTAÇÕES</p><h2>Histórico do mês</h2></div></div>
          <div className="tx-list">{data.transactions.length?data.transactions.map(t=><div className="tx" key={t.id}>
            <div className={'txicon '+t.type}>{t.type==='income'?'↗':'↘'}</div>
            <div className="txinfo"><strong>{t.description}</strong><span>{t.category} • {t.date} • {t.created_by_name||'Família'}</span></div>
            <div className={'txval '+(t.type==='income'?'pos':'neg')}>{t.type==='income'?'+':'−'} {money(t.amount)}</div>
            <button className="iconbtn" onClick={()=>api('DELETE',{action:'transaction',id:t.id})}>🗑</button>
          </div>):<Empty text="Nenhum lançamento neste mês."/>}</div>
        </section>
      </div>
    </main>
  </div>;
}

function Stat({label,value,icon}){return <div className="stat"><div className="stat-top"><span>{label}</span><span className="stat-icon">{icon}</span></div><strong>{money(value)}</strong></div>;}
function Progress({current,total}){const p=total>0?Math.min(100,current/total*100):0;return <div className="progress"><span style={{width:p+'%'}}/></div>;}
function Empty({text}){return <div className="empty"><div className="bubble">◇</div><strong>{text}</strong></div>;}
