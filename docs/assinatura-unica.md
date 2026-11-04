# Bemora — assinatura única (novembro de 2026)

## Novo modelo

Uma oferta comercial **Bemora** com:
- 1 pessoa no preço-base;
- preço recorrente por integrante extra (até o limite configurado);
- módulo de **Compras + scanner** opcional, com preço recorrente próprio;
- periodicidades mensal, semestral e anual, com descontos configuráveis.

O cliente pode escolher os adicionais na página pública e revisá-los no checkout. O servidor recalcula todos os valores antes de criar a cobrança no Asaas; preços não são aceitos diretamente do navegador.

Internamente há três registros técnicos em `subscription_plans`, um por ciclo, **mas apenas uma assinatura comercial**. Não são três níveis ou famílias de planos. Os planos antigos são arquivados (`active=0`), nunca apagados, preservando `subscription_orders`, `tenant_internal_plans`, mudanças históricas, cupons e contratos Asaas.

## Implantação segura

1. Faça backup completo do banco, idealmente com teste de restauração.
2. Execute `git pull origin main`.
3. Execute as migrations pendentes em **Administração → Configurações → Manutenção**:
   - `20261104_045_single_offer` (configuração única e campos de opções dos pedidos).
   - `20261104_046_addon_changes` (alterações posteriores com pagamento proporcional).
4. Acesse **Administração → Assinatura**.
5. Configure nome público, preço-base mensal, valor por integrante extra, máximo de integrantes, preço do módulo Compras e descontos semestral/anual.
6. Confira os valores da prévia e marque **Disponibilizar contratação pública**. Na primeira ativação, confirme com `SUBSTITUIR PLANOS`. A ativação retira da venda as ofertas antigas e mantém os contratos existentes. O servidor não apaga planos no deploy.
7. Na página pública, confirme que existe **um único plano** com seletores de periodicidade, número de integrantes e Compras.
8. Teste o checkout e os cupons no Sandbox. Confira o valor exato da cobrança do período na API do Asaas e o valor recorrente registrado no pedido.
9. Confirme a criação da família com o limite contratado. Com Compras, verifique lista e scanner; sem Compras, confirme bloqueio das URLs diretas e endpoints.
10. Teste acréscimos após a assinatura no Sandbox, incluindo pagamentos atrasados, rejeitados, duplicados e a conciliação do cron.

## Fórmula de cobrança inicial

- Mensal = preço-base + (quantidade total de pessoas − 1) × adicional por integrante + adicional de Compras (quando selecionado).
- Semestral = mensal × 6 × (1 − desconto semestral / 100).
- Anual = mensal × 12 × (1 − desconto anual / 100).
- Todos os preços são calculados em centavos e o Asaas recebe **o valor integral do período**.
- O pedido registra a quantidade contratada e se Compras foi incluído; o valor fica no contrato e no Asaas no momento da contratação.
- Editar a tabela de preços **não altera automaticamente** contratos Asaas existentes.

## Adicionais depois de assinar

- Em **Minha assinatura → Gerenciar adicionais**, o administrador familiar pode solicitar mais pessoas ou adicionar Compras em uma assinatura nova.
- Somente **acréscimos** são aceitos; reduções, exclusões de Compras e alterações de ciclo precisam de uma política de renovação separada.
- O sistema confere a assinatura no Asaas, gera cobrança avulsa **proporcional** e aguarda confirmação validada do pagamento antes de atualizar a recorrência da assinatura original e liberar os recursos.
- A solicitação, o pagamento, a nova recorrência e a ativação ficam registrados para conciliação. Um webhook não confirmado não libera acesso.
- Mudanças durante período de cortesia ficam indisponíveis.
- Em produção, esta operação começa **bloqueada** por segurança (`config('asaas','addon_upgrades_production_enabled','0')`).
  **Somente após homologação** e revisão das condições comerciais, é possível configurar `addon_upgrades_production_enabled => '1'` no bloco `asaas` do `config.php`.
- A conciliação de adicionais também roda em `scripts/reconcile_billing_access.php`. Configure o cron e acompanhe erros com `reconciliation_required`. Não habilite a venda posterior dos adicionais sem uma rotina operacional para casos excepcionais.
- A cobrança de diferença proporcional é estimada conforme dias até o próximo vencimento; a duração de referência é conservadora (31/184/366 dias). Faça revisão jurídica/financeira dessa política antes de produção.

## Compatibilidade e limitações

- Contratos legados preservam os planos históricos, limites e direitos de acesso.
- Módulos e benefícios comerciais continuam armazenados para fins históricos. A oferta nova usa a seleção de Compras diretamente no pedido e mantém Relatórios na base.
- O antigo painel de planos continua disponível para consulta histórica, mas fica bloqueado para edição quando a assinatura única está ativa.
- Esta implementação não oferece um plano público gratuito, nem mudança de periodicidade no contrato existente.
- Open Finance continua fora do escopo.
