# Bemora — planos modulares (fase 1)

## Objetivo
Cada oferta continua tendo preço, periodicidade e limite de integrantes próprios, mas também passa a determinar quais recursos estão liberados. A configuração fica em **Administração → Planos**, no campo **Módulos incluídos**.

## Implantação
1. Faça backup do banco antes de atualizar.
2. Execute `git pull origin main` no servidor.
3. Execute a migration `20261104_043_subscription_plan_modules` em **Administração → Configurações → Manutenção**.
4. Confira que os planos já cadastrados aparecem com todos os três módulos habilitados (comportamento anterior preservado).
5. Crie um plano de teste **interno** para testar a restrição de módulos antes de editar ofertas públicas com assinantes ativos.
6. Acesse com uma família vinculada ao plano de teste: Relatórios e Compras devem aparecer apenas quando seus módulos estiverem ativos. Teste também o acesso direto a `/relatorios`, `/compras` e POST `/compras/acao`.
7. Para Compras sem Scanner: a lista e o registro manual de preço permanecem disponíveis, enquanto o botão da câmera desaparece e tentativas de gravação com origem `label` são rejeitadas pelo servidor.
8. Confira os cards da página pública em todas as periodicidades e o direcionamento para o checkout dos planos públicos.

## Módulos liberados por plano
- **Base financeira**: painel, receitas, despesas, categorias e contas previstas. Sempre disponível.
- **Relatórios completos** (`reports`): acesso à página de relatórios.
- **Listas de compras** (`shopping`): planejamento e modo mercado.
- **Leitor de etiquetas** (`scanner`): depende de Compras; pode ser desabilitado sem remover o registro manual.

A migração marca os módulos dos planos antigos como ativos. Famílias legadas sem plano atribuído continuam com o funcionamento atual. Planos novos recebem suas permissões conforme a seleção do administrador.

## Comercial e faturamento
- Não há alteração nos valores, na criação de assinaturas, na autorização de pagamentos ou no webhook Asaas.
- O limite de integrantes já é configurável em cada oferta, mas **cobrança automática de adicionais por integrante e módulos avulsos ainda não está implementada**. Para variar o preço, cadastre ofertas próprias com seus preços finais e limites apropriados.
- O catálogo **público** continua aceitando planos pagos. Planos internos com preço zero podem ser atribuídos pelo administrador, mas não representam cadastro gratuito público automático.
- Não foram criados módulos para metas avançadas ou Open Finance. Open Finance está expressamente fora do escopo.
- Alterar as permissões de uma oferta existente pode afetar as famílias vinculadas a ela imediatamente. Antes de reduzir acesso de assinantes atuais, considere manter a oferta antiga e cadastrar outra.

## Segurança
As permissões são verificadas no servidor para as rotas protegidas e gravações por scanner. A navegação também esconde itens não contratados, mas nunca é a única barreira. Essas verificações não substituem autorização multi-tenant, isolamento de dados, CSRF, proteção de assinaturas ou validação do backend.

## Próxima fase
Projetar preços de módulos avulsos e adicionais por integrante, incluindo cotação, alterações de assinatura e reconciliação dos valores no Asaas. Não disponibilize esses adicionais à venda antes de homologar o ciclo completo de cobrança.
