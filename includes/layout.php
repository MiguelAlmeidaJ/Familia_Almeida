<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/branding.php';

function nav_icon(string $name): string
{
    $bootstrapIcons = ['relatorios'=>'bi-graph-up-arrow','dashboard'=>'bi-grid-1x2-fill','movimentacoes'=>'bi-arrow-left-right','contas'=>'bi-receipt-cutoff','categorias'=>'bi-bookmarks','configuracoes'=>'bi-sliders2','meu-perfil'=>'bi-person-circle','membros'=>'bi-people','logout'=>'bi-box-arrow-right','home'=>'bi-house','shield'=>'bi-shield-check','heart'=>'bi-heart','chevron'=>'bi-chevron-right','menu'=>'bi-list'];
    if (isset($bootstrapIcons[$name])) return '<i class="bi ' . $bootstrapIcons[$name] . '" aria-hidden="true"></i>';
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>',
        'movimentacoes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="M7 9h10M7 13h6"/></svg>',
        'categorias' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>',
        'contas' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18H6z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg>',
        'cartoes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/></svg>',
        'metas' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="m15 9 4-4"/></svg>',
        'dividas' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="7" width="16" height="10" rx="2"/><path d="M4 11h16"/></svg>',
        'compras' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16l-1.5 12h-13z"/><path d="M8 7a4 4 0 0 1 8 0M8 11v4M16 11v4"/></svg>',
        'estoque' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8 12 4l8 4-8 4z"/><path d="M4 8v8l8 4 8-4V8M12 12v8"/></svg>',
        'investimentos' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 17 9 12l4 3 7-8"/><path d="M15 7h5v5"/></svg>',
        'configuracoes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.08-1l2-1.55-2-3.46-2.45 1A7 7 0 0 0 14.7 6l-.36-2.63h-4L10 6a7 7 0 0 0-1.77 1L5.78 6l-2 3.46L5.8 11a7 7 0 0 0 0 2l-2 1.55 2 3.46 2.45-1A7 7 0 0 0 10 18l.36 2.63h4L14.7 18a7 7 0 0 0 1.77-1l2.45 1 2-3.46L18.92 13c.05-.33.08-.66.08-1Z"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H5v14h5M14 8l4 4-4 4M8 12h10"/></svg>',
        'home' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 11 8-7 8 7v9H4z"/><path d="M9 20v-6h6v6"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.7 2.8 8 7 10 4.2-2 7-5.3 7-10V6z"/><path d="m9.5 12 1.7 1.7L15 10"/></svg>',
        'heart' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 8.5c0 5-8 10-8 10s-8-5-8-10A4.5 4.5 0 0 1 12 5a4.5 4.5 0 0 1 8 3.5Z"/><path d="M8.5 10h2l1-2 2 4 1-2h2"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
    ];

    return $icons[$name] ?? '';
}

