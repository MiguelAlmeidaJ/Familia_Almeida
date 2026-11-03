# Compras: listas familiares e leitura de etiquetas

## Implantação
1. Atualize o repositório com `git pull --ff-only origin main`.
2. No administrador global, execute a migration `20261103_041_shopping_lists.php`.
3. Faça login em uma família e abra **Compras**, logo depois de Contas previstas.
4. Crie uma lista, adicione itens e suas quantidades e abra **Estou no mercado**.
5. No celular com HTTPS, permita o uso da câmera, centralize a etiqueta e toque em **Capturar etiqueta**. Se a câmera não estiver disponível, escolha uma foto.

O módulo usa as novas tabelas `bemora_market_lists` e `bemora_market_items`, isoladas das tabelas de compras legadas descontinuadas. Cada consulta e alteração é limitada ao `tenant_id` da sessão autenticada. As listas são compartilhadas entre membros da mesma família, independentemente do modo de finanças individuais. Elas **não** geram lançamento financeiro automático.

## Reconhecimento de texto

A câmera é usada mediante permissão do navegador. O aplicativo recorta a região central de 85% de largura por 40% de altura, correspondente à moldura exibida. O recorte é processado pelo Tesseract.js v5 (modelo `por`) dentro do navegador; nenhum endpoint PHP recebe fotos da etiqueta.

A primeira leitura precisa de conexão para baixar o motor OCR e os dados do idioma. A integração usa a distribuição `https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js`, que deve estar acessível na hospedagem e no navegador. Considere hospedar uma versão revisada dos arquivos localmente antes de depender do recurso em produção.

**O OCR é uma sugestão, não uma identificação garantida.** Em etiquetas com múltiplos preços, unidades por quilo, descontos do programa de fidelidade, layout não convencional ou baixa iluminação, o nome ou preço podem estar incorretos. O usuário deve conferir e corrigir. A aplicação oferece correspondência de produto já planejado, mas apenas a confirmação explícita altera a lista.

Se o OCR falhar ou o navegador bloquear a câmera, o nome, preço e quantidade podem ser inseridos manualmente na mesma tela.

## Homologação recomendada

- [ ] Migration aplicada sem conflitos com módulos antigos
- [ ] Um membro da família não consegue selecionar listas de outro tenant por ID manipulado
- [ ] Adicionar uma lista e itens sem preço; editar quantidades e unidades
- [ ] Testar câmera em Android Chrome e Safari iOS, via HTTPS, com permissão explícita
- [ ] Testar fotografia de etiqueta em ambientes claros e escuros, com vírgula decimal
- [ ] Conferir produto que já estava na lista, garantindo que apenas o item selecionado seja atualizado
- [ ] Conferir produto novo e total calculado por quantidade × preço unitário
- [ ] Testar nome parecido: correspondência sugerida deve poder ser alterada manualmente
- [ ] Rejeitar preço vazio ao marcar produto como comprado
- [ ] Testar status de lista concluída: edição bloqueada, reabrir para alterar
- [ ] Verificar funcionamento sem o CDN: entrada manual continua disponível

Fora do escopo desta entrega inicial: sincronização offline, histórico comparativo por supermercado, cálculo de preços por peso/unidade automaticamente a partir da etiqueta e criação de despesas financeiras automáticas.


## Atualização — carrinho reversível e controles no celular

No modo **Estou no mercado**, a seção de itens é apresentada antes do formulário de conferência em telas pequenas, com progresso e total. O atalho **Ver carrinho e alterar produtos** permite chegar rapidamente a ela. Após confirmar um item escaneado no celular, a tela retorna à seção de itens.

- **Desfazer compra:** altera somente `purchased` para `0`, preservando o nome, a quantidade e o preço confirmado. O item volta à lista de pendentes e seu valor deixa de integrar o total. É possível marcá-lo como comprado novamente sem precisar escanear.
- **Retirar da lista:** exclui o produto após confirmação do usuário e recalcula o total se ele já estava comprado. Este é um comando distinto de desfazer.
- As ações são executadas por POST autenticado, com CSRF e validação de `tenant_id` / `list_id` / `item_id`, respeitando listas concluídas e evitando edição simultânea por botões na mesma página.

### Testes manuais adicionais

- [ ] Em uma lista com dois produtos comprados, desfazer um deles e confirmar redução do total
- [ ] Confirmar que o preço anterior continua no item pendente e pode ser marcado comprado novamente
- [ ] Retirar um produto pendente e outro comprado; conferir contagem e total após confirmação
- [ ] Cancelar a confirmação de retirada e verificar que nada mudou
- [ ] Tentar editar uma lista concluída e verificar bloqueio no servidor
- [ ] Abrir no celular, acessar o carrinho pelo atalho da câmera e voltar ao scanner
- [ ] Após confirmar produto escaneado, verificar atualização da seção de carrinho
- [ ] Tentar alterar IDs de produtos de outra família e confirmar que não há acesso


## Refinamento mobile — 03/11/2026

Na tela **Estou no mercado**, o formulário **Conferir produto** vem antes do carrinho tanto no desktop quanto no celular, para não exigir percorrer listas extensas antes de confirmar o OCR. Os botões globais flutuantes de suporte, novo lançamento e nova conta prevista são ocultos **somente nessa tela e em telas de até 750 px**; as funcionalidades continuam acessíveis pelo menu fora do modo mercado.

Os cartões de produtos são montados em lotes de **10** conforme a rolagem pelo `IntersectionObserver`, com botão **Carregar mais produtos** como alternativa acessível. Após alterar um produto, a quantidade de cartões já exibida é preservada. Totais, progresso e correspondência com produtos planejados consideram todos os itens.

**Limitação atual:** a renderização da lista é progressiva no navegador, mas o JSON inicial ainda traz os itens da lista para permitir correspondência e cálculo imediato. Portanto, isso reduz o custo de DOM e de rolagem; uma paginação real de consultas ao servidor deve ser implementada se o limite de até 300 itens por lista precisar aumentar.

- [ ] Conferir que o formulário aparece antes do carrinho no celular
- [ ] Confirmar que os três botões flutuantes não cobrem os campos no modo mercado, mas permanecem nas outras páginas
- [ ] Com mais de 20 itens, observar os lotes de 10 surgindo ao rolar
- [ ] Verificar botão de carregamento adicional se a observação automática não estiver disponível
- [ ] Desfazer compra e remover item após abrir lotes adicionais, sem perder o restante da lista visível
