CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name varchar(80) NOT NULL,
  email varchar(160) UNIQUE NOT NULL,
  password_hash text NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS transactions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  created_by uuid REFERENCES users(id) ON DELETE SET NULL,
  type varchar(20) NOT NULL CHECK (type IN ('income', 'expense', 'investment', 'debt')),
  description varchar(160) NOT NULL,
  category varchar(100) NOT NULL,
  amount numeric(12,2) NOT NULL CHECK (amount > 0),
  occurred_on date NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS transactions_occurred_on_idx ON transactions(occurred_on DESC);

CREATE TABLE IF NOT EXISTS fixed_bills (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name varchar(120) NOT NULL,
  amount numeric(12,2) NOT NULL DEFAULT 0 CHECK (amount >= 0),
  due_day smallint NOT NULL CHECK (due_day BETWEEN 1 AND 31),
  active boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS bill_payments (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  bill_id uuid NOT NULL REFERENCES fixed_bills(id) ON DELETE CASCADE,
  month char(7) NOT NULL CHECK (month ~ '^\\d{4}-\\d{2}$'),
  paid boolean NOT NULL DEFAULT false,
  paid_at timestamptz,
  UNIQUE (bill_id, month)
);

CREATE TABLE IF NOT EXISTS spending_goals (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  category varchar(100) UNIQUE NOT NULL,
  monthly_limit numeric(12,2) NOT NULL DEFAULT 0 CHECK (monthly_limit >= 0),
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS debts (
  id uuid PRIMARY KEY DEFAULT gen_random_uuuid(),
  name varchar(160) NOT NULL,
  total_amount numeric(12,2) NOT NULL CHECK (total_amount > 0),
  paid_amount numeric(12,2) NOT NULL DEFAULT 0 CHECK (paid_amount >= 0),
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS settings (
  key varchar(100) PRIMARY KEY,
  value text NOT NULL,
  updated_at timestamptz NOT NULL DEFAULT now()
);

INSERT INTO settings(key, value)
VALUES ('investment_goal', '0')
ON CONFLICT (key) DO NOTHING;
