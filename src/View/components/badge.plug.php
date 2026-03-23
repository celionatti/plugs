<?php
/**
 * Built-in Badge Component
 *
 * Usage: <x-badge type="success">Active</x-badge>
 *        <x-badge type="danger" pill="true">Urgent</x-badge>
 *
 * Props: type (primary|success|danger|warning|info|secondary), pill, size (sm|md|lg)
 */

$type = $type ?? 'primary';
$pill = filter_var($pill ?? false, FILTER_VALIDATE_BOOLEAN);
$size = $size ?? 'md';

$colors = [
    'primary'   => ['bg' => '#eef2ff', 'text' => '#4338ca', 'border' => '#c7d2fe'],
    'secondary' => ['bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#e2e8f0'],
    'success'   => ['bg' => '#ecfdf5', 'text' => '#047857', 'border' => '#a7f3d0'],
    'danger'    => ['bg' => '#fef2f2', 'text' => '#dc2626', 'border' => '#fecaca'],
    'warning'   => ['bg' => '#fffbeb', 'text' => '#d97706', 'border' => '#fde68a'],
    'info'      => ['bg' => '#eff6ff', 'text' => '#2563eb', 'border' => '#bfdbfe'],
];

$c = $colors[$type] ?? $colors['primary'];

$sizes = [
    'sm' => 'font-size:11px;padding:2px 8px;',
    'md' => 'font-size:12px;padding:3px 10px;',
    'lg' => 'font-size:13px;padding:4px 14px;',
];

$sizeStyle = $sizes[$size] ?? $sizes['md'];
$radius = $pill ? '999px' : '6px';
?>
<span style="display:inline-flex;align-items:center;<?= $sizeStyle ?>border-radius:<?= $radius ?>;background:<?= $c['bg'] ?>;color:<?= $c['text'] ?>;border:1px solid <?= $c['border'] ?>;font-weight:600;line-height:1.4;white-space:nowrap;vertical-align:middle" <?= $attributes ?? '' ?>><?= $slot ?? '' ?></span>
