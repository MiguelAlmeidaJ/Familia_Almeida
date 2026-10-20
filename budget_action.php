<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/monthly_budget.php';
require_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {http_response_code(405);exit('Método não permitido.');}
verify_csrf();
$pdo=db();
$month=valid_month($_POST['month'] ?? null);
$back='/?month='.rawurlencode($month);
try {
 if (!monthly_budget_ready($pdo)) throw new RuntimeException('Execute a migration de orçamento mensal em Configurações > Manutenção.');
 $action=(string)($_POST['action'] ?? '');
 if ($action === 'save_income') {
    $name=trim((string)($_POST['name'] ?? ''));
    $amount=(float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
    if ($name==='' || strlen($name)>160 || !is_finite($amount) || $amount<=0 || $amount>9999999999) throw new RuntimeException('Informe uma receita e um valor válido.');
    $stmt=$pdo->prepare('INSERT INTO recurring_incomes(name,amount) VALUES (?,?)');
    $stmt->execute([$name,$amount]);
 } elseif ($action === 'remove_income') {
    $id=filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT);
    if (!$id || $id<1) throw new RuntimeException('Receita inválida.');
    $pdo->prepare('UPDATE recurring_incomes SET active=0 WHERE id=?')->execute([$id]);
 } elseif ($action === 'save_allocation') {
    $category=trim((string)($_POST['category'] ?? ''));
    $amount=(float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
    if ($category==='' || strlen($category)>100 || !is_finite($amount) || $amount<0 || $amount>9999999999) throw new RuntimeException('Preencha categoria e valor válido.');
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('INSERT INTO monthly_allocations(month,category,amount) VALUES (?,?,?) ON DUPLICATE KEY UPDATE amount=VALUES(amount)');
    $stmt->execute([$month,$category,$amount]);
    $pdo->commit();
 } elseif ($action === 'remove_allocation') {
    $category=trim((string)($_POST['category'] ?? ''));
    $pdo->prepare('DELETE FROM monthly_allocations WHERE month=? AND category=?')->execute([$month,$category]);
 } elseif ($action === 'save_bill') {
    $name=trim((string)($_POST['name'] ?? ''));
    $amount=(float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
    $day=filter_var($_POST['due_day'] ?? null,FILTER_VALIDATE_INT);
    if ($name==='' || strlen($name)>120 || !is_finite($amount) || $amount<=0 || $amount>9999999999 || !$day || $day<1 || $day>31) throw new RuntimeException('Preencha conta, valor e vencimento válidos.');
    $stmt=$pdo->prepare("INSERT INTO fixed_bills(name,billing_type,amount,due_day,active) VALUES (?,'fixed',?,?,1)");
    $stmt->execute([$name,$amount,$day]);
 } elseif ($action === 'remove_bill') {
    $id=filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT);
    if (!$id || $id<1) throw new RuntimeException('Conta inválida.');
    $pdo->prepare('UPDATE fixed_bills SET active=0 WHERE id=?')->execute([$id]);
 } else throw new RuntimeException('Ação inválida.');
 flash('success','Orçamento atualizado.');
} catch(Throwable $e) {
 if($pdo->inTransaction()) $pdo->rollBack();
 flash('error',$e->getMessage());
}
redirect_to($back);
