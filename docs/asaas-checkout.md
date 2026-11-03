# Bemora — Checkout de assinaturas via Asaas

## Escopo implementado
- Cada plano público leva a `/checkout?plano=ID` (não há carrinho).
- O usuário informa nome, e-mail, CPF/CNPJ e celular.
- O backend busca o preço vigente no MySQL e valida a cotação da página.
- O backend cadastra o cliente no Asaas e cria uma **assinatura recorrente** usando `POST /v3/subscriptions`.
- Modalidades: mensal = `MONTHLY`; semestral = `SEMIANNUALLY`; anual = `YEARLY`.
- O usuário recebe um link de cobrança do Asaas, se a primeira cobrança já estiver disponível, e acompanha o pedido em `/checkout/aguardando`.
- O webhook `/api/asaas/webhook` valida `asaas-access-token`, registra eventos únicos e confirma o pagamento também na API do Asaas antes de gerar o convite de uso único.
- O convite é enviado ao e-mail do comprador; se o e-mail não chegar, o comprador poderá acessar o link pelo mesmo navegador enquanto a sessão e o convite estiverem válidos.
- A família e os membros são cadastrados **somente depois do pagamento confirmado** e da conclusão do registro pelo comprador.
- Administração global: `/admin/assinaturas` lista pedidos e suas situações.

## Pré-requisitos
1. Fazer backup e atualizar o código na ValueHost via `git pull --ff-only origin main`.
2. Executar a migration `20261030_024_asaas_orders` em **Configurações → Manutenção**, após migrations anteriores.
3. No `config.php` **não versionado**, configurar:
```php
'asaas' => [
    'enabled' => '1',
    'environment' => 'sandbox',
    'production_enabled' => '0',
    'api_key' => 'COLE_CHAVE_PRIVADA_SANDBOX',
    'webhook_token' => 'TOKEN_ALEATORIO_WEBHOOK_DE_32_OU_MAIS_CARACTERES',
    'invite_secret' => 'OUTRO_SEGREDO_ALEATORIO_DE_32_OU_MAIS_CARACTERES',
    'system_admin_user_id' => 1, // Confirme o ID real do admin global
],
```
4. Configure `app.base_url` como `https://bemora.anoar.com.br`.
5. O fluxo de cadastro de famílias depende de `BEMORA_MULTI_TENANT_RELEASE=1` no PHP da ValueHost. **Não ative** essa chave antes de validar o isolamento cruzado de dados.
6. Habilitar cURL, TLS e conexões de saída HTTPS para `api-sandbox.asaas.com`.

## Webhook no Asaas Sandbox
- Endpoint exato: `https://bemora.anoar.com.br/api/asaas/webhook` (sem redirecionamento).
- Configure um token independente, igual a `asaas.webhook_token`; não use a API Key.
- Header recebido: `asaas-access-token`.
- Eventos de cobrança recomendados: `PAYMENT_CREATED`, `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED`, `PAYMENT_OVERDUE`, `PAYMENT_REFUNDED`, `PAYMENT_CHARGEBACK_REQUESTED`.
- Testar pagamentos no Sandbox e observar logs do Asaas. O webhook responde erro 500 para falhas de processamento, permitindo novas tentativas, e ignora eventos já registrados.
- A chave do Sandbox não deve ser reutilizada em Produção.

## Precificação promocional
O período promocional cadastrado no admin é o **intervalo de elegibilidade para contratar**. Na contratação atual, o valor vigente passa a ser o valor recorrente da assinatura Asaas. O preço NÃO retorna automaticamente ao valor normal na renovação. Isso é informado explicitamente no checkout; para outras condições comerciais será necessário implementar reajustes ou descontos por ciclos.

