<?php
/**
 * Built-in Dropdown Component
 *
 * Usage: <x-dropdown label="Options">
 *            <a href="#">Edit</a>
 *            <a href="#">Delete</a>
 *        </x-dropdown>
 *
 * Props: label, align (left|right)
 */

$label = $label ?? 'Menu';
$align = $align ?? 'left';
$dropId = 'dropdown-' . substr(md5(uniqid()), 0, 8);
$alignStyle = $align === 'right' ? 'right:0' : 'left:0';
?>
<div style="position:relative;display:inline-block" <?= $attributes ?? '' ?>>
    <button type="button" onclick="(function(el){var m=document.getElementById('<?= $dropId ?>');m.style.display=m.style.display==='block'?'none':'block';document.addEventListener('click',function h(e){if(!el.closest('[style*=relative]').contains(e.target)){m.style.display='none';document.removeEventListener('click',h)}},true)})(this)" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:14px;font-weight:500;color:#334155;background:#fff;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;transition:all .2s ease">
        <?= htmlspecialchars($label) ?>
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" style="transition:transform .2s"><path d="M3 4.5L6 7.5L9 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <div id="<?= $dropId ?>" style="display:none;position:absolute;<?= $alignStyle ?>;top:calc(100% + 6px);min-width:180px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.1);padding:6px;z-index:50;animation:plugDropIn .15s ease">
        <?= $slot ?? '' ?>
    </div>
</div>
<style>
#<?= $dropId ?> a,#<?= $dropId ?> button{display:block;padding:8px 14px;font-size:13px;color:#334155;text-decoration:none;border-radius:8px;border:none;background:none;width:100%;text-align:left;cursor:pointer;transition:background .15s}
#<?= $dropId ?> a:hover,#<?= $dropId ?> button:hover{background:#f1f5f9;color:#0f172a}
@keyframes plugDropIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
</style>
