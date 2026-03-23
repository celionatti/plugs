<?php
/**
 * Built-in Avatar Component
 *
 * Usage: <x-avatar src="/img/user.jpg" alt="John" />
 *        <x-avatar fallback="JD" size="lg" />
 *
 * Props: src, alt, size (sm|md|lg|xl), fallback (initials)
 */

$src = $src ?? '';
$alt = $alt ?? '';
$size = $size ?? 'md';
$fallback = $fallback ?? '';

$sizes = [
    'sm' => ['dim' => '32px', 'font' => '12px'],
    'md' => ['dim' => '40px', 'font' => '14px'],
    'lg' => ['dim' => '52px', 'font' => '18px'],
    'xl' => ['dim' => '72px', 'font' => '24px'],
];

$s = $sizes[$size] ?? $sizes['md'];

if (!$fallback && $alt) {
    $words = explode(' ', trim($alt));
    $fallback = strtoupper(substr($words[0], 0, 1));
    if (count($words) > 1) {
        $fallback .= strtoupper(substr(end($words), 0, 1));
    }
}
?>
<?php if ($src): ?>
<img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($alt) ?>" style="width:<?= $s['dim'] ?>;height:<?= $s['dim'] ?>;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;flex-shrink:0" <?= $attributes ?? '' ?> onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" />
<span style="display:none;width:<?= $s['dim'] ?>;height:<?= $s['dim'] ?>;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-size:<?= $s['font'] ?>;font-weight:700;align-items:center;justify-content:center;flex-shrink:0;border:2px solid #e5e7eb"><?= htmlspecialchars($fallback) ?></span>
<?php else: ?>
<span style="display:inline-flex;width:<?= $s['dim'] ?>;height:<?= $s['dim'] ?>;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-size:<?= $s['font'] ?>;font-weight:700;align-items:center;justify-content:center;flex-shrink:0;border:2px solid #e5e7eb" <?= $attributes ?? '' ?>><?= htmlspecialchars($fallback ?: '?') ?></span>
<?php endif; ?>
