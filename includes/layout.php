<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function nav_icon(string $name): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v9h-6zM4 14h6v6H4zM14 17h6v3h-6z"/></svg>',
        'movimentacoes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4v14m0 0-3-3m3 3 3-3M17 20V6m0 0-3 3m3-3 3 3"/></svg>',
        'contas' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/></svg>',
        'metas' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="m15 9 4-4"/></svg>',
        'dividas' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 8 9-8 9-8-9z"/><path d="M9 12h6"/></svg>',
        'configuracoes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.08-1l2-1.55-2-3.46-2.45 1A7 7 0 0 0 14.7 6l-.36-2.63h-4L10 6a7 7 0 0 0-1.77 1L5.78 6l-2 3.46L5.8 11a7 7 0 0 0 0 2l-2 1.55 2 3.46 2.45-1A7 7 0 0 0 10 18l.36 2.63h4L14.7 18a7 7 0 0 0 1.77-1l2.45 1 2-3.46L18.92 13c.05-.33.08-.66.08-1Z"/></svg>',
        'logout' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5H5v14h5M14 8l4 4-4 4M8 12h10"/></svg>',
        'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
    ];

    return $icons[$name] ?? '';
}

function render_sidebar(string $active, string $csrf): void
{
    $items = [
        ['key' => 'dashboard', 'href' => '/', 'label' => 'Dashboard'],
        ['key' => 'movimentacoes', 'href' => '/movimentacoes', 'label' => 'Movimentações'],
        ['key' => 'contas', 'href' => '/contas', 'label' => 'Contas fixas'],
        ['key' => 'metas', 'href' => '/metas', 'label' => 'Metas'],
        ['key' => 'dividas', 'href' => '/dividas', 'label' => 'Dívidas'],
        ['key' => 'configuracoes', 'href' => '/configuracoes', 'label' => 'Configurações'],
    ];

    $user = function_exists('current_user') ? current_user() : null;
    $initial = $user && !empty($user['name']) ? mb_strtoupper(mb_substr((string) $user['name'], 0, 1)) : 'F';
    ?>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <a class="brand modern-brand" href="/" aria-label="Família Almeida Finanças">
                <div class="brandmark">FA</div>
                <div class="brandcopy"><b>FAMÍLIA</b><strong>ALMEIDA</strong><small>FINANÇAS</small></div>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Recolher menu" aria-expanded="true">
                <?= nav_icon('chevron') ?>
            </button>
        </div>

        <nav class="side-nav modern-nav" aria-label="Menu principal">
            <small class="nav-label">NAVEGAÇÃO</small>

            <?php foreach ($items as $item): ?>
                <a class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>" title="<?= e($item['label']) ?>">
                    <span class="nav-icon"><?= nav_icon($item['key']) ?></span>
                    <span class="nav-item-label"><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <?php if ($active === 'configuracoes' || $active === 'manutencao'): ?>
                <div class="nav-sub">
                    <a class="<?= $active === 'manutencao' ? 'active' : '' ?>" href="/configuracoes/manutencao">Manutenção</a>
                </div>
            <?php endif; ?>
        </nav>

        <div class="sidebar-spacer"></div>

        <div class="sidebar-user">
            <div class="user-avatar"><?= e($initial) ?></div>
            <div class="sidebar-user-copy">
                <strong><?= e((string) ($user['name'] ?? 'Família')) ?></strong>
                <span><?= e((string) ($user['email'] ?? 'Área financeira')) ?></span>
            </div>
        </div>

        <form method="post" action="/sair" class="sidebar-logout">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <button class="logout modern-logout" type="submit" title="Sair da conta">
                <span class="nav-icon"><?= nav_icon('logout') ?></span>
                <span class="nav-item-label">Sair da conta</span>
            </button>
        </form>
    </aside>

    <button class="sidebar-overlay" id="sidebarOverlay" type="button" aria-label="Fechar menu"></button>
    <script src="/assets/app.js" defer></script>
    <?php
}

function render_topbar(array $user, string $context = 'MySQL conectado'): void
{
    ?>
    <header class="topbar modern-topbar">
        <div class="topbar-left">
            <button class="mobile-menu-toggle" id="mobileSidebarToggle" type="button" aria-label="Abrir menu" aria-expanded="false">
                <?= nav_icon('menu') ?>
            </button>
            <div class="who">
                <small>OLÁ,</small>
                <strong><?= e($user['name']) ?></strong>
            </div>
        </div>

        <div class="topbar-right">
            <div class="privacy"><i></i><?= e($context) ?></div>
        </div>
    </header>
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
