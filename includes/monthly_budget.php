<?php
declare(strict_types=1);
require_once __DIR__ . '/finance.php';
function monthly_budget_ready(PDO $pdo): bool {
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name IN ('recurring_incomes','monthly_allocations')");
    return (int)$stmt->fetchColumn() === 2;
}
function monthly_budget_data(PDO $pdo, string $month): array {
    $start = $month . '-01';
    $end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
    $incomeStmt=$pdo->prepare('SELECT id,name,amount FROM recurring_incomes WHERE tenant_id=? AND active=1' . finance_read_owner_clause($pdo,'recurring_incomes') . ' ORDER BY name');
    $incomeStmt->execute([require_tenant_id()]);
    $incomes=$incomeStmt->fetchAll();
    $profileIncomes=profile_monthly_income($pdo,$month);
    $profileIncomeTotal=array_sum(array_map(static fn($r)=>(float)$r['amount'],$profileIncomes));
    $recurringIncomeTotal = array_sum(array_map(static fn($r) => (float)$r['amount'], $incomes)) + $profileIncomeTotal;
    // Ganhos lançados no mês fazem parte da previsão, sem status de recebido.
    $stmtIncome = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE tenant_id=? AND type='income' AND occurred_on >= ? AND occurred_on < ?" . finance_read_owner_clause($pdo,'transactions'));
    $stmtIncome->execute([require_tenant_id(),$start,$end]);
    $recordedIncomeTotal = (float)$stmtIncome->fetchColumn();
    $incomeTotal = $recurringIncomeTotal + $recordedIncomeTotal;
    $bills = fixed_bills_data($pdo, $month);
    $billTotal = array_sum(array_map(static fn($b) => (float)$b['amount'], $bills));
    $missingBills = count(array_filter($bills, static fn($b) => $b['needs_amount']));
    $stmt = $pdo->prepare("SELECT category, COALESCE(SUM(amount),0) spent
      FROM transactions WHERE tenant_id=? AND type='expense' AND bill_payment_id IS NULL
      AND LOWER(category) NOT IN ('contas recorrentes','contas fixas')
      AND occurred_on >= ? AND occurred_on < ?" . finance_read_owner_clause($pdo,'transactions') . "
      GROUP BY category");
    $stmt->execute([require_tenant_id(),$start,$end]);
    $spent = [];
    foreach ($stmt->fetchAll() as $r) {
        $spent[strtolower(trim((string)$r['category']))] = (float)$r['spent'];
    }
    $stmt = $pdo->prepare('SELECT category, amount FROM monthly_allocations WHERE tenant_id=? AND month = ?' . finance_read_owner_clause($pdo,'monthly_allocations') . ' ORDER BY category');
    $stmt->execute([require_tenant_id(),$month]);
    $allocations = $stmt->fetchAll();
    $allocated = array_sum(array_map(static fn($r) => (float)$r['amount'], $allocations));
    $stmt = $pdo->prepare("SELECT type,COALESCE(SUM(amount),0) total FROM transactions
       WHERE tenant_id=? AND type IN ('income','expense') AND occurred_on >= ? AND occurred_on < ?" . finance_read_owner_clause($pdo,'transactions') . "
       GROUP BY type");
    $stmt->execute([require_tenant_id(),$start,$end]);
    $actuals=['income'=>0.0,'expense'=>0.0];
    foreach($stmt->fetchAll() as $r) $actuals[$r['type']] = (float)$r['total'];
    // Recorded recurring-bill payments are represented by the forecast already.
    // Exclude linked bill transactions and legacy recurring expense categories from discretionary spending.
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions
      WHERE tenant_id=? AND type='expense' AND occurred_on >= ? AND occurred_on < ?
      AND (bill_payment_id IS NOT NULL OR LOWER(category) IN ('contas recorrentes','contas fixas'))" . finance_read_owner_clause($pdo,'transactions'));
    $stmt->execute([require_tenant_id(),$start,$end]);
    $billTransactions=(float)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions
      WHERE tenant_id=? AND type='expense' AND occurred_on >= ? AND occurred_on < ?
      AND bill_payment_id IS NULL AND LOWER(category) NOT IN ('contas recorrentes','contas fixas')" . finance_read_owner_clause($pdo,'transactions'));
    $stmt->execute([require_tenant_id(),$start,$end]);
    $variableSpent=(float)$stmt->fetchColumn();
    return compact('incomes','profileIncomes','profileIncomeTotal','recurringIncomeTotal','recordedIncomeTotal','incomeTotal','bills','billTotal','missingBills','allocations','allocated','spent','actuals','billTransactions','variableSpent') + [
      'after_bills'=>$incomeTotal-$billTotal,
      'unallocated'=>$incomeTotal-$billTotal-$allocated,
      'expected_end'=>$incomeTotal-$billTotal-$variableSpent,
    ];
}