function render_sidebar(string $active, string $csrf): void
{
    $items = [
        ['key' => 'dashboard', 'href' => '/', 'label' => 'Painel'],
        ['key' => 'relatorios', 'href' => '/relatorios', 'label' => 'Relatórios'],
        ['key' => 'movimentacoes', 'href' => '/movimentacoes', 'label' => 'Lançamentos'],
        ['key' => 'contas', 'href' => '/contas', 'label' => 'Contas previstas'],
        ['key' => 'categorias', 'href' => '/categorias', 'label' => 'Categorias'],
        ['key' => 'membros', 'href' => '/membros', 'label' => 'Membros'],
        ['key' => 'meu-perfil', 'href' => '/meu-perfil', 'label' => 'Meu perfil'],
    ];

    $user = function_exists('current_user') ? current_user() : null;
    $initial = $user && !empty($user['name']) ? strtoupper(substr((string) $user['name'], 0, 1)) : 'F';
    ?>
    <aside class="sidebar ref-sidebar" id="sidebar">
        <div class="ref-sidebar-head">
            <a class="ref-brand" href="/">
                <span class="ref-brand-icon"><?php if(brand_asset_url('logo')): ?><img src="<?= e(brand_asset_url('logo')) ?>" alt="Bemora"><?php else: ?><strong>bemora</strong><?php endif; ?></span>
                <span class="ref-brand-favicon"><?php if(brand_asset_url('favicon')): ?><img src="<?= e(brand_asset_url('favicon')) ?>" alt="Bemora"><?php else: ?><span aria-hidden="true">B</span><?php endif; ?></span>
                <span class="ref-brand-copy"><small>Área financeira</small></span>
            </a>
        </div>

        <nav class="side-nav ref-nav" aria-label="Menu principal">
            <?php foreach ($items as $item): ?>
                <a class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>" title="<?= e($item['label']) ?>">
                    <span class="nav-icon"><?= nav_icon($item['key']) ?></span>
                    <span class="nav-item-label"><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <div class="ref-nav-divider"></div>

            <a class="nav-item <?= $active === 'minha-conta' ? 'active' : '' ?>" href="/minha-conta" title="Configurações da conta">
                <span class="nav-icon"><?= nav_icon('configuracoes') ?></span>
                <span class="nav-item-label">Configurações</span>
            </a>
        </nav>

        <a class="sidebar-user ref-sidebar-user" href="/meu-perfil" title="Editar meu perfil" style="text-decoration:none;color:inherit">
            <div class="user-avatar"><?php if($user && !empty($user['avatar_path'])): ?><img src="/meu-perfil/foto?v=<?= e(substr((string)$user['avatar_path'],0,12)) ?>" alt="" style="width:100%;height:100%;border-radius:50%;object-fit:cover"><?php else: ?><?= e($initial) ?><?php endif; ?></div>
            <div class="sidebar-user-copy">
                <strong><?= e($user['name'] ?? 'Usuário') ?></strong>
                <span>Ver meu perfil</span>
            </div>
        </a>

        <form method="post" action="/sair" class="sidebar-logout">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <button class="logout modern-logout" type="submit" title="Sair da conta">
                <span class="nav-icon"><?= nav_icon('logout') ?></span>
                <span class="nav-item-label">Sair</span>
            </button>
        </form>
    </aside>

    <button class="sidebar-overlay" id="sidebarOverlay" type="button" aria-label="Fechar menu"></button>
    <script src="/assets/app.js" defer></script>
    <?php
}

