<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/finance.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/receipts.php';

$user = require_auth();
$pdo = db();
$month = valid_month($_GET['month'] ?? null);
$data = dashboard_data($pdo, $month);
$csrf = csrf_token();
$flash = pull_flash();
$defaultDate = $month === date('Y-m') ? date('Y-m-d') : $month . '-01';
$current = new DateTimeImmutable($month . '-01');
$prevMonth = $current->modify('-1 month')->format('Y-m');
$nextMonth = $current->modify('+1 month')->format('Y-m');
$receiptsReady = receipts_schema_ready($pdo);
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
    <title>Lançamentos • Família Almeida</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="shell ref-shell">
<?php render_sidebar('movimentacoes', $csrf); ?>
<main>
<?php render_topbar($user); ?>

<div class="content ref-dashboard movements-reference-page">
    <section class="ref-dashboard-heading movements-reference-heading">
        <div>
            <p class="eyebrow">ENTRADAS E SAÍDAS</p>
            <h1>Lançamentos</h1>
            <p>Registre as compras e guarde as notas para saber exatamente quanto foi gasto em cada dia.</p>
        </div>

        <div class="ref-month-picker">
            <a href="?month=<?= e($prevMonth) ?>" aria-label="Mês anterior">‹</a>
            <span><?= e($monthLabel) ?></span>
            <span class="ref-calendar">▣</span>
            <a href="?month=<?= e($nextMonth) ?>" aria-label="Próximo mês">›</a>
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
        <article><span>Entradas</span><strong><?= money($data['totals']['income']) ?></strong><small>recebido no mês</small></article>
        <article><span>Gastos</span><strong><?= money($data['totals']['expense']) ?></strong><small><?= $expenseDays ?> dia(s) com compras</small></article>
        <article><span>Média por dia</span><strong><?= money($averageDay) ?></strong><small>considerando dias com gastos</small></article>
        <article class="receipt-stat"><span>Notas anexadas</span><strong><?= $totalReceipts ?></strong><small>imagens e PDFs guardados</small></article>
    </section>

    <section class="movements-main-grid">
        <article class="card movement-entry-card">
            <div class="movement-card-heading">
                <div><p class="eyebrow">NOVO LANÇAMENTO</p><h2>Registrar movimento</h2></div>
            </div>

            <form method="post" action="/acao" enctype="multipart/form-data" class="movement-entry-form" id="transaction-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="return_to" value="/movimentacoes">
                <input type="hidden" name="action" value="add_transaction">
                <input type="hidden" name="month" value="<?= e($month) ?>">

                <label>Tipo
                    <select name="type" id="transaction-type" required>
                        <option value="expense">Gasto</option>
                        <option value="income">Entrada</option>
                        <option value="investment">Investimento</option>
                    </select>
                </label>

                <label>Descrição
                    <input name="description" maxlength="160" placeholder="Ex.: Compra no supermercado" required>
                </label>

                <div class="movement-form-grid">
                    <label>Categoria
                        <input name="category" maxlength="100" placeholder="Ex.: Mercado" required>
                    </label>
                    <label>Valor (R$)
                        <input type="number" name="amount" min="0.01" step="0.01" placeholder="0,00" required>
                    </label>
                </div>

                <label>Data
                    <input type="date" name="date" value="<?= e($defaultDate) ?>" required>
                </label>

                <div class="receipt-upload-field" id="receipt-upload-field">
                    <label>Nota / comprovante <span>opcional</span>
                        <input
                            type="file"
                            name="receipt_files[]"
                            accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf,.jpg,.jpeg,.png,.webp,.heic,.heif,.pdf"
                            multiple
                            <?= $receiptsReady ? '' : 'disabled' ?>
                        >
                    </label>
                    <small>Até 5 arquivos por gasto. JPG, PNG, WEBP, HEIC ou PDF, com até 8 MB cada.</small>
                </div>

                <button class="ref-green-button movement-save-button" type="submit">Salvar lançamento</button>
            </form>
        </article>

        <article class="card daily-spending-card">
            <div class="movement-card-heading">
                <div>
                    <p class="eyebrow">GASTOS POR DIA</p>
                    <h2>Quanto gastamos em cada dia</h2>
                    <p>Compras e despesas registradas em <?= e($monthLabel) ?>.</p>
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
                <h2>Movimentações do mês</h2>
            </div>
            <span><?= count($data['transactions']) ?> lançamento(s)</span>
        </header>

        <div class="movement-history-list">
        <?php if ($data['transactions']): ?>
            <?php foreach ($data['transactions'] as $transaction): ?>
                <?php
                $transactionId = (int) $transaction['id'];
                $transactionReceipts = $receiptsByTransaction[$transactionId] ?? [];
                ?>
                <article class="movement-history-row">
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

                        <?php if (!empty($transaction['bill_payment_id'])): ?>
                            <span class="auto-transaction-badge">Automática</span>
                        <?php else: ?>
                            <form method="post" action="/acao" onsubmit="return confirm('Remover este lançamento?')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="return_to" value="/movimentacoes">
                                <input type="hidden" name="action" value="delete_transaction">
                                <input type="hidden" name="month" value="<?= e($month) ?>">
                                <input type="hidden" name="transaction_id" value="<?= $transactionId ?>">
                                <button class="movement-delete-button" type="submit">Excluir</button>
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
        <span>Família Almeida&nbsp;&nbsp; / &nbsp;&nbsp;Cada comprovante conta uma parte da história do mês.</span>
        <span><?= e(ucfirst($monthLabel)) ?></span>
    </footer>
</div>
</main>
</div>

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
const transactionType = document.getElementById('transaction-type');
const receiptUploadField = document.getElementById('receipt-upload-field');

function syncReceiptField() {
    receiptUploadField.hidden = transactionType.value !== 'expense';
}

transactionType.addEventListener('change', syncReceiptField);
syncReceiptField();

function openReceiptDialog(transactionId, description) {
    document.getElementById('receipt-transaction-id').value = transactionId;
    document.getElementById('receipt-dialog-description').textContent = 'Anexar comprovante a: ' + description;
    document.getElementById('receipt-dialog').showModal();
}
</script>
</body>
</html>
