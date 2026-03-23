<?php
/**
 * Built-in Toast Component
 *
 * Usage: <x-toast type="success" message="Saved successfully!" />
 *        <x-toast type="error" message="Something went wrong" position="top-left" />
 *
 * Props: type (success|error|warning|info), message, position (top-right|top-left|bottom-right|bottom-left|top-center|bottom-center), duration (ms)
 */

$type = $type ?? 'info';
$message = $message ?? ($slot ?? '');
$position = $position ?? 'top-right';
$duration = (int)($duration ?? 5000);

$colors = [
    'success' => ['bg' => '#ecfdf5', 'border' => '#10b981', 'text' => '#065f46', 'icon' => '✓'],
    'error'   => ['bg' => '#fef2f2', 'border' => '#ef4444', 'text' => '#991b1b', 'icon' => '✕'],
    'warning' => ['bg' => '#fffbeb', 'border' => '#f59e0b', 'text' => '#92400e', 'icon' => '⚠'],
    'info'    => ['bg' => '#eff6ff', 'border' => '#3b82f6', 'text' => '#1e40af', 'icon' => 'ℹ'],
];

$c = $colors[$type] ?? $colors['info'];

$positions = [
    'top-right'     => 'top:20px;right:20px',
    'top-left'      => 'top:20px;left:20px',
    'bottom-right'  => 'bottom:20px;right:20px',
    'bottom-left'   => 'bottom:20px;left:20px',
    'top-center'    => 'top:20px;left:50%;transform:translateX(-50%)',
    'bottom-center' => 'bottom:20px;left:50%;transform:translateX(-50%)',
];

$posStyle = $positions[$position] ?? $positions['top-right'];
$toastId = 'toast-' . substr(md5(uniqid()), 0, 8);
?>
<div id="<?= $toastId ?>" role="alert" style="position:fixed;<?= $posStyle ?>;z-index:10000;display:flex;align-items:center;gap:12px;padding:14px 20px;border-radius:12px;background:<?= $c['bg'] ?>;border:1px solid <?= $c['border'] ?>;color:<?= $c['text'] ?>;font-size:14px;box-shadow:0 10px 30px rgba(0,0,0,.12);animation:plugToastIn .3s ease;max-width:420px;min-width:280px" <?= $attributes ?? '' ?>>
    <span style="font-size:18px;line-height:1;flex-shrink:0"><?= $c['icon'] ?></span>
    <div style="flex:1;line-height:1.5"><?= htmlspecialchars($message) ?></div>
    <button type="button" onclick="document.getElementById('<?= $toastId ?>').remove()" style="background:none;border:none;cursor:pointer;color:<?= $c['text'] ?>;font-size:18px;line-height:1;padding:0;opacity:.6;flex-shrink:0" aria-label="Close">&times;</button>
</div>
<style>
@keyframes plugToastIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
</style>
<?php if ($duration > 0): ?>
<script>setTimeout(function(){var el=document.getElementById('<?= $toastId ?>');if(el){el.style.opacity='0';el.style.transform='translateY(-10px)';el.style.transition='all .3s ease';setTimeout(function(){el.remove()},300)}},<?= $duration ?>)</script>
<?php endif; ?>
