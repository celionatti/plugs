<?php
/**
 * Built-in Card Component
 *
 * Usage: <x-card>Content here</x-card>
 *        <x-card shadow="lg" padding="lg">
 *            <slot:header>Title</slot:header>
 *            Content
 *            <slot:footer>Footer info</slot:footer>
 *        </x-card>
 *
 * Props: shadow (none|sm|md|lg), padding (sm|md|lg), bordered
 * Slots: header, footer
 */

$shadow = $shadow ?? 'md';
$padding = $padding ?? 'md';
$bordered = filter_var($bordered ?? true, FILTER_VALIDATE_BOOLEAN);

$shadows = [
    'none' => 'none',
    'sm'   => '0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04)',
    'md'   => '0 4px 12px rgba(0,0,0,.06),0 2px 4px rgba(0,0,0,.04)',
    'lg'   => '0 10px 25px rgba(0,0,0,.08),0 4px 10px rgba(0,0,0,.04)',
];

$paddings = [
    'sm' => '14px',
    'md' => '20px',
    'lg' => '28px',
];

$shadowVal = $shadows[$shadow] ?? $shadows['md'];
$paddingVal = $paddings[$padding] ?? $paddings['md'];
$borderStyle = $bordered ? 'border:1px solid #e5e7eb;' : '';
?>
<div style="border-radius:14px;background:#fff;box-shadow:<?= $shadowVal ?>;<?= $borderStyle ?>overflow:hidden;transition:box-shadow .2s ease" <?= $attributes ?? '' ?>>
    <?php if (!empty($header)): ?>
    <div style="padding:<?= $paddingVal ?>;border-bottom:1px solid #f1f5f9;font-weight:600;font-size:15px;color:#1e293b"><?= $header ?></div>
    <?php endif; ?>

    <div style="padding:<?= $paddingVal ?>"><?= $slot ?? '' ?></div>

    <?php if (!empty($footer)): ?>
    <div style="padding:<?= $paddingVal ?>;border-top:1px solid #f1f5f9;font-size:13px;color:#64748b"><?= $footer ?></div>
    <?php endif; ?>
</div>
