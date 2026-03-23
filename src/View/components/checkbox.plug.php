<?php
/**
 * Built-in Checkbox Component
 *
 * Usage: <x-checkbox name="terms" label="I accept the terms" />
 *        <x-checkbox name="newsletter" label="Subscribe" checked="true" />
 *
 * Props: name, label, checked, value, disabled, error
 */

$name = $name ?? '';
$label = $label ?? '';
$checked = filter_var($checked ?? false, FILTER_VALIDATE_BOOLEAN);
$value = $value ?? '1';
$disabled = filter_var($disabled ?? false, FILTER_VALIDATE_BOOLEAN);
$error = $error ?? '';

$inputId = $name ? 'checkbox-' . htmlspecialchars($name) : 'checkbox-' . substr(md5(uniqid()), 0, 8);
?>
<div style="margin-bottom:12px" <?= $attributes ?? '' ?>>
    <label for="<?= $inputId ?>" style="display:inline-flex;align-items:center;gap:10px;cursor:<?= $disabled ? 'not-allowed' : 'pointer' ?>;font-size:14px;color:<?= $disabled ? '#94a3b8' : '#374151' ?>;user-select:none">
        <input
            type="checkbox"
            id="<?= $inputId ?>"
            <?php if ($name): ?>name="<?= htmlspecialchars($name) ?>"<?php endif; ?>
            value="<?= htmlspecialchars($value) ?>"
            <?php if ($checked): ?>checked<?php endif; ?>
            <?php if ($disabled): ?>disabled<?php endif; ?>
            style="width:18px;height:18px;accent-color:#6366f1;cursor:inherit;border-radius:4px;flex-shrink:0"
        />
        <?= htmlspecialchars($label) ?>
    </label>
    <?php if ($error): ?>
    <p style="margin:4px 0 0 28px;font-size:12px;color:#ef4444"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
</div>
