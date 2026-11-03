# Bemora — Fase 3: backup, observabilidade e recuperação

**Estado:** ferramentas implementadas. Só considerar a operação pronta depois de executar um backup real, testar a restauração isolada, configurar a cópia externa e verificar os alertas.

## 1. Preparar diretório privado e ferramentas

Exemplo para a hospedagem DirectAdmin (ajuste usuário e caminhos conforme o ambiente):
```bash
mkdir -p /home/anoa0880/bemora_private_operations
chmod 700 /home/anoa0880/bemora_private_operations
command -v mysqldump || command -v mariadb-dump
command -v mysql || command -v mariadb
/usr/local/php83/bin/php -m | grep -E '^(zip|zlib)$'
```
Nunca crie o diretório dentro de `public_html` ou dentro do repositório. Não coloque backups no Git, não forneça URLs de download e não compartilhe arquivos de backup por e-mail.

Em `config.php` (arquivo **não versionado**) adicionar:
```php
'operations' => [
  'private_dir' => '/home/anoa0880/bemora_private_operations',
  'alert_email' => 'ADMINISTRADOR@SEU-DOMINIO.COM.BR',
  'retention_days' => 14,
  'mysqldump_binary' => '/usr/bin/mysqldump', // confira command -v, use mariadb-dump se indicado
  'mysql_binary' => '/usr/bin/mysql',
  'restore_test_database' => 'bemora_restore_test', // banco separado, preexistente e vazio
],
```
As credenciais existentes do MySQL em `db` são reutilizadas no backup. O script exige `proc_open`, `ZipArchive`, `zlib` e o utilitário mysqldump. Se a hospedagem desabilitar algum, **não** marque o backup como ativo: contrate backup externo da hospedagem ou adapte a infraestrutura antes de lançar.

## 2. Primeiro backup e verificação

A partir de `public_html`:
```bash
/usr/local/php83/bin/php scripts/ops_run.php --task=backup
/usr/local/php83/bin/php scripts/ops_verify_restore.php
```
O dump é consistente para tabelas InnoDB (`--single-transaction`). Ele inclui o banco, `storage/receipts`, `storage/avatars` e `assets/brand`. O arquivo SQL é comprimido e acompanhado por hashes SHA-256 dos dois arquivos. O backup só é publicado após verificação da integridade.

**Limitações e cuidados:** o SQL e os arquivos não constituem uma snapshot atômica conjunta; se uploads ocorrerem durante o dump, restaure e confronte as referências a anexos. O backup local é protegido por permissões de sistema, mas **não é criptografado**. Configure uma cópia externa criptografada (por exemplo, backup do provedor ou solução S3 com criptografia e retenção imutável). O script não envia os arquivos para fora do servidor.

Mantenha backups **diários** no mínimo 14 dias. Teste restauração mensalmente. Para menor perda de dados que 24 horas, considere backups incrementais ou registros binários do MySQL com políticas específicas.

## 3. Restaurar em ambiente ISOLADO

Crie **manualmente** um novo banco MySQL vazio e separado, com permissão para o mesmo usuário configurado em `db`. Defina `operations.restore_test_database` para ele. Depois:
```bash
/usr/local/php83/bin/php scripts/ops_verify_restore.php --restore-test
```
O comando recusa o banco de produção e qualquer base de destino que já tenha tabelas. Não exclui a base de teste. Confirme manualmente contagens de famílias, lançamentos, assinaturas, tabelas de migrations e referências a comprovantes no ambiente restaurado. O ZIP é inspecionado e validado, mas **não é extraído automaticamente em produção**.

Para selecionar uma cópia específica, use `--backup=AAAAMMDD_HHMMSS_XXXXXXXX` conforme o nome do backup privado.

## 4. Cron no DirectAdmin

Edite o `crontab -e`. **Não execute uma linha de cron diretamente no terminal**, pois ela não é um comando bash. Substitua o cron antigo de conciliação pelo runner monitorado, evitando duplicidades.

Exemplo de horários locais do servidor:
```cron
15 2 * * * /usr/local/php83/bin/php /home/anoa0880/domains/bemora.anoar.com.br/public_html/scripts/ops_run.php --task=billing >/dev/null 2>&1
10 3 * * * /usr/local/php83/bin/php /home/anoa0880/domains/bemora.anoar.com.br/public_html/scripts/ops_run.php --task=backup >/dev/null 2>&1
5 * * * * /usr/local/php83/bin/php /home/anoa0880/domains/bemora.anoar.com.br/public_html/scripts/ops_watchdog.php >/dev/null 2>&1
```
A rotina `plans` permanece intencionalmente pausada até implementar a renovação segura. Não configure o runner de planos por enquanto.

O runner utiliza locks para evitar duas execuções simultâneas e registra início, finalização, resultado e última execução bem-sucedida em arquivos privados. Falhas enviam e-mail para `operations.alert_email` (no máximo uma tentativa de alerta a cada 6 horas por rotina); o watchdog verifica ausência de execução por mais de **28 horas**. Se SMTP também falhar, consulte os logs PHP da hospedagem.

