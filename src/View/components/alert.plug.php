<?php
/**
 * Built-in Alert Component
 *
 * Usage: <x-alert type="success">Message here</x-alert>
 *        <x-alert type="danger" dismissible="true">Error!</x-alert>
 *
 * Props: type (success|danger|warning|info), dismissible
 */

$type = $type ?? 'info';
$dismissible = filter_var($dismissible ?? false, FILTER_VALIDATE_BOOLEAN);

$colors = [
    'success' => ['bg' => '#ecfdf5', 'border' => '#10b981', 'text' => '#065f46', 'icon' => '✓'],
    'danger'  => ['bg' => '#fef2f2', 'border' => '#ef4444', 'text' => '#991b1b', 'icon' => '✕'],
    'warning' => ['bg' => '#fffbeb', 'border' => '#f59e0b', 'text' => '#92400e', 'icon' => '⚠'],
    'info'    => ['bg' => '#eff6ff', 'border' => '#3b82f6', 'text' => '#1e40af', 'icon' => 'ℹ'],
];

$c = $colors[$type] ?? $colors['info'];
$id = 'alert-' . substr(md5(uniqid()), 0, 8);
?>
<div id="<?= $id ?>" role="alert" style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;border-radius:10px;border-left:4px solid <?= $c['border'] ?>;background:<?= $c['bg'] ?>;color:<?= $c['text'] ?>;font-size:14px;line-height:1.6;margin-bottom:12px;transition:opacity .3s ease,transform .3s ease" <?= $attributes ?? '' ?>>
    <span style="font-size:18px;line-height:1;flex-shrink:0;margin-top:1px"><?= $c['icon'] ?></span>
    <div style="flex:1"><?= $slot ?? '' ?></div>
    <?php if ($dismissible): ?>
    <button type="button" onclick="(function(el){el.closest('[role=alert]').style.opacity='0';el.closest('[role=alert]').style.transform='translateX(20px)';setTimeout(function(){el.closest('[role=alert]').remove()},300)})(this)" style="background:none;border:none;cursor:pointer;color:<?= $c['text'] ?>;font-size:20px;line-height:1;padding:0;opacity:.6" aria-label="Close">&times;</button>
    <?php endif; ?>
</div>
