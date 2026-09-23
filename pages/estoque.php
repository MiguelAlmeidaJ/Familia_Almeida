<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/inventory.php';

$user = require_auth();
$pdo = db();
$csrf = csrf_token();
$flash = pull_flash();
$schemaReady = inventory_schema_ready($pdo);

$items = $schemaReady ? inventory_items_with_balance($pdo) : [];
$recentMovements = $schemaReady ? inventory_recent_movements($pdo, 18) : [];

$totalProducts = count($items);
$lowStock = 0;
$outOfStock = 0;

foreach ($items as $item) {
    if ($item['current_quantity'] <= 0) {
        $outOfStock++;
    } elseif ($item['min_quantity'] > 0 && $item['current_quantity'] <= $item['min_quantity']) {
        $lowStock++;
    }
}

$monthMovementCount = 0;
if ($schemaReady) {
    $monthStart = date('Y-m-01 00:00:00');
    $nextMonth = (new DateTimeImmutable(date('Y-m-01')))->modify('+1 month')->format('Y-m-d 00:00:00');

    $movementCountStmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM inventory_movements
         WHERE occurred_at >= ? AND occurred_at < ?'
    );
    $movementCountStmt->execute([$monthStart, $nextMonth]);
    $monthMovementCount = (int) $movementCountStmt->fetchColumn();
}

