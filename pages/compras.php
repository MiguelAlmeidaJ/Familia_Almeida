<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/shopping.php';

$user = require_auth();
$pdo = db();
$csrf = csrf_token();
$flash = pull_flash();

$month = valid_month($_GET['month'] ?? null);
$tab = (($_GET['tab'] ?? '') === 'moveis') ? 'moveis' : 'mercado';
$schemaReady = shopping_schema_ready($pdo);

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;

$currentDate = new DateTimeImmutable($month . '-01');
$prevMonth = $currentDate->modify('-1 month')->format('Y-m');
$nextMonth = $currentDate->modify('+1 month')->format('Y-m');

$marketList = null;
$marketItems = [];
$marketSummary = ['estimated' => 0.0, 'actual' => 0.0, 'purchased' => 0, 'pending' => 0];
$previousMarketList = null;

$furnitureList = null;
$furnitureItems = [];
$furnitureTotal = 0.0;
$priorityCounts = ['high' => 0, 'medium' => 0, 'low' => 0];

if ($schemaReady) {
    $marketList = shopping_get_list($pdo, 'market', $month, (int) $user['id'], true);
    $marketItems = $marketList ? shopping_items($pdo, (int) $marketList['id']) : [];
    $marketSummary = shopping_market_summary($marketItems);
    $previousMarketList = shopping_previous_market_list($pdo, $month);

    $furnitureList = shopping_get_list($pdo, 'furniture', null, (int) $user['id'], true);
    $furnitureItems = $furnitureList ? shopping_items($pdo, (int) $furnitureList['id']) : [];

    foreach ($furnitureItems as $item) {
        $furnitureTotal += (float) ($item['estimated_price'] ?? 0);
        $priority = (string) ($item['priority'] ?? 'medium');
        if (isset($priorityCounts[$priority])) {
            $priorityCounts[$priority]++;
        }
    }
}

