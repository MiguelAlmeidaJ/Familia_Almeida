import 'dotenv/config';
import fs from 'node:fs/promises';
import path from 'node:path';
import pg from 'pg';

const { Client } = pg;
const connectionString = process.env.DATABASE_URL;
if (!connectionString) throw new Error('DATABASE_URL não configurado');

const client = new Client({ connectionString });
await client.connect();
try {
  const dir = path.join(process.cwd(), 'db', 'migrations');
  const files = (await fs.readdir(dir)).filter((f) => f.endsWith('.sql')).sort();
  for (const file of files) {
    const sql = await fs.readFile(path.join(dir, file), 'utf8');
    await client.query(sql);
    console.log(`✓ ${file}`);
  }
} finally {
  await client.end();
}
