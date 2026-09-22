'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function submit(e) {
    e.preventDefault();
    setLoading(true);
    setError('');
    const res = await fetch('/api/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password }) });
    const data = await res.json().catch(() => ({}));
    setLoading(false);
    if (!res.ok) return setError(data.error || 'Não foi possível entrar.');
    router.replace('/');
    router.refresh();
  }

  return (
    <main className="login-screen">
      <section className="login-card">
        <div className="brand brand-dark"><div className="brandmark">FA</div><div className="brandcopy"><b>FAMÍLIA</b><strong>ALMEIDA</strong><small>FINANÇAS</small></div></div>
        <div className="login-copy"><p className="eyebrow">ACESSO DA FAMÍLIA</p><h1>Entrar no financeiro</h1><p>Use seu e-mail e senha para acessar os dados compartilhados da família.</p></div>
        <form onSubmit={submit} className="login-form">
          <label>E-mail<input type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" required /></label>
          <label>Senha<input type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete="current-password" required /></label>
          {error && <div className="form-error">{error}</div>}
          <button className="primary login-button" disabled={loading}>{loading ? 'Entrando…' : 'Entrar'}</button>
        </form>
      </section>
    </main>
  );
}
