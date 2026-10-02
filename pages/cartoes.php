<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/finance.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/credit_cards.php';

$user = require_auth();
$pdo = db();
$month = valid_month($_GET['month'] ?? null);
$csrf = csrf_token();
$flash = pull_flash();
$current = new DateTimeImmutable($month . '-01');
$prevMonth = $current->modify('-1 month')->format('Y-m');
$nextMonth = $current->modify('+1 month')->format('Y-m');
$schemaReady = credit_cards_schema_ready($pdo);
$cards = $schemaReady ? credit_cards_all($pdo, true) : [];
$activeCards = array_values(array_filter($cards, fn($card) => (bool) $card['active']));
$defaultDate = $month === date('Y-m') ? date('Y-m-d') : $month . '-01';

$selectedCardId = (int) ($_GET['card'] ?? 0);
if ($selectedCardId <= 0 && $activeCards) $selectedCardId = (int) $activeCards[0]['id'];
$selectedCard = null;
foreach ($cards as $card) if ((int) $card['id'] === $selectedCardId) $selectedCard = $card;

$invoice = ($schemaReady && $selectedCard) ? credit_card_invoice($pdo, $selectedCardId, $month) : [];
$invoiceTotal = ($schemaReady && $selectedCard) ? credit_card_invoice_total($pdo, $selectedCardId, $month) : 0.0;
$payment = ($schemaReady && $selectedCard) ? credit_card_payment($pdo, $selectedCardId, $month) : null;
$openCommitment = ($schemaReady && $selectedCard) ? credit_card_open_commitment($pdo, $selectedCardId) : 0.0;
$limit = $selectedCard ? (float) $selectedCard['limit_amount'] : 0.0;
$available = max(0, $limit - $openCommitment);
$usage = $limit > 0 ? min(100, ($openCommitment / $limit) * 100) : 0;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Cartões de crédito • Família Almeida</title>
    <link rel="stylesheet" href="/assets/style.css">
    <link rel="stylesheet" href="/assets/credit-cards.css">
</head>
<body>
<div class="shell ref-shell">
<?php render_sidebar('cartoes', $csrf); ?>
<main>
<?php render_topbar($user); ?>

