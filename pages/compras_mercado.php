<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/shopping.php';

$user = require_auth();
$pdo = db();
$csrf = csrf_token();
$month = valid_month($_GET['month'] ?? null);
$schemaReady = shopping_schema_ready($pdo);
$quantityReady = $schemaReady && shopping_purchase_quantity_ready($pdo);

[$year, $monthNumber] = array_map('intval', explode('-', $month));
$monthNames = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$monthLabel = $monthNames[$monthNumber] . ' de ' . $year;

$list = $schemaReady ? shopping_get_list($pdo, 'market', $month, (int) $user['id'], true) : null;
$items = $list ? shopping_items($pdo, (int) $list['id']) : [];
$pendingItems = array_values(array_filter($items, fn(array $item) => !$item['purchased']));
$purchasedItems = array_values(array_filter($items, fn(array $item) => $item['purchased']));

$estimatedPending = 0.0;
foreach ($pendingItems as $item) {
    $estimatedPending += (float) $item['quantity'] * (float) ($item['estimated_price'] ?? 0);
}

$offlinePayload = [
    'csrfToken' => $csrf,
    'syncUrl' => '/api/compras/sincronizar',
    'listId' => $list ? (int) $list['id'] : 0,
    'month' => $month,
    'purchaseDate' => date('Y-m-d'),
    'items' => array_map(static fn(array $item): array => [
        'id' => (int) $item['id'],
        'name' => (string) $item['name'],
        'quantity' => (float) $item['quantity'],
        'purchased_quantity' => $item['purchased_quantity'] !== null ? (float) $item['purchased_quantity'] : null,
        'estimated_price' => (float) ($item['estimated_price'] ?? 0),
        'purchased' => (bool) $item['purchased'],
        'purchased_price' => $item['purchased_price'] !== null ? (float) $item['purchased_price'] : null,
        'store_name' => $item['store_name'],
    ], $items),
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#10343c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Modo compra • Família Almeida</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="shopping-mode-body">
<div class="shopping-mode-shell">
    <header class="shopping-mode-header">
        <div>
            <a href="/compras?month=<?= e($month) ?>&tab=mercado" class="shopping-mode-back">←</a>
            <div>
                <small>LISTA DE MERCADO</small>
                <h1><?= e(ucfirst($monthLabel)) ?></h1>
            </div>
        </div>

        <div class="shopping-connection-status" id="shopping-connection-status">
            <span></span>
            <strong>Verificando...</strong>
        </div>
    </header>

    <?php if (!$schemaReady): ?>
        <main class="shopping-mode-unavailable">
            <strong>Lista de compras ainda não configurada.</strong>
            <p>Execute a migration em Configurações → Manutenção antes de usar o modo compra.</p>
            <a href="/configuracoes/manutencao">Abrir manutenção</a>
        </main>
    <?php else: ?>
        <main class="shopping-mode-main">
            <?php if (!$quantityReady): ?>
                <div class="shopping-pending-sync-banner">
                    <strong>Atualização pendente.</strong>
                    <span>Execute a migration 007 para registrar a quantidade realmente comprada antes de sincronizar novas compras.</span>
                </div>
            <?php endif; ?>

            <section class="shopping-live-summary">
                <div>
                    <span>Selecionados</span>
                    <strong id="shopping-selected-count">0</strong>
                </div>
                <div>
                    <span>Estimado</span>
                    <strong id="shopping-selected-estimated"><?= money(0) ?></strong>
                </div>
                <div class="actual">
                    <span>Total da compra</span>
                    <strong id="shopping-selected-actual"><?= money(0) ?></strong>
                </div>
            </section>

            <div class="shopping-offline-banner" id="shopping-offline-banner" hidden>
                <strong>Você está sem internet.</strong>
                <span>Pode continuar marcando produtos e informando preços. Tudo fica salvo neste aparelho.</span>
            </div>

            <div class="shopping-pending-sync-banner" id="shopping-pending-sync-banner" hidden>
                <strong>Compra aguardando sincronização.</strong>
                <span>Assim que a internet voltar, vamos enviar os dados para o financeiro.</span>
            </div>

            <section class="shopping-use-card">
                <header>
                    <div>
                        <p class="eyebrow">DURANTE A COMPRA</p>
                        <h2>Produtos pendentes</h2>
                        <p>Marque o que colocou no carrinho e informe a quantidade e o preço reais.</p>
                    </div>
                    <strong><?= count($pendingItems) ?> item(ns)</strong>
                </header>

                <div class="shopping-live-list" id="shopping-live-list">
                    <?php if ($pendingItems): ?>
                        <?php foreach ($pendingItems as $item): ?>
                            <article
                                class="shopping-live-row"
                                data-shopping-item
                                data-item-id="<?= (int) $item['id'] ?>"
                                data-quantity="<?= e((string) $item['quantity']) ?>"
                                data-estimated-price="<?= e((string) ($item['estimated_price'] ?? 0)) ?>"
                            >
                                <label class="shopping-live-check">
                                    <input type="checkbox" data-field="selected">
                                    <span>✓</span>
                                </label>

                                <div class="shopping-live-product">
                                    <strong><?= e($item['name']) ?></strong>
                                    <span>
                                        Planejado: <?= e(rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',')) ?>
                                        • estimado
                                        <?= $item['estimated_price'] !== null ? money($item['estimated_price']) . '/un.' : 'não informado' ?>
                                    </span>
                                </div>

                                <div class="shopping-live-estimated">
                                    <small>Previsto</small>
                                    <strong>
                                        <?= $item['estimated_price'] !== null
                                            ? money((float) $item['estimated_price'] * (float) $item['quantity'])
                                            : '—' ?>
                                    </strong>
                                </div>

                                <div class="shopping-purchase-fields" data-purchase-fields hidden>
                                    <label class="shopping-quantity-field">
                                        <span>Qtd. comprada</span>
                                        <div class="shopping-quantity-control">
                                            <button type="button" data-qty-action="minus" aria-label="Diminuir quantidade">−</button>
                                            <input
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                inputmode="decimal"
                                                value="<?= e((string) $item['quantity']) ?>"
                                                data-field="purchased_quantity"
                                            >
                                            <button type="button" data-qty-action="plus" aria-label="Aumentar quantidade">＋</button>
                                        </div>
                                    </label>

                                    <label>
                                        <span>Preço comprado / un.</span>
                                        <input
                                            type="number"
                                            min="0.01"
                                            step="0.01"
                                            inputmode="decimal"
                                            placeholder="0,00"
                                            data-field="purchased_price"
                                        >
                                    </label>

                                    <label class="shopping-store-field">
                                        <span>Qual mercado?</span>
                                        <input
                                            type="text"
                                            maxlength="160"
                                            placeholder="Ex.: Bahamas"
                                            data-field="store_name"
                                        >
                                    </label>

                                    <div class="shopping-line-total-box">
                                        <span>Total deste produto</span>
                                        <strong data-line-total><?= money(0) ?></strong>
                                    </div>
                                </div>

                                <span class="shopping-sync-lock" data-sync-lock hidden>Aguardando internet</span>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="shopping-mode-empty">
                            <span>✓</span>
                            <strong>Nenhum produto pendente</strong>
                            <p>A lista deste mês já foi concluída ou ainda não possui produtos.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($purchasedItems): ?>
                <details class="shopping-bought-section">
                    <summary>
                        <span>Já comprados neste mês</span>
                        <strong><?= count($purchasedItems) ?></strong>
                    </summary>

                    <div>
                        <?php foreach ($purchasedItems as $item): ?>
                            <article>
                                <span>✓</span>
                                <div>
                                    <strong><?= e($item['name']) ?></strong>
                                    <small>
                                        <?= e($item['store_name'] ?: 'Mercado não informado') ?>
                                        • qtd. <?= e(rtrim(rtrim(number_format((float) ($item['purchased_quantity'] ?? $item['quantity']), 2, ',', '.'), '0'), ',')) ?>
                                        • <?= money((float) ($item['purchased_price'] ?? 0)) ?>/un.
                                    </small>
                                </div>
                                <strong><?= money((float) ($item['purchased_price'] ?? 0) * (float) ($item['purchased_quantity'] ?? $item['quantity'])) ?></strong>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

            <section class="shopping-offline-info">
                <span>☁</span>
                <div>
                    <strong>Preparada para funcionar sem internet e sem login</strong>
                    <p>Depois de abrir esta tela online uma vez, a lista fica salva neste aparelho. A versão offline não depende da sessão do sistema.</p>
                    <a class="shopping-offline-open-link" href="/compras/offline">Abrir versão offline →</a>
                </div>
            </section>
        </main>

        <footer class="shopping-mode-footer">
            <div>
                <span>Total selecionado</span>
                <strong id="shopping-footer-total"><?= money(0) ?></strong>
            </div>
            <button type="button" id="shopping-finalize-button" disabled>Finalizar compra</button>
        </footer>
    <?php endif; ?>
</div>

<?php if ($schemaReady): ?>
<script>
window.shoppingOfflineConfig = <?= json_encode(
    $offlinePayload,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?>;
</script>
<script src="/assets/shopping.js"></script>
<?php endif; ?>
</body>
</html>
