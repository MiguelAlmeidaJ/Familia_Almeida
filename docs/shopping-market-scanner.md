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
