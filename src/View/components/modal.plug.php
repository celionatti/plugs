<?php
/**
 * Built-in Modal Component
 *
 * Usage: <x-modal id="my-modal" title="Confirm">Are you sure?</x-modal>
 *        <x-modal id="edit-modal" size="lg">
 *            <slot:header>Edit Profile</slot:header>
 *            Content
 *            <slot:footer><x-button>Save</x-button></slot:footer>
 *        </x-modal>
 *
 * Props: id, title, size (sm|md|lg|xl), closable
 * Slots: header, footer
 */

$id = $id ?? 'modal-' . substr(md5(uniqid()), 0, 8);
$title = $title ?? '';
$size = $size ?? 'md';
$closable = filter_var($closable ?? true, FILTER_VALIDATE_BOOLEAN);

$widths = [
    'sm' => '400px',
    'md' => '540px',
    'lg' => '720px',
    'xl' => '900px',
];

$width = $widths[$size] ?? $widths['md'];
?>
<div id="<?= htmlspecialchars($id) ?>" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);transition:opacity .3s ease" onclick="if(event.target===this)this.style.display='none'" <?= $attributes ?? '' ?>>
    <div style="background:#fff;border-radius:16px;width:90%;max-width:<?= $width ?>;max-height:85vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.15);animation:plugModalIn .25s ease" role="dialog" aria-modal="true" <?php if ($title): ?>aria-labelledby="<?= htmlspecialchars($id) ?>-title"<?php endif; ?>>
        <?php if (!empty($header) || $title || $closable): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid #f1f5f9">
            <div style="font-weight:700;font-size:16px;color:#1e293b" <?php if ($title): ?>id="<?= htmlspecialchars($id) ?>-title"<?php endif; ?>>
                <?= $header ?? htmlspecialchars($title) ?>
            </div>
            <?php if ($closable): ?>
            <button type="button" onclick="document.getElementById('<?= htmlspecialchars($id) ?>').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:22px;color:#94a3b8;padding:0;line-height:1" aria-label="Close">&times;</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div style="padding:22px;overflow-y:auto;max-height:60vh"><?= $slot ?? '' ?></div>

        <?php if (!empty($footer)): ?>
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid #f1f5f9;background:#f8fafc"><?= $footer ?></div>
        <?php endif; ?>
    </div>
</div>
<style>@keyframes plugModalIn{from{opacity:0;transform:scale(.95) translateY(10px)}to{opacity:1;transform:scale(1) translateY(0)}}</style>
