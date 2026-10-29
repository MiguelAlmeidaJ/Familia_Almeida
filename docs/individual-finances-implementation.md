# Separação financeira por membro — implementação em andamento

## Garantias já adicionadas
- Migration 015 cria `owner_user_id` nullable nas tabelas financeiras principais com índice (tenant_id,owner_user_id).
- Registros existentes **não** são atribuídos automaticamente a um membro: permanecem compartilhados.
- `require_auth()` nega acesso financeiro de tenants diferentes do legado e de modo `separate` até que todos os módulos estejam isolados.
- Finanças compartilhadas da família legada permanecem como antes.

## Não habilitar o modo individual
A implementação ainda não está completa. Rotas não filtradas identificadas: `action.php`, `budget_action.php`, `categories_action.php`, `credit_card_action.php`, `shopping_sync.php`, `receipt.php`, funções de `includes/finance.php`, `includes/monthly_budget.php`, `includes/shopping.php`, `includes/inventory.php`, `includes/credit_cards.php`, `includes/categories.php` e consultas do painel. Todos devem validar `tenant_id` e, no modo individual, `owner_user_id` por leitura, escrita, agregações, exportações e downloads.

## Modelo de acesso a concluir
- Compartilhado: dados pertencem à família e todos os membros autorizados podem visualizar.
- Individual: cada registro privado tem titular, e toda leitura/escrita exige esse titular. Valores compartilhados precisam de uma classificação explícita distinta, nunca presumida pelo criador.
- Responsável por pagar uma conta não é automaticamente proprietário do lançamento, nem recebe permissão de ler outras contas.
- Migrar saldos e históricos antigos exige decisão consciente do administrador familiar, sem inferência pela pessoa que fez o lançamento.
- Adotar validação transacional de vínculo ativo de usuário, tenant e titular em todos os identificadores de recursos.
- Testes de regressão com duas famílias e dois membros: cross-tenant, cross-member, links diretos, formulários, sincronização offline e cartões.

## Publicação
Antes de executar migration 015 em produção: backup, teste em cópia do MySQL e análise de tamanho de tabelas/índices. É uma preparação de esquema; **não libera finanças individuais**.

## Etapa seguinte — lançamentos (código publicado)
- Novo `includes/finance_scope.php` resolve família pelo vínculo autenticado e rejeita modo individual até a auditoria completa.
- `action.php`: novos lançamentos recebem `tenant_id` explicitamente. Anexar/remover comprovantes e excluir lançamentos exigem associação à família.
- `index.php`: lista recente de lançamentos filtrada pela família ativa.
- Pendência importante: consultas agregadas, contas, orçamento, categorias, cartões, compras, estoque, arquivos de recibos e demais gravações ainda exigem isolamento. Nenhuma alteração habilitou o modo individual.
- Os testes de sintaxe e integração no servidor ainda precisam ser executados antes de produção.
