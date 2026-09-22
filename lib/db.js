import { Pool } from 'pg';

const globalForDb = globalThis;

export const db =
  globalForDb.__familiaDb ||
  new Pool({
    connectionString: process.env.DATABASE_URL,
    ssl: process.env.NODE_ENV === 'production' && process.env.DATABASE_SSL !== 'false'
      ? { rejectUnauthorized: false }
      : false,
  });

if (process.env.NODE_ENV !== 'production') globalForDb.__familiaDb = db;