$priorityLabels = ['high' => 'Alta', 'medium' => 'Média', 'low' => 'Baixa'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Compras • Família Almeida</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="shell ref-shell">
    <?php render_sidebar('compras', $csrf); ?>

    <main>
        <?php render_topbar($user); ?>

        <div class="content ref-dashboard shopping-page">
            <section class="ref-dashboard-heading shopping-heading">
                <div>
                    <p class="eyebrow">PLANEJAR ANTES DE COMPRAR</p>
                    <h1>Lista de compras</h1>
                    <p>Organize o mercado do mês e as compras maiores da casa em um só lugar.</p>
                </div>

                <?php if ($tab === 'mercado'): ?>
                    <div class="ref-month-picker">
                        <a href="/compras?month=<?= e($prevMonth) ?>&tab=mercado" aria-label="Mês anterior">‹</a>
                        <span><?= e($monthLabel) ?></span>
                        <span class="ref-calendar">▣</span>
                        <a href="/compras?month=<?= e($nextMonth) ?>&tab=mercado" aria-label="Próximo mês">›</a>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($flash): ?>
                <div class="alert <?= $flash['type'] === 'success' ? 'success' : '' ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (!$schemaReady): ?>
                <div class="alert migration-alert">
                    A estrutura da lista de compras ainda não foi criada.
                    <a href="/configuracoes/manutencao">Executar migration →</a>
                </div>
            <?php endif; ?>

            <nav class="shopping-tabs">
                <a class="<?= $tab === 'mercado' ? 'active' : '' ?>" href="/compras?month=<?= e($month) ?>&tab=mercado">
                    <span>🛒</span>
                    <div><strong>Mercado</strong><small>Lista mensal e modo compra</small></div>
                </a>
                <a class="<?= $tab === 'moveis' ? 'active' : '' ?>" href="/compras?month=<?= e($month) ?>&tab=moveis">
                    <span>⌂</span>
                    <div><strong>Móveis</strong><small>Prioridades para a casa</small></div>
                </a>
            </nav>

            <?php if ($tab === 'mercado'): ?>
                <section class="shopping-summary-grid">
                    <article>
                        <span>Estimado</span>
                        <strong><?= money($marketSummary['estimated']) ?></strong>
                        <small><?= count($marketItems) ?> produto(s) na lista</small>
                    </article>
                    <article class="positive">
                        <span>Já comprado</span>
                        <strong><?= money($marketSummary['actual']) ?></strong>
                        <small><?= (int) $marketSummary['purchased'] ?> item(ns) concluído(s)</small>
                    </article>
                    <article>
                        <span>Pendentes</span>
                        <strong><?= (int) $marketSummary['pending'] ?></strong>
                        <small>produto(s) ainda na lista</small>
                    </article>
                </section>

                <section class="card shopping-list-card">
                    <header class="shopping-card-head">
                        <div>
                            <p class="eyebrow">MERCADO DE <?= e(strtoupper($monthNames[$monthNumber])) ?></p>
                            <h2>Lista do mês</h2>
                            <p>Preço aproximado é por unidade. O valor real é informado durante a compra.</p>
                        </div>

                        <div class="shopping-card-actions">
                            <?php if ($previousMarketList): ?>
                                <form method="post" action="/acao">
                                    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="return_to" value="/compras">
                                    <input type="hidden" name="return_tab" value="mercado">
                                    <input type="hidden" name="month" value="<?= e($month) ?>">
                                    <input type="hidden" name="action" value="copy_market_previous">
                                    <button class="shopping-secondary-button" type="submit">↙ Puxar mês passado</button>
                                </form>
                            <?php endif; ?>

                            <button class="shopping-secondary-button" type="button" onclick="openMarketItemCreate()" <?= !$schemaReady ? 'disabled' : '' ?>>＋ Produto</button>
                            <a class="ref-green-button shopping-mode-button <?= !$schemaReady || !$marketItems ? 'disabled' : '' ?>" href="<?= $schemaReady && $marketItems ? '/compras/mercado?month=' . e($month) : '#' ?>">Usar durante a compra →</a>
                        </div>
                    </header>

                    <div class="shopping-market-list">
                        <?php if ($marketItems): ?>
                            <?php foreach ($marketItems as $item): ?>
                                <article class="shopping-market-row <?= $item['purchased'] ? 'is-purchased' : '' ?>">
                                    <div class="shopping-market-check"><?= $item['purchased'] ? '✓' : '' ?></div>

                                    <div class="shopping-market-copy">
                                        <div>
                                            <strong><?= e($item['name']) ?></strong>
                                            <?php if ($item['purchased']): ?><span class="shopping-done-tag">Comprado</span><?php endif; ?>
                                            <span class="shopping-stock-tag <?= !empty($item['track_inventory']) ? 'stock' : 'quick' ?>">
                                                <?= !empty($item['track_inventory']) ? 'Estoque' : 'Consumo rápido' ?>
                                            </span>
                                        </div>
                                        <span>
                                            Qtd. <?= e(rtrim(rtrim(number_format(
                                                (float) ($item['purchased'] ? ($item['purchased_quantity'] ?? $item['quantity']) : $item['quantity']),
                                                2,
                                                ',',
                                                '.'
                                            ), '0'), ',')) ?>
                                            <?php if ($item['purchased']): ?>
                                                • planejado <?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',')) ?>
                                            <?php endif; ?>
                                            <?php if ($item['purchased'] && $item['store_name']): ?>
                                                • <?= e($item['store_name']) ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <div class="shopping-price-column">
                                        <small><?= $item['purchased'] ? 'Preço comprado' : 'Preço aproximado' ?></small>
                                        <strong>
                                            <?= $item['purchased']
                                                ? money($item['purchased_price'])
                                                : ($item['estimated_price'] !== null ? money($item['estimated_price']) : '—') ?>
                                        </strong>
                                    </div>

                                    <div class="shopping-line-total">
                                        <small>Total</small>
                                        <strong>
                                            <?php
                                            $unit = $item['purchased']
                                                ? (float) ($item['purchased_price'] ?? 0)
                                                : (float) ($item['estimated_price'] ?? 0);
                                            $quantityUsed = $item['purchased']
                                                ? (float) ($item['purchased_quantity'] ?? $item['quantity'])
                                                : (float) $item['quantity'];
                                            echo $unit > 0 ? money($unit * $quantityUsed) : '—';
                                            ?>
                                        </strong>
                                    </div>

                                    <div class="shopping-row-actions">
                                        <?php if (!$item['purchased']): ?>
                                            <button
                                                class="fixed-icon-action"
                                                type="button"
                                                title="Editar"
                                                onclick='openMarketItemEdit(
                                                    <?= (int) $item["id"] ?>,
                                                    <?= json_encode($item["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                    <?= json_encode($item["quantity"]) ?>,
                                                    <?= json_encode($item["estimated_price"]) ?>,
                                                    <?= !empty($item["track_inventory"]) ? 'true' : 'false' ?>
                                                )'
                                            >✎</button>

                                            <form method="post" action="/acao" onsubmit="return confirm('Remover este produto da lista?')">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                                <input type="hidden" name="return_to" value="/compras">
                                                <input type="hidden" name="return_tab" value="mercado">
                                                <input type="hidden" name="month" value="<?= e($month) ?>">
                                                <input type="hidden" name="action" value="delete_market_item">
                                                <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                                <button class="fixed-icon-action danger" type="submit" title="Excluir">⌫</button>
                                            </form>
                                        <?php else: ?>
                                            <a class="shopping-history-link" href="/movimentacoes?month=<?= e($month) ?>">Ver gasto ↗</a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="shopping-empty-state">
                                <span>🛒</span>
                                <strong>Sua lista de <?= e($monthLabel) ?> está vazia</strong>
                                <p>Adicione os produtos manualmente ou importe a lista do mês anterior.</p>
                                <button class="ref-green-button" type="button" onclick="openMarketItemCreate()" <?= !$schemaReady ? 'disabled' : '' ?>>＋ Adicionar produto</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

            <?php else: ?>
                <section class="shopping-summary-grid furniture-summary">
                    <article>
                        <span>Estimativa total</span>
                        <strong><?= money($furnitureTotal) ?></strong>
                        <small><?= count($furnitureItems) ?> item(ns) planejado(s)</small>
                    </article>
                    <article class="priority-high">
                        <span>Prioridade alta</span>
                        <strong><?= (int) $priorityCounts['high'] ?></strong>
                        <small>compra(s) mais urgente(s)</small>
                    </article>
                    <article>
                        <span>Outras prioridades</span>
                        <strong><?= (int) $priorityCounts['medium'] + (int) $priorityCounts['low'] ?></strong>
                        <small>média e baixa</small>
                    </article>
                </section>

                <section class="card shopping-list-card">
                    <header class="shopping-card-head">
                        <div>
                            <p class="eyebrow">NOSSA CASA</p>
                            <h2>Prioridades de móveis</h2>
                            <p>Uma lista permanente para decidir o que comprar primeiro e quanto precisamos reservar.</p>
                        </div>

                        <button class="ref-green-button shopping-mode-button" type="button" onclick="openFurnitureCreate()" <?= !$schemaReady ? 'disabled' : '' ?>>＋ Adicionar item</button>
                    </header>

                    <div class="furniture-list">
                        <?php if ($furnitureItems): ?>
                            <?php foreach ($furnitureItems as $item): ?>
                                <article class="furniture-row">
                                    <div class="furniture-priority <?= e((string) $item['priority']) ?>">
                                        <?= e($priorityLabels[$item['priority']] ?? 'Média') ?>
                                    </div>

                                    <div class="furniture-copy">
                                        <strong><?= e($item['name']) ?></strong>
                                        <span><?= e($item['category'] ?: 'Sem categoria') ?></span>
                                    </div>

                                    <div class="furniture-price">
                                        <small>Estimativa</small>
                                        <strong><?= $item['estimated_price'] !== null ? money($item['estimated_price']) : '—' ?></strong>
                                    </div>

                                    <div class="furniture-link">
                                        <?php if ($item['product_url']): ?>
                                            <a href="<?= e($item['product_url']) ?>" target="_blank" rel="noopener noreferrer">Abrir produto ↗</a>
                                        <?php else: ?>
                                            <span>Sem link</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="shopping-row-actions">
                                        <button
                                            class="fixed-icon-action"
                                            type="button"
                                            title="Editar"
                                            onclick='openFurnitureEdit(
                                                <?= (int) $item["id"] ?>,
                                                <?= json_encode($item["name"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                <?= json_encode($item["category"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                <?= json_encode($item["priority"]) ?>,
                                                <?= json_encode($item["estimated_price"]) ?>,
                                                <?= json_encode($item["product_url"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                                            )'
                                        >✎</button>

                                        <form method="post" action="/acao" onsubmit="return confirm('Remover este item da lista de móveis?')">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="return_to" value="/compras">
                                            <input type="hidden" name="return_tab" value="moveis">
                                            <input type="hidden" name="month" value="<?= e($month) ?>">
                                            <input type="hidden" name="action" value="delete_furniture_item">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                            <button class="fixed-icon-action danger" type="submit" title="Excluir">⌫</button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="shopping-empty-state">
                                <span>⌂</span>
                                <strong>Nenhum móvel planejado ainda</strong>
                                <p>Cadastre o que a casa precisa e use a prioridade para organizar as próximas compras.</p>
                                <button class="ref-green-button" type="button" onclick="openFurnitureCreate()" <?= !$schemaReady ? 'disabled' : '' ?>>＋ Adicionar primeiro item</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <footer class="ref-page-footer shopping-page-footer">
                <span>Família Almeida&nbsp;&nbsp; / &nbsp;&nbsp;Comprar com lista também é cuidar do orçamento.</span>
                <span><?= $tab === 'mercado' ? e(ucfirst($monthLabel)) : 'Lista permanente' ?></span>
            </footer>
        </div>
    </main>
</div>

<dialog id="market-item-dialog" class="fixed-reference-dialog">
    <form method="post" action="/acao" class="fixed-reference-dialog-form" id="market-item-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="return_to" value="/compras">
        <input type="hidden" name="return_tab" value="mercado">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="action" id="market-item-action" value="add_market_item">
        <input type="hidden" name="item_id" id="market-item-id">

        <div class="fixed-reference-dialog-head">
            <div>
                <h3 id="market-item-title">Adicionar produto</h3>
                <p>Monte a lista antes de sair para o mercado.</p>
            </div>
            <button type="button" onclick="document.getElementById('market-item-dialog').close()">×</button>
        </div>

        <label>Nome do produto
            <input name="name" id="market-item-name" maxlength="160" placeholder="Ex.: Leite integral" required>
        </label>

        <div class="fixed-reference-dialog-grid">
            <label>Quantidade
                <input type="number" name="quantity" id="market-item-quantity" min="0.01" step="0.01" value="1" required>
            </label>
            <label>Preço aproximado por unidade
                <input type="number" name="estimated_price" id="market-item-estimated" min="0" step="0.01" placeholder="0,00">
            </label>
        </div>

        <label>Destino do produto
            <select name="track_inventory" id="market-item-track" required>
                <option value="1">Vai para o estoque da casa</option>
                <option value="0">Consumo rápido — não entra no estoque</option>
            </select>
        </label>

        <div class="shopping-dialog-note">Use “Consumo rápido” para bombom, lanche, bebida consumida na hora e outros itens que não precisam de controle no estoque.</div>

        <div class="fixed-reference-dialog-actions">
            <button class="fixed-cancel-button" type="button" onclick="document.getElementById('market-item-dialog').close()">Cancelar</button>
            <button class="fixed-save-button" type="submit">Salvar produto ✓</button>
        </div>
    </form>
</dialog>

<dialog id="furniture-dialog" class="fixed-reference-dialog">
    <form method="post" action="/acao" class="fixed-reference-dialog-form" id="furniture-form">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="return_to" value="/compras">
        <input type="hidden" name="return_tab" value="moveis">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="action" id="furniture-action" value="add_furniture_item">
        <input type="hidden" name="item_id" id="furniture-id">

        <div class="fixed-reference-dialog-head">
            <div>
                <h3 id="furniture-title">Adicionar móvel</h3>
                <p>Registre o que a casa precisa e em qual ordem comprar.</p>
            </div>
            <button type="button" onclick="document.getElementById('furniture-dialog').close()">×</button>
        </div>

        <label>Nome
            <input name="name" id="furniture-name" maxlength="160" placeholder="Ex.: Mesa de jantar" required>
        </label>

        <div class="fixed-reference-dialog-grid">
            <label>Categoria
                <input name="category" id="furniture-category" maxlength="100" placeholder="Ex.: Sala" required>
            </label>
            <label>Prioridade
                <select name="priority" id="furniture-priority" required>
                    <option value="high">Alta</option>
                    <option value="medium" selected>Média</option>
                    <option value="low">Baixa</option>
                </select>
            </label>
        </div>

        <label>Preço estimado
            <input type="number" name="estimated_price" id="furniture-estimated" min="0" step="0.01" placeholder="0,00">
        </label>

        <label>Link do produto
            <input type="url" name="product_url" id="furniture-url" maxlength="700" placeholder="https://...">
        </label>

        <div class="fixed-reference-dialog-actions">
            <button class="fixed-cancel-button" type="button" onclick="document.getElementById('furniture-dialog').close()">Cancelar</button>
            <button class="fixed-save-button" type="submit">Salvar item ✓</button>
        </div>
    </form>
</dialog>

<script>
function openMarketItemCreate() {
    const form = document.getElementById('market-item-form');
    form.reset();
    document.getElementById('market-item-action').value = 'add_market_item';
    document.getElementById('market-item-id').value = '';
    document.getElementById('market-item-quantity').value = '1';
    document.getElementById('market-item-track').value = '1';
    document.getElementById('market-item-title').textContent = 'Adicionar produto';
    document.getElementById('market-item-dialog').showModal();
}

function openMarketItemEdit(id, name, quantity, estimatedPrice, trackInventory) {
    document.getElementById('market-item-action').value = 'update_market_item';
    document.getElementById('market-item-id').value = id;
    document.getElementById('market-item-name').value = name;
    document.getElementById('market-item-quantity').value = quantity;
    document.getElementById('market-item-estimated').value = estimatedPrice || '';
    document.getElementById('market-item-track').value = trackInventory ? '1' : '0';
    document.getElementById('market-item-title').textContent = 'Editar produto';
    document.getElementById('market-item-dialog').showModal();
}

function openFurnitureCreate() {
    const form = document.getElementById('furniture-form');
    form.reset();
    document.getElementById('furniture-action').value = 'add_furniture_item';
    document.getElementById('furniture-id').value = '';
    document.getElementById('furniture-priority').value = 'medium';
    document.getElementById('furniture-title').textContent = 'Adicionar móvel';
    document.getElementById('furniture-dialog').showModal();
}

function openFurnitureEdit(id, name, category, priority, estimatedPrice, productUrl) {
    document.getElementById('furniture-action').value = 'update_furniture_item';
    document.getElementById('furniture-id').value = id;
    document.getElementById('furniture-name').value = name;
    document.getElementById('furniture-category').value = category || '';
    document.getElementById('furniture-priority').value = priority || 'medium';
    document.getElementById('furniture-estimated').value = estimatedPrice || '';
    document.getElementById('furniture-url').value = productUrl || '';
    document.getElementById('furniture-title').textContent = 'Editar móvel';
    document.getElementById('furniture-dialog').showModal();
}
</script>
</body>
</html>