## Antes de produção
- Testar plano mensal, semestral e anual no Sandbox.
- Testar promoção vigente, expirada e preço alterado durante o preenchimento.
- Conferir cobranças duplicadas, eventos webhook repetidos, e-mail de confirmação, expiração do convite e cadastros de famílias distintos.
- Validar o pagamento de uma assinatura, depois concluir o cadastro e confirmar seu vínculo no painel administrativo.
- Implementar e testar **renovação de acesso, inadimplência, cancelamento, reembolsos, cobrança contestada, recuperação do convite e conciliação periódica** antes de vender assinaturas reais. A integração atual não suspende automaticamente o acesso de uma família quando uma renovação fica em atraso.
- Só após esses testes, alterar `environment` para `production`, usar a chave real e habilitar `production_enabled=1`. Esta segunda chave evita cobranças reais acidentais.

## Segurança
Não salvar números de cartão, dados sensíveis de cartão, chaves do Asaas ou tokens de webhook em Git.
Os dados da cobrança (nome, e-mail, CPF/CNPJ e celular) são utilizados para o pedido e enviados ao Asaas. Revise a política de privacidade e LGPD antes da comercialização.


## Cupons — Sandbox (migration 20261102_027)
Após git pull, execute a migration `20261102_027_coupon_benefit_fulfillment` na manutenção antes de testar cupons.
- `first_payment_discount`: assinatura Asaas criada com valor recorrente integral; a primeira cobrança gerada é ajustada via `PUT /payments/{id}`. O link só aparece depois que o valor da primeira cobrança foi confirmado pela API. Não pagar diretamente uma cobrança do Asaas antes dessa conciliação.
- `free_months`: assinatura começa a cobrar na data futura; convite de cadastro é disponibilizado na página do pedido, na mesma sessão. O período gratuito não exige pagamento e precisa ser acompanhado para cancelar cobranças futuras quando aplicável.
- `annual_discount`: desconto aplicado de forma recorrente ao valor anual (não apenas no primeiro ano).
- O sistema só aceita confirmação do primeiro pagamento promocional pelo identificador da cobrança efetivamente ajustada.
- Valide manualmente no Sandbox: BPLUS, ACREDITAR, cupom anual, valores das renovações, convite, falhas, webhooks e recuperação do link. Ainda não há teste ponta a ponta com credenciais do Asaas na automação do GitHub.


## Evolução de plano dentro do painel — Sandbox
Em `/evoluir-plano`, o administrador familiar pode alterar a assinatura Asaas existente. Não é criado novo cliente nem uma segunda assinatura.
- Exige migration `20261102_028_plan_change_requests`.
- Somente Sandbox e assinatura ACTIVE no Asaas; identidade do cliente e periodicidade são conferidas antes do PUT.
- A API recebe `PUT /subscriptions/{id}` com novo `value`, `cycle` e `updatePendingPayments:false`. Não há cobrança proporcional imediata. Cobranças existentes permanecem inalteradas; somente faturas futuras não geradas usam o novo plano.
- Atualiza preço, ciclo e quantidade de membros no banco somente quando o Asaas confirma resposta correspondente. Em erro de rede ou resposta ambígua, solicita conciliação manual e bloqueia novas trocas.
- Impede mudança para plano que não comporte os membros atuais, mantém o processo exclusivo ao administrador e bloqueia mudança enquanto houver cortesia ativa.
- Teste no Sandbox upgrade, downgrade, troca de ciclo e faturas já geradas; verifique também o estado 'reconciliation_required' antes de considerar a operação pronta para produção.
- Não usar o fluxo automático em produção sem uma rotina administrativa de conciliação auditada e aprovação dos efeitos sobre renovações.

## Troca agora ou na próxima renovação (Sandbox)
Requer migration `20261102_029_plan_change_schedule` além da 028. A tela `/evoluir-plano` oferece seleção explícita entre troca imediata e agendamento na data `nextDueDate` que o Asaas informar. A troca imediata altera assinatura existente sem cobrança proporcional. O agendamento registra uma solicitação `scheduled` e somente é efetivado quando o servidor executa `php scripts/process_scheduled_plan_changes.php` no dia indicado ou depois. Configure um cron diário (por exemplo: `15 1 * * * /usr/bin/php /CAMINHO/DO/PROJETO/scripts/process_scheduled_plan_changes.php`), ajustando a rota e o timezone do PHP/servidor. Não exponha o script em HTTP.

