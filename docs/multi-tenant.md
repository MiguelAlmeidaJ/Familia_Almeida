# Multi-tenant — fase 1 (não habilitar novas famílias)

Esta branch prepara os dados existentes da Família Almeida para uma arquitetura multi-tenant.
**Não está pronta para produção com múltiplas famílias.** As consultas atuais ainda não
aplicam `tenant_id` e, portanto, a criação de um segundo tenant seria insegura.

## Migração da família atual

1. Pare as escritas, faça backup completo do MySQL e teste uma restauração.
2. Confira a consistência das tabelas atuais e rode todas as migrations pendentes.
3. Execute a migration `20261029_011_tenant_foundation.php` via manutenção.
4. Verifique que todos os registros ganharam `tenant_id` da Família Almeida.
5. Confirme que os usuários existentes foram vinculados em `tenant_users`.
6. Antes de repetir a migration ou de fazer rollback, restaure um backup em ambiente de ensaio.
   MySQL executa DDL com commits implícitos; a operação não é atomicamente reversível.

## Administrador do sistema

Depois da migration, crie ou promova um usuário existente usando um shell seguro:

```bash
export SYSTEM_ADMIN_EMAIL="admin@exemplo.com"
read -r -s -p "Senha do admin: " SYSTEM_ADMIN_PASSWORD; echo
export SYSTEM_ADMIN_PASSWORD
export SYSTEM_ADMIN_NAME="Administrador do sistema"
php scripts/create_system_admin.php
unset SYSTEM_ADMIN_PASSWORD
```

Esse usuário recebe `system_role = system_admin`. Nesta fase, o login usa a interface
legada e o usuário é associado à família atual apenas para compatibilidade. A tela de
administração global **ainda precisa ser implementada**. Não use este script como
provisionamento multi-tenant definitivo.

## Critérios antes de habilitar mais famílias

- Resolver tenant ativo por sessão autenticada e validar membership no servidor.
- Restringir todas as leituras, inserts, updates, deletes, anexos e sincronizações por tenant.
- Validar ownership de chaves estrangeiras entre tabelas e revisar índices únicos
  (categoria por tenant/mês, compras, contas, cartões etc.).
- Separar administração global da administração de cada família.
- Implementar cadastro de tenants e convite/atribuição de membros com auditoria.
- Testar dois tenants com IDs de registros cruzados e APIs offline/PWA, garantindo
  que nenhum dado vaze para outra família.
- Preparar plano de rollback e janela de manutenção com backup restaurável.
