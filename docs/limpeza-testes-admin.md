# Bemora — limpeza administrativa de testes

Esta funcionalidade está disponível somente a administradores globais do Bemora.

## Preparação

1. Faça um backup do banco (e valide sua restauração).
2. Rode `git pull origin main`.
3. Execute a migration `20261104_048_admin_cleanup` em **Configurações → Manutenção**.
4. Verifique se a chave do Asaas aponta para o ambiente pretendido: Sandbox ou produção.

**Nenhum registro é removido durante a migration ou ao abrir as telas.** A exclusão só acontece por POST autenticado/CSRF, após confirmação textual exata no diálogo.

## Assinaturas: /admin/assinaturas

Cada pedido com recorrência ainda existente no Asaas mostra **Cancelar assinatura**. A operação verifica o identificador do cliente e a assinatura, consulta suas faturas e usa `DELETE /subscriptions/{id}`. O Asaas encerra a recorrência, remove cobranças pendentes/vencidas e mantém faturas pagas.

Uma marca local de remoção (e uma indicação de faturas já liquidadas) evita excluir pedidos com pagamentos. Caso haja falha na persistência local depois de confirmação da API, a tela informa necessidade de conciliação; **não repita a remoção automaticamente**.

Pedidos sem pagamento e sem assinatura Asaas ativa podem mostrar **Excluir teste**. O servidor proíbe essa operação quando:
- a cobrança foi paga, confirmada, revertida ou exige atenção;
- o pedido ainda possui recorrência não removida no Asaas;
- o Asaas registra qualquer fatura liquidada ou status remoto não inequivocamente aberto/deletado;
- existem cobranças relacionadas a upgrades ou adicionais que precisam de conciliação.

A exclusão local elimina o pedido e registros dependentes de cupom, solicitações e cancelamentos que não tenham pagamentos. O convite de cadastro ainda não utilizado é revogado. O cadastro de cliente remoto no Asaas não é excluído automaticamente.

## Famílias: /admin/tenants

Na coluna **Ações**, o botão vermelho de lixeira solicita a frase `EXCLUIR ESPAÇO <id>`. Depois da confirmação:

- O backend bloqueia o espaço inicial `familia-almeida` e espaços contendo administradores globais.
- É obrigatório limpar antes os pedidos não pagos vinculados; espaços com pedidos pagos permanecem protegidos.
- São excluídas as linhas com `tenant_id` daquele espaço, respeitando a ordem de dependências de chaves estrangeiras, além do registro do espaço e dos usuários que **não pertencem a nenhum outro espaço**.
- Convites/solicitações de cadastro concluídas, quando sem pedidos remanescentes, também são excluídos.
- Se houver alguma dependência de banco não conciliável, a transação inteira é revertida; não desative chaves estrangeiras para forçar a exclusão.

## Confirmação e rastreabilidade

O diálogo exige digitar exatamente **CANCELAR ASSINATURA <id>**, **EXCLUIR PEDIDO <id>** ou **EXCLUIR ESPAÇO <id>**, conforme a ação. O backend compara o texto independente do JavaScript. Há auditoria mínima em `admin_cleanup_audit` com identificador do admin, tipo de operação, ID do alvo e data, sem copiar documentos ou dados de pagamento.

## Limitações e testes

- Não exclua registros financeiros pagos de produção através desta ferramenta. Use os fluxos LGPD/retenção e conciliação de pagamentos.
- Não apaga o cadastro remoto do cliente nem faturas pagas do Asaas.
- Arquivos físicos anexados (por exemplo comprovantes) podem permanecer no armazenamento: a limpeza implementada é das **linhas do banco**. Para sua exclusão, use a política de armazenamento e LGPD apropriada.
- Se a família possui outros vínculos inesperados, o banco bloqueia a operação e mantém o estado anterior.
- **Primeiro teste no Sandbox:** criar assinatura não paga, cancelar recorrência, excluir pedido, excluir o espaço e confirmar no painel do Asaas que não haverá novas cobranças. Verifique que a família antiga e demais espaços permanecem íntegros.
- Cancelar uma assinatura é uma ação externa e não pode participar da mesma transação SQL da limpeza local. Em caso de erro de rede, confira manualmente o Asaas antes de repetir.

### Como usar

1. **Administração → Assinaturas:** clique em **Cancelar assinatura** no pedido desejado (quando houver recorrência).
2. Após confirmação, se o pedido não tiver qualquer pagamento, clique em **Excluir teste**.
3. **Administração → Famílias:** clique na lixeira do espaço correspondente e digite a frase apresentada.
4. Confirme a remoção na listagem e consulte o histórico em `admin_cleanup_audit` quando necessário.
