# Família Almeida — Finanças

Sistema privado de gestão financeira familiar com autenticação, PostgreSQL e Docker.

## Stack

- Next.js (interface + API)
- PostgreSQL 16
- Docker Compose para o banco local
- Sessão por cookie HTTP-only assinado
- Senhas armazenadas com scrypt + salt

## Rodando localmente

1. Copie `.env.example` para `.env`.
2. Preencha `SESSION_SECRET` e as variáveis `SEED_*` com os dois usuários da família.
3. Inicie o PostgreSQL:

```bash
docker compose up -d
```

4. Instale as dependências e prepare o banco:

```bash
npm install
npm run db:setup
npm run dev
```

Abra `http://localhost:3000`.

O comando `npm run db:setup` executa a migração e cria/atualiza os dois usuários definidos no `.env`, além das contas fixas iniciais (Dízimo, Luz, Água, Gás, Internet e Aluguel) e das metas Mercado, Farmácia, Pet e Lazer.

## Banco

O Docker expõe PostgreSQL em `localhost:5432` e persiste os dados no volume `familia_almeida_pgdata`.

Para apagar completamente os dados locais:

```bash
docker compose down -v
```

## Segurança

Credenciais reais não são commitadas. O `.env` está no `.gitignore`. O seed recebe nome, e-mail e senha por variável de ambiente e grava apenas o hash da senha no PostgreSQL.

## Produção / Vercel

A Vercel hospeda a aplicação Next.js, mas não executa o PostgreSQL do `docker-compose.yml`. Para produção, configure `DATABASE_URL` apontando para um PostgreSQL acessível pela aplicação (por exemplo Neon, Supabase, Railway ou servidor próprio) e configure `SESSION_SECRET` nas Environment Variables do projeto.
