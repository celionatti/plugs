<?php
/**
 * Built-in Table Component
 *
 * Usage: <x-table striped="true" hoverable="true">
 *            <thead><tr><th>Name</th><th>Email</th></tr></thead>
 *            <tbody><tr><td>John</td><td>john@example.com</td></tr></tbody>
 *        </x-table>
 *
 * Props: striped, hoverable, bordered, compact
 */

$striped = filter_var($striped ?? false, FILTER_VALIDATE_BOOLEAN);
$hoverable = filter_var($hoverable ?? true, FILTER_VALIDATE_BOOLEAN);
$bordered = filter_var($bordered ?? false, FILTER_VALIDATE_BOOLEAN);
$compact = filter_var($compact ?? false, FILTER_VALIDATE_BOOLEAN);

$tableId = 'table-' . substr(md5(uniqid()), 0, 8);
$cellPadding = $compact ? '8px 12px' : '12px 16px';
?>
<div style="overflow-x:auto;border-radius:12px;border:1px solid #e5e7eb;background:#fff" <?= $attributes ?? '' ?>>
    <table id="<?= $tableId ?>" style="width:100%;border-collapse:collapse;font-size:14px;color:#374151">
        <?= $slot ?? '' ?>
    </table>
</div>
<style>
#<?= $tableId ?> th{padding:<?= $cellPadding ?>;text-align:left;font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#64748b;background:#f8fafc;border-bottom:2px solid #e5e7eb;white-space:nowrap}
#<?= $tableId ?> td{padding:<?= $cellPadding ?>;border-bottom:1px solid #f1f5f9;vertical-align:middle}
<?php if ($striped): ?>
#<?= $tableId ?> tbody tr:nth-child(even){background:#f8fafc}
<?php endif; ?>
<?php if ($hoverable): ?>
#<?= $tableId ?> tbody tr:hover{background:#f1f5f9;transition:background .15s ease}
<?php endif; ?>
<?php if ($bordered): ?>
#<?= $tableId ?> td,#<?= $tableId ?> th{border:1px solid #e5e7eb}
<?php endif; ?>
</style>
