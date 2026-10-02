<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function credit_cards_schema_ready(PDO $pdo): bool
{
    foreach (['credit_cards', 'credit_card_purchases', 'credit_card_installments', 'credit_card_invoice_payments'] as $table) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        if ((int) $stmt->fetchColumn() === 0) return false;
    }
    return true;
}

function credit_card_first_invoice_month(string $purchaseDate, int $closingDay): string
{
    $date = new DateTimeImmutable($purchaseDate);
    $month = new DateTimeImmutable($date->format('Y-m-01'));
    if ((int) $date->format('j') > $closingDay) {
        $month = $month->modify('+1 month');
    }
    return $month->format('Y-m');
}

function credit_card_add_months(string $month, int $months): string
{
    return (new DateTimeImmutable($month . '-01'))->modify('+' . $months . ' month')->format('Y-m');
}

function credit_card_month_label(string $month): string
{
    $names = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    [$year,$number] = array_map('intval', explode('-', $month));
    return $names[$number] . ' de ' . $year;
}

function credit_card_due_date(string $month, int $dueDay): string
{
    $base = new DateTimeImmutable($month . '-01');
    $day = min($dueDay, (int) $base->format('t'));
    return sprintf('%s-%02d', $month, $day);
}

function credit_cards_all(PDO $pdo, bool $includeInactive = false): array
{
    $sql = 'SELECT * FROM credit_cards';
    if (!$includeInactive) $sql .= ' WHERE active = 1';
    $sql .= ' ORDER BY active DESC, name';
    return $pdo->query($sql)->fetchAll();
}

function credit_card_invoice(PDO $pdo, int $cardId, string $month): array
{
    $stmt = $pdo->prepare(
        'SELECT
            i.id, i.purchase_id, i.installment_number, i.installment_total, i.amount, i.invoice_month,
            p.description, p.category, p.total_amount, p.purchase_date
         FROM credit_card_installments i
         INNER JOIN credit_card_purchases p ON p.id = i.purchase_id
         WHERE i.card_id = ? AND i.invoice_month = ?
         ORDER BY p.purchase_date DESC, p.id DESC'
    );
    $stmt->execute([$cardId, $month]);
    return $stmt->fetchAll();
}

function credit_card_invoice_total(PDO $pdo, int $cardId, string $month): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount),0)
         FROM credit_card_installments
         WHERE card_id = ? AND invoice_month = ?'
    );
    $stmt->execute([$cardId, $month]);
    return (float) $stmt->fetchColumn();
}

function credit_card_open_commitment(PDO $pdo, int $cardId): float
{
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(i.amount),0)
         FROM credit_card_installments i
         LEFT JOIN credit_card_invoice_payments p
           ON p.card_id = i.card_id AND p.month = i.invoice_month
         WHERE i.card_id = ? AND p.id IS NULL'
    );
    $stmt->execute([$cardId]);
    return (float) $stmt->fetchColumn();
}

function credit_card_payment(PDO $pdo, int $cardId, string $month): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM credit_card_invoice_payments WHERE card_id = ? AND month = ? LIMIT 1'
    );
    $stmt->execute([$cardId, $month]);
    $row = $stmt->fetch();
    return $row ?: null;
}
