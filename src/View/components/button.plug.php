<?php
/**
 * Built-in Button Component
 *
 * Usage: <x-button>Click Me</x-button>
 *        <x-button variant="primary" size="lg">Submit</x-button>
 *        <x-button href="/about">About</x-button>
 *
 * Props: variant (primary|secondary|danger|success|warning|outline|ghost), size (sm|md|lg), disabled, href, type
 */

$variant = $variant ?? 'primary';
$size = $size ?? 'md';
$disabled = filter_var($disabled ?? false, FILTER_VALIDATE_BOOLEAN);
$href = $href ?? null;
$type = $type ?? 'button';

$variants = [
    'primary'   => 'background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;border:none;box-shadow:0 2px 8px rgba(99,102,241,.3);',
    'secondary' => 'background:#f1f5f9;color:#334155;border:1px solid #e2e8f0;',
    'danger'    => 'background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;box-shadow:0 2px 8px rgba(239,68,68,.3);',
    'success'   => 'background:linear-gradient(135deg,#10b981,#059669);color:#fff;border:none;box-shadow:0 2px 8px rgba(16,185,129,.3);',
    'warning'   => 'background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;border:none;box-shadow:0 2px 8px rgba(245,158,11,.3);',
    'outline'   => 'background:transparent;color:#4f46e5;border:2px solid #4f46e5;',
    'ghost'     => 'background:transparent;color:#64748b;border:none;',
];

$sizes = [
    'sm' => 'font-size:13px;padding:6px 14px;',
    'md' => 'font-size:14px;padding:10px 20px;',
    'lg' => 'font-size:16px;padding:12px 28px;',
];

$variantStyle = $variants[$variant] ?? $variants['primary'];
$sizeStyle = $sizes[$size] ?? $sizes['md'];
$disabledStyle = $disabled ? 'opacity:.5;cursor:not-allowed;pointer-events:none;' : 'cursor:pointer;';
$baseStyle = 'display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:10px;font-weight:600;line-height:1;text-decoration:none;transition:all .2s ease;white-space:nowrap;';

$tag = $href ? 'a' : 'button';
$extraAttrs = $href ? 'href="' . htmlspecialchars($href, ENT_QUOTES) . '"' : 'type="' . htmlspecialchars($type, ENT_QUOTES) . '"';
if ($disabled && $tag === 'button') {
    $extraAttrs .= ' disabled';
}
?>
<<?= $tag ?> <?= $extraAttrs ?> style="<?= $baseStyle . $variantStyle . $sizeStyle . $disabledStyle ?>" <?= $attributes ?? '' ?>><?= $slot ?? '' ?></<?= $tag ?>>
