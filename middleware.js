import { NextResponse } from 'next/server';

export function middleware(request) {
  const { pathname } = request.nextUrl;
  const isPublic = pathname.startsWith('/login') || pathname.startsWith('/api/auth/login') || pathname.startsWith('/_next') || pathname === '/favicon.ico';
  if (isPublic) return NextResponse.next();
  if (!request.cookies.get('familia_session')) {
    if (pathname.startsWith('/api/')) return NextResponse.json({ error: 'Não autenticado.' }, { status: 401 });
    return NextResponse.redirect(new URL('/login', request.url));
  }
  return NextResponse.next();
}

export const config = { matcher: ['/((?!_next/static|_next/image).*)'] };
