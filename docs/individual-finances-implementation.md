# Separação financeira por membro — implementação em andamento

## Garantias já adicionadas
- Migration 015 cria `owner_user_id` nullable nas tabelas financeiras principais com índice (tenant_id,owner_user_id).
- Registros existentes **não** são atribuídos automaticamente a um membro: permanecem compartilhados.
- `require_auth()` nega acesso financeiro de tenants diferentes do legado e de modo `separate` até que todos os módulos estejam isolados.
- Finanças compartilhadas da família legada permanecem como antes.

## Não habilitar o modo individual
A implementação ainda não está completa. Rotas não filtradas identificadas: `action.php`, `budget_action.php`, `categories_action.php`, `receipt.php`, funções de `includes/finance.php`, `includes/monthly_budget.php`, `includes/categories.php` e consultas do painel. Todos devem validar `tenant_id` e, no modo individual, `owner_user_id` por leitura, escrita, agregações, exportações e downloads.

## Modelo de acesso a concluir
- Compartilhado: dados pertencem à família e todos os membros autorizados podem visualizar.
- Individual: cada registro privado tem titular, e toda leitura/escrita exige esse titular. Valores compartilhados precisam de uma classificação explícita distinta, nunca presumida pelo criador.
- Responsável por pagar uma conta não é automaticamente proprietário do lançamento, nem recebe permissão de ler outras contas.
- Migrar saldos e históricos antigos exige decisão consciente do administrador familiar, sem inferência pela pessoa que fez o lançamento.
- Adotar validação transacional de vínculo ativo de usuário, tenant e titular em todos os identificadores de recursos.
- Testes de regressão com duas famílias e dois membros: cross-tenant, cross-member, links diretos, formulários, telas e ações financeiras ativas.

## Publicação
Antes de executar migration 015 em produção: backup, teste em cópia do MySQL e análise de tamanho de tabelas/índices. É uma preparação de esquema; **não libera finanças individuais**.

## Etapa seguinte — lançamentos (código publicado)
- Novo `includes/finance_scope.php` resolve família pelo vínculo autenticado e rejeita modo individual até a auditoria completa.
- `action.php`: novos lançamentos recebem `tenant_id` explicitamente. Anexar/remover comprovantes e excluir lançamentos exigem associação à família.
- `index.php`: lista recente de lançamentos filtrada pela família ativa.
- Pendência importante: consultas agregadas, contas previstas, orçamento, categorias, arquivos de recibos e demais gravações ainda exigem isolamento. Nenhuma alteração habilitou o modo individual.
- Os testes de sintaxe e integração no servidor ainda precisam ser executados antes de produção.

## Auditoria adicional de consultas e gravações — outubro de 2026
Foram introduzidos filtros por tenant em:
- `receipt.php` (download de comprovantes);
- `includes/receipts.php` (criação, listagem mensal e procura de arquivos);
- `includes/categories.php` e `categories_action.php` (consultas, inserção, reativação e exclusão);
- `budget_action.php` (receitas recorrentes, alocações, contas previstas);
- algumas rotas de `action.php` e lista recente do painel (etapa anterior).

**Não concluído / não liberado no escopo atual:** `includes/finance.php`, `includes/monthly_budget.php`, demais caminhos de `action.php`, configurações e tabelas com índices únicos sem `tenant_id`. Esses módulos ainda requerem auditoria detalhada de SQL, gravações indiretas e testes de integração.

IMPORTANTE: `finance_mode=separate` e famílias adicionais permanecem bloqueados na autenticação; nenhum destes commits é autorização para remover essa trava. Os commits de isolamento parcial devem ser testados em homologação antes de publicar em produção.

## Escopo funcional vigente (confirmado)
A aplicação Bemora foi simplificada. O produto ativo inclui somente **Painel financeiro, Ganhos e Gastos, Contas Previstas, Categorias, Configurações pessoais/familiares e administração global de famílias**. **Cartões, estoque e compras não fazem parte do produto atual** e NÃO devem ser reintroduzidos ou incluídos no plano de desenvolvimento.

Arquivos legados que eventualmente permaneçam no repositório devem ser tratados como passivo técnico: verificar se rotas ainda estão publicamente acessíveis e desativá-las com segurança em etapa específica, sem restabelecer menus ou funcionalidades. O isolamento restante deve se concentrar exclusivamente nos fluxos ativos e seus anexos/comprovantes.

## Continuação — consultas de leitura (outubro de 2026)
- `includes/finance.php`: consultas de lançamentos, contas previstas, pagamentos de contas, metas, dívidas, investimento e agregações do painel agora usam `tenant_id` da sessão.
- `includes/monthly_budget.php`: receitas recorrentes, alocações, despesas, pagamentos e saldos passaram a calcular apenas registros da família autenticada.
- Não foram reativados cartões, estoque ou compras. O escopo vigente permanece Painel, Ganhos e Gastos, Contas Previstas, Categorias e Configurações.
- **Ainda não liberar finanças individuais**: falta impor `owner_user_id` em cada leitura e gravação, classificar registros antigos, auditar outras gravações em `action.php` e testes automatizados contra acesso cruzado. Atualização em produção depende de validação PHP/SQL com base de homologação.
