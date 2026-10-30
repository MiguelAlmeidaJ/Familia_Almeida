<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/finance.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/receipts.php';
require_once __DIR__ . '/../includes/categories.php';

$user = require_auth();
$pdo = db();
$month = selected_month();
$data = dashboard_data($pdo, $month);
$csrf = csrf_token();
$flash = pull_flash();
$defaultDate = $month === date('Y-m') ? date('Y-m-d') : $month . '-01';
$current = new DateTimeImmutable($month . '-01');
$prevMonth = $current->modify('-1 month')->format('Y-m');
$nextMonth = $current->modify('+1 month')->format('Y-m');
$receiptsReady = receipts_schema_ready($pdo);
$categories = category_list($pdo);
$categoriesReady = categories_ready($pdo);
$receiptsByTransaction = receipts_for_month($pdo, $month);

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthAbbr = [1 => 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;

$dailySpending = [];
$totalReceipts = 0;

foreach ($data['transactions'] as $transaction) {
    $transactionId = (int) $transaction['id'];
    $receiptCount = count($receiptsByTransaction[$transactionId] ?? []);
    $totalReceipts += $receiptCount;

    if ($transaction['type'] !== 'expense') {
        continue;
    }

    $date = (string) $transaction['date'];
    if (!isset($dailySpending[$date])) {
        $dailySpending[$date] = [
            'total' => 0.0,
            'purchases' => 0,
            'receipts' => 0,
        ];
    }

    $dailySpending[$date]['total'] += (float) $transaction['amount'];
    $dailySpending[$date]['purchases']++;
    $dailySpending[$date]['receipts'] += $receiptCount;
}
krsort($dailySpending);

$expenseDays = count($dailySpending);
$largestDay = 0.0;
foreach ($dailySpending as $day) {
    $largestDay = max($largestDay, (float) $day['total']);
}
$averageDay = $expenseDays > 0 ? ((float) $data['totals']['expense'] / $expenseDays) : 0.0;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Lançamentos • Bemora</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"><link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="/assets/categories.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/bemora.css?v=2"><link rel="stylesheet" href="/assets/bemora-finance.css?v=2"><link rel="stylesheet" href="/assets/bemora-finish.css?v=3"><?php brand_head_tags(); ?><link rel="stylesheet" href="/assets/bemora-sidebar.css?v=3"></head>
<body class="bemora-finance">
<div class="shell ref-shell">
<?php render_sidebar('movimentacoes', $csrf); ?>
<main>
<?php render_topbar($user); ?>

<div class="content ref-dashboard movements-reference-page">
    <section class="ref-dashboard-heading movements-reference-heading">
        <div>
            <p class="eyebrow">ENTRADAS E SAÍDAS</p>
            <h1>Lançamentos</h1>
            <p>Acompanhe as entradas, despesas e investimentos da família. Registre novas movimentações pelo botão flutuante.</p>
        </div>

        
    </section>

    <?php if ($flash): ?>
        <div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if (!$receiptsReady): ?>
        <div class="alert migration-alert">
            Há uma atualização pendente para anexar notas e comprovantes.
            <a href="/configuracoes/manutencao">Executar migration →</a>
        </div>
    <?php endif; ?>

    <section class="movements-summary-grid">
        <article><span>Entradas</span><strong><?= money($data['totals']['income']) ?></strong><small>inclui renda mensal prevista do perfil</small></article>
        <article><span>Gastos</span><strong><?= money($data['totals']['expense']) ?></strong><small><?= $expenseDays ?> dia(s) com despesas</small></article>
        <article><span>Média por dia</span><strong><?= money($averageDay) ?></strong><small>considerando dias com gastos</small></article>
        <article class="receipt-stat"><span>Notas anexadas</span><strong><?= $totalReceipts ?></strong><small>imagens e PDFs guardados</small></article>
    </section>

    <section class="movements-main-grid bemora-movements-overview">
        <article class="card daily-spending-card">
            <div class="movement-card-heading">
                <div>
                    <p class="eyebrow">GASTOS POR DIA</p>
                    <h2>Despesas por dia</h2>
                    <p>Despesas registradas em <?= e($monthLabel) ?>.</p>
                </div>
                <span class="largest-day-badge">Maior dia: <?= money($largestDay) ?></span>
            </div>

            <div class="daily-spending-list">
                <?php if ($dailySpending): ?>
                    <?php foreach (array_slice($dailySpending, 0, 8, true) as $date => $day): ?>
                        <?php $dateObject = new DateTimeImmutable($date); ?>
                        <div class="daily-spending-row">
                            <div class="daily-spending-date">
                                <strong><?= e($dateObject->format('d')) ?></strong>
                                <span><?= e($monthAbbr[(int) $dateObject->format('n')]) ?></span>
                            </div>
                            <div class="daily-spending-copy">
                                <strong><?= money($day['total']) ?></strong>
                                <span><?= (int) $day['purchases'] ?> gasto(s)<?= $day['receipts'] ? ' • ' . (int) $day['receipts'] . ' nota(s)' : '' ?></span>
                            </div>
                            <div class="daily-spending-bar"><span style="width:<?= $largestDay > 0 ? min(100, ($day['total'] / $largestDay) * 100) : 0 ?>%"></span></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="movements-empty-state">
                        <span>▧</span>
                        <strong>Nenhum gasto neste mês</strong>
                        <p>Os totais por dia aparecem assim que vocês começarem a registrar as compras.</p>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    </section>

    <section class="card movement-history-card">
        <header class="movement-history-head">
            <div>
                <p class="eyebrow">HISTÓRICO</p>
                <h2>Histórico de lançamentos</h2>
            </div>
            <span><?= count($data['transactions']) ?> lançamento(s)</span>
        </header>

        <div class="bemora-history-tools"><label class="bemora-history-search"><i class="bi bi-search"></i><input id="bemora-history-search" type="search" placeholder="Buscar descrição, categoria ou membro" aria-label="Buscar lançamentos"></label><label class="bemora-history-filter"><span>Tipo</span><select id="bemora-history-type" aria-label="Filtrar por tipo"><option value="all">Todos</option><option value="income">Ganhos</option><option value="expense">Gastos</option><option value="investment">Investimentos</option></select></label></div>
        <p class="bemora-filter-empty" id="bemora-filter-empty" hidden>Nenhum lançamento corresponde aos filtros.</p>
        <div class="movement-history-list">
        <?php if ($data['transactions']): ?>
            <?php foreach ($data['transactions'] as $transaction): ?>
                <?php
                $transactionId = (int) $transaction['id'];
                $transactionReceipts = $receiptsByTransaction[$transactionId] ?? [];
                ?>
                <article class="movement-history-row" data-transaction-type="<?= e($transaction['type']) ?>">
                    <div class="movement-history-icon <?= e($transaction['type']) ?>"><?= $transaction['type'] === 'income' ? '↙' : '↗' ?></div>

                    <div class="movement-history-copy">
                        <strong><?= e($transaction['description']) ?></strong>
                        <span><?= e($transaction['category']) ?> • <?= e(date('d/m/Y', strtotime($transaction['date']))) ?> • <?= e($transaction['created_by_name'] ?: 'Família') ?></span>

                        <?php if ($transactionReceipts): ?>
                            <div class="receipt-chip-list">
                                <?php foreach ($transactionReceipts as $index => $receipt): ?>
                                    <span class="receipt-chip">
                                        <a href="/comprovante?id=<?= (int) $receipt['id'] ?>" target="_blank" rel="noopener">
                                            ▧ <?= e(count($transactionReceipts) === 1 ? 'Ver nota' : 'Nota ' . ($index + 1)) ?>
                                        </a>
                                        <form method="post" action="/acao" onsubmit="return confirm('Remover este comprovante?')">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="return_to" value="/movimentacoes">
                                            <input type="hidden" name="action" value="delete_receipt">
                                            <input type="hidden" name="month" value="<?= e($month) ?>">
                                            <input type="hidden" name="receipt_id" value="<?= (int) $receipt['id'] ?>">
                                            <button type="submit" title="Remover nota">×</button>
                                        </form>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="movement-history-value <?= $transaction['type'] === 'income' ? 'positive' : 'negative' ?>">
                        <?= $transaction['type'] === 'income' ? '+' : '−' ?> <?= money($transaction['amount']) ?>
                    </div>

                    <div class="movement-history-actions">
                        <?php if ($transaction['type'] === 'expense' && $receiptsReady): ?>
                            <button
                                class="receipt-attach-button"
                                type="button"
                                onclick='openReceiptDialog(<?= $transactionId ?>, <?= json_encode($transaction["description"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                title="Anexar nota"
                            >＋ Nota</button>
                        <?php endif; ?>

                        <?php if (!empty($transaction['virtual_income'])): ?>
                            <span class="auto-transaction-badge">Renda mensal · Meu perfil</span>
                        <?php elseif (!empty($transaction['bill_payment_id'])): ?>
                            <span class="auto-transaction-badge">Automática</span>
                        <?php else: ?>
                            <form method="post" action="/acao" onsubmit="return confirm('Remover este lançamento?')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="return_to" value="/movimentacoes">
                                <input type="hidden" name="action" value="delete_transaction">
                                <input type="hidden" name="month" value="<?= e($month) ?>">
                                <input type="hidden" name="transaction_id" value="<?= $transactionId ?>">
                                <button class="movement-delete-button btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="movements-empty-state history-empty">
                <span>↕</span>
                <strong>Nenhum lançamento neste mês</strong>
            </div>
        <?php endif; ?>
        </div>
    </section>

    <footer class="ref-page-footer movements-page-footer">
        <span>Lançamentos financeiros&nbsp;&nbsp; / &nbsp;&nbsp;Organize os registros da sua família.</span>
        <span><?= e(ucfirst($monthLabel)) ?></span>
    </footer>
</div>
</main>
</div>
<link rel="stylesheet" href="/assets/bemora-movements-refresh.css?v=1">
<script src="/assets/bemora-movements-refresh.js?v=1" defer></script>

<dialog id="receipt-dialog" class="fixed-reference-dialog fixed-reference-small-dialog">
    <form method="post" action="/acao" enctype="multipart/form-data" class="fixed-reference-dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="return_to" value="/movimentacoes">
        <input type="hidden" name="action" value="add_receipts">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="transaction_id" id="receipt-transaction-id">

        <div class="fixed-reference-dialog-head">
            <div>
                <h3>Anexar nota</h3>
                <p id="receipt-dialog-description">Adicione a foto ou PDF do comprovante.</p>
            </div>
            <button type="button" onclick="document.getElementById('receipt-dialog').close()">×</button>
        </div>

        <label>Arquivo(s)
            <input
                type="file"
                name="receipt_files[]"
                accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,.pdf"
                multiple
                required
            >
        </label>

        <div class="receipt-dialog-help">Até 5 arquivos, com no máximo 8 MB cada.</div>

        <div class="fixed-reference-dialog-actions">
            <button class="fixed-cancel-button" type="button" onclick="document.getElementById('receipt-dialog').close()">Cancelar</button>
            <button class="fixed-save-button" type="submit">Anexar nota ✓</button>
        </div>
    </form>
</dialog>

<script>
function openReceiptDialog(transactionId, description) {
    document.getElementById('receipt-transaction-id').value = transactionId;
    document.getElementById('receipt-dialog-description').textContent = 'Anexar comprovante a: ' + description;
    document.getElementById('receipt-dialog').showModal();
}
</script>
<script>window.familiaCategories = <?= json_encode($categories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>

</body>
</html>
