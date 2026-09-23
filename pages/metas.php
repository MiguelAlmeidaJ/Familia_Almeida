<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/finance.php';
require_once __DIR__ . '/../includes/layout.php';

$user = require_auth();
$pdo = db();
$month = valid_month($_GET['month'] ?? null);
$tab = (($_GET['tab'] ?? '') === 'investimento') ? 'investimento' : 'gastos';
$data = dashboard_data($pdo, $month);
$csrf = csrf_token();
$flash = pull_flash();

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthShort = [1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;

$currentMonth = new DateTimeImmutable($month . '-01');
$prevMonth = $currentMonth->modify('-1 month')->format('Y-m');
$nextMonth = $currentMonth->modify('+1 month')->format('Y-m');

$totalPlanned = array_reduce($data['goals'], fn(float $sum, array $goal) => $sum + (float) $goal['monthly_limit'], 0.0);
$totalSpent = array_reduce($data['goals'], fn(float $sum, array $goal) => $sum + (float) $goal['spent'], 0.0);
$remainingBudget = max(0, $totalPlanned - $totalSpent);
$overLimitCount = count(array_filter(
    $data['goals'],
    fn(array $goal) => (float) $goal['monthly_limit'] > 0 && (float) $goal['spent'] > (float) $goal['monthly_limit']
));

$investmentGoal = (float) $data['investmentGoal'];
$investedThisMonth = (float) $data['totals']['investment'];
$investmentRemaining = max(0, $investmentGoal - $investedThisMonth);
$investmentProgress = $investmentGoal > 0 ? min(100, ($investedThisMonth / $investmentGoal) * 100) : 0;

$historyStart = $currentMonth->modify('-5 months')->format('Y-m-d');
$historyEnd = $currentMonth->modify('+1 month')->format('Y-m-d');
$historyStmt = $pdo->prepare(
    'SELECT DATE_FORMAT(occurred_on, "%Y-%m") AS month, SUM(amount) AS total
     FROM transactions
     WHERE type = "investment"
       AND occurred_on >= ?
       AND occurred_on < ?
     GROUP BY DATE_FORMAT(occurred_on, "%Y-%m")
     ORDER BY month'
);
$historyStmt->execute([$historyStart, $historyEnd]);

$historyIndex = [];
foreach ($historyStmt->fetchAll() as $row) {
    $historyIndex[$row['month']] = (float) $row['total'];
}

$investmentHistory = [];
for ($i = 0; $i < 6; $i++) {
    $date = $currentMonth->modify('-' . (5 - $i) . ' months');
    $key = $date->format('Y-m');
    $investmentHistory[] = [
        'month' => $key,
        'label' => ucfirst($monthShort[(int) $date->format('n')]) . '/' . $date->format('y'),
        'value' => $historyIndex[$key] ?? 0.0,
    ];
}

$historyTotal = array_reduce($investmentHistory, fn(float $sum, array $row) => $sum + $row['value'], 0.0);
$historyAverage = $historyTotal / 6;
$historyMax = max(1, ...array_map(fn(array $row) => $row['value'], $investmentHistory));

function goal_progress_value(array $goal): float
{
    $limit = (float) $goal['monthly_limit'];
    return $limit > 0 ? min(100, ((float) $goal['spent'] / $limit) * 100) : 0;
}

function goal_remaining_value(array $goal): float
{
    return max(0, (float) $goal['monthly_limit'] - (float) $goal['spent']);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Metas financeiras • Família Almeida</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="shell ref-shell">
    <?php render_sidebar('metas', $csrf); ?>

    <main>
        <?php render_topbar($user); ?>

        <div class="content ref-dashboard goals-reference-page">
            <section class="ref-dashboard-heading goals-reference-heading">
                <div>
                    <p class="eyebrow">LIMITES E PRIORIDADES</p>
                    <h1>Metas financeiras</h1>
                    <p>Planeje seus limites de gastos e acompanhe a construção dos seus investimentos.</p>
                </div>

                <div class="ref-month-picker">
                    <a href="?month=<?= e($prevMonth) ?>&tab=<?= e($tab) ?>" aria-label="Mês anterior">‹</a>
                    <span><?= e($monthLabel) ?></span>
                    <span class="ref-calendar">▣</span>
                    <a href="?month=<?= e($nextMonth) ?>&tab=<?= e($tab) ?>" aria-label="Próximo mês">›</a>
                </div>
            </section>

            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <nav class="goals-tabs" aria-label="Seções de metas financeiras">
                <a class="<?= $tab === 'gastos' ? 'active' : '' ?>" href="/metas?month=<?= e($month) ?>&tab=gastos">
                    <span>◎</span>
                    <div><strong>Gastos</strong><small>Limites por categoria</small></div>
                </a>
                <a class="<?= $tab === 'investimento' ? 'active' : '' ?>" href="/metas?month=<?= e($month) ?>&tab=investimento">
                    <span>↗</span>
                    <div><strong>Investimento</strong><small>Meta e evolução mensal</small></div>
                </a>
            </nav>

            <?php if ($tab === 'gastos'): ?>
                <section class="goals-summary-grid">
                    <article>
                        <span>Planejado</span>
                        <strong><?= money($totalPlanned) ?></strong>
                        <small><?= count($data['goals']) ?> categoria(s) acompanhada(s)</small>
                    </article>
                    <article>
                        <span>Gasto</span>
                        <strong><?= money($totalSpent) ?></strong>
                        <small><?= $totalPlanned > 0 ? number_format(min(100, ($totalSpent / $totalPlanned) * 100), 0, ',', '.') . '% do planejado' : 'Defina seus limites' ?></small>
                    </article>
                    <article class="<?= $overLimitCount ? 'attention' : 'positive' ?>">
                        <span>Disponível</span>
                        <strong><?= money($remainingBudget) ?></strong>
                        <small><?= $overLimitCount ? $overLimitCount . ' categoria(s) acima do limite' : 'Dentro do planejamento' ?></small>
                    </article>
                </section>

                <section class="card goals-reference-card">
                    <header class="goals-reference-card-head">
                        <div>
                            <p class="eyebrow">GASTAR COM INTENÇÃO</p>
                            <h2>Limites por categoria</h2>
                            <p>Veja rapidamente quanto já foi usado e quanto ainda está disponível.</p>
                        </div>
                        <button class="ref-green-button goals-add-button" type="button" onclick="openGoalCreate()">＋&nbsp; Nova meta</button>
                    </header>

                    <div class="goals-clean-list">
                        <?php if ($data['goals']): ?>
                            <?php foreach ($data['goals'] as $index => $goal): ?>
                                <?php
                                $progress = goal_progress_value($goal);
                                $remaining = goal_remaining_value($goal);
                                $over = (float) $goal['monthly_limit'] > 0 && (float) $goal['spent'] > (float) $goal['monthly_limit'];
                                ?>
                                <article class="goal-clean-row <?= $over ? 'is-over' : '' ?>">
                                    <div class="goal-clean-icon tone-<?= ($index % 4) + 1 ?>">
                                        <?= e(strtoupper(substr((string) $goal['category'], 0, 1))) ?>
                                    </div>

                                    <div class="goal-clean-main">
                                        <div class="goal-clean-title">
                                            <strong><?= e($goal['category']) ?></strong>
                                            <?php if ($over): ?><span>Limite ultrapassado</span><?php endif; ?>
                                        </div>

                                        <div class="goal-clean-values">
                                            <span><?= money($goal['spent']) ?> de <?= money($goal['monthly_limit']) ?></span>
                                            <strong><?= $goal['monthly_limit'] > 0 ? number_format($progress, 0, ',', '.') . '%' : 'Sem limite' ?></strong>
                                        </div>

                                        <div class="goal-clean-progress">
                                            <span class="<?= $over ? 'over' : '' ?>" style="width:<?= $progress ?>%"></span>
                                        </div>

                                        <small>
                                            <?php if ((float) $goal['monthly_limit'] <= 0): ?>
                                                Defina um limite para acompanhar esta categoria.
                                            <?php elseif ($over): ?>
                                                Excedeu <?= money((float) $goal['spent'] - (float) $goal['monthly_limit']) ?>.
                                            <?php else: ?>
                                                Restam <?= money($remaining) ?> neste mês.
                                            <?php endif; ?>
                                        </small>
                                    </div>

                                    <button
                                        class="goal-clean-edit"
                                        type="button"
                                        title="Editar meta"
                                        onclick='openGoalEdit(
                                            <?= (int) $goal["id"] ?>,
                                            <?= json_encode($goal["category"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                            <?= json_encode($goal["monthly_limit"]) ?>
                                        )'
                                    >✎</button>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="goals-empty-state">
                                <span>◎</span>
                                <strong>Nenhuma meta de gasto ainda</strong>
                                <p>Crie limites para as categorias que mais importam no orçamento.</p>
                                <button class="ref-green-button" type="button" onclick="openGoalCreate()">＋ Criar primeira meta</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

            <?php else: ?>
                <section class="investment-unified-grid" id="investimento">
                    <article class="investment-unified-hero">
                        <div class="investment-unified-head">
                            <div>
                                <p class="eyebrow">CONSTRUINDO O FUTURO</p>
                                <h2>Meta de investimento</h2>
                                <p>Transforme o aporte mensal em um compromisso com os seus planos.</p>
                            </div>
                            <button type="button" class="investment-edit-button" onclick="document.getElementById('investment-goal-dialog').showModal()">✎ Editar meta</button>
                        </div>

                        <div class="investment-unified-numbers">
                            <div>
                                <span>Investido</span>
                                <strong><?= money($investedThisMonth) ?></strong>
                            </div>
                            <div>
                                <span>Meta mensal</span>
                                <strong><?= money($investmentGoal) ?></strong>
                            </div>
                            <div>
                                <span>Falta</span>
                                <strong><?= money($investmentRemaining) ?></strong>
                            </div>
                        </div>

                        <div class="investment-unified-progress">
                            <span style="width:<?= $investmentProgress ?>%"></span>
                        </div>
                        <div class="investment-progress-caption">
                            <span><?= number_format($investmentProgress, 0, ',', '.') ?>% da meta concluída</span>
                            <span><?= money($investedThisMonth) ?> de <?= money($investmentGoal) ?></span>
                        </div>

                        <a class="investment-register-button" href="/movimentacoes?month=<?= e($month) ?>">＋ Registrar investimento</a>
                    </article>

                    <aside class="investment-unified-summary">
                        <p class="eyebrow">ÚLTIMOS 6 MESES</p>
                        <h2>Consistência</h2>
                        <div class="investment-summary-stat">
                            <span>Total investido</span>
                            <strong><?= money($historyTotal) ?></strong>
                        </div>
                        <div class="investment-summary-stat">
                            <span>Média mensal</span>
                            <strong><?= money($historyAverage) ?></strong>
                        </div>
                        <small>Acompanhar a regularidade é tão importante quanto o valor de um único aporte.</small>
                    </aside>
                </section>

                <section class="card investment-history-card">
                    <header class="goals-reference-card-head">
                        <div>
                            <p class="eyebrow">EVOLUÇÃO</p>
                            <h2>Investimentos por mês</h2>
                            <p>Uma visão simples dos aportes mais recentes.</p>
                        </div>
                    </header>

                    <div class="investment-history-list">
                        <?php foreach ($investmentHistory as $row): ?>
                            <?php $bar = $historyMax > 0 ? ($row['value'] / $historyMax) * 100 : 0; ?>
                            <div class="investment-history-row">
                                <span><?= e($row['label']) ?></span>
                                <div class="investment-history-track"><span style="width:<?= $bar ?>%"></span></div>
                                <strong><?= money($row['value']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <footer class="ref-page-footer goals-page-footer">
                <span>Família Almeida&nbsp;&nbsp; / &nbsp;&nbsp;Planejar também é cuidar.</span>
                <span><?= e(ucfirst($monthLabel)) ?></span>
            </footer>
        </div>
    </main>
</div>

<dialog id="goal-dialog" class="fixed-reference-dialog goals-dialog">
    <form method="post" action="/acao" class="fixed-reference-dialog-form" id="goal-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="return_to" value="/metas">
        <input type="hidden" name="return_tab" value="gastos">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="action" id="goal-action" value="add_goal">
        <input type="hidden" name="goal_id" id="goal-id">

        <div class="fixed-reference-dialog-head">
            <div>
                <h3 id="goal-dialog-title">Nova meta</h3>
                <p id="goal-dialog-copy">Defina um limite mensal para uma categoria de gasto.</p>
            </div>
            <button type="button" onclick="document.getElementById('goal-dialog').close()">×</button>
        </div>

        <label>Categoria
            <input name="category" id="goal-category" maxlength="100" placeholder="Ex.: Mercado" required>
        </label>

        <label>Limite mensal (R$)
            <input type="number" name="limit" id="goal-limit" min="0" step="0.01" placeholder="0,00" required>
        </label>

        <div class="goal-delete-zone" id="goal-delete-zone" hidden>
            <button type="button" onclick="deleteCurrentGoal()">Excluir meta</button>
            <span>O histórico das movimentações não será apagado.</span>
        </div>

        <div class="fixed-reference-dialog-actions">
            <button class="fixed-cancel-button" type="button" onclick="document.getElementById('goal-dialog').close()">Cancelar</button>
            <button class="fixed-save-button" type="submit">Salvar meta ✓</button>
        </div>
    </form>
</dialog>

<form method="post" action="/acao" id="delete-goal-form" hidden>
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="return_to" value="/metas">
    <input type="hidden" name="return_tab" value="gastos">
    <input type="hidden" name="month" value="<?= e($month) ?>">
    <input type="hidden" name="action" value="delete_goal">
    <input type="hidden" name="goal_id" id="delete-goal-id">
</form>

<dialog id="investment-goal-dialog" class="fixed-reference-dialog fixed-reference-small-dialog">
    <form method="post" action="/acao" class="fixed-reference-dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="return_to" value="/metas">
        <input type="hidden" name="return_tab" value="investimento">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="action" value="set_investment_goal">

        <div class="fixed-reference-dialog-head">
            <div>
                <h3>Meta de investimento</h3>
                <p>Defina quanto a família pretende investir por mês.</p>
            </div>
            <button type="button" onclick="document.getElementById('investment-goal-dialog').close()">×</button>
        </div>

        <label>Meta mensal (R$)
            <input type="number" name="value" min="0" step="0.01" value="<?= e($investmentGoal) ?>" required>
        </label>

        <div class="fixed-reference-dialog-actions">
            <button class="fixed-cancel-button" type="button" onclick="document.getElementById('investment-goal-dialog').close()">Cancelar</button>
            <button class="fixed-save-button" type="submit">Salvar meta ✓</button>
        </div>
    </form>
</dialog>

<script>
let currentGoalId = null;

function openGoalCreate() {
    currentGoalId = null;
    document.getElementById('goal-form').reset();
    document.getElementById('goal-action').value = 'add_goal';
    document.getElementById('goal-id').value = '';
    document.getElementById('goal-dialog-title').textContent = 'Nova meta';
    document.getElementById('goal-dialog-copy').textContent = 'Defina um limite mensal para uma categoria de gasto.';
    document.getElementById('goal-delete-zone').hidden = true;
    document.getElementById('goal-dialog').showModal();
}

function openGoalEdit(id, category, limit) {
    currentGoalId = id;
    document.getElementById('goal-action').value = 'update_goal';
    document.getElementById('goal-id').value = id;
    document.getElementById('goal-category').value = category;
    document.getElementById('goal-limit').value = limit;
    document.getElementById('goal-dialog-title').textContent = 'Editar meta';
    document.getElementById('goal-dialog-copy').textContent = 'Ajuste o nome da categoria ou o limite mensal.';
    document.getElementById('goal-delete-zone').hidden = false;
    document.getElementById('goal-dialog').showModal();
}

function deleteCurrentGoal() {
    if (!currentGoalId) return;
    if (!confirm('Excluir esta meta? As movimentações da categoria serão preservadas.')) return;

    document.getElementById('delete-goal-id').value = currentGoalId;
    document.getElementById('delete-goal-form').submit();
}
</script>
</body>
</html>
