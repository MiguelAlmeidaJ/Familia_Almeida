import { db } from '@/lib/db';
import { getSession } from '@/lib/session';

export const runtime = 'nodejs';
const monthOk = (m) => /^\d{4}-\d{2}$/.test(m || '');

async function session() {
  const user = await getSession();
  if (!user) throw Object.assign(new Error('Não autenticado.'), { status: 401 });
  return user;
}

export async function GET(request) {
  try {
    await session();
    const url = new URL(request.url);
    const month = monthOk(url.searchParams.get('month')) ? url.searchParams.get('month') : new Date().toISOString().slice(0, 7);
    const start = `${month}-01`;
    const [tx, bills, goals, debts, setting] = await Promise.all([
      db.query(`SELECT t.id,t.type,t.description,t.category,t.amount::float amount,to_char(t.occurred_on,'YYYY-MM-DD') date,u.name created_by_name FROM transactions t LEFT JOIN users u ON u.id=t.created_by WHERE t.occurred_on >= $1::date AND t.occurred_on < ($1::date + interval '1 month') ORDER BY t.occurred_on DESC,t.created_at DESC`, [start]),
      db.query(`SELECT b.id,b.name,b.amount::float amount,b.due_day,COALESCE(p.paid,false) paid FROM fixed_bills b LEFT JOIN bill_payments p ON p.bill_id=b.id AND p.month=$1 WHERE b.active=true ORDER BY b.due_day,b.name`, [month]),
      db.query(`SELECT g.id,g.category,g.monthly_limit::float monthly_limit,COALESCE(SUM(CASE WHEN t.type='expense' THEN t.amount ELSE 0 END),0)::float spent FROM spending_goals g LEFT JOIN transactions t ON lower(t.category)=lower(g.category) AND t.occurred_on >= $1::date AND t.occurred_on < ($1::date + interval '1 month') GROUP BY g.id ORDER BY g.category`, [start]),
      db.query(`SELECT id,name,total_amount::float total_amount,paid_amount::float paid_amount FROM debts ORDER BY created_at DESC`),
      db.query(`SELECT value FROM settings WHERE key='investment_goal'`),
    ]);
    const totals = tx.rows.reduce((a, t) => { a[t.type] = (a[t.type] || 0) + Number(t.amount); return a; }, { income: 0, expense: 0, investment: 0, debt: 0 });
    return Response.json({ month, transactions: tx.rows, bills: bills.rows, goals: goals.rows, debts: debts.rows, investmentGoal: Number(setting.rows[0]?.value || 0), totals });
  } catch (e) {
    return Response.json({ error: e.message }, { status: e.status || 500 });
  }
}

export async function POST(request) {
  try {
    const user = await session();
    const b = await request.json();
    if (b.action === 'transaction') {
      if (!['income','expense','investment','debt'].includes(b.type) || !b.description || !b.category || !(Number(b.amount) > 0) || !b.date) return Response.json({ error: 'Dados inválidos.' }, { status: 400 });
      const r = await db.query(`INSERT INTO transactions(created_by,type,description,category,amount,occurred_on) VALUES($1,$2,$3,$4,$5,$6) RETURNING id`, [user.id,b.type,b.description.trim(),b.category.trim(),Number(b.amount),b.date]);
      if (b.type === 'debt' && b.debtId) await db.query(`UPDATE debts SET paid_amount=LEAST(total_amount,paid_amount+$1) WHERE id=$2`, [Number(b.amount), b.debtId]);
      return Response.json({ ok: true, id: r.rows[0].id });
    }
    if (b.action === 'bill') { const r = await db.query(`INSERT INTO fixed_bills(name,amount,due_day) VALUES($1,$2,$3) RETURNING id`, [b.name?.trim(),Number(b.amount||0),Number(b.dueDay||10)]); return Response.json({ ok:true,id:r.rows[0].id }); }
    if (b.action === 'goal') { const r = await db.query(`INSERT INTO spending_goals(category,monthly_limit) VALUES($1,$2) ON CONFLICT(category) DO UPDATE SET monthly_limit=EXCLUDED.monthly_limit RETURNING id`, [b.category?.trim(),Number(b.limit||0)]); return Response.json({ ok:true,id:r.rows[0].id }); }
    if (b.action === 'debt') { const r = await db.query(`INSERT INTO debts(name,total_amount,paid_amount) VALUES($1,$2,$3) RETURNING id`, [b.name?.trim(),Number(b.total),Number(b.paid||0)]); return Response.json({ ok:true,id:r.rows[0].id }); }
    return Response.json({ error: 'Ação inválida.' }, { status: 400 });
  } catch (e) { return Response.json({ error:e.message }, { status:e.status || 500 }); }
}

export async function PATCH(request) {
  try {
    await session(); const b = await request.json();
    if (b.action === 'bill-paid') { await db.query(`INSERT INTO bill_payments(bill_id,month,paid,paid_at) VALUES($1,$2,$3,CASE WHEN $3 THEN now() ELSE NULL END) ON CONFLICT(bill_id,month) DO UPDATE SET paid=EXCLUDED.paid,paid_at=EXCLUDED.paid_at`, [b.id,b.month,!!b.paid]); return Response.json({ok:true}); }
    if (b.action === 'bill') { await db.query(`UPDATE fixed_bills SET name=$1,amount=$2,due_day=$3 WHERE id=$4`, [b.name?.trim(),Number(b.amount||0),Number(b.dueDay),b.id]); return Response.json({ok:true}); }
    if (b.action === 'goal') { await db.query(`UPDATE spending_goals SET category=$1,monthly_limit=$2 WHERE id=$3`, [b.category?.trim(),Number(b.limit||0),b.id]); return Response.json({ok:true}); }
    if (b.action === 'investment-goal') { await db.query(`INSERT INTO settings(key,value) VALUES('investment_goal',$1) ON CONFLICT(key) DO UPDATE SET value=EXCLUDED.value,updated_at=now()`, [String(Number(b.value||0))]); return Response.json({ok:true}); }
    return Response.json({error:'Ação inválida.'},{status:400});
  } catch(e) { return Response.json({error:e.message},{status:e.status||500}); }
}

export async function DELETE(request) {
  try {
    await session(); const b = await request.json();
    if (b.action === 'transaction') await db.query(`DELETE FROM transactions WHERE id=$1`, [b.id]);
    else if (b.action === 'debt') await db.query(`DELETE FROM debts WHERE id=$1`, [b.id]);
    else return Response.json({error:'Ação inválida.'},{status:400});
    return Response.json({ok:true});
  } catch(e) { return Response.json({error:e.message},{status:e.status||500}); }
}
