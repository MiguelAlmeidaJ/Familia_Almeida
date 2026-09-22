<?php

declare(strict_types=1);

function fixed_bills_data(PDO $pdo, string $month, bool $includeInactive = false): array
{
    $sql =
        'SELECT b.id, b.name, b.amount, b.due_day, b.active, COALESCE(p.paid, 0) AS paid
         FROM fixed_bills b
         LEFT JOIN bill_payments p ON p.bill_id = b.id AND p.month = ?';

    if (!$includeInactive) {
        $sql .= ' WHERE b.active = 1';
    }

    $sql .= ' ORDER BY b.active DESC, b.due_day, b.name';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$month]);
    $bills = $stmt->fetchAll();

    foreach ($bills as &$bill) {
        $bill['amount'] = (float) $bill['amount'];
        $bill['paid'] = (bool) $bill['paid'];
        $bill['active'] = (bool) $bill['active'];
    }
    unset($bill);

    return $bills;
}

function dashboard_data(PDO $pdo, string $month): array
{
    $start = $month . '-01';
    $next = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');

    $stmt = $pdo->prepare(
        'SELECT t.id, t.type, t.description, t.category, t.amount,
                DATE_FORMAT(t.occurred_on, "%Y-%m-%d") AS date,
                t.debt_id, u.name AS created_by_name
         FROM transactions t
         LEFT JOIN users u ON u.id = t.created_by
         WHERE t.occurred_on >= ? AND t.occurred_on < ?
         ORDER BY t.occurred_on DESC, t.created_at DESC'
    );
    $stmt->execute([$start, $next]);
    $transactions = $stmt->fetchAll();

    $totals = ['income' => 0.0, 'expense' => 0.0, 'investment' => 0.0, 'debt' => 0.0];
    foreach ($transactions as &$transaction) {
        $transaction['amount'] = (float) $transaction['amount'];
        if (isset($totals[$transaction['type']])) {
            $totals[$transaction['type']] += $transaction['amount'];
        }
    }
    unset($transaction);

    $bills = fixed_bills_data($pdo, $month);

    $stmt = $pdo->prepare(
        'SELECT g.id, g.category, g.monthly_limit,
                COALESCE(SUM(CASE WHEN t.type = "expense" THEN t.amount ELSE 0 END), 0) AS spent
         FROM spending_goals g
         LEFT JOIN transactions t
           ON LOWER(t.category) = LOWER(g.category)
          AND t.occurred_on >= ? AND t.occurred_on < ?
         GROUP BY g.id, g.category, g.monthly_limit
         ORDER BY g.category'
    );
    $stmt->execute([$start, $next]);
    $goals = $stmt->fetchAll();
    foreach ($goals as &$goal) {
        $goal['monthly_limit'] = (float) $goal['monthly_limit'];
        $goal['spent'] = (float) $goal['spent'];
    }
    unset($goal);

    $debts = $pdo->query(
        'SELECT id, name, total_amount, paid_amount
         FROM debts
         ORDER BY created_at DESC'
    )->fetchAll();
    foreach ($debts as &$debt) {
        $debt['total_amount'] = (float) $debt['total_amount'];
        $debt['paid_amount'] = (float) $debt['paid_amount'];
    }
    unset($debt);

    $investmentGoalStmt = $pdo->query(
        "SELECT value FROM settings WHERE setting_key = 'investment_goal' LIMIT 1"
    );
    $investmentGoal = (float) ($investmentGoalStmt->fetchColumn() ?: 0);

    return compact('transactions', 'totals', 'bills', 'goals', 'debts', 'investmentGoal');
}