Em Administração → Configurações → Manutenção é possível ver o status dos serviços, última execução bem-sucedida e metadados dos cinco backups mais recentes. Não são exibidos dados pessoais nem há botão para restaurar o banco de produção.

## 5. Monitoramento complementar

Além dos crons, configure no provedor:
- notificações de falhas PHP/LiteSpeed e indisponibilidade HTTP;
- alerta de disco cheio, espaço disponível e utilização de CPU;
- alerta de falha no MySQL, expiração de TLS, quota e SMTP;
- retenção privada com rotação dos logs e revisão diária dos erros;
- verificação externa de disponibilidade, sem publicar endpoint administrativo ou dados financeiros.

Não grave senhas, tokens, dados de pagamento ou corpo de requisições nos logs. Nunca disponibilize relatórios de operações fora do admin global.

## 6. Runbook de implantação e rollback

**Antes de atualizar:**
1. Validar as duas rotinas GitHub Actions e anotar o commit implantado: `git rev-parse HEAD`.
2. Fazer backup `ops_run.php --task=backup`, verificar com `ops_verify_restore.php` e confirmar cópia externa.
3. Planejar janela curta de manutenção se houver migration potencialmente incompatível.
4. Executar `git pull --ff-only origin main`, migrations (se houver), teste funcional mínimo e checagem de crons.

**Se a nova versão falhar:**
1. Bloquear temporariamente novas vendas com a flag de emergência já prevista no Bemora; evitar mudanças no banco até identificar o problema.
2. Parar temporariamente os crons, preservando logs, backups e estado operacional.
3. Identificar o último commit aprovado, executar `git checkout --detach COMMIT_APROVADO` para voltar o código da hospedagem e validar o funcionamento.
4. **Não fazer downgrade automático de schema.** Migrations podem alterar dados e não são revertidas por Git. Se necessário restaurar, primeiro reproduzir o procedimento em banco isolado e planejar a perda/recuperação de transações posteriores.
5. Para voltar ao fluxo normal: `git checkout main` e `git pull --ff-only origin main` após corrigir a falha. Revisar migrations antes de reaplicar.
6. Retomar os crons, testar conciliação e registrar o incidente.

Nunca execute `git reset --hard`, `DROP DATABASE` ou restauração do SQL diretamente sobre produção como primeira resposta a um incidente.

## 7. Critérios de aceite

- [ ] Backup real gerado e verificado via CLI
- [ ] Arquivos privados preservados e verificados no ZIP
- [ ] Restauração em base isolada concluída e dados conferidos
- [ ] Cópia criptografada externa e retenção verificadas
- [ ] Cron de backup e conciliação executado no horário
- [ ] Watchdog alerta sobre tarefa atrasada ou falha SMTP
- [ ] Simulação de falha de cron e posterior recuperação
- [ ] Teste de rollback apenas do código em homologação
- [ ] Logs PHP e monitoramento de disponibilidade configurados pelo provedor

**Até concluir a lista, a Fase 3 permanece em homologação, não concluída.**


## Teste controlado do e-mail de alerta (sem afetar produção)

O utilitário `scripts/ops_test_alert.php` utiliza o SMTP configurado em `mail` e envia somente para `operations.alert_email`, com assunto iniciado por `[TESTE]`. Ele **não** interrompe os crons, não provoca falhas reais, não consulta o Asaas, não altera o banco e não modifica `status-backup.json` ou `status-billing.json`.

Dentro de `public_html`, faça a pré-verificação sem enviar mensagens:
```bash
/usr/local/php83/bin/php scripts/ops_test_alert.php
```
Para enviar o alerta fictício de falha:
```bash
/usr/local/php83/bin/php scripts/ops_test_alert.php --send --scenario=failure
```
Confira o assunto `[TESTE] Bemora · Falha operacional simulada` na caixa de entrada (e spam) do administrador. **A aceitação pelo SMTP não comprova a entrega final ao destinatário.**

Após pelo menos 60 segundos, envie a simulação de recuperação:
```bash
/usr/local/php83/bin/php scripts/ops_test_alert.php --send --scenario=recovery
```
Confira o assunto `[TESTE] Bemora · Recuperação operacional simulada`. O utilitário salva apenas a última simulação, horário UTC e resultado `smtp_accepted`, em `operations.private_dir/alert-test-last.json`, com permissão de proprietário. Essa trilha não substitui os arquivos de status reais.

Por fim, execute:
```bash
/usr/local/php83/bin/php scripts/ops_watchdog.php
```
O resultado deve continuar `Rotinas essenciais dentro do prazo.`. Testes de modelo do e-mail e validação dos cenários também fazem parte dos testes PHP automatizados. Para homologar *gatilhos reais* de ausência de execução, faça posteriormente uma simulação separada em homologação, sem alterar os estados do cron de produção.

Se houver falha de SMTP, confira a configuração de `operations.alert_email`, `mail`, as dependências do Composer e o log PHP privado. Não compartilhe senhas ou tokens.