Cobranças já emitidas NÃO são modificadas em nenhum dos modos. Como o Asaas pode gerar a fatura antes da data de vencimento, a atualização agendada pode não alterar o preço da fatura da data escolhida, apenas das seguintes. Não prometa valor do próximo boleto sem verificar sua emissão. Em falha ambígua, o estado `reconciliation_required` bloqueia novas trocas; confira no Asaas e reconcilie manualmente. Solicitações antigas nesse estado também precisam de reconciliação. Não use em produção antes de validar agendamento e faturas.


## Pagamento dentro do Bemora — primeira etapa
A rota `/pagamento` exibe, sem sair do domínio Bemora, os códigos de Pix e boleto recuperados com `GET /payments/{id}/pixQrCode` e `GET /payments/{id}/identificationField`.
- Checkout público usa `checkout_order_id` e token da sessão, reconciliados com o hash do pedido.
- Upgrade exige família autenticada e só revela cobrança vinculada ao seu `tenant_id`.
- Todos os valores, clientes e IDs de cobrança são conferidos no Asaas por consulta autenticada; o navegador não fornece esses IDs livremente.
- Cartão de crédito ainda utiliza a fatura hospedada no Asaas enquanto o processamento transparente, 3DS e conformidade PCI não forem homologados.
- Cartão de débito exige a fatura hospedada no Asaas: a API não aceita envio direto dos dados de débito.
- A página não concede acesso por conta própria: a ativação continua dependente de webhook confirmado.
- A rota funciona para cobranças já geradas. No checkout público, a primeira fatura da assinatura precisa existir antes de acessar Pix e boleto.
- Não confundir a conclusão visual da tela com pagamento confirmado. Validar no Sandbox os fluxos Pix, boleto, crédito hospedado, débito hospedado, expiração dos códigos e login de famílias diferentes.


## Suspensão financeira automática (migration 034)
O acesso financeiro por inadimplência é controlado separadamente em `tenant_billing_access`. Não altere `tenants.active` nem `tenants.status` para suspender cobrança: isso impediria o cliente de chegar à página de regularização.

- Regra inicial: 3 dias completos de tolerância após o vencimento de uma cobrança RECORRENTE da assinatura. O quarto dia de atraso resulta em `suspended`.
- Usa o endpoint Asaas `GET /subscriptions/{id}/payments`, percorrendo as páginas e conferindo o cliente/assinatura. Só faturas com status `PENDING` ou `OVERDUE` e vencidas são consideradas; cobranças avulsas de upgrades não entram.
- Estado `grace`: fatura vencida, mas acesso normal dentro da tolerância.
- Estado `suspended`: a autenticação permite somente Minha assinatura, perfil e saída, preservando os dados da família. O usuário pode visualizar as cobranças recorrentes no Asaas a partir da tela de faturas.
- A reativação ocorre quando o Asaas confirma o pagamento e não há outras faturas vencidas além do prazo. O webhook solicita conciliação; o cron diário corrige webhooks atrasados. A tela Minha assinatura tenta atualizar cobranças suspensas ao ser visitada, respeitando janela de dois minutos.
- Planos internos sem assinatura Asaas e pedidos sem vínculo de família não recebem suspensão automática. Durante cortesia ainda vigente, a suspensão é ignorada.
- Se a consulta externa falhar, nenhuma nova suspensão ou reativação é aplicada com dados incompletos. Investigue os logs; a próxima execução tenta novamente.
- **Obrigatório:** executar a migration `20261103_034_billing_access` no admin antes de habilitar a rotina diária.
- Cron recomendado para o DirectAdmin PHP 8.3, ajustado ao caminho usado nesta hospedagem:
```
15 2 * * * /usr/local/php83/bin/php /home/anoa0880/domains/bemora.anoar.com.br/public_html/scripts/reconcile_billing_access.php
```
- O cron anterior `scripts/process_scheduled_plan_changes.php` permanece desabilitado: não o confunda com a rotina de cobrança. Esse cron antigo pode ser removido do agendador.
- Valide no Sandbox com fatura vencida, fatura dentro da tolerância, pagamento confirmado e plano interno; antes de operar em produção, ajuste a política de tolerância, comunicações e bloqueios à política comercial aprovada.


