import { db } from '@/lib/db';
import { verifyPassword } from '@/lib/password.mjs';
import { createSessionToken, setSessionCookie } from '@/lib/session';

export const runtime = 'nodejs';

export async function POST(request) {
  try {
    const { email, password } = await request.json();
    if (!email || !password) {
      return Response.json({ error: 'Informe e-mail e senha.' }, { status: 400 });
    }

    const result = await db.query(
      'SELECT id, name, email, password_hash FROM users WHERE lower(email)=lower($1) LIMIT 1',
      [email]
    );
    const user = result.rows[0];
    if (!user || !verifyPassword(password, user.password_hash)) {
      return Response.json({ error: 'E-mail ou senha inválidos.' }, { status: 401 });
    }

    const token = createSessionToken(user);
    await setSessionCookie(token);
    return Response.json({ user: { id: user.id, name: user.name, email: user.email } });
  } catch (error) {
    console.error(error);
    return Response.json({ error: 'Não foi possível entrar.' }, { status: 500 });
  }
}
