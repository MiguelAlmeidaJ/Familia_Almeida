import 'dotenv/config';
import pg from 'pg';
import { hashPassword } from '../lib/password.mjs';

const { Client } = pg;
const required = [
  'DATABASE_URL',
  'SEED_MIGUEL_NAME', 'SEED_MIGUEL_EMAIL', 'SEED_MIGUEL_PASSWORD',
  'SEED_GABI_NAME', 'SEED_GABI_EMAIL', 'SEED_GABI_PASSWORD',
];
for (const key of required) {
  if (!process.env[key] || process.env[key] === 'troque-aqui') {
    throw new Error(`${key} não configurado no .env`);
  }
}

const client = new Client({ connectionString: process.env.DATABASE_URL });
await client.connect();

async function upsertUser(name, email, password) {
  const passwordHash = hashPassword(password);
  await client.query(
    `INSERT INTO users(name, email, password_hash)
     VALUES ($1, lower($2), $3)
     ON CONFLICT (email)
     DO UPDATE SET name = EXCLUDED.name, password_hash = EXCLUDED.password_hash`,
    [name, email, passwordHash]
  );
}

try {
  await client.query('BEGIN');
  await upsertUser(process.env.SEED_MIGUEL_NAME, process.env.SEED_MIGUEL_EMAIL, process.env.SEED_MIGUEL_PASSWORD);
  await upsertUser(process.env.SEED_GABI_NAME, process.env.SEED_GABI_EMAIL, process.env.SEED_GABI_PASSWORD);

  const defaults = [
    ['Dízimo', 0, 10], ['Luz', 0, 15], ['Água', 0, 15], ['Gás', 0, 20],
    ['Internet', 0, 10], ['Aluguel', 0, 10]
  ];
  for (const [name, amount, dueDay] of defaults) {
    const exists = await client.query('SELECT 1 FROM fixed_bills WHERE lower(name)=lower($1) LIMIT 1', [name]);
    if (!exists.rowCount) {
      await client.query('INSERT INTO fixed_bills(name, amount, due_day) VALUES ($1,$2,$3)', [name, amount, dueDay]);
    }
  }

  for (const category of ['Mercado', 'Farmácia', 'Pet', 'Lazer']) {
    await client.query(
      `INSERT INTO spending_goals(category, monthly_limit) VALUES ($1, 0)
       ON CONFLICT (category) DO NOTHING`,
      [category]
    );
  }

  await client.query('COMMIT');
  console.log('✓ Usuários, contas e categorias iniciais criados.');
} catch (error) {
  await client.query('ROLLBACK');
  throw error;
} finally {
  await client.end();
}
