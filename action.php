<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

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
$redirect = $returnTo . '?month=' . rawurlencode($month);

try {
    switch ($action) {
        case 'add_transaction':
            $type = (string) ($_POST['type'] ?? '');
            $description = trim((string) ($_POST['description'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
            $date = (string) ($_POST['date'] ?? '');

            if (!in_array($type, ['income', 'expense', 'investment'], true)) {
                throw new RuntimeException('Tipo de lançamento inválido.');
            }
            if ($description === '' || $category === '' || $amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new RuntimeException('Preencha corretamente os dados do lançamento.');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO transactions (created_by, type, description, category, amount, occurred_on)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([(int) $user['id'], $type, $description, $category, $amount, $date]);
            flash('success', 'Lançamento salvo.');
            break;

        case 'add_bill':
            $name = trim((string) ($_POST['name'] ?? ''));
            $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
            $dueDay = (int) ($_POST['due_day'] ?? 0);

            if ($name === '' || $amount < 0 || $dueDay < 1 || $dueDay > 31) {
                throw new RuntimeException('Dados da conta fixa inválidos.');
            }

            $stmt = $pdo->prepare('INSERT INTO fixed_bills (name, amount, due_day) VALUES (?, ?, ?)');
            $stmt->execute([$name, $amount, $dueDay]);
            flash('success', 'Conta fixa adicionada.');
            break;

        case 'update_bill':
            $billId = (int) ($_POST['bill_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $amount = (float) str_replace(',', '.', (string) ($_POST['amount'] ?? '0'));
            $dueDay = (int) ($_POST['due_day'] ?? 0);

            if ($billId <= 0 || $name === '' || $amount < 0 || $dueDay < 1 || $dueDay > 31) {
                throw new RuntimeException('Dados da conta fixa inválidos.');
            }

            $stmt = $pdo->prepare('UPDATE fixed_bills SET name = ?, amount = ?, due_day = ? WHERE id = ?');
            $stmt->execute([$name, $amount, $dueDay, $billId]);
            flash('success', 'Conta fixa atualizada.');
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

        case 'toggle_bill':
            $billId = (int) ($_POST['bill_id'] ?? 0);
            $paid = isset($_POST['paid']) && $_POST['paid'] === '1' ? 1 : 0;

            if ($billId <= 0) {
                throw new RuntimeException('Conta inválida.');
            }

            $columnStmt = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'bill_payment_id'");
            if (!$columnStmt || !$columnStmt->fetch()) {
                throw new RuntimeException('Existe uma migration pendente para contas fixas. Execute em Configurações > Manutenção antes de alterar o pagamento.');
            }

            $pdo->beginTransaction();

            $billStmt = $pdo->prepare('SELECT id, name, amount, due_day FROM fixed_bills WHERE id = ? FOR UPDATE');
            $billStmt->execute([$billId]);
            $bill = $billStmt->fetch();

            if (!$bill) {
                throw new RuntimeException('Conta fixa não encontrada.');
            }

            $monthStart = new DateTimeImmutable($month . '-01');
            $lastDay = (int) $monthStart->format('t');
            $dueDay = min((int) $bill['due_day'], $lastDay);
            $paymentDate = $month === date('Y-m')
                ? date('Y-m-d')
                : sprintf('%s-%02d', $month, $dueDay);

            $paymentStmt = $pdo->prepare(
                'INSERT INTO bill_payments (bill_id, month, paid, paid_at, paid_on)
                 VALUES (?, ?, ?, IF(? = 1, NOW(), NULL), IF(? = 1, ?, NULL))
                 ON DUPLICATE KEY UPDATE
                    paid = VALUES(paid),
                    paid_at = VALUES(paid_at),
                    paid_on = VALUES(paid_on)'
            );
            $paymentStmt->execute([$billId, $month, $paid, $paid, $paid, $paymentDate]);

            $paymentIdStmt = $pdo->prepare('SELECT id FROM bill_payments WHERE bill_id = ? AND month = ? LIMIT 1');
            $paymentIdStmt->execute([$billId, $month]);
            $paymentId = (int) $paymentIdStmt->fetchColumn();

            if ($paid === 1) {
                $transactionStmt = $pdo->prepare(
                    'INSERT INTO transactions
                        (created_by, bill_payment_id, type, description, category, amount, occurred_on)
                     VALUES (?, ?, "expense", ?, "Contas fixas", ?, ?)
                     ON DUPLICATE KEY UPDATE
                        description = VALUES(description),
                        category = VALUES(category),
                        amount = VALUES(amount),
                        occurred_on = VALUES(occurred_on)'
                );
                $transactionStmt->execute([
                    (int) $user['id'],
                    $paymentId,
                    $bill['name'],
                    (float) $bill['amount'],
                    $paymentDate,
                ]);
                flash('success', 'Conta marcada como paga e lançada nas movimentações.');
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

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id, type, amount, debt_id, bill_payment_id FROM transactions WHERE id = ? FOR UPDATE');
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch();

            if (!$transaction) {
                throw new RuntimeException('Lançamento não encontrado.');
            }

            if (!empty($transaction['bill_payment_id'])) {
                throw new RuntimeException('Esta movimentação foi gerada por uma conta fixa. Desmarque o pagamento em Contas fixas para removê-la.');
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
