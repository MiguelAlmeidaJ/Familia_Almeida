<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/credit_cards.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

verify_csrf();
$pdo = db();
$month = valid_month($_POST['month'] ?? null);
$redirect = '/cartoes?month=' . rawurlencode($month);

try {
    if (!credit_cards_schema_ready($pdo)) {
        throw new RuntimeException('Execute a migration de cartões em Configurações > Manutenção.');
    }

    $action = (string) ($_POST['action'] ?? '');

    switch ($action) {
        case 'add_credit_card':
            $name = trim((string) ($_POST['name'] ?? ''));
            $holder = trim((string) ($_POST['holder_name'] ?? ''));
            $lastFour = preg_replace('/\D+/', '', (string) ($_POST['last_four'] ?? ''));
            $limit = (float) str_replace(',', '.', (string) ($_POST['limit_amount'] ?? '0'));
            $closingDay = (int) ($_POST['closing_day'] ?? 0);
            $dueDay = (int) ($_POST['due_day'] ?? 0);

            if ($name === '' || $limit < 0 || $closingDay < 1 || $closingDay > 31 || $dueDay < 1 || $dueDay > 31) {
                throw new RuntimeException('Revise os dados do cartão.');
            }
            if ($lastFour !== '' && strlen($lastFour) !== 4) {
                throw new RuntimeException('Os últimos dígitos devem ter exatamente 4 números.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO credit_cards (name, holder_name, last_four, limit_amount, closing_day, due_day)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $holder ?: null, $lastFour ?: null, $limit, $closingDay, $dueDay]);
            flash('success', 'Cartão cadastrado.');
            break;

        case 'update_credit_card':
            $cardId = (int) ($_POST['card_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $holder = trim((string) ($_POST['holder_name'] ?? ''));
            $lastFour = preg_replace('/\D+/', '', (string) ($_POST['last_four'] ?? ''));
            $limit = (float) str_replace(',', '.', (string) ($_POST['limit_amount'] ?? '0'));
            $closingDay = (int) ($_POST['closing_day'] ?? 0);
            $dueDay = (int) ($_POST['due_day'] ?? 0);

            if ($cardId <= 0 || $name === '' || $limit < 0 || $closingDay < 1 || $closingDay > 31 || $dueDay < 1 || $dueDay > 31) {
                throw new RuntimeException('Revise os dados do cartão.');
            }
            if ($lastFour !== '' && strlen($lastFour) !== 4) {
                throw new RuntimeException('Os últimos dígitos devem ter exatamente 4 números.');
            }

            $stmt = $pdo->prepare(
                'UPDATE credit_cards SET name=?, holder_name=?, last_four=?, limit_amount=?, closing_day=?, due_day=? WHERE id=?'
            );
            $stmt->execute([$name, $holder ?: null, $lastFour ?: null, $limit, $closingDay, $dueDay, $cardId]);
            flash('success', 'Cartão atualizado.');
            break;

        case 'archive_credit_card':
        case 'restore_credit_card':
            $cardId = (int) ($_POST['card_id'] ?? 0);
            if ($cardId <= 0) throw new RuntimeException('Cartão inválido.');
            $active = $action === 'restore_credit_card' ? 1 : 0;
            $stmt = $pdo->prepare('UPDATE credit_cards SET active = ? WHERE id = ?');
            $stmt->execute([$active, $cardId]);
            flash('success', $active ? 'Cartão restaurado.' : 'Cartão arquivado. O histórico foi preservado.');
            break;

        case 'add_credit_purchase':
            $cardId = (int) ($_POST['card_id'] ?? 0);
            $description = trim((string) ($_POST['description'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
            $installments = (int) ($_POST['installments'] ?? 1);
            $purchaseDate = (string) ($_POST['purchase_date'] ?? '');

            if ($cardId <= 0 || $description === '' || $category === '' || $amount <= 0 || $installments < 1 || $installments > 60 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
                throw new RuntimeException('Revise os dados da compra.');
            }

            $cardStmt = $pdo->prepare('SELECT * FROM credit_cards WHERE id = ? AND active = 1 LIMIT 1');
            $cardStmt->execute([$cardId]);
            $card = $cardStmt->fetch();
            if (!$card) throw new RuntimeException('Cartão não encontrado ou arquivado.');

            $firstMonth = credit_card_first_invoice_month($purchaseDate, (int) $card['closing_day']);
            $baseAmount = floor(($amount / $installments) * 100) / 100;
            $remaining = round($amount - ($baseAmount * $installments), 2);

            $pdo->beginTransaction();
            $purchase = $pdo->prepare(
                'INSERT INTO credit_card_purchases
                    (card_id, created_by, description, category, total_amount, installments, purchase_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $purchase->execute([$cardId, (int) $user['id'], $description, $category, $amount, $installments, $purchaseDate]);
            $purchaseId = (int) $pdo->lastInsertId();

            $install = $pdo->prepare(
                'INSERT INTO credit_card_installments
                    (purchase_id, card_id, installment_number, installment_total, amount, invoice_month)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            for ($n = 1; $n <= $installments; $n++) {
                $part = $baseAmount;
                if ($n === $installments) $part = round($baseAmount + $remaining, 2);
                $install->execute([$purchaseId, $cardId, $n, $installments, $part, credit_card_add_months($firstMonth, $n - 1)]);
            }

            $pdo->commit();
            flash('success', $installments > 1 ? 'Compra parcelada distribuída nas próximas faturas.' : 'Compra adicionada à fatura.');
            break;

        case 'delete_credit_purchase':
            $purchaseId = (int) ($_POST['purchase_id'] ?? 0);
            if ($purchaseId <= 0) throw new RuntimeException('Compra inválida.');

            $paidCheck = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM credit_card_installments i
                 INNER JOIN credit_card_invoice_payments p ON p.card_id=i.card_id AND p.month=i.invoice_month
                 WHERE i.purchase_id=?'
            );
            $paidCheck->execute([$purchaseId]);
            if ((int) $paidCheck->fetchColumn() > 0) {
                throw new RuntimeException('Não é possível excluir uma compra que já participa de uma fatura paga.');
            }

            $stmt = $pdo->prepare('DELETE FROM credit_card_purchases WHERE id = ?');
            $stmt->execute([$purchaseId]);
            flash('success', 'Compra removida das faturas.');
            break;

        case 'toggle_credit_invoice':
            $cardId = (int) ($_POST['card_id'] ?? 0);
            $paid = (string) ($_POST['paid'] ?? '0') === '1';

            $cardStmt = $pdo->prepare('SELECT * FROM credit_cards WHERE id = ? LIMIT 1');
            $cardStmt->execute([$cardId]);
            $card = $cardStmt->fetch();
            if (!$card) throw new RuntimeException('Cartão não encontrado.');

            $total = credit_card_invoice_total($pdo, $cardId, $month);
            if ($total <= 0) throw new RuntimeException('Esta fatura não possui lançamentos.');

            $pdo->beginTransaction();
            $existing = credit_card_payment($pdo, $cardId, $month);

            if ($paid) {
                if ($existing) {
                    $pdo->commit();
                    flash('success', 'A fatura já estava marcada como paga.');
                    break;
                }

                $paidOn = credit_card_due_date($month, (int) $card['due_day']);
                if ($month === date('Y-m')) $paidOn = date('Y-m-d');

                $transaction = $pdo->prepare(
                    'INSERT INTO transactions (created_by, type, description, category, amount, occurred_on)
                     VALUES (?, "expense", ?, "Cartão de crédito", ?, ?)'
                );
                $transaction->execute([(int) $user['id'], 'Fatura ' . $card['name'] . ' — ' . credit_card_month_label($month), $total, $paidOn]);
                $transactionId = (int) $pdo->lastInsertId();

                $payment = $pdo->prepare(
                    'INSERT INTO credit_card_invoice_payments (card_id, month, amount, paid_on, transaction_id)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $payment->execute([$cardId, $month, $total, $paidOn, $transactionId]);
                flash('success', 'Fatura paga e lançada como uma saída nas movimentações.');
            } else {
                if ($existing) {
                    if (!empty($existing['transaction_id'])) {
                        $deleteTransaction = $pdo->prepare('DELETE FROM transactions WHERE id = ?');
                        $deleteTransaction->execute([(int) $existing['transaction_id']]);
                    }
                    $deletePayment = $pdo->prepare('DELETE FROM credit_card_invoice_payments WHERE id = ?');
                    $deletePayment->execute([(int) $existing['id']]);
                }
                flash('success', 'Pagamento da fatura desmarcado e saída removida.');
            }

            $pdo->commit();
            break;

        default:
            throw new RuntimeException('Ação de cartão inválida.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', $e->getMessage());
}

header('Location: ' . $redirect);
exit;
