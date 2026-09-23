<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/finance.php';
require_once __DIR__ . '/includes/receipts.php';

$user = require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

verify_csrf();

$pdo = db();
$action = (string) ($_POST['action'] ?? '');
$month = valid_month($_POST['month'] ?? null);
$returnTo = (string) ($_POST['return_to'] ?? '/');
$allowedReturns = ['/', '/movimentacoes', '/contas', '/metas', '/dividas'];
if (!in_array($returnTo, $allowedReturns, true)) {
    $returnTo = '/';
}
$returnTab = (string) ($_POST['return_tab'] ?? '');
if ($returnTo === '/metas' && in_array($returnTab, ['gastos', 'investimento'], true)) {
    $redirect = '/metas?month=' . rawurlencode($month) . '&tab=' . rawurlencode($returnTab);
} else {
    $redirect = $returnTo . '?month=' . rawurlencode($month);
}

try {
    switch ($action) {
        case 'add_transaction':
            $type = (string) ($_POST['type'] ?? '');
            $description = trim((string) ($_POST['description'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
            $date = (string) ($_POST['date'] ?? '');
            $receiptUploads = prepare_receipt_uploads($_FILES['receipt_files'] ?? null);

            if (!in_array($type, ['income', 'expense', 'investment'], true)) {
                throw new RuntimeException('Tipo de lançamento inválido.');
            }
            if ($description === '' || $category === '' || $amount <= 0 || !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) {
                throw new RuntimeException('Preencha corretamente os dados do lançamento.');
            }
            if ($receiptUploads && $type !== 'expense') {
                throw new RuntimeException('Notas e comprovantes podem ser anexados somente a gastos.');
            }
            if ($receiptUploads && !receipts_schema_ready($pdo)) {
                throw new RuntimeException('Existe uma migration pendente para anexar notas. Execute em Configurações > Manutenção.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO transactions (created_by, type, description, category, amount, occurred_on)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([(int) $user['id'], $type, $description, $category, $amount, $date]);

            $transactionId = (int) $pdo->lastInsertId();
            save_receipts_for_transaction($pdo, $transactionId, (int) $user['id'], $receiptUploads);

            $pdo->commit();
            flash('success', $receiptUploads ? 'Gasto e nota salvos.' : 'Lançamento salvo.');
            break;

        case 'add_receipts':
            $transactionId = (int) ($_POST['transaction_id'] ?? 0);
            $receiptUploads = prepare_receipt_uploads($_FILES['receipt_files'] ?? null);

            if ($transactionId <= 0 || !$receiptUploads) {
                throw new RuntimeException('Selecione pelo menos uma nota para anexar.');
            }
            if (!receipts_schema_ready($pdo)) {
                throw new RuntimeException('Existe uma migration pendente para anexar notas. Execute em Configurações > Manutenção.');
            }

            $stmt = $pdo->prepare('SELECT id, type FROM transactions WHERE id = ? LIMIT 1');
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch();

            if (!$transaction || $transaction['type'] !== 'expense') {
                throw new RuntimeException('Só é possível anexar notas a gastos.');
            }

            $pdo->beginTransaction();
            save_receipts_for_transaction($pdo, $transactionId, (int) $user['id'], $receiptUploads);
            $pdo->commit();

            flash('success', count($receiptUploads) === 1 ? 'Nota anexada.' : count($receiptUploads) . ' notas anexadas.');
            break;

        case 'delete_receipt':
            $receiptId = (int) ($_POST['receipt_id'] ?? 0);

            if ($receiptId <= 0 || !receipts_schema_ready($pdo)) {
                throw new RuntimeException('Comprovante inválido.');
            }

            $stmt = $pdo->prepare(
                'SELECT id, stored_name
                 FROM transaction_receipts
                 WHERE id = ?
                 LIMIT 1'
            );
            $stmt->execute([$receiptId]);
            $receipt = $stmt->fetch();

            if (!$receipt) {
                throw new RuntimeException('Comprovante não encontrado.');
            }

            $delete = $pdo->prepare('DELETE FROM transaction_receipts WHERE id = ?');
            $delete->execute([$receiptId]);
            delete_receipt_files([$receipt]);

            flash('success', 'Nota removida.');
            break;
        case 'add_bill':
            $name = trim((string) ($_POST['name'] ?? ''));
            $billingType = (string) ($_POST['billing_type'] ?? 'fixed');
            $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
            $dueDay = (int) ($_POST['due_day'] ?? 0);
            $startMonth = trim((string) ($_POST['start_month'] ?? ''));
            $installmentTotal = (int) ($_POST['installment_total'] ?? 0);

            if (!in_array($billingType, ['fixed', 'variable', 'installment'], true)) {
                throw new RuntimeException('Tipo de conta recorrente inválido.');
            }

            if ($name === '' || $dueDay < 1 || $dueDay > 31 || $amount < 0) {
                throw new RuntimeException('Dados da conta recorrente inválidos.');
            }

            if ($billingType !== 'variable' && $amount <= 0) {
                throw new RuntimeException('Informe o valor da conta.');
            }

            if ($billingType === 'installment') {
                if (!preg_match('/^\d{4}-\d{2}$/', $startMonth)) {
                    $startMonth = $month;
                }
                if ($installmentTotal < 1 || $installmentTotal > 360) {
                    throw new RuntimeException('Informe a quantidade total de parcelas.');
                }
            } else {
                $startMonth = '';
                $installmentTotal = 0;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO fixed_bills
                    (name, billing_type, amount, due_day, start_month, installment_total)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $name,
                $billingType,
                $amount,
                $dueDay,
                $startMonth !== '' ? $startMonth : null,
                $installmentTotal > 0 ? $installmentTotal : null,
            ]);

            flash('success', 'Conta recorrente adicionada.');
            break;

        case 'update_bill':
            $billId = (int) ($_POST['bill_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $billingType = (string) ($_POST['billing_type'] ?? 'fixed');
            $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
            $dueDay = (int) ($_POST['due_day'] ?? 0);
            $startMonth = trim((string) ($_POST['start_month'] ?? ''));
            $installmentTotal = (int) ($_POST['installment_total'] ?? 0);

            if ($billId <= 0 || !in_array($billingType, ['fixed', 'variable', 'installment'], true)) {
                throw new RuntimeException('Conta recorrente inválida.');
            }

            if ($name === '' || $dueDay < 1 || $dueDay > 31 || $amount < 0) {
                throw new RuntimeException('Dados da conta recorrente inválidos.');
            }

            if ($billingType !== 'variable' && $amount <= 0) {
                throw new RuntimeException('Informe o valor da conta.');
            }

            if ($billingType === 'installment') {
                if (!preg_match('/^\d{4}-\d{2}$/', $startMonth) || $installmentTotal < 1 || $installmentTotal > 360) {
                    throw new RuntimeException('Revise o mês inicial e a quantidade de parcelas.');
                }
            } else {
                $startMonth = '';
                $installmentTotal = 0;
            }

            $stmt = $pdo->prepare(
                'UPDATE fixed_bills
                 SET name = ?, billing_type = ?, amount = ?, due_day = ?, start_month = ?, installment_total = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $name,
                $billingType,
                $amount,
                $dueDay,
                $startMonth !== '' ? $startMonth : null,
                $installmentTotal > 0 ? $installmentTotal : null,
                $billId,
            ]);

            flash('success', 'Conta recorrente atualizada.');
            break;

        case 'archive_bill':
            $billId = (int) ($_POST['bill_id'] ?? 0);
            if ($billId <= 0) {
                throw new RuntimeException('Conta inválida.');
            }

            $stmt = $pdo->prepare('UPDATE fixed_bills SET active = 0 WHERE id = ?');
            $stmt->execute([$billId]);
            flash('success', 'Conta arquivada. O histórico foi preservado.');
            break;

        case 'restore_bill':
            $billId = (int) ($_POST['bill_id'] ?? 0);
            if ($billId <= 0) {
                throw new RuntimeException('Conta inválida.');
            }

            $stmt = $pdo->prepare('UPDATE fixed_bills SET active = 1 WHERE id = ?');
            $stmt->execute([$billId]);
            flash('success', 'Conta restaurada.');
            break;

        case 'delete_bill':
            $billId = (int) ($_POST['bill_id'] ?? 0);

            if ($billId <= 0) {
                throw new RuntimeException('Conta inválida.');
            }

            $pdo->beginTransaction();

            if (db_column_exists($pdo, 'transactions', 'bill_payment_id')) {
                $deleteTransactions = $pdo->prepare(
                    'DELETE t
                     FROM transactions t
                     INNER JOIN bill_payments p ON p.id = t.bill_payment_id
                     WHERE p.bill_id = ?'
                );
                $deleteTransactions->execute([$billId]);
            }

            $deletePayments = $pdo->prepare('DELETE FROM bill_payments WHERE bill_id = ?');
            $deletePayments->execute([$billId]);

            $deleteBill = $pdo->prepare('DELETE FROM fixed_bills WHERE id = ?');
            $deleteBill->execute([$billId]);

            if ($deleteBill->rowCount() === 0) {
                throw new RuntimeException('Conta não encontrada.');
            }

            $pdo->commit();
            flash('success', 'Conta excluída com seus pagamentos vinculados.');
            break;

        case 'set_bill_month_amount':
            $billId = (int) ($_POST['bill_id'] ?? 0);
            $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));

            if ($billId <= 0 || $amount <= 0) {
                throw new RuntimeException('Informe um valor válido para o mês.');
            }

            $billStmt = $pdo->prepare(
                'SELECT id, name, billing_type, amount, due_day, start_month, installment_total
                 FROM fixed_bills WHERE id = ? LIMIT 1'
            );
            $billStmt->execute([$billId]);
            $bill = $billStmt->fetch();

            if (!$bill) {
                throw new RuntimeException('Conta recorrente não encontrada.');
            }

            $installmentNumber = null;
            if ($bill['billing_type'] === 'installment') {
                $distance = bill_month_distance((string) $bill['start_month'], $month);
                $total = (int) $bill['installment_total'];
                if ($distance < 0 || $distance >= $total) {
                    throw new RuntimeException('Esta parcela não pertence ao mês selecionado.');
                }
                $installmentNumber = $distance + 1;
            }

            $pdo->beginTransaction();

            $paymentStmt = $pdo->prepare(
                'INSERT INTO bill_payments (bill_id, month, amount_due, installment_number, paid)
                 VALUES (?, ?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE
                    amount_due = VALUES(amount_due),
                    installment_number = VALUES(installment_number)'
            );
            $paymentStmt->execute([$billId, $month, $amount, $installmentNumber]);

            $paymentIdStmt = $pdo->prepare('SELECT id, paid FROM bill_payments WHERE bill_id = ? AND month = ? LIMIT 1');
            $paymentIdStmt->execute([$billId, $month]);
            $payment = $paymentIdStmt->fetch();

            if ($payment && (bool) $payment['paid']) {
                $description = $bill['billing_type'] === 'installment'
                    ? $bill['name'] . ' — Parcela ' . $installmentNumber . '/' . (int) $bill['installment_total']
                    : $bill['name'];

                $updateTransaction = $pdo->prepare(
                    'UPDATE transactions
                     SET amount = ?, description = ?, category = "Contas recorrentes"
                     WHERE bill_payment_id = ?'
                );
                $updateTransaction->execute([$amount, $description, (int) $payment['id']]);
            }

            $pdo->commit();
            flash('success', 'Valor de ' . $month . ' atualizado.');
            break;

        case 'toggle_bill':
            $billId = (int) ($_POST['bill_id'] ?? 0);
            $paid = isset($_POST['paid']) && $_POST['paid'] === '1' ? 1 : 0;

            if ($billId <= 0) {
                throw new RuntimeException('Conta recorrente inválida.');
            }

            $columnStmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'bill_payment_id'");
            $amountColumnStmt = $pdo->query("SHOW COLUMNS FROM bill_payments LIKE 'amount_due'");
            if (!$columnStmt || !$columnStmt->fetch() || !$amountColumnStmt || !$amountColumnStmt->fetch()) {
                throw new RuntimeException('Existem migrations pendentes para contas recorrentes. Execute em Configurações > Manutenção.');
            }

            $pdo->beginTransaction();

            $billStmt = $pdo->prepare(
                'SELECT id, name, billing_type, amount, due_day, start_month, installment_total
                 FROM fixed_bills WHERE id = ? FOR UPDATE'
            );
            $billStmt->execute([$billId]);
            $bill = $billStmt->fetch();

            if (!$bill) {
                throw new RuntimeException('Conta recorrente não encontrada.');
            }

            $installmentNumber = null;
            if ($bill['billing_type'] === 'installment') {
                $distance = bill_month_distance((string) $bill['start_month'], $month);
                $total = (int) $bill['installment_total'];

                if ($distance < 0 || $distance >= $total) {
                    throw new RuntimeException('Esta parcela não pertence ao mês selecionado.');
                }

                $installmentNumber = $distance + 1;
            }

            $existingStmt = $pdo->prepare(
                'SELECT id, amount_due
                 FROM bill_payments
                 WHERE bill_id = ? AND month = ?
                 LIMIT 1'
            );
            $existingStmt->execute([$billId, $month]);
            $existingPayment = $existingStmt->fetch();

            $monthlyAmount = $existingPayment && $existingPayment['amount_due'] !== null
                ? (float) $existingPayment['amount_due']
                : (float) $bill['amount'];

            if ($bill['billing_type'] === 'variable' && (!$existingPayment || $existingPayment['amount_due'] === null)) {
                throw new RuntimeException('Informe o valor desta conta no mês antes de marcá-la como paga.');
            }

            if ($monthlyAmount <= 0) {
                throw new RuntimeException('O valor da conta no mês precisa ser maior que zero.');
            }

            $monthStart = new DateTimeImmutable($month . '-01');
            $lastDay = (int) $monthStart->format('t');
            $dueDay = min((int) $bill['due_day'], $lastDay);
            $paymentDate = $month === date('Y-m')
                ? date('Y-m-d')
                : sprintf('%s-%02d', $month, $dueDay);

            $paymentStmt = $pdo->prepare(
                'INSERT INTO bill_payments
                    (bill_id, month, amount_due, installment_number, paid, paid_at, paid_on)
                 VALUES (?, ?, ?, ?, ?, IF(? = 1, NOW(), NULL), IF(? = 1, ?, NULL))
                 ON DUPLICATE KEY UPDATE
                    amount_due = VALUES(amount_due),
                    installment_number = VALUES(installment_number),
                    paid = VALUES(paid),
                    paid_at = VALUES(paid_at),
                    paid_on = VALUES(paid_on)'
            );
            $paymentStmt->execute([
                $billId,
                $month,
                $monthlyAmount,
                $installmentNumber,
                $paid,
                $paid,
                $paid,
                $paymentDate,
            ]);

            $paymentIdStmt = $pdo->prepare('SELECT id FROM bill_payments WHERE bill_id = ? AND month = ? LIMIT 1');
            $paymentIdStmt->execute([$billId, $month]);
            $paymentId = (int) $paymentIdStmt->fetchColumn();

            if ($paid === 1) {
                $description = $bill['billing_type'] === 'installment'
                    ? $bill['name'] . ' — Parcela ' . $installmentNumber . '/' . (int) $bill['installment_total']
                    : $bill['name'];

                $transactionStmt = $pdo->prepare(
                    'INSERT INTO transactions
                        (created_by, bill_payment_id, type, description, category, amount, occurred_on)
                     VALUES (?, ?, "expense", ?, "Contas recorrentes", ?, ?)
                     ON DUPLICATE KEY UPDATE
                        description = VALUES(description),
                        category = VALUES(category),
                        amount = VALUES(amount),
                        occurred_on = VALUES(occurred_on)'
                );
                $transactionStmt->execute([
                    (int) $user['id'],
                    $paymentId,
                    $description,
                    $monthlyAmount,
                    $paymentDate,
                ]);

                flash('success', 'Conta paga e lançada automaticamente nas movimentações.');
            } else {
                $deleteStmt = $pdo->prepare('DELETE FROM transactions WHERE bill_payment_id = ?');
                $deleteStmt->execute([$paymentId]);
                flash('success', 'Pagamento desmarcado e movimentação removida.');
            }

            $pdo->commit();
            break;

        case 'add_goal':
            $category = trim((string) ($_POST['category'] ?? ''));
            $limit = (float) str_replace(',', '.', (string) ($_POST['limit'] ?? '0'));

            if ($category === '' || $limit < 0) {
                throw new RuntimeException('Meta inválida.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO spending_goals (category, monthly_limit)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE monthly_limit = VALUES(monthly_limit)'
            );
            $stmt->execute([$category, $limit]);
            flash('success', 'Meta salva.');
            break;

        case 'update_goal':
            $goalId = (int) ($_POST['goal_id'] ?? 0);
            $category = trim((string) ($_POST['category'] ?? ''));
            $limit = (float) str_replace(',', '.', (string) ($_POST['limit'] ?? '0'));

            if ($goalId <= 0 || $category === '' || $limit < 0) {
                throw new RuntimeException('Meta inválida.');
            }

            $stmt = $pdo->prepare('UPDATE spending_goals SET category = ?, monthly_limit = ? WHERE id = ?');
            $stmt->execute([$category, $limit, $goalId]);
            flash('success', 'Meta atualizada.');
            break;

        case 'delete_goal':
            $goalId = (int) ($_POST['goal_id'] ?? 0);

            if ($goalId <= 0) {
                throw new RuntimeException('Meta inválida.');
            }

            $stmt = $pdo->prepare('DELETE FROM spending_goals WHERE id = ?');
            $stmt->execute([$goalId]);
            flash('success', 'Meta removida.');
            break;

        case 'add_debt':
            $name = trim((string) ($_POST['name'] ?? ''));
            $total = (float) str_replace(',', '.', (string) ($_POST['total'] ?? '0'));
            $paid = (float) str_replace(',', '.', (string) ($_POST['paid'] ?? '0'));

            if ($name === '' || $total <= 0 || $paid < 0 || $paid > $total) {
                throw new RuntimeException('Dados da dívida inválidos.');
            }

            $stmt = $pdo->prepare('INSERT INTO debts (name, total_amount, paid_amount) VALUES (?, ?, ?)');
            $stmt->execute([$name, $total, $paid]);
            flash('success', 'Dívida adicionada.');
            break;

        case 'pay_debt':
            $debtId = (int) ($_POST['debt_id'] ?? 0);
            $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
            $date = (string) ($_POST['date'] ?? '');

            if ($debtId <= 0 || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new RuntimeException('Pagamento inválido.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT id, name, total_amount, paid_amount FROM debts WHERE id = ? FOR UPDATE');
            $stmt->execute([$debtId]);
            $debt = $stmt->fetch();

            if (!$debt) {
                throw new RuntimeException('Dívida não encontrada.');
            }

            $remaining = (float) $debt['total_amount'] - (float) $debt['paid_amount'];
            if ($amount > $remaining + 0.0001) {
                throw new RuntimeException('O pagamento não pode ser maior que o saldo da dívida.');
            }

            $stmt = $pdo->prepare('UPDATE debts SET paid_amount = paid_amount + ? WHERE id = ?');
            $stmt->execute([$amount, $debtId]);

            $stmt = $pdo->prepare(
                'INSERT INTO transactions (created_by, debt_id, type, description, category, amount, occurred_on)
                 VALUES (?, ?, "debt", ?, "Dívidas", ?, ?)'
            );
            $stmt->execute([(int) $user['id'], $debtId, 'Pagamento: ' . $debt['name'], $amount, $date]);

            $pdo->commit();
            flash('success', 'Pagamento registrado.');
            break;

        case 'set_investment_goal':
            $value = (float) str_replace(',', '.', (string) ($_POST['value'] ?? '0'));
            if ($value < 0) {
                throw new RuntimeException('Meta de investimento inválida.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO settings (setting_key, value)
                 VALUES ("investment_goal", ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)'
            );
            $stmt->execute([(string) $value]);
            flash('success', 'Meta de investimento atualizada.');
            break;

        case 'delete_transaction':
            $transactionId = (int) ($_POST['transaction_id'] ?? 0);
            if ($transactionId <= 0) {
                throw new RuntimeException('Lançamento inválido.');
            }

            $receiptFiles = receipt_files_for_transaction($pdo, $transactionId);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, type, amount, debt_id, bill_payment_id FROM transactions WHERE id = ? FOR UPDATE');
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch();

            if (!$transaction) {
                throw new RuntimeException('Lançamento não encontrado.');
            }

            if (!empty($transaction['bill_payment_id'])) {
                throw new RuntimeException('Esta movimentação foi gerada por uma conta recorrente. Desmarque o pagamento em Contas recorrentes para removê-la.');
            }

            if ($transaction['type'] === 'debt' && !empty($transaction['debt_id'])) {
                $stmt = $pdo->prepare(
                    'UPDATE debts SET paid_amount = GREATEST(0, paid_amount - ?) WHERE id = ?'
                );
                $stmt->execute([(float) $transaction['amount'], (int) $transaction['debt_id']]);
            }

            $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ?');
            $stmt->execute([$transactionId]);
            $pdo->commit();
            delete_receipt_files($receiptFiles);
            flash('success', 'Lançamento removido.');
            break;

        case 'delete_debt':
            $debtId = (int) ($_POST['debt_id'] ?? 0);
            if ($debtId <= 0) {
                throw new RuntimeException('Dívida inválida.');
            }

            $stmt = $pdo->prepare('DELETE FROM debts WHERE id = ?');
            $stmt->execute([$debtId]);
            flash('success', 'Dívida removida.');
            break;

        default:
            throw new RuntimeException('Ação inválida.');
    }
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash('error', $exception->getMessage());
}

redirect_to($redirect);
