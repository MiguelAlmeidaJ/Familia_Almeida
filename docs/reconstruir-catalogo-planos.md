# Bemora — reconstruir catálogo comercial por módulos

Esta versão permite **arquivar** o catálogo público antigo e criar novas ofertas em pacotes. A operação é iniciada pelo administrador; o deploy não apaga nada automaticamente.

## Passo a passo no servidor

1. Faça um backup do banco e verifique a agenda de cobranças Asaas.
2. Rode `git pull origin main`.
3. No painel **Configurações → Manutenção**, confira se a migration `20261104_043_subscription_plan_modules` está executada. Não há migration adicional nesta etapa.
4. Em **Administração → Planos**, abra **Recomeçar catálogo público com segurança**. Leia o aviso, digite `ARQUIVAR PLANOS` e confirme.
5. Os planos antigos deixam de aparecer como ofertas ativas no site e no checkout. Eles continuam no banco e podem ser consultados em **Mostrar ofertas arquivadas**; pedidos, assinaturas, famílias e cupons não são excluídos.
6. Na tela **Administração → Planos**, clique em **Criar plano** para abrir a nova página **/admin/planos/novo**. No formulário **Monte planos por módulos e integrantes**, escolha o nome do pacote, número máximo de integrantes, preço base mensal, adicional mensal por integrante (opcional, pode ser zero), descontos semestral e anual, módulos e descrição.
7. Escolha **Criar como rascunho** (padrão) para homologar antes de vender, ou **Publicar as 3 ofertas agora**.
8. Na nova página, clique em **Criar 3 ofertas**. A mesma configuração gera um registro mensal, um semestral e um anual. Para ofertar outras quantidades de pessoas ou outro conjunto de funcionalidades, repita o processo com um nome/limite distintos.
9. Confira a vitrine pública e cada checkout de teste. Publique os rascunhos individualmente quando a operação comercial estiver pronta.

## Como são calculados os valores

- Valor mensal = **preço base mensal + (integrantes permitidos - 1) × adicional por integrante**
- Semestral = mensal × 6 × (1 - desconto semestral / 100)
- Anual = mensal × 12 × (1 - desconto anual / 100)
- Os valores são arredondados para centavos e gravados em cada plano; o checkout Asaas cobra **o valor total do período**, não doze parcelas mensais.
- O preço é calculado no servidor; a prévia JavaScript é apenas ilustrativa.

## Proteções

- A opção de arquivamento usa confirmação textual, POST autenticado e CSRF.
- Arquivar significa `active=0`; não há `DELETE` em `subscription_plans`, `subscription_orders`, `tenant_internal_plans`, `subscription_change_requests`, ou no Asaas.
- Os planos internos não participam do arquivamento em lote.
- A oferta gerada por padrão começa como rascunho. Nenhuma cobrança é criada na página administrativa.
- O cadastro de novos pacotes é atômico: ou todas as três ofertas são gravadas, ou nenhuma.
- Duas ofertas ativas de mesmo nome, quantidade de pessoas e periodicidade não podem ser geradas pela ferramenta.
- A atribuição de módulos continua validada no backend; Scanner depende de Compras.
- **Open Finance não foi criado nem configurado.**

## Limitações atuais

- O adicional por integrante vira parte do preço fixo da oferta gerada; **não há acréscimo dinâmico no checkout nem alteração automática de quantidade de membros após contratar**.
- Também não há contratação isolada de módulos avulsos ou mudança automática dos contratos existentes; para isso será necessária uma etapa específica de alterações e reconciliação das assinaturas Asaas.
- A vitrine pública atual exige valores maiores que zero. Planos internos gratuitos continuam possíveis no editor de ofertas, mas um cadastro público gratuito ainda precisa de fluxo próprio.
- Se um cliente tiver contrato ativo em um plano arquivado, as permissões já contratadas permanecem vinculadas ao registro antigo. A política de migração/upgrade de contratos deve ser homologada antes de alterar assinaturas em produção.
- Evite criar uma quantidade excessiva de variantes: cada variante de membros gera três cards, um em cada aba. Teste o layout público ao compor um catálogo grande.

## Sugestão de catálogo inicial

Configure de forma incremental: um pacote individual, depois um pacote para casal e outro para família. Escolha os módulos e preços no próprio administrador. Não há preços ou descontos pré-preenchidos como decisão de negócio.

### Checklist de testes

- Plano interno existente mantém acesso.
- Planos arquivados desaparecem da página pública, mas ainda possuem histórico.
- Rascunhos não aparecem no site/checkout.
- Ao publicar, a oferta aparece apenas em sua aba correta.
- Nome, módulos, limites e preços estão corretos nos três períodos.
- Cupom e checkout Asaas permanecem com o preço final correto.
- Membros já contratantes preservam acesso; acesso aos módulos funciona por rotas diretas e endpoints.
