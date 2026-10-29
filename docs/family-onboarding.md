# Bemora — convites de famílias (pré-cadastro controlado)

## Estado de segurança
O cadastro via convite **não cria um tenant ativo nem libera acesso ao módulo financeiro**.
A migração anterior possui tabelas financeiras com `tenant_id DEFAULT <família legada>`
e consultas existentes ainda não filtradas. Ativar novos tenants nessas condições
levaria ao compartilhamento indevido de dados. Os registros ficam na fila
`family_registrations` em estado `pending` até a conclusão do isolamento.

## Utilização
1. Criar backup restaurável do banco e executar migration `20261029_012_family_invitations`.
2. Administrador global → Famílias → gerar convite (1 a 30 membros).
3. Copiar link completo do token de 256 bits e enviar por canal privado.
4. O convidado preenche o formulário em três etapas (família, pessoas, administrador).
5. O token expira após 72 horas e é invalidado transacionalmente no primeiro envio.
6. O administrador vê os cadastros pendentes na página Famílias.

## Observações
- Nome de família é único, ignorando diferenças de caixa conforme a collation MySQL.
- Nomes duplicados também são impedidos entre cadastros pendentes por índice único.
- E-mails devem ser distintos; usuários já existentes não são registrados novamente.
- Senhas são armazenadas somente como hash em `members_json`, não como texto simples.
- O formulário pede os sexos Masculino/Feminino conforme o requisito atual.
- Arquivar/manutenção torna a família inativa (sem membership ativa); ativar só é
  permitido para famílias já existentes que não estejam em estado `pending`.
- O link de convite não deve aparecer em logs públicos, analytics ou sistemas de terceiros.
- As telas de gerenciamento não têm ainda aprovação/ativação de novos cadastros.
  Falta implementar filtro de tenant em **todas** as rotas de leitura/escrita,
  FKs, índices, uploads e sincronização offline, com testes cruzados, antes de liberar.

## Verificação após deploy
- Em ambiente de teste: link válido, expirado, reutilizado, limites de membros,
  nome repetido, e-mail repetido, conflito simultâneo e controle CSRF.
- Testar arquivo PHP com `php -l` e servidor MySQL migrado.
- Confirmar que novas famílias não acessam dados financeiros.
