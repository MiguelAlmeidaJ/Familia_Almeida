<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function render_sidebar(string $active, string $csrf): void
{
    $items = [
        ['key' => 'dashboard', 'href' => '/', 'icon' => '⌂', 'label' => 'Visão geral'],
        ['key' => 'movimentacoes', 'href' => '/movimentacoes', 'icon' => '↕', 'label' => 'Movimentações'],
        ['key' => 'contas', 'href' => '/contas', 'icon' => '▤', 'label' => 'Contas fixas'],
        ['key' => 'metas', 'href' => '/metas', 'icon' => '◎', 'label' => 'Metas'],
        ['key' => 'dividas', 'href' => '/dividas', 'icon' => '◇', 'label' => 'Dívidas'],
        ['key' => 'configuracoes', 'href' => '/configuracoes', 'icon' => '⚙', 'label' => 'Configurações'],
    ];
    ?>
    <aside class="sidebar">
        <a class="brand" href="/">
            <div class="brandmark">FA</div>
            <div class="brandcopy"><b>FAMÍLIA</b><strong>ALMEIDA</strong><small>FINANÇAS</small></div>
        </a>

        <nav class="side-nav" aria-label="Menu principal">
            <small class="nav-label">MENU</small>
            <?php foreach ($items as $item): ?>
                <a class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                    <span class="nav-icon"><?= e($item['icon']) ?></span>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>

            <?php if ($active === 'configuracoes' || $active === 'manutencao'): ?>
                <div class="nav-sub">
                    <a class="<?= $active === 'manutencao' ? 'active' : '' ?>" href="/configuracoes/manutencao">Manutenção</a>
                </div>
            <?php endif; ?>
        </nav>

        <div class="side-footer">
            <div class="side-note">
                <small>PROPÓSITO</small>
                <p>Dar nome a cada real para construir liberdade com intenção.</p>
            </div>

            <form method="post" action="/sair">
                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <button class="logout" type="submit">Sair da conta</button>
            </form>
        </div>
    </aside>
    <?php
}

function render_topbar(array $user, string $context = 'MySQL conectado'): void
{
    ?>
    <header class="topbar">
        <div class="who"><small>LOGADO COMO</small><strong><?= e($user['name']) ?></strong></div>
        <div class="privacy"><i></i><?= e($context) ?></div>
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