<div class="content ref-dashboard credit-page">
    <section class="ref-dashboard-heading credit-heading">
        <div>
            <p class="eyebrow">FATURAS E LIMITES</p>
            <h1>Cartões de crédito</h1>
            <p>Acompanhe o que já foi comprado, as parcelas futuras e quanto cada cartão ainda tem disponível.</p>
        </div>
        <div class="ref-month-picker">
            <a href="?month=<?= e($prevMonth) ?>&card=<?= $selectedCardId ?>" aria-label="Mês anterior">‹</a>
            <span><?= e(credit_card_month_label($month)) ?></span>
            <a href="?month=<?= e($nextMonth) ?>&card=<?= $selectedCardId ?>" aria-label="Próximo mês">›</a>
        </div>
    </section>

    <?php if ($flash): ?>
        <div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if (!$schemaReady): ?>
        <div class="alert migration-alert">Há uma atualização pendente para habilitar cartões de crédito. <a href="/configuracoes/manutencao">Executar migration →</a></div>
    <?php else: ?>

    <section class="credit-card-selector">
        <?php foreach ($activeCards as $card): ?>
            <?php
            $cardOpen = credit_card_open_commitment($pdo, (int) $card['id']);
            $cardLimit = (float) $card['limit_amount'];
            ?>
            <a class="credit-mini-card <?= (int)$card['id'] === $selectedCardId ? 'active' : '' ?>" href="?month=<?= e($month) ?>&card=<?= (int)$card['id'] ?>">
                <span><?= e($card['name']) ?><?= $card['last_four'] ? ' •••• ' . e($card['last_four']) : '' ?></span>
                <strong><?= money($cardOpen) ?></strong>
                <small><?= $cardLimit > 0 ? money(max(0,$cardLimit-$cardOpen)) . ' disponível' : 'sem limite definido' ?></small>
            </a>
        <?php endforeach; ?>
        <button class="credit-add-card-button" type="button" onclick="document.getElementById('card-dialog').showModal()">＋ Novo cartão</button>
    </section>

    <?php if ($selectedCard): ?>
    <section class="credit-summary-grid">
        <article><span>Fatura de <?= e(credit_card_month_label($month)) ?></span><strong><?= money($invoiceTotal) ?></strong><small><?= count($invoice) ?> parcela(s) nesta fatura</small></article>
        <article><span>Comprometido no cartão</span><strong><?= money($openCommitment) ?></strong><small>faturas ainda não pagas</small></article>
        <article><span>Limite disponível</span><strong><?= money($available) ?></strong><small>de <?= money($limit) ?></small></article>
        <article class="<?= $payment ? 'paid' : '' ?>"><span>Status da fatura</span><strong><?= $payment ? 'Paga' : 'Em aberto' ?></strong><small>vencimento dia <?= (int)$selectedCard['due_day'] ?></small></article>
    </section>

    <section class="card credit-limit-card">
        <div>
            <strong><?= e($selectedCard['name']) ?></strong>
            <span>Fecha dia <?= (int)$selectedCard['closing_day'] ?> • vence dia <?= (int)$selectedCard['due_day'] ?><?= $selectedCard['holder_name'] ? ' • ' . e($selectedCard['holder_name']) : '' ?></span>
        </div>
        <div class="credit-limit-progress"><span style="width:<?= $usage ?>%"></span></div>
        <small><?= number_format($usage,0,',','.') ?>% do limite comprometido</small>
        <button type="button" class="credit-edit-link" onclick="document.getElementById('edit-card-dialog').showModal()">Editar cartão</button>
    </section>

    <section class="credit-workspace">
        <article class="card credit-purchase-card">
            <p class="eyebrow">NOVA COMPRA</p>
            <h2>Adicionar à fatura</h2>
            <form method="post" action="/cartoes/acao" class="credit-form">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="add_credit_purchase">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <label>Cartão
                    <select name="card_id" required>
                        <?php foreach ($activeCards as $card): ?>
                            <option value="<?= (int)$card['id'] ?>" <?= (int)$card['id']===$selectedCardId?'selected':'' ?>><?= e($card['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Descrição
                    <input name="description" maxlength="160" placeholder="Ex.: Mercado, tênis, assinatura..." required>
                </label>
                <div class="credit-form-grid">
                    <label>Categoria<input name="category" maxlength="100" placeholder="Ex.: Mercado" required></label>
                    <label>Valor total (R$)<input type="number" name="amount" min="0.01" step="0.01" required></label>
                </div>
                <div class="credit-form-grid">
                    <label>Parcelas<input type="number" name="installments" min="1" max="60" value="1" required></label>
                    <label>Data da compra<input type="date" name="purchase_date" value="<?= e($defaultDate) ?>" required></label>
                </div>
                <button class="ref-green-button" type="submit">Adicionar compra</button>
                <small class="credit-form-note">A compra entra na fatura conforme o dia de fechamento. Parcelas futuras são distribuídas automaticamente.</small>
            </form>
        </article>

        <article class="card credit-invoice-card">
            <header>
                <div><p class="eyebrow">FATURA</p><h2><?= e(credit_card_month_label($month)) ?></h2></div>
                <form method="post" action="/cartoes/acao">
                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="toggle_credit_invoice">
                    <input type="hidden" name="month" value="<?= e($month) ?>">
                    <input type="hidden" name="card_id" value="<?= $selectedCardId ?>">
                    <input type="hidden" name="paid" value="<?= $payment ? '0' : '1' ?>">
                    <button class="<?= $payment ? 'credit-unpay-button' : 'ref-green-button' ?>" type="submit" <?= $invoiceTotal<=0?'disabled':'' ?>>
                        <?= $payment ? 'Desmarcar pagamento' : 'Marcar fatura como paga' ?>
                    </button>
                </form>
            </header>

            <div class="credit-invoice-list">
                <?php if ($invoice): ?>
                    <?php foreach ($invoice as $item): ?>
                        <div class="credit-invoice-row">
                            <div>
                                <strong><?= e($item['description']) ?></strong>
                                <span><?= e($item['category']) ?> • compra em <?= e(date('d/m/Y', strtotime($item['purchase_date']))) ?></span>
                            </div>
                            <div class="credit-installment"><?= (int)$item['installment_number'] ?>/<?= (int)$item['installment_total'] ?></div>
                            <strong><?= money((float)$item['amount']) ?></strong>
                            <?php if (!$payment): ?>
                            <form method="post" action="/cartoes/acao" onsubmit="return confirm('Remover esta compra e todas as parcelas futuras?')">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="delete_credit_purchase">
                                <input type="hidden" name="month" value="<?= e($month) ?>">
                                <input type="hidden" name="purchase_id" value="<?= (int)$item['purchase_id'] ?>">
                                <button class="credit-delete-button" type="submit">⌫</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="credit-empty"><strong>Nenhuma compra nesta fatura.</strong><span>Os lançamentos aparecerão aqui.</span></div>
                <?php endif; ?>
            </div>
            <footer><span>Total da fatura</span><strong><?= money($invoiceTotal) ?></strong></footer>
        </article>
    </section>
    <?php else: ?>
        <div class="card credit-first-state"><strong>Cadastre o primeiro cartão</strong><p>Depois você poderá lançar compras, parcelamentos e acompanhar cada fatura.</p><button class="ref-green-button" onclick="document.getElementById('card-dialog').showModal()">Cadastrar cartão</button></div>
    <?php endif; ?>

    <?php if (array_filter($cards, fn($c)=>!(bool)$c['active'])): ?>
    <details class="card credit-archived">
        <summary>Cartões arquivados</summary>
        <?php foreach ($cards as $card): if ((bool)$card['active']) continue; ?>
            <div><span><?= e($card['name']) ?></span><form method="post" action="/cartoes/acao"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="restore_credit_card"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="card_id" value="<?= (int)$card['id'] ?>"><button type="submit">Restaurar</button></form></div>
        <?php endforeach; ?>
    </details>
    <?php endif; ?>

    <dialog id="card-dialog" class="credit-dialog">
        <form method="post" action="/cartoes/acao">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="add_credit_card"><input type="hidden" name="month" value="<?= e($month) ?>">
            <header><h2>Novo cartão</h2><button type="button" onclick="this.closest('dialog').close()">×</button></header>
            <label>Nome do cartão<input name="name" placeholder="Ex.: Nubank" required></label>
            <label>Titular<input name="holder_name" placeholder="Opcional"></label>
            <div class="credit-form-grid"><label>Últimos 4 dígitos<input name="last_four" maxlength="4" inputmode="numeric"></label><label>Limite (R$)<input type="number" name="limit_amount" min="0" step="0.01" required></label></div>
            <div class="credit-form-grid"><label>Dia do fechamento<input type="number" name="closing_day" min="1" max="31" required></label><label>Dia do vencimento<input type="number" name="due_day" min="1" max="31" required></label></div>
            <button class="ref-green-button" type="submit">Salvar cartão</button>
        </form>
    </dialog>

    <?php if ($selectedCard): ?>
    <dialog id="edit-card-dialog" class="credit-dialog">
        <form method="post" action="/cartoes/acao">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="update_credit_card"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="card_id" value="<?= $selectedCardId ?>">
            <header><h2>Editar cartão</h2><button type="button" onclick="this.closest('dialog').close()">×</button></header>
            <label>Nome do cartão<input name="name" value="<?= e($selectedCard['name']) ?>" required></label>
            <label>Titular<input name="holder_name" value="<?= e((string)$selectedCard['holder_name']) ?>"></label>
            <div class="credit-form-grid"><label>Últimos 4 dígitos<input name="last_four" maxlength="4" value="<?= e((string)$selectedCard['last_four']) ?>"></label><label>Limite (R$)<input type="number" name="limit_amount" min="0" step="0.01" value="<?= e((string)$selectedCard['limit_amount']) ?>" required></label></div>
            <div class="credit-form-grid"><label>Dia do fechamento<input type="number" name="closing_day" min="1" max="31" value="<?= (int)$selectedCard['closing_day'] ?>" required></label><label>Dia do vencimento<input type="number" name="due_day" min="1" max="31" value="<?= (int)$selectedCard['due_day'] ?>" required></label></div>
            <button class="ref-green-button" type="submit">Salvar alterações</button>
        </form>
        <form method="post" action="/cartoes/acao" class="credit-archive-form" onsubmit="return confirm('Arquivar este cartão? O histórico será mantido.')">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="archive_credit_card"><input type="hidden" name="month" value="<?= e($month) ?>"><input type="hidden" name="card_id" value="<?= $selectedCardId ?>">
            <button type="submit">Arquivar cartão</button>
        </form>
    </dialog>
    <?php endif; ?>
    <?php endif; ?>
</div>
</main>
</div>
</body>
</html>
