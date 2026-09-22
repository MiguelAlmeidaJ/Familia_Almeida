import { getSession } from '@/lib/session';

export async function GET() {
  const session = await getSession();
  if (!session) return Response.json({ error: 'Não autenticado.' }, { status: 401 });
  return Response.json({ user: session });
}
