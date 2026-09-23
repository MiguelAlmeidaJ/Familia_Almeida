<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function nav_icon(string $name): string
{
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>',
        'movimentacoes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v12H4z"/><path d="M7 9h10M7 13h6"/></svg>',
        'contas' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18H6z"/><path d="M9 7h6M9 11h6M9 15h4"/></svg>',
        'metas' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="3"/><path d="m15 9 4-4"/></svg>',
        'dividas' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="7" width="16" height="10" rx="2"/><path d="M4 11h16"/></svg>',
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
        ['key' => 'dashboard', 'href' => '/', 'label' => 'Visão geral'],
        ['key' => 'movimentacoes', 'href' => '/movimentacoes', 'label' => 'Lançamentos'],
        ['key' => 'contas', 'href' => '/contas', 'label' => 'Contas recorrentes'],
        ['key' => 'metas', 'href' => '/metas', 'label' => 'Metas de gastos'],
        ['key' => 'dividas', 'href' => '/dividas', 'label' => 'Dívidas'],
        ['key' => 'investimentos', 'href' => '/metas?section=investment#investimento', 'label' => 'Investimentos'],
    ];

    $user = function_exists('current_user') ? current_user() : null;
    $initial = $user && !empty($user['name']) ? strtoupper(substr((string) $user['name'], 0, 1)) : 'F';
    ?>
    <aside class="sidebar ref-sidebar" id="sidebar">
        <div class="ref-sidebar-head">
            <a class="ref-brand" href="/">
                <span class="ref-brand-icon"><?= nav_icon('home') ?></span>
                <span class="ref-brand-copy"><small>FAMÍLIA</small><strong>Almeida.</strong></span>
            </a>
            <button class="sidebar-toggle ref-collapse" id="sidebarToggle" type="button" aria-label="Recolher menu" aria-expanded="true">
                <?= nav_icon('chevron') ?>
            </button>
        </div>

        <nav class="side-nav ref-nav" aria-label="Menu principal">
            <?php foreach ($items as $item): ?>
                <a class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>" title="<?= e($item['label']) ?>">
                    <span class="nav-icon"><?= nav_icon($item['key']) ?></span>
                    <span class="nav-item-label"><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <div class="ref-nav-divider"></div>

            <a class="nav-item <?= in_array($active, ['configuracoes', 'manutencao'], true) ? 'active' : '' ?>" href="/configuracoes" title="Configurações">
                <span class="nav-icon"><?= nav_icon('configuracoes') ?></span>
                <span class="nav-item-label">Configurações</span>
            </a>
        </nav>

        <div class="ref-sidebar-message">
            <span><?= nav_icon('heart') ?></span>
            <small>Organizar o presente.</small>
            <strong>Cuidar do nosso futuro.</strong>
        </div>

        <div class="sidebar-user ref-sidebar-user">
            <div class="user-avatar"><?= e($initial) ?></div>
            <div class="sidebar-user-copy">
                <strong>Família Almeida</strong>
                <span>Finanças da família</span>
            </div>
        </div>

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
    ?>
    <header class="topbar ref-topbar">
        <div class="topbar-left">
            <button class="mobile-menu-toggle" id="mobileSidebarToggle" type="button" aria-label="Abrir menu" aria-expanded="false">
                <?= nav_icon('menu') ?>
            </button>
            <span class="ref-topbar-title">Nosso planejamento financeiro</span>
        </div>

        <div class="ref-private">
            <?= nav_icon('shield') ?>
            <span><?= e($context) ?></span>
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
