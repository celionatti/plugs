<?php
/**
 * Built-in Spinner Component
 *
 * Usage: <x-spinner />
 *        <x-spinner size="lg" color="#ef4444" />
 *
 * Props: size (sm|md|lg), color
 */

$size = $size ?? 'md';
$color = $color ?? '#6366f1';

$sizes = [
    'sm' => ['dim' => '20px', 'border' => '2px'],
    'md' => ['dim' => '32px', 'border' => '3px'],
    'lg' => ['dim' => '48px', 'border' => '4px'],
];

$s = $sizes[$size] ?? $sizes['md'];
$spinnerId = 'spinner-' . substr(md5(uniqid()), 0, 8);
?>
<span id="<?= $spinnerId ?>" role="status" aria-label="Loading" style="display:inline-block;width:<?= $s['dim'] ?>;height:<?= $s['dim'] ?>;border:<?= $s['border'] ?> solid #e5e7eb;border-top-color:<?= htmlspecialchars($color) ?>;border-radius:50%;animation:plugSpin .7s linear infinite;flex-shrink:0" <?= $attributes ?? '' ?>></span>
<style>@keyframes plugSpin{to{transform:rotate(360deg)}}</style>