function inventory_status(array $item): array
{
    if ($item['current_quantity'] <= 0) {
        return ['out', 'Sem estoque'];
    }

    if ($item['min_quantity'] > 0 && $item['current_quantity'] <= $item['min_quantity']) {
        return ['low', 'Repor'];
    }

    return ['ok', 'Em estoque'];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Estoque • Família Almeida</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="shell ref-shell">
    <?php render_sidebar('estoque', $csrf); ?>

    <main>
        <?php render_topbar($user); ?>

        <div class="content ref-dashboard inventory-page">
            <section class="ref-dashboard-heading inventory-heading">
                <div>
                    <p class="eyebrow">O QUE TEMOS EM CASA</p>
                    <h1>Estoque</h1>
                    <p>Acompanhe o que entra, o que sai e o que está chegando na hora de repor.</p>
                </div>

                <button class="ref-green-button inventory-add-button" type="button" onclick="openInventoryCreate()" <?= !$schemaReady ? 'disabled' : '' ?>>
                    ＋ Adicionar produto
                </button>
            </section>

            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (!$schemaReady): ?>
                <div class="alert migration-alert">
                    A estrutura do estoque ainda não foi criada.
                    <a href="/configuracoes/manutencao">Executar migration →</a>
                </div>
            <?php endif; ?>

            <section class="inventory-summary-grid">
                <article>
                    <span>Produtos ativos</span>
                    <strong><?= $totalProducts ?></strong>
                    <small>itens acompanhados em casa</small>
                </article>
                <article class="<?= $lowStock ? 'attention' : '' ?>">
                    <span>Precisam repor</span>
                    <strong><?= $lowStock ?></strong>
                    <small>abaixo ou no estoque mínimo</small>
                </article>
                <article class="<?= $outOfStock ? 'danger' : '' ?>">
                    <span>Sem estoque</span>
                    <strong><?= $outOfStock ?></strong>
                    <small>produtos zerados</small>
                </article>
                <article>
                    <span>Movimentações no mês</span>
                    <strong><?= $monthMovementCount ?></strong>
                    <small>entradas e saídas registradas</small>
                </article>
            </section>

            <section class="card inventory-list-card">
                <header class="inventory-card-head">
                    <div>
                        <p class="eyebrow">SALDO ATUAL</p>
                        <h2>Produtos em casa</h2>
                        <p>Compras de mercado entram automaticamente aqui depois de finalizadas.</p>
                    </div>

                    <div class="inventory-search-wrap">
                        <span>⌕</span>
                        <input type="search" id="inventory-search" placeholder="Buscar produto...">
                    </div>
                </header>

                <div class="inventory-table-head">
                    <span>Produto</span>
                    <span>Saldo</span>
                    <span>Mínimo</span>
                    <span>Status</span>
                    <span></span>
                </div>

                <div class="inventory-list" id="inventory-list">
                    <?php if ($items): ?>
                        <?php foreach ($items as $item): ?>
                            <?php [$statusClass, $statusLabel] = inventory_status($item); ?>
                            <article
                                class="inventory-row"
                                data-inventory-row
                                data-name="<?= e(strtolower($item['name'] . ' ' . ($item['category'] ?? ''))) ?>"
                            >
                                <div class="inventory-product">
                                    <div class="inventory-product-icon"><?= e(strtoupper(substr((string) $item['name'], 0, 1))) ?></div>
                                    <div>
                                        <strong><?= e($item['name']) ?></strong>
                                        <span>
                                            <?= e($item['category'] ?: 'Sem categoria') ?>
                                            <?php if ($item['last_movement_at']): ?>
                                                • atualizado <?= e(date('d/m', strtotime($item['last_movement_at']))) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="inventory-balance <?= $statusClass ?>">
                                    <strong><?= e(inventory_quantity_label((float) $item['current_quantity'], (string) $item['unit'])) ?></strong>
                                </div>

                                <div class="inventory-minimum">
                                    <?= $item['min_quantity'] > 0
                                        ? e(inventory_quantity_label((float) $item['min_quantity'], (string) $item['unit']))
                                        : '—' ?>
                                </div>

                                <div>
                                    <span class="inventory-status <?= $statusClass ?>"><?= e($statusLabel) ?></span>
                                </div>

                                <div class="inventory-actions">
                                    <button
                                        class="inventory-move-button entry"
                                        type="button"
                                        onclick='openInventoryMovement(
                                            <?= (int) $item["id"] ?>,
                                            <?= json_encode($item["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                            <?= json_encode($item["unit"]) ?>,
                                            <?= json_encode($item["current_quantity"]) ?>,
                                            "entry"
                                        )'
                                    >＋ Entrada</button>

                                    <button
                                        class="inventory-move-button exit"
                                        type="button"
                                        <?= $item['current_quantity'] <= 0 ? 'disabled' : '' ?>
                                        onclick='openInventoryMovement(
                                            <?= (int) $item["id"] ?>,
                                            <?= json_encode($item["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                            <?= json_encode($item["unit"]) ?>,
                                            <?= json_encode($item["current_quantity"]) ?>,
                                            "exit"
                                        )'
                                    >− Saída</button>

                                    <button
                                        class="fixed-icon-action"
                                        type="button"
                                        title="Editar produto"
                                        onclick='openInventoryEdit(
                                            <?= (int) $item["id"] ?>,
                                            <?= json_encode($item["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                            <?= json_encode($item["category"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                            <?= json_encode($item["unit"]) ?>,
                                            <?= json_encode($item["min_quantity"]) ?>
                                        )'
                                    >✎</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="inventory-empty">
                            <span>▣</span>
                            <strong>Estoque vazio</strong>
                            <p>Adicione um produto manualmente ou finalize uma compra de mercado para preencher o estoque.</p>
                            <button class="ref-green-button" type="button" onclick="openInventoryCreate()" <?= !$schemaReady ? 'disabled' : '' ?>>＋ Adicionar primeiro produto</button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="inventory-no-results" id="inventory-no-results" hidden>
                    Nenhum produto encontrado.
                </div>
            </section>

            <section class="card inventory-history-card">
                <header class="inventory-card-head">
                    <div>
                        <p class="eyebrow">HISTÓRICO</p>
                        <h2>Últimas movimentações</h2>
                        <p>Entradas de compras e movimentações feitas manualmente.</p>
                    </div>
                </header>

                <div class="inventory-history-list">
                    <?php if ($recentMovements): ?>
                        <?php foreach ($recentMovements as $movement): ?>
                            <article class="inventory-history-row">
                                <div class="inventory-history-icon <?= e($movement['movement_type']) ?>">
                                    <?= $movement['movement_type'] === 'entry' ? '↙' : '↗' ?>
                                </div>

                                <div class="inventory-history-copy">
                                    <div>
                                        <strong><?= e($movement['item_name']) ?></strong>
                                        <span class="inventory-source-tag <?= e($movement['source_type']) ?>">
                                            <?= $movement['source_type'] === 'purchase' ? 'Compra de mercado' : 'Manual' ?>
                                        </span>
                                    </div>
                                    <span>
                                        <?= e(date('d/m/Y H:i', strtotime($movement['occurred_at']))) ?>
                                        <?php if ($movement['note']): ?> • <?= e($movement['note']) ?><?php endif; ?>
                                    </span>
                                </div>

                                <strong class="inventory-history-quantity <?= e($movement['movement_type']) ?>">
                                    <?= $movement['movement_type'] === 'entry' ? '+' : '−' ?>
                                    <?= e(inventory_quantity_label((float) $movement['quantity'], (string) $movement['unit'])) ?>
                                </strong>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="inventory-empty small">
                            <span>↕</span>
                            <strong>Nenhuma movimentação ainda</strong>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <footer class="ref-page-footer inventory-page-footer">
                <span>Família Almeida&nbsp;&nbsp; / &nbsp;&nbsp;Saber o que já temos também evita gastar duas vezes.</span>
                <span>Saldo atualizado pelo histórico</span>
            </footer>
        </div>
    </main>
</div>

<dialog id="inventory-item-dialog" class="fixed-reference-dialog">
    <form method="post" action="/acao" class="fixed-reference-dialog-form" id="inventory-item-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="return_to" value="/estoque">
        <input type="hidden" name="month" value="<?= e(date('Y-m')) ?>">
        <input type="hidden" name="action" id="inventory-item-action" value="add_inventory_item">
        <input type="hidden" name="item_id" id="inventory-item-id">

        <div class="fixed-reference-dialog-head">
            <div>
                <h3 id="inventory-item-title">Adicionar produto</h3>
                <p id="inventory-item-copy">Cadastre um item que vocês querem acompanhar em casa.</p>
            </div>
            <button type="button" onclick="document.getElementById('inventory-item-dialog').close()">×</button>
        </div>

        <label>Nome do produto
            <input name="name" id="inventory-name" maxlength="160" placeholder="Ex.: Arroz" required>
        </label>

        <div class="fixed-reference-dialog-grid">
            <label>Categoria
                <input name="category" id="inventory-category" maxlength="100" placeholder="Ex.: Alimentos">
            </label>

            <label>Unidade
                <select name="unit" id="inventory-unit" required>
                    <option value="un">Unidade (un)</option>
                    <option value="kg">Quilo (kg)</option>
                    <option value="g">Grama (g)</option>
                    <option value="L">Litro (L)</option>
                    <option value="ml">Mililitro (ml)</option>
                    <option value="pct">Pacote (pct)</option>
                    <option value="cx">Caixa (cx)</option>
                </select>
            </label>
        </div>

        <div class="fixed-reference-dialog-grid">
            <label>Estoque mínimo
                <input type="number" name="min_quantity" id="inventory-minimum" min="0" step="0.001" value="0">
            </label>

            <label id="inventory-initial-field">Quantidade inicial
                <input type="number" name="initial_quantity" id="inventory-initial" min="0" step="0.001" value="0">
            </label>
        </div>

        <div class="shopping-dialog-note">Quando o saldo chegar ao estoque mínimo, o produto será destacado para reposição.</div>

        <div class="fixed-reference-dialog-actions">
            <button class="fixed-cancel-button" type="button" onclick="document.getElementById('inventory-item-dialog').close()">Cancelar</button>
            <button class="fixed-save-button" type="submit">Salvar produto ✓</button>
        </div>
    </form>
</dialog>

<dialog id="inventory-movement-dialog" class="fixed-reference-dialog fixed-reference-small-dialog">
    <form method="post" action="/acao" class="fixed-reference-dialog-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="return_to" value="/estoque">
        <input type="hidden" name="month" value="<?= e(date('Y-m')) ?>">
        <input type="hidden" name="action" value="inventory_movement">
        <input type="hidden" name="item_id" id="inventory-movement-item-id">
        <input type="hidden" name="movement_type" id="inventory-movement-type">
        <input type="hidden" name="unit" id="inventory-movement-unit">

        <div class="fixed-reference-dialog-head">
            <div>
                <h3 id="inventory-movement-title">Movimentar estoque</h3>
                <p id="inventory-movement-copy"></p>
            </div>
            <button type="button" onclick="document.getElementById('inventory-movement-dialog').close()">×</button>
        </div>

        <label>Quantidade
            <input type="number" name="quantity" id="inventory-movement-quantity" min="0.001" step="0.001" required>
        </label>

        <label>Data
            <input type="date" name="occurred_on" value="<?= e(date('Y-m-d')) ?>" required>
        </label>

        <label>Observação
            <input name="note" maxlength="255" placeholder="Ex.: consumimos, ganhou de alguém, correção...">
        </label>

        <div class="fixed-reference-dialog-actions">
            <button class="fixed-cancel-button" type="button" onclick="document.getElementById('inventory-movement-dialog').close()">Cancelar</button>
            <button class="fixed-save-button" id="inventory-movement-submit" type="submit">Registrar ✓</button>
        </div>
    </form>
</dialog>

<script>
const searchInput = document.getElementById('inventory-search');
const inventoryRows = Array.from(document.querySelectorAll('[data-inventory-row]'));
const noResults = document.getElementById('inventory-no-results');

searchInput?.addEventListener('input', () => {
    const term = searchInput.value.trim().toLowerCase();
    let visible = 0;

    inventoryRows.forEach((row) => {
        const matches = !term || row.dataset.name.includes(term);
        row.hidden = !matches;
        if (matches) visible++;
    });

    if (noResults) noResults.hidden = visible > 0 || !term;
});

function openInventoryCreate() {
    const form = document.getElementById('inventory-item-form');
    form.reset();
    document.getElementById('inventory-item-action').value = 'add_inventory_item';
    document.getElementById('inventory-item-id').value = '';
    document.getElementById('inventory-item-title').textContent = 'Adicionar produto';
    document.getElementById('inventory-item-copy').textContent = 'Cadastre um item que vocês querem acompanhar em casa.';
    document.getElementById('inventory-unit').value = 'un';
    document.getElementById('inventory-minimum').value = '0';
    document.getElementById('inventory-initial').value = '0';
    document.getElementById('inventory-initial-field').hidden = false;
    document.getElementById('inventory-item-dialog').showModal();
}

function openInventoryEdit(id, name, category, unit, minimum) {
    document.getElementById('inventory-item-action').value = 'update_inventory_item';
    document.getElementById('inventory-item-id').value = id;
    document.getElementById('inventory-name').value = name;
    document.getElementById('inventory-category').value = category || '';
    document.getElementById('inventory-unit').value = unit || 'un';
    document.getElementById('inventory-minimum').value = minimum || 0;
    document.getElementById('inventory-item-title').textContent = 'Editar produto';
    document.getElementById('inventory-item-copy').textContent = 'Altere dados do produto sem mexer no histórico de entradas e saídas.';
    document.getElementById('inventory-initial-field').hidden = true;
    document.getElementById('inventory-item-dialog').showModal();
}

function openInventoryMovement(id, name, unit, balance, type) {
    const isEntry = type === 'entry';

    document.getElementById('inventory-movement-item-id').value = id;
    document.getElementById('inventory-movement-type').value = type;
    document.getElementById('inventory-movement-unit').value = unit;
    document.getElementById('inventory-movement-quantity').value = '';

    document.getElementById('inventory-movement-title').textContent = isEntry ? 'Registrar entrada' : 'Registrar saída';
    document.getElementById('inventory-movement-copy').textContent =
        name + ' • saldo atual: ' + Number(balance).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) + ' ' + unit;

    const submit = document.getElementById('inventory-movement-submit');
    submit.textContent = isEntry ? 'Adicionar ao estoque ✓' : 'Dar baixa ✓';
    submit.classList.toggle('inventory-exit-submit', !isEntry);

    document.getElementById('inventory-movement-dialog').showModal();
}
</script>
</body>
</html>
