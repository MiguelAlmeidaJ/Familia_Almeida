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
