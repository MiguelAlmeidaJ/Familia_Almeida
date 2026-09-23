<?php

declare(strict_types=1);

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?'
    );
    $stmt->execute([$table, $column]);

    return $cache[$key] = ((int) $stmt->fetchColumn() > 0);
}

function recurring_bills_schema_ready(PDO $pdo): bool
{
    return db_column_exists($pdo, 'fixed_bills', 'billing_type')
        && db_column_exists($pdo, 'fixed_bills', 'start_month')
        && db_column_exists($pdo, 'fixed_bills', 'installment_total')
        && db_column_exists($pdo, 'bill_payments', 'amount_due')
        && db_column_exists($pdo, 'bill_payments', 'installment_number');
}

function bill_month_distance(string $fromMonth, string $toMonth): int
{
    [$fromYear, $fromNumber] = array_map('intval', explode('-', $fromMonth));
    [$toYear, $toNumber] = array_map('intval', explode('-', $toMonth));
    return (($toYear - $fromYear) * 12) + ($toNumber - $fromNumber);
}

function fixed_bills_data(PDO $pdo, string $month, bool $includeInactive = false): array
{
    $recurringReady = recurring_bills_schema_ready($pdo);
    $paidOnReady = db_column_exists($pdo, 'bill_payments', 'paid_on');

    if ($recurringReady) {
        $select =
            'SELECT
                b.id,
                b.name,
                b.billing_type,
                b.amount AS base_amount,
                b.due_day,
                b.start_month,
                b.installment_total,
                b.active,
                p.id AS payment_id,
                p.amount_due,
                p.installment_number AS stored_installment_number,
                COALESCE(p.paid, 0) AS paid,' .
                ($paidOnReady ? ' p.paid_on ' : ' NULL AS paid_on ') .
            'FROM fixed_bills b
             LEFT JOIN bill_payments p ON p.bill_id = b.id AND p.month = ?
             ORDER BY b.active DESC, b.due_day, b.name';
    } else {
        // Compatibility mode: keeps the site online before migration 003 is applied.
        $select =
            'SELECT
                b.id,
                b.name,
                "fixed" AS billing_type,
                b.amount AS base_amount,
                b.due_day,
                NULL AS start_month,
                NULL AS installment_total,
                b.active,
                p.id AS payment_id,
                NULL AS amount_due,
                NULL AS stored_installment_number,
                COALESCE(p.paid, 0) AS paid,' .
                ($paidOnReady ? ' p.paid_on ' : ' NULL AS paid_on ') .
            'FROM fixed_bills b
             LEFT JOIN bill_payments p ON p.bill_id = b.id AND p.month = ?
             ORDER BY b.active DESC, b.due_day, b.name';
    }

    $stmt = $pdo->prepare($select);
    $stmt->execute([$month]);
    $rows = $stmt->fetchAll();

    $bills = [];

    foreach ($rows as $row) {
        $active = (bool) $row['active'];
        $billingType = (string) ($row['billing_type'] ?: 'fixed');
        $scheduleStatus = 'current';
        $installmentNumber = null;

        if ($billingType === 'installment') {
            $startMonth = (string) ($row['start_month'] ?? '');
            $installmentTotal = (int) ($row['installment_total'] ?? 0);

            if (!preg_match('/^\d{4}-\d{2}$/', $startMonth) || $installmentTotal < 1) {
                $scheduleStatus = 'invalid';
            } else {
                $distance = bill_month_distance($startMonth, $month);

                if ($distance < 0) {
                    $scheduleStatus = 'future';
                } elseif ($distance >= $installmentTotal) {
                    $scheduleStatus = 'completed';
                } else {
                    $installmentNumber = $distance + 1;
                }
            }
        }

        $applicable = $active && $scheduleStatus === 'current';

        if (!$includeInactive && !$applicable) {
            continue;
        }

        $amountDue = $row['amount_due'] !== null ? (float) $row['amount_due'] : null;
        $baseAmount = (float) $row['base_amount'];
        $needsAmount = $recurringReady
            && ($billingType === 'variable' || ($billingType === 'fixed' && $baseAmount <= 0))
            && $amountDue === null;
        $effectiveAmount = $amountDue ?? ($billingType === 'variable' ? 0.0 : $baseAmount);

        $bills[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'billing_type' => $billingType,
            'base_amount' => $baseAmount,
            'amount' => $effectiveAmount,
            'amount_due' => $amountDue,
            'due_day' => (int) $row['due_day'],
            'start_month' => $row['start_month'],
            'installment_total' => $row['installment_total'] !== null ? (int) $row['installment_total'] : null,
            'installment_number' => $installmentNumber,
            'schedule_status' => $scheduleStatus,
            'applicable' => $applicable,
            'needs_amount' => $needsAmount,
            'active' => $active,
            'paid' => (bool) $row['paid'],
            'paid_on' => $row['paid_on'],
            'payment_id' => $row['payment_id'] !== null ? (int) $row['payment_id'] : null,
            'schema_ready' => $recurringReady,
        ];
    }

    return $bills;
}
function dashboard_data(PDO $pdo, string $month): array
{
    $start = $month . '-01';
    $next = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');

    $billPaymentSelect = db_column_exists($pdo, 'transactions', 'bill_payment_id')
        ? 't.bill_payment_id'
        : 'NULL AS bill_payment_id';

    $stmt = $pdo->prepare(
        'SELECT t.id, t.type, t.description, t.category, t.amount,
                DATE_FORMAT(t.occurred_on, "%Y-%m-%d") AS date,
                t.debt_id, ' . $billPaymentSelect . ', u.name AS created_by_name
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


function monthly_transaction_totals(PDO $pdo, string $month): array
{
    $start = $month . '-01';
    $next = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');

    $stmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END), 0) AS expense,
            COALESCE(SUM(CASE WHEN type = "investment" THEN amount ELSE 0 END), 0) AS investment,
            COALESCE(SUM(CASE WHEN type = "debt" THEN amount ELSE 0 END), 0) AS debt
         FROM transactions
         WHERE occurred_on >= ? AND occurred_on < ?'
    );
    $stmt->execute([$start, $next]);
    $row = $stmt->fetch() ?: [];

    return [
        'income' => (float) ($row['income'] ?? 0),
        'expense' => (float) ($row['expense'] ?? 0),
        'investment' => (float) ($row['investment'] ?? 0),
        'debt' => (float) ($row['debt'] ?? 0),
    ];
}

