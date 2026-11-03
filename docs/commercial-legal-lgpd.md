# Fase 4 — Comercial, proteção de dados e direitos dos titulares

**Estado:** implementação técnica inicial, com documentos PRELIMINARES. O preenchimento da identidade contratual e a avaliação jurídica não foram realizados pelo sistema. Não apresentar "LGPD homologada" antes dos itens de aceite.

## Entregas de código

- `/termos` e `/privacidade`: documentos públicos versionados, que exibem claramente o estado **minuta / revisão jurídica pendente** enquanto `legal.approved` não for liberado.
- `/meus-dados`: pedidos autenticados e limitados por usuário, com histórico visível apenas ao titular. Continua acessível mesmo sob suspensão financeira. Solicitações de exclusão **não eliminam dados automaticamente**.
- `/admin/privacidade`: fila de atendimento exclusiva do administrador global, com status, resposta registrada e usuário responsável.
- `/checkout`: quando documentos estão aprovados, inclui checkbox obrigatória de aceite e registra versões + data em `subscription_orders`; servidor verifica versões, não apenas o navegador.
- A produção recusa novos pedidos quando `legal.approved` não está devidamente configurado. Sandbox continua utilizável para homologação, com aviso explícito de documentos pendentes e sem dados reais.
- Migration `20261103_040_privacy_requests_and_contracts`: novos registros de solicitações e colunas de auditoria de aceite nas contratações.

## Publicação dos documentos

A configuração `legal` em `config.php` exige:
```php
'legal' => [
  'approved' => '0',
  'company_name' => 'RAZÃO SOCIAL/NOME DO FORNECEDOR',
  'company_tax_id' => 'CNPJ/CPF CONFORME CADASTRO',
  'privacy_email' => 'PRIVACIDADE@DOMINIO',
],
```
**Não altere `approved` para 1 sem:**

1. Revisar com profissional jurídico a identificação do fornecedor, condições dos planos, cobrança, renovação, reembolso, arrependimento, cancelamento, obrigações e responsabilidades.
2. Confirmar direitos do titular, classificação do tratamento de dados e relação de controlador/operador com o administrador familiar e fornecedores.
3. Publicar prazo/critério de conservação por categoria (financeiro, contratos/faturas, comprovantes, tickets, logs, backups) e definir processo auditável para retenção/exclusão.
4. Validar se a organização precisa indicar encarregado ou se está abrangida por exceção aplicável; em qualquer caso manter canal de privacidade acessível e monitorado.
5. Garantir que todos os dados e finalidades descritos correspondem ao funcionamento real, sem promessas de eliminação instantânea ou segurança absoluta.
6. Registrar aprovação documental, responsável, versão e data. Alterações substanciais requerem NOVA versão e comunicação adequada.
7. Verificar direitos do consumidor, incluindo informações do fornecedor, preços totais, periodicidade, cancelamento e direito de arrependimento conforme o caso.

Os textos versionados atuais são **base de trabalho**; não há documentação da revisão nem indicação da empresa contratante validada.

## Inventário inicial de tratamento a completar

| Categoria | Exemplos | Finalidade e controles a verificar |
| --- | --- | --- |
| Identidade e autenticação | Nome, email, hash da senha, telefone | Controle de acesso, atendimento, segurança; limitar visibilidade |
| Dados da família | Pessoas vinculadas, perfis, permissões, titularidade | Organização multi-tenant e regras de visibilidade |
| Dados financeiros | Entradas, saídas, fontes de renda, categorias, anexos | Executar ferramenta contratada; limitar por família e dono |
| Assinaturas | CPF/CNPJ do pagador, forma de cobrança, plano, faturas | Execução contratual, faturamento e obrigações contábeis |
| Atendimento | Descrições e contato do titular | Resolver dúvidas e solicitações; minimizar conteúdo |
| Logs e segurança | Registros técnicos, prevenção de abuso, estado de cron | Proteger serviço e detectar incidentes |
| Backups | SQL e comprovantes protegidos | Continuidade, testes de recuperação; retenção separada |

**Não definir tempos numéricos de retenção sem inventário jurídico/contábil**, nem confundir término de assinatura com extinção de obrigações legais de guarda.

## Política de atendimento aos titulares

1. Receber solicitações por `/meus-dados` ou canal de privacidade para pessoas sem acesso à conta.
2. Confirmar identidade sem requisitar senhas e sem revelar informações de terceiros.
3. Identificar a natureza do pedido e os dados específicos, incluindo compartilhamento com outros membros.
4. Identificar possibilidade de resposta imediata, necessidade de correção, exceções/guarda legal e providência compatível.
5. Registrar decisão e explicação na área administrativa. O titular autenticado pode acompanhar o status.
6. O pedido de exclusão não deve excluir documentos de outros usuários, obrigações fiscais ou dados financeiros da família sem base/avaliação apropriadas.
7. Revisar prazo de resposta conforme direitos e condições legais aplicáveis; registrar controles de SLA, incidente e escalonamento antes do lançamento.

## Fluxos a homologar (obrigatório)

- [ ] Migration aplicada, telas acessíveis somente aos papéis corretos e solicitações isoladas entre famílias
- [ ] Conta suspensa consegue apresentar pedido de direitos sem liberar operações financeiras
- [ ] Administrador responde e usuário vê sua própria resposta, sem vazamento de dados pessoais
- [ ] E-mails de notificação chegam ao canal de privacidade
- [ ] Checkout Sandbox registra versões/data somente com aceite expresso após publicação autorizada
- [ ] Checkout produção permanece bloqueado com documentos não aprovados
- [ ] Termos, política e identificação exibidos corretamente em todas as telas e no celular
- [ ] Responsável formal aprova documentos e define bases legais/retencões
- [ ] Teste documentado de solicitações de exclusão com dados compartilhados e exigências de guarda
- [ ] Verificação do Decreto 7.962/2013 e CDC no checkout, incluindo contato, informação e arrependimento
- [ ] Contratos e localização de prestadores avaliados (Asaas, SMTP, hospedagem, backup externo)

**Fontes para a revisão:** Lei 13.709/2018 (LGPD), orientações da ANPD sobre direitos do titular e agentes de pequeno porte, CDC e Decreto 7.962/2013 (comércio eletrônico). A documentação técnica não substitui parecer jurídico.
