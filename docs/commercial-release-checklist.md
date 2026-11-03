# Checklist de liberação comercial multi-tenant — Bemora

**Status:** pendente de validação na hospedagem. Este PR **não** habilita `BEMORA_MULTI_TENANT_RELEASE`.

## Evidências já presentes no código
- `tenant_context()` obtém famílias da tabela `tenant_users` a partir da sessão autenticada, não de IDs enviados pelo cliente.
- `require_auth()` bloqueia famílias adicionais sem a flag de release e usa lista positiva de módulos auditados.
- `finance_scope()` e testes `finance_isolation_integration.php` cobrem separação por família e por proprietário em módulos financeiros auditados.
- Checkout exige flag de multi-tenant e confirmação de pagamento antes da ativação.

## Checklist bloqueante, antes de mudar a flag
- [ ] Backup e restauração testada de banco e arquivos em ambiente isolado.
- [ ] Todas as migrations aplicadas e integridade de chaves `tenant_id`, `owner_user_id` e relacionamentos verificada.
- [ ] CI de lint PHP, validações do checkout e integração financeira passando.
- [ ] Testes em dois usuários e duas famílias: leitura, escrita, exclusão, downloads e IDs forjados de outra família; garantir 403/404 e ausência de dados.
- [ ] Testar rotas diretas, endpoints antigos, CSRF, troca de família, convites, sessão e escopo de administração global.
- [ ] Testar uploads de avatar e comprovantes, caminhos diretos e autorização do download.
- [ ] Testar pedidos duplicados, webhook Asaas idempotente, cupom concorrente, pagamento recusado, reembolso/cancelamento e acesso apenas após confirmação.
- [ ] Validar credenciais e endpoint de webhook, HTTPS, logs sem dados sensíveis e fluxos de produção em ambiente controlado.
- [ ] Testar cadastro e contratação em navegador real na hospedagem; rollback da flag pronto.

## Liberação e reversão
Somente após registrar evidências de todos os itens acima, definir `BEMORA_MULTI_TENANT_RELEASE=1` no ambiente PHP da hospedagem. Em incidente, voltar para `0` e investigar antes de retomar. **Não** liberar o cadastro alterando apenas o aviso da interface administrativa.

## Checkout
Campos CPF/CNPJ e celular possuem máscaras no navegador, mas os dados são normalizados e validados de novo em PHP, com dígitos verificadores de CPF/CNPJ e padrão de celular brasileiro. Testes unitários independentes ficam em `tests/checkout_validation_cli.php`.


## Fase 1 — hardening (2026-11-03)
- Atualização dos testes: painel autenticado em `/pages/painel.php` em lugar da landing page `index.php`; dados de teste com datas consistentes e mês selecionado explicitamente; sondagens de rota de acesso suspenso.
- Login: migration `20261103_037_login_rate_limits`; tentativas por conta (5/15 minutos) e origem (20/15 minutos), com bloqueio temporário de 15 minutos. Não confiar em `X-Forwarded-For` diretamente. Nenhum identificador de origem ou e-mail é armazenado em texto na tabela.
- Sessões: `session.use_strict_mode=1`, sessão com cookie, expiração por inatividade de 2 horas e idade máxima de 24 horas; troca do ID após login.
- HTTP: pastas internas, testes, scripts, vendor, migrations e storage negadas no `.htaccess`, mantendo anexos acessíveis apenas pelos controllers autenticados.

### Ordem segura de implantação
1. Backup do banco e dos arquivos privados.
2. `git pull --ff-only origin main` mantendo uma sessão de administrador ativa ou executando as migrations pelo PHP CLI.
3. Antes de fazer logout, execute `20261103_037_login_rate_limits` pelo painel de Manutenção. Alternativamente:
```bash
/usr/local/php83/bin/php scripts/run_migrations.php
/usr/local/php83/bin/php scripts/run_migrations.php --apply
```
4. Confirme os dois workflows do último commit na main. Abra duas famílias e dois membros, teste isolamento e suspensão manual.
5. Se a migration 037 não estiver aplicada, o login exibirá indisponibilidade (fail-closed). Não ofereça acesso público antes de aplicar.
6. Confirme que `/storage/receipts/`, `/storage/avatars/`, `/includes/`, `/vendor/` e `/database/` não permitem download HTTP direto.
7. Faça um teste de bloqueio em conta exclusiva de teste; o administrador deverá aguardar 15 minutos após o limite.
