# Família Almeida — Finanças

Sistema de gestão financeira familiar feito para hospedagem PHP compartilhada.

## Stack

- PHP 8.1+
- MySQL 5.7+ ou MariaDB 10.4+
- PDO MySQL
- HTML/CSS/JavaScript sem framework
- Sessão PHP com cookie HTTP-only
- Senhas com `password_hash()` / `password_verify()`

Não usa Node.js, Next.js, Docker ou Vercel.

## Estrutura

- `index.php` — dashboard financeiro
- `login.php` / `logout.php` — autenticação
- `action.php` — gravações e atualizações no MySQL
- `install.php` — instalação inicial via navegador
- `schema.sql` — estrutura do banco
- `includes/` — conexão, autenticação e consultas
- `assets/style.css` — identidade visual
- `config.example.php` — modelo de configuração
- `config.php` — configuração real da hospedagem, ignorada pelo Git

## Instalação em hospedagem compartilhada

### 1. Criar o banco no painel da hospedagem

No cPanel/Plesk ou painel equivalente:

1. Crie um banco MySQL.
2. Crie um usuário MySQL.
3. Vincule o usuário ao banco com todos os privilégios.
4. Anote host, nome do banco, usuário e senha.

Em muitas hospedagens o host é `localhost`, mas confirme no painel.

### 2. Enviar os arquivos

Envie o conteúdo deste repositório para a pasta desejada, por exemplo:

`public_html/financas/`

### 3. Criar config.php

Copie `config.example.php` para `config.php` e preencha:

```php
<?php

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'NOME_DO_BANCO',
        'user' => 'USUARIO_DO_BANCO',
        'pass' => 'SENHA_DO_BANCO',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Família Almeida Finanças',
        'install_key' => 'UMA-CHAVE-GRANDE-E-ALEATORIA',
    ],
];
```

Nunca envie `config.php` para o GitHub.

### 4. Rodar o instalador pelo navegador

Acesse:

`https://seu-dominio.com/instalar?key=SUA_INSTALL_KEY`

Informe os dados de acesso de Miguel e Gabi. O instalador:

- cria todas as tabelas;
- cria/atualiza os dois usuários;
- aplica `password_hash()` às senhas;
- cadastra Dízimo, Luz, Água, Gás, Internet e Aluguel;
- cadastra Mercado, Farmácia, Pet e Lazer;
- marca a instalação como concluída.

Depois de instalar, remova ou renomeie `install.php` no servidor. Mesmo que ele permaneça, o sistema detecta que a instalação já foi concluída e não executa novamente.

### 5. Entrar

Acesse:

`https://seu-dominio.com/login`

Os dois usuários usam a mesma base financeira. Cada lançamento registra quem o criou.

## Segurança

- credenciais reais não ficam no repositório;
- `config.php` está no `.gitignore`;
- queries usam PDO Prepared Statements;
- alterações usam token CSRF;
- login regenera o ID da sessão;
- senhas não são armazenadas em texto puro;
- `.htaccess` bloqueia acesso web aos arquivos de configuração e ao schema.

## Recursos

- entradas, gastos e investimentos;
- contas fixas mensais e status de pagamento;
- metas de gasto por categoria;
- meta mensal de investimento;
- dívidas e pagamentos;
- histórico mensal;
- identificação do usuário que criou cada lançamento;
- notas e comprovantes vinculados aos gastos;
- resumo de gastos por dia e quantidade de notas anexadas;\n- listas mensais de mercado com reaproveitamento do mês anterior;\n- lista permanente de móveis por prioridade;\n- modo compra com calculadora e armazenamento offline;\n- sincronização da compra com os gastos quando a conexão retorna.


## Rotas do sistema

- `/` — visão geral
- `/movimentacoes` — lançamentos e histórico\n- `/compras` — listas de mercado e móveis\n- `/compras/mercado` — modo compra com suporte offline
- `/contas` — contas fixas
- `/metas` — metas de gastos e investimento
- `/dividas` — dívidas e pagamentos
- `/configuracoes` — configurações
- `/configuracoes/manutencao` — diagnóstico, migrations e otimização

## Migrations pelo navegador

A página `/configuracoes/manutencao` cria e utiliza a tabela `schema_migrations`.
Novas migrations devem ser adicionadas em `database/migrations/` como arquivos PHP que retornem `description` e uma função `up(PDO $pdo)`.
A página mostra quais migrations estão pendentes e permite executá-las em ordem, sem repetir as que já foram registradas.


## Notas e comprovantes

Os anexos ficam em `storage/receipts/`, bloqueados para acesso web direto. Eles são abertos somente pela rota autenticada `/comprovante?id=...`.

Regras:
- até 5 arquivos por gasto;
- até 8 MB por arquivo;
- JPG, PNG, WEBP, HEIC/HEIF ou PDF;
- nomes físicos aleatórios, sem expor o nome original no caminho.

Depois de atualizar uma instalação existente, execute a migration de comprovantes em `/configuracoes/manutencao`.


## Lista de compras offline

O modo `/compras/mercado` registra um Service Worker e salva o estado da compra no IndexedDB do navegador.

Fluxo:
1. abra o modo compra com internet ao menos uma vez;
2. no mercado, os produtos podem ser marcados mesmo sem conexão;
3. preço comprado e mercado são gravados localmente a cada alteração;
4. ao finalizar offline, a compra entra em uma fila local;
5. quando a conexão retorna, o sistema tenta sincronizar automaticamente;
6. a sincronização cria um lançamento único na categoria `Mercado` e marca os itens como comprados.

Quando o navegador não suporta Background Sync, a sincronização acontece ao manter a página aberta durante a reconexão ou na próxima vez que o modo compra for aberto.
