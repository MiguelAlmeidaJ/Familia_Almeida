# Família Almeida — Fluxo mensal

Aplicação familiar em PHP 8.1+ e MySQL/MariaDB, sem framework, para planejar **quanto entra, quanto sai e para onde destinar o restante**.

## Visão geral

O painel principal mostra, por mês:
- receitas recorrentes previstas (ex.: salários), sem precisar marcar como recebidas;
- contas recorrentes previstas, independentemente de pagamento;
- dinheiro disponível após essas contas;
- envelopes por categoria (Mercado, Farmácia, Lazer, Pet e categorias livres);
- total já gasto em cada categoria e valor ainda sem destino;
- gastos registrados e saldo projetado.

**Cálculo:** disponível após contas = receitas previstas − contas previstas. Livre para distribuir = disponível após contas − total dos envelopes. O lançamento de um gasto na categoria consome seu envelope; não é subtraído novamente do valor livre para distribuir. A previsão do fim do mês considera contas previstas e gastos avulsos já registrados.

Os envelopes são configurados **por mês**; receitas recorrentes e contas fixas continuam aparecendo nos meses seguintes. O acompanhamento é de **planejamento**, não um extrato/saldo bancário nem conciliação de pagamentos.

## Migração de instalação existente

1. Faça backup do banco.
2. Publique o código PHP atualizado.
3. Em **Configurações → Manutenção**, execute a migration `20261020_009_monthly_budget.php`.
4. Volte ao painel e cadastre os ganhos recorrentes e a distribuição do mês.

A migração não apaga tabelas antigas nem o histórico de compras, estoque e metas. Essas áreas saíram do menu principal, mas permanecem no código/banco por segurança até uma limpeza posterior.

## Configuração

Use `config.example.php` como base para o `config.php` local, que não deve ir ao Git. A aplicação usa autenticação com sessão e proteção CSRF.

As contas existentes são reaproveitadas da tabela `fixed_bills`, com suporte a parcelas e valores específicos por mês; esses valores podem ser ajustados na tela de contas. Os lançamentos continuam em `/movimentacoes`. Históricos de receitas não são automaticamente transformados em previsão recorrente: cadastre os ganhos mensais no novo painel.

## Observações

Receitas extraordinárias lançadas apenas no histórico não aumentam automaticamente a receita recorrente prevista. Gastos de contas vinculados ao sistema de pagamentos não são descontados novamente da projeção. Para garantir uma previsão correta, classifique gastos avulsos em categorias diferentes de “Contas recorrentes” e “Contas fixas”.