function render_topbar(array $user, string $context = 'Espaço privado'): void
{
    $month = selected_month();
    $date = new DateTimeImmutable($month . '-01');
    $previous = $date->modify('-1 month')->format('Y-m');
    $next = $date->modify('+1 month')->format('Y-m');
    $monthNames = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    $monthLabel = $monthNames[(int)$date->format('n')] . ' de ' . $date->format('Y');
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (!str_starts_with($currentPath, '/') || str_starts_with($currentPath, '//')) $currentPath = '/';
    $monthLink = static fn(string $target): string => $currentPath . '?month=' . rawurlencode($target);
    ?>
    <header class="topbar ref-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle ref-collapse bemora-header-collapse" id="sidebarToggle" type="button" aria-label="Recolher menu" aria-expanded="true"><i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i></button>
            <button class="mobile-menu-toggle" id="mobileSidebarToggle" type="button" aria-label="Abrir menu" aria-expanded="false">
                <?= nav_icon('menu') ?>
            </button>
            <span class="ref-topbar-title">Seu dinheiro em ordem. Sua vida mais leve.</span>
        </div>

        <div class="topbar-month" aria-label="Mês do planejamento">
            <a href="<?= e($monthLink($previous)) ?>" aria-label="Mês anterior">‹</a>
            <label class="topbar-month-current" for="global-month-picker"><span><?= e($monthLabel) ?></span><input id="global-month-picker" type="month" value="<?= e($month) ?>" aria-label="Selecionar mês"></label>
            <a href="<?= e($monthLink($next)) ?>" aria-label="Próximo mês">›</a>
        </div>
        <div class="ref-private">
            <?= nav_icon('shield') ?>
            <span><?= e($context) ?></span>
        </div>
    </header>
    <?php
    // A single quick-entry action shared by authenticated family pages.
    $quickCsrf=csrf_token();
    $quickMonth=selected_month();
    $quickDate=$quickMonth===date('Y-m')?date('Y-m-d'):$quickMonth.'-01';
    $quickPath=(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
    $quickAllowed=['/','/movimentacoes','/contas','/categorias','/membros','/meu-perfil','/minha-conta'];
    $quickReturn=in_array($quickPath,$quickAllowed,true)?$quickPath:'/movimentacoes';
    ?>
    <link rel="stylesheet" href="/assets/bemora-quick-entry.css?v=2">
    <button class="bemora-fab" id="bemora-fab" type="button" aria-label="Novo lançamento" aria-haspopup="dialog" aria-controls="bemora-quick-dialog" title="Novo lançamento"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
    <dialog class="bemora-quick-dialog" id="bemora-quick-dialog" aria-labelledby="bemora-quick-title">
      <div class="bemora-quick-head"><div class="bemora-quick-symbol"><i class="bi bi-arrow-left-right"></i></div><div><h2 id="bemora-quick-title">Novo lançamento</h2><p>Registre uma movimentação em poucos passos.</p></div><button type="button" class="bemora-quick-close" id="bemora-quick-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div>
      <form method="post" action="/acao" enctype="multipart/form-data" id="bemora-quick-form">
        <input type="hidden" name="csrf_token" value="<?= e($quickCsrf) ?>">
        <input type="hidden" name="action" value="add_transaction">
        <input type="hidden" name="return_to" value="<?= e($quickReturn) ?>">
        <input type="hidden" name="month" value="<?= e($quickMonth) ?>">
        <fieldset class="bemora-quick-types"><legend>Tipo de movimentação</legend>
          <label><input type="radio" name="type" value="expense" checked><span><i class="bi bi-arrow-up-right"></i> Gasto</span></label>
          <label><input type="radio" name="type" value="income"><span><i class="bi bi-arrow-down-left"></i> Ganho</span></label>
          <label><input type="radio" name="type" value="investment"><span><i class="bi bi-graph-up-arrow"></i> Investimento</span></label>
        </fieldset>
        <div class="bemora-quick-fields"><label>Descrição<input class="form-control" name="description" maxlength="160" placeholder="Ex.: Mercado, serviço, pagamento..." required></label>
        <div class="bemora-quick-grid"><label>Valor (R$)<input class="form-control" name="amount" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="0,00" required></label>
        <label>Data<input class="form-control" name="date" type="date" value="<?= e($quickDate) ?>" required></label></div>
        <label>Categoria<input class="form-control" name="category" maxlength="100" placeholder="Ex.: Alimentação" list="bemora-quick-categories" autocomplete="off" required></label>
        <datalist id="bemora-quick-categories"></datalist>
        <label class="bemora-quick-attachment" id="bemora-quick-attachment">Comprovante <small>(opcional, somente gastos)</small><input type="file" name="receipt_files[]" multiple accept="image/jpeg,image/png,image/webp,image/heic,image/heif,application/pdf,.pdf"></label></div>
        <div class="bemora-quick-footer"><button class="btn btn-outline-secondary" id="bemora-quick-cancel" type="button">Cancelar</button><button class="btn btn-success" type="submit"><i class="bi bi-check-lg"></i> Salvar lançamento</button></div>
      </form>
    </dialog>
    <script src="/assets/bemora-quick-entry.js?v=1" defer></script>
    <link rel="stylesheet" href="/assets/bemora-global-actions.css?v=1">
    <button class="bemora-global-bill-fab" type="button" id="bemora-global-bill-fab" aria-label="Nova conta prevista" title="Nova conta prevista" aria-haspopup="dialog" aria-controls="<?= $quickPath==='/contas'?'bill-dialog':'bemora-global-bill-dialog' ?>"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i></button>
    <?php if($quickPath!=='/contas'): ?>
    <dialog id="bemora-global-bill-dialog" class="bemora-global-bill-dialog" aria-labelledby="bemora-global-bill-title">
      <div class="bemora-quick-head"><div class="bemora-quick-symbol"><i class="bi bi-calendar-check"></i></div><div><h2 id="bemora-global-bill-title">Nova conta prevista</h2><p>Organize o vencimento e a recorrência.</p></div><button type="button" id="bemora-global-bill-close" class="bemora-quick-close" aria-label="Fechar"><i class="bi bi-x-lg"></i></button></div>
      <form method="post" action="/acao" id="bemora-global-bill-form">
        <input type="hidden" name="csrf_token" value="<?= e($quickCsrf) ?>">
        <input type="hidden" name="action" value="add_bill">
        <input type="hidden" name="return_to" value="<?= e($quickReturn) ?>">
        <input type="hidden" name="month" value="<?= e($quickMonth) ?>">
        <div class="bemora-quick-fields">
          <label>Descrição<input class="form-control" name="name" maxlength="120" placeholder="Ex.: Aluguel, energia, internet" required></label>
          <div class="bemora-quick-grid"><label>Valor mensal (R$)<input class="form-control bemora-money" type="text" name="amount" inputmode="numeric" maxlength="16" placeholder="0,00"></label><label>Dia do vencimento<input class="form-control" type="number" name="due_day" min="1" max="31" step="1" value="10" required></label></div>
          <label>Tipo de vencimento<select class="form-select" name="due_mode"><option value="fixed">Dia fixo (1 a 31)</option><option value="business">Dia útil do mês</option></select></label><label class="bemora-business-rule" hidden>Qual dia útil?<select class="form-select" name="business_day_rule"><option value="first">1º dia útil</option><option value="fifth">5º dia útil</option><option value="penultimate">Penúltimo dia útil</option><option value="last">Último dia útil</option></select></label>
          <p class="bemora-due-preview" role="status"></p>
          <label>Tipo de conta<select class="form-select" name="billing_type" id="bemora-global-bill-type"><option value="fixed">Fixa — mesmo valor todo mês</option><option value="variable">Variável — valor definido por mês</option><option value="installment">Parcelada — quantidade determinada</option></select></label>
          <div id="bemora-global-bill-installments" hidden><div class="bemora-quick-grid"><label>Mês de início<input class="form-control" type="month" name="start_month" value="<?= e($quickMonth) ?>"></label><label>Quantidade de parcelas<input class="form-control" type="number" name="installment_total" min="1" max="360" value="10"></label></div></div>
          <p class="bemora-global-bill-note" id="bemora-global-bill-hint">O valor será repetido mensalmente. O pagamento é controlado em cada mês.</p>
        </div>
        <div class="bemora-quick-footer"><button class="btn btn-outline-secondary" type="button" id="bemora-global-bill-cancel">Cancelar</button><button class="btn btn-success" type="submit">Salvar conta</button></div>
      </form>
    </dialog>
    <?php endif; ?>
    <script src="/assets/bemora-global-actions.js?v=2" defer></script>
    <script src="/assets/bemora-bill-inputs.js?v=3" defer></script>
    <link rel="stylesheet" href="/assets/bemora-bill-inputs.css?v=3">

    <style>
    .ref-topbar{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
    .topbar-month{margin-left:auto;display:flex;align-items:center;gap:9px;border:1px solid #dde7eb;border-radius:12px;background:#fff;padding:4px 8px;color:#173749;white-space:nowrap}
    .topbar-month>a{display:inline-flex;align-items:center;justify-content:center;width:26px;height:29px;color:#173749;text-decoration:none;font-size:21px;border-radius:7px}
    .topbar-month>a:hover{background:#edf5f3}
    .topbar-month-current{position:relative;display:flex;align-items:center;justify-content:center;min-width:132px;cursor:pointer;font-size:12px;font-weight:650}
    .topbar-month-current input{position:absolute;inset:0;opacity:0;width:100%;cursor:pointer}
    .ref-private{margin-left:0}
    @media(max-width:700px){.topbar-month{order:3;width:100%;justify-content:space-between;margin-left:0}.ref-private{margin-left:auto}.topbar-month-current{flex:1}}
    </style>
    <script>document.addEventListener("DOMContentLoaded",()=>{const picker=document.getElementById("global-month-picker");picker?.addEventListener("change",()=>{if(/^\d{4}-\d{2}$/.test(picker.value)){const url=new URL(location.href);url.searchParams.set("month",picker.value);location.assign(url.toString());}});});</script>
    <?php
}

function page_header(string $eyebrow, string $title, string $subtitle = ''): void
{
    ?>
    <div class="page-heading">
        <p class="eyebrow"><?= e($eyebrow) ?></p>
        <h1><?= e($title) ?></h1>
        <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
    </div>
    <?php
}
