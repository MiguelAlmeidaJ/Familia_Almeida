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