## Notificações de cobrança e suspensão administrativa (migration 035)
Execute `20261103_035_billing_notifications_manual_suspension` após a migration 034.
A rotina diária existente `scripts/reconcile_billing_access.php` agora também envia emails SMTP ao administrador familiar (papel `family_admin`) associado à assinatura:
- Na data de vencimento: aviso de fatura disponível para pagamento.
- No segundo dia após o vencimento: aviso do período de tolerância de 3 dias completos.
- No quarto dia após o vencimento: aviso de acesso financeiro suspenso (após a verificação diária).
- Não envia avisos de atraso para cobrança já confirmada pelo Asaas, nem durante uma cortesia vigente.
- Emails são enviados em HTML pelo template Bemora já utilizado pelo SMTP. A tabela `billing_notification_log` registra o par único (família, cobrança, tipo de aviso) e o status. Se o SMTP falhar, a notificação pode ser repetida na próxima execução.
- O cron existente de 02h15 permanece o mesmo, não adicione um segundo cron para notificações. Requer SMTP já configurado e `composer install`.
- A notificação contém link de fatura validado com o domínio do Asaas (ou `/minha-assinatura` como alternativa). Nunca inclui dados bancários ou identificadores confidenciais.

Em Administração → Famílias → Ações, o botão de escudo permite `Suspender acesso para teste` ou `Remover suspensão manual`. A mesma opção aparece em Detalhes da família. Este estado é independente do `state` calculado pelo Asaas e do `tenants.active`; portanto, a conciliação não remove a suspensão manual, e a reativação manual não contorna bloqueio por inadimplência. O cliente ainda pode acessar `/minha-assinatura`, perfil e logout. Não altera o Asaas nem apaga registros.

**Homologar no Sandbox**: caso de conta gratuita sem Asaas, família paga em dia, fatura vencida no segundo dia e quarto dia, duplicação do cron, SMTP indisponível e confirmação tardia de pagamento. Em produção, garantir fuso horário e política de notificações compatível com os termos de assinatura.


## Suporte durante suspensão (migration 036)
Execute `20261103_036_support_tickets` via Administração → Configurações → Manutenção.
A rota autenticada `/suporte/relatar` recebe relatos da família por POST com CSRF, descrição de 15 a 3000 caracteres e contato por WhatsApp (DDD + 10 ou 11 dígitos) ou e-mail. Limite de três relatos por usuário em uma hora. Não recebe uploads nem dados de cartão.
Os relatos são gravados em `support_tickets` independentemente da entrega SMTP. A página `/admin/suporte` permite visualizar a família, o autor, o contato, a descrição e atualizar a situação; apenas o administrador global tem acesso.
Configure no `config.php` (não versionado):
```php
'support' => ['email' => 'suporte@seudominio.com.br'],
```
Sem `support.email`, o sistema utiliza `mail.from`, se for um endereço válido. As credenciais existentes em `mail` continuam responsáveis pelo SMTP. Se a entrega falhar, o chamado permanece no admin com `email_status=failed`.

Durante a suspensão, `require_auth()` permite apenas Minha assinatura, o POST de suporte e logout, além da consulta de pagamento autorizada. `render_topbar()` não inclui os popups de lançamento/contas; `render_sidebar()` oculta os links financeiros e perfil. O cliente vê faturas e pode enviar relato. O bloqueio é verificado no servidor inclusive quando alguém envia um POST direto. A tela aberta em outra aba também consulta o estado após retornar ao foco para invalidar UI antiga.

Teste: suspender família no admin, tentar `POST /acao` com sessão e CSRF válidos (não pode gravar), visitar perfil (redireciona), abrir Minha assinatura, enviar relato por WhatsApp e e-mail, conferir registro e e-mail administrativo, reativar família e confirmar que os acessos retornaram.
