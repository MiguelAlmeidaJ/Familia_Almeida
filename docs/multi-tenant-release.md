# Bemora — liberação controlada multi-tenant

## Situação
A área financeira utiliza os vínculos `tenant_users`, a família ativa da sessão,
predicados `tenant_id` e, em finanças individuais, `owner_user_id`.

A aprovação de novos cadastros e o acesso a famílias adicionais são protegidos pela
variável de ambiente `BEMORA_MULTI_TENANT_RELEASE=1`. Sem ela, a família legada
continua funcionando e as novas famílias não são ativadas.

## Antes de habilitar
1. Fazer backup completo do MySQL e de `storage/receipts` e `storage/avatars`.
2. Rodar as migrations em **Administração global → Manutenção**.
3. Confirmar os dois fluxos de CI: **PHP syntax validation** e
   **Finance tenant and owner integration**, incluindo os testes
   `tenant_access_cli.php`.
4. Garantir que o servidor PHP realmente recebe `BEMORA_MULTI_TENANT_RELEASE`
   como variável de ambiente; o mecanismo depende da configuração PHP/Apache
   disponível na ValueHost.
5. Criar um convite de teste no painel global; cadastrar duas famílias distintas
   com e-mails diferentes, verificando a ativação automática e o login imediato.
6. Em dois navegadores, testar Painel, Lançamentos, Comprovantes, Contas,
   Categorias, Relatórios, Meu perfil, Membros e Finanças individuais.
7. Confirmar que links ou IDs de outra família retornam **403/404** e que
   dados privados de outro membro não ficam visíveis.
8. Conferir backup e restauração antes de aprovar famílias comerciais.

## Ativação
Depois de verificar todas as condições, definir
`BEMORA_MULTI_TENANT_RELEASE=1` na configuração de ambiente do PHP no
servidor. Não colocar essa chave como parâmetro de URL ou campo de formulário.
Ela não é alterada pela página de administração.

O fluxo previsto: Administração global → Famílias → Criar link → Cadastro →
criação automática de família e membros → acesso imediato do administrador familiar.
Não há fila de aprovação. Os usuários possuem e-mails exclusivos e são vinculados
à família criada. O administrador pode fornecer um e-mail opcional na geração do
convite: a aplicação envia via PHPMailer e SMTP autenticado, mantendo sempre
um link copiável. Instale as dependências com `composer install --no-dev --optimize-autoloader`
e configure `app.base_url`, `mail.host`, `mail.port`, `mail.encryption`,
`mail.username`, `mail.password`, `mail.from` e `mail.from_name` no `config.php`
fora do GitHub. TLS STARTTLS na porta 587 ou SMTPS na porta 465.
Verifique a conectividade de saída SMTP da ValueHost e os registros SPF/DKIM/DMARC.
O aceite pelo servidor SMTP não garante entrega na caixa de entrada.

## Desativação rápida
Remover a variável ou ajustá-la para `0`. Isso impede novos cadastros e
bloqueia o acesso das famílias adicionais até corrigir qualquer incidente.
Não apaga dados nem altera os vínculos existentes.

## Limitações
- Os testes de CI não substituem teste de implantação real na ValueHost.
- Feriados municipais não entram no vencimento por dia útil.
- Históricos anteriores à implantação de versionamento de salários podem ser
  incompletos se os dados antigos foram sobrescritos.
- Módulos antigos de cartões, estoque e compras continuam fora do escopo.
