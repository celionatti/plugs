<?php
/**
 * Built-in Input Component
 *
 * Usage: <x-input name="email" label="Email" type="email" />
 *        <x-input name="name" label="Full Name" error="Name is required" placeholder="Enter name" />
 *
 * Props: name, label, type, error, placeholder, value, required, disabled
 */

$name = $name ?? '';
$label = $label ?? '';
$type = $type ?? 'text';
$error = $error ?? '';
$placeholder = $placeholder ?? '';
$value = $value ?? '';
$required = filter_var($required ?? false, FILTER_VALIDATE_BOOLEAN);
$disabled = filter_var($disabled ?? false, FILTER_VALIDATE_BOOLEAN);

$inputId = $name ? 'input-' . htmlspecialchars($name) : 'input-' . substr(md5(uniqid()), 0, 8);
$borderColor = $error ? '#ef4444' : '#e2e8f0';
$focusBorder = $error ? '#ef4444' : '#6366f1';
?>
<div style="margin-bottom:16px" <?= $attributes ?? '' ?>>
    <?php if ($label): ?>
    <label for="<?= $inputId ?>" style="display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:#374151">
        <?= htmlspecialchars($label) ?>
        <?php if ($required): ?><span style="color:#ef4444;margin-left:2px">*</span><?php endif; ?>
    </label>
    <?php endif; ?>
    <input
        type="<?= htmlspecialchars($type) ?>"
        id="<?= $inputId ?>"
        <?php if ($name): ?>name="<?= htmlspecialchars($name) ?>"<?php endif; ?>
        <?php if ($value !== ''): ?>value="<?= htmlspecialchars($value) ?>"<?php endif; ?>
        <?php if ($placeholder): ?>placeholder="<?= htmlspecialchars($placeholder) ?>"<?php endif; ?>
        <?php if ($required): ?>required<?php endif; ?>
        <?php if ($disabled): ?>disabled<?php endif; ?>
        style="width:100%;padding:10px 14px;font-size:14px;line-height:1.5;color:#1e293b;background:<?= $disabled ? '#f8fafc' : '#fff' ?>;border:1.5px solid <?= $borderColor ?>;border-radius:10px;outline:none;transition:border-color .2s ease,box-shadow .2s ease;box-sizing:border-box"
        onfocus="this.style.borderColor='<?= $focusBorder ?>';this.style.boxShadow='0 0 0 3px <?= $error ? 'rgba(239,68,68,.1)' : 'rgba(99,102,241,.1)' ?>'"
        onblur="this.style.borderColor='<?= $borderColor ?>';this.style.boxShadow='none'"
    />
    <?php if ($error): ?>
    <p style="margin:4px 0 0;font-size:12px;color:#ef4444"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
</div>
