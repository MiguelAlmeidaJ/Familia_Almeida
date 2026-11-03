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


## Fase 2 — conciliação financeira (Sandbox, 2026-11-03)
A migration `20261103_038_billing_reason` registra o motivo do bloqueio financeiro em `tenant_billing_access.reason`.
Sem ela, a suspensão básica permanece funcional, mas as mensagens específicas de estorno ou cancelamento dependem de atualizar a estrutura.

- Assinaturas `INACTIVE` ou `EXPIRED`: não concedem acesso mesmo durante cortesia.
- Evento autenticado `SUBSCRIPTION_DELETED`: bloqueia imediatamente a família vinculada; pedidos ainda sem família ativada são marcados para análise. Nenhuma transação financeira é apagada.
- Sem webhook: duas verificações de ausência da assinatura (HTTP 404) com intervalo mínimo de 20 horas suspendem o acesso. Falhas de rede, 401, 429 e 5xx não disparam suspensão por ausência.
- Cobranças recorrentes `REFUNDED`, `PARTIALLY_REFUNDED`, `REFUND_IN_PROGRESS`, `CHARGEBACK_REQUESTED`, `CHARGEBACK_DISPUTE`, `AWAITING_CHARGEBACK_REVERSAL`: impedem acesso até confirmação de pagamento posterior. A janela da cortesia não prevalece sobre esses eventos.
- Após novo pagamento confirmado e sem atrasos ou pendências financeiras, a conciliação retorna o estado a `active`. Suspensão administrativa permanece independente.
- Estorno/chargeback de upgrade já ativado: marca o pedido `reconciliation_required` e suspende o acesso financeiro sem reduzir automaticamente o plano ou reescrever as transações. O cron revisita cobranças de upgrades recentes e pendentes; confirmação posterior válida remove o bloqueio.
- Em caso de erro na API do Asaas, o estado anterior é preservado. A rotina diária registra falhas e retorna código diferente de zero.
- **Configuração importante:** no Asaas, habilitar eventos de assinatura `SUBSCRIPTION_UPDATED`, `SUBSCRIPTION_INACTIVATED`, `SUBSCRIPTION_DELETED` além de eventos de pagamento. O token secreto existente deve permanecer válido.

### Matriz manual de homologação obrigatória
1. No Sandbox, criar família com cobrança recorrente e confirmar acesso.
2. Simular vencimento D+3 (tolerância), D+4 (bloqueio), pagamento confirmado (retorno), inclusive com repetição do webhook.
3. Inativar assinatura no Asaas e confirmar bloqueio, mesmo com cortesia.
4. Remover assinatura no Asaas e confirmar que o webhook bloqueia a família. Verificar também o fallback de 404 repetido.
5. Solicitar reembolso/chargeback de cobrança recorrente e confirmar bloqueio, sem perda de dados.
6. Confirmar cobrança posterior para verificar retorno do acesso; reembolso não deve permitir reativação prematura.
7. Fazer upgrade pago e estornar a cobrança avulsa; verificar status `reconciliation_required`, acesso bloqueado e nenhuma reversão silenciosa de limites. Verificar recuperação após conciliação.
8. Manter suspensão administrativa enquanto um pagamento é confirmado; a reativação automática não pode superar o bloqueio manual.
9. Simular indisponibilidade da API e validar que a rotina não altera contas com base em dados incompletos.
10. Confirmar os workflows PHP e isolamento do commit implantado, atualizar o banco por migration e testar o retorno em Meu plano, Admin → Famílias e Suporte.

Não liberar faturamento comercial antes desses testes em ambiente de homologação. A implementação não substitui auditoria contábil nem tratamento jurídico de reembolso.


## Fase 2 — cancelamento pelo próprio cliente (migration 039)
- Execute `20261103_039_subscription_cancellations` no painel Manutenção após atualizar a `main`.
- O botão aparece em Minha assinatura somente para o administrador familiar com assinatura Asaas comprovadamente `ACTIVE`. Planos internos não têm recorrência para cancelar.
- A opção `Cancelar renovação da assinatura` exibe um diálogo com as consequências, motivo opcional, confirmação explícita e CSRF.
- Ao confirmar, registra solicitação exclusiva por família, confere a assinatura/cliente e faturas diretamente no Asaas, calcula prazo de acesso com base no período pago ou cortesia, e executa `PUT /subscriptions/{id}` com `status: INACTIVE`. Isso interrompe a geração de novas faturas, mas preserva cobranças pendentes e vencidas.
- O fluxo NÃO usa `DELETE` porque o Asaas pode eliminar cobranças pendentes. Não ocorre reembolso automático nem apagamento de dados financeiros.
- O acesso ao Bemora se mantém até a data comprovada do período pago/cortesia, desde que não haja cobrança em atraso, contestação, reembolso ou suspensão manual. Se não houver pagamento/cortesia confirmado, não se presume prazo de acesso. Após a data, o bloqueio é aplicado inclusive se a API do Asaas ou o cron estiverem indisponíveis.
- O usuário não pode evoluir o plano enquanto existir cancelamento confirmado ou aguardando conciliação. Uma recontratação posterior deverá ter fluxo próprio; não se deve reativar a cobrança sem novo consentimento.
- A operação evita duplicidade usando um registro único por família. Falha após a chamada externa deixa `reconciliation_required`; a rotina de conciliação recupera o estado remoto, só repetindo a inativação após a janela de segurança de 10 minutos.
- Um e-mail de confirmação é enviado para o comprador e reentregue pela rotina financeira se o SMTP estiver indisponível.
- O painel Admin → Assinaturas mostra cancelamentos confirmados, pendências e a data final de acesso.
- No **Sandbox** a funcionalidade está disponível com o Asaas habilitado. Para permitir em produção, configurar explicitamente `'cancellation_production_enabled' => '1'` em `asaas` no `config.php` *somente depois da homologação*.

### Testes exigidos para lançar
1. Cancelar plano mensal com período efetivamente pago e confirmar a data de encerramento, sem nova fatura gerada após o cancelamento.
2. Cancelar plano anual e semestral com cobrança confirmada; verificar que o acesso continua disponível até o fim do período.
3. Cancelar plano gratuito por cupom durante a cortesia: verificar o limite do período gratuito e a interrupção da renovação.
4. Cancelar sem pagamento confirmado: acesso não pode ser concedido além do que estiver comprovado.
5. Simular fatura vencida, reembolso ou contestação: não ignorar o bloqueio financeiro mesmo antes do fim de acesso.
6. Verificar permissões: apenas `family_admin` consegue postar, POST requer CSRF, requisição duplicada é rejeitada e planos internos não permitem cancelamento.
7. Desligar SMTP e API durante operação, validar recuperação pelo cron e reenvio da notificação sem criar assinaturas duplicadas.
8. Confirmar que o webhook de `SUBSCRIPTION_INACTIVATED` não suspende antecipadamente período pago no Bemora.
9. Testar encerramento de prazo com e sem acesso à API para garantir suspensão pontual e preservação dos lançamentos.
10. Validar que a aplicação só será habilitada em produção depois de ligar a flag específica, finalizar testes reais e revisar os termos comerciais.