function dashboard_analytics(PDO $pdo, string $month): array
{
    $currentStart = new DateTimeImmutable($month . '-01');
    $nextStart = $currentStart->modify('+1 month');
    $previousStart = $currentStart->modify('-1 month');
    $previousMonth = $previousStart->format('Y-m');

    $currentTotals = monthly_transaction_totals($pdo, $month);
    $previousTotals = monthly_transaction_totals($pdo, $previousMonth);

    $dailyStmt = $pdo->prepare(
        'SELECT
            DATE_FORMAT(occurred_on, "%Y-%m-%d") AS day,
            COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN type IN ("expense", "debt") THEN amount ELSE 0 END), 0) AS outflow,
            COALESCE(SUM(CASE WHEN type = "investment" THEN amount ELSE 0 END), 0) AS investment
         FROM transactions
         WHERE occurred_on >= ? AND occurred_on < ?
         GROUP BY occurred_on
         ORDER BY occurred_on'
    );
    $dailyStmt->execute([$currentStart->format('Y-m-d'), $nextStart->format('Y-m-d')]);
    $daily = [];
    foreach ($dailyStmt->fetchAll() as $row) {
        $daily[] = [
            'day' => $row['day'],
            'income' => (float) $row['income'],
            'outflow' => (float) $row['outflow'],
            'investment' => (float) $row['investment'],
        ];
    }

    $categoryStmt = $pdo->prepare(
        'SELECT category, SUM(amount) AS total
         FROM transactions
         WHERE type = "expense"
           AND occurred_on >= ? AND occurred_on < ?
         GROUP BY category
         HAVING SUM(amount) > 0
         ORDER BY total DESC'
    );
    $categoryStmt->execute([$currentStart->format('Y-m-d'), $nextStart->format('Y-m-d')]);
    $categories = [];
    foreach ($categoryStmt->fetchAll() as $row) {
        $categories[] = ['category' => $row['category'], 'total' => (float) $row['total']];
    }

    $sixStart = $currentStart->modify('-5 months');
    $sixStmt = $pdo->prepare(
        'SELECT
            DATE_FORMAT(occurred_on, "%Y-%m") AS month,
            COALESCE(SUM(CASE WHEN type = "income" THEN amount ELSE 0 END), 0) AS income,
            COALESCE(SUM(CASE WHEN type IN ("expense", "debt") THEN amount ELSE 0 END), 0) AS outflow,
            COALESCE(SUM(CASE WHEN type = "investment" THEN amount ELSE 0 END), 0) AS investment
         FROM transactions
         WHERE occurred_on >= ? AND occurred_on < ?
         GROUP BY DATE_FORMAT(occurred_on, "%Y-%m")
         ORDER BY month'
    );
    $sixStmt->execute([$sixStart->format('Y-m-d'), $nextStart->format('Y-m-d')]);
    $indexed = [];
    foreach ($sixStmt->fetchAll() as $row) {
        $indexed[$row['month']] = [
            'income' => (float) $row['income'],
            'outflow' => (float) $row['outflow'],
            'investment' => (float) $row['investment'],
        ];
    }

    $sixMonths = [];
    for ($i = 0; $i < 6; $i++) {
        $date = $sixStart->modify('+' . $i . ' months');
        $key = $date->format('Y-m');
        $values = $indexed[$key] ?? ['income' => 0.0, 'outflow' => 0.0, 'investment' => 0.0];
        $sixMonths[] = [
            'month' => $key,
            'income' => $values['income'],
            'outflow' => $values['outflow'],
            'investment' => $values['investment'],
            'balance' => $values['income'] - $values['outflow'] - $values['investment'],
        ];
    }

    $largestStmt = $pdo->prepare(
        'SELECT description, category, amount, DATE_FORMAT(occurred_on, "%Y-%m-%d") AS occurred_on
         FROM transactions
         WHERE type = "expense" AND occurred_on >= ? AND occurred_on < ?
         ORDER BY amount DESC
         LIMIT 1'
    );
    $largestStmt->execute([$currentStart->format('Y-m-d'), $nextStart->format('Y-m-d')]);
    $largestExpense = $largestStmt->fetch() ?: null;
    if ($largestExpense) {
        $largestExpense['amount'] = (float) $largestExpense['amount'];
    }

    return [
        'current' => $currentTotals,
        'previous' => $previousTotals,
        'previous_month' => $previousMonth,
        'daily' => $daily,
        'categories' => $categories,
        'six_months' => $sixMonths,
        'largest_expense' => $largestExpense,
    ];
}
