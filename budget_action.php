<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/monthly_budget.php';
require_once __DIR__ . '/includes/finance_scope.php';
$user=require_family_access();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();
$scope=finance_scope($pdo,$user);
$tenantId=(int)$scope['tenant_id'];
$month=valid_month($_POST['month'] ?? null);
$back='/?month='.rawurlencode($month);
try {
 if (!monthly_budget_ready($pdo)) throw new RuntimeException('Execute a migration de orçamento mensal em Configurações > Manutenção.');
 $action=(string)($_POST['action'] ?? '');
 if ($action === 'save_income') {
    $name=trim((string)($_POST['name'] ?? ''));
    $amount=(float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
    if ($name==='' || strlen($name)>160 || !is_finite($amount) || $amount<=0 || $amount>9999999999) throw new RuntimeException('Informe uma receita e um valor válido.');
    $stmt=$pdo->prepare('INSERT INTO recurring_incomes(tenant_id,name,amount) VALUES (?,?,?)');
    $stmt->execute([$tenantId,$name,$amount]);
 } elseif ($action === 'remove_income') {
    $id=filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT);
    if (!$id || $id<1) throw new RuntimeException('Receita inválida.');
    finance_assert_owned_record($pdo,'recurring_incomes',(int)$id,$scope);
    $pdo->prepare('UPDATE recurring_incomes SET active=0 WHERE id=? AND tenant_id=?')->execute([$id,$tenantId]);
 } elseif ($action === 'save_allocation') {
    $category=trim((string)($_POST['category'] ?? ''));
    $amount=(float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
    if ($category==='' || strlen($category)>100 || !is_finite($amount) || $amount<0 || $amount>9999999999) throw new RuntimeException('Preencha categoria e valor válido.');
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('INSERT INTO monthly_allocations(tenant_id,month,category,amount) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)');
    $stmt->execute([$tenantId,$month,$category,$amount]);
    $pdo->commit();
 } elseif ($action === 'remove_allocation') {
    $category=trim((string)($_POST['category'] ?? ''));
    $pdo->prepare('DELETE FROM monthly_allocations WHERE tenant_id=? AND month=? AND category=?')->execute([$tenantId,$month,$category]);
 } elseif ($action === 'save_bill') {
    $name=trim((string)($_POST['name'] ?? ''));
    $amount=(float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
    $day=filter_var($_POST['due_day'] ?? null,FILTER_VALIDATE_INT);
    if ($name==='' || strlen($name)>120 || !is_finite($amount) || $amount<=0 || $amount>9999999999 || !$day || $day<1 || $day>31) throw new RuntimeException('Preencha conta, valor e vencimento válidos.');
    $stmt=$pdo->prepare("INSERT INTO fixed_bills(tenant_id,name,billing_type,amount,due_day,active) VALUES (?,?,'fixed',?,?,1)");
    $stmt->execute([$tenantId,$name,$amount,$day]);
 } elseif ($action === 'remove_bill') {
    $id=filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT);
    if (!$id || $id<1) throw new RuntimeException('Conta inválida.');
    finance_assert_owned_record($pdo,'fixed_bills',(int)$id,$scope);
    $pdo->prepare('UPDATE fixed_bills SET active=0 WHERE id=? AND tenant_id=?')->execute([$id,$tenantId]);
 } else throw new RuntimeException('Ação inválida.');
 flash('success','Orçamento atualizado.');
} catch(Throwable $e) {
 if($pdo->inTransaction()) $pdo->rollBack();
 flash('error',$e->getMessage());
}
redirect_to($back);
