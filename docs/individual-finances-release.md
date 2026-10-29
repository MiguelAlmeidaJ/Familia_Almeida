# Bemora — ativação do modo de finanças individuais

## Escopo
Somente o Bemora simplificado: Painel, Ganhos e Gastos, Contas Previstas, Categorias e Configurações da Família. Cartões, compras, estoque, metas e dívidas não são funcionalidades publicadas. A ativação, nesta etapa, está liberada apenas para a família legada `familia-almeida`; novas famílias continuam bloqueadas até validação do onboarding multi-tenant.

## Testes do repositório
- Workflow `.github/workflows/php-syntax.yml`: todas as fontes PHP são verificadas com `php -l`.
- Workflow `.github/workflows/finance-integration.yml`: inicia MySQL 8, aplica `schema.sql` e migrations, e verifica os filtros de tenant e proprietário para receitas, lançamentos, contas previstas, alocações, comprovantes e páginas financeiras ativas.
- Os testes sintéticos não substituem testes com dados reais e usuários reais na hospedagem.

## Publicação na ValueHost
1. Faça backup do MySQL e dos anexos de `storage/receipts`, preservando credenciais fora do repositório.
2. Em uma cópia de homologação, execute os testes manuais de acesso com dois membros.
3. No terminal da ValueHost: `cd ~/public_html && git status && git pull --ff-only origin main`.
4. Em **Configurações → Manutenção**, execute as migrations pendentes (incluindo 014, 015 e **016**). Não use o script de integração como comando em produção: ele cria usuários e registros de teste.
5. Em **Minha conta → Configurações da família**, confira os titulares atribuídos. Os dados legados que permanecerem sem titular serão **compartilhados**, mesmo no modo individual.
6. O administrador da família pode ativar **Finanças individuais** após a verificação de migrações. É obrigatória a confirmação de que dados antigos sem titular continuarão visíveis.
7. Teste com dois logins reais: lançamentos, totais, contas, receitas recorrentes, comprovantes, URL direta de documentos, criação, edição e exclusão de recursos pessoais. Confira que dados privados de A não aparecem para B.
8. Se houver comportamento inesperado, volte a **Finanças compartilhadas**, consciente de que isso tornará os registros privados visíveis à família. Para rollback completo, restaure de backup em ambiente controlado.

## Observações de privacidade
- Um registro com `owner_user_id=NULL` é compartilhado. Em `monthly_allocations`, `owner_user_id=0` identifica alocação compartilhada.
- Uma conta prevista privada gera despesas com o mesmo proprietário.
- Responsabilidade pelo pagamento não transfere a propriedade do recurso.
- O administrador familiar não vê nomes de contas e lançamentos privados pertencentes a outros membros na página de configurações durante o modo individual.
- O administrador do sistema possui privilégios de manutenção separados dos membros da família.

## Limites restantes
O modo individual não representa, por si só, autorização para comercializar o Bemora como multi-tenant. O onboarding de famílias adicionais ainda depende de revisão de permissões e dos módulos administrativos.
