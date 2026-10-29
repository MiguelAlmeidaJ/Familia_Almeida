<?php
declare(strict_types=1);
function brand_asset_path(string $kind): string {
    if (!in_array($kind, ['logo','favicon'], true)) throw new InvalidArgumentException('Tipo inválido.');
    $directory = dirname(__DIR__) . '/assets/brand';
    foreach (['png','webp'] as $ext) {
        if (is_file($directory . '/' . $kind . '.' . $ext)) return '/assets/brand/' . $kind . '.' . $ext;
    }
    return '';
}
function brand_asset_url(string $kind): string {
    $path = brand_asset_path($kind);
    if ($path === '') return '';
    $file = dirname(__DIR__) . $path;
    return $path . '?v=' . (string)(filemtime($file) ?: 1);
}
function brand_head_tags(): void {
    $favicon = brand_asset_url('favicon');
    if ($favicon === '') return;
    $type = str_contains($favicon,'.webp') ? 'image/webp' : 'image/png';
    echo '<link rel="icon" type="' . $type . '" href="' . htmlspecialchars($favicon,ENT_QUOTES,'UTF-8') . '">';
}
