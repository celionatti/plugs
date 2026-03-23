<?php
/**
 * Built-in Async Component Wrapper
 *
 * Loads content asynchronously via AJAX immediately after the page loads.
 * Useful for deferring non-critical content to speed up initial page render.
 *
 * Usage with a URL:
 * <x-async src="/api/stats">
 *     <x-spinner size="sm" /> Loading stats...
 * </x-async>
 *
 * Usage with a Component Payload (automatically handled by the framework):
 * <x-async component="Admin::Stats" :props="['interval' => '7d']">
 *     Loading...
 * </x-async>
 */

$src = $src ?? null;
$component = $component ?? null;
$props = $props ?? [];

$payloadAttr = '';
if ($component) {
    // Encrypt the component and its props to safely pass to the frontend
    $lazyPayload = [
        'component' => $component,
        'attributes' => $props
    ];
    $encryptedPayload = \Plugs\Facades\Crypt::encrypt($lazyPayload);
    $payloadAttr = 'data-plugs-async-payload="' . htmlspecialchars($encryptedPayload, ENT_QUOTES) . '"';
}

$srcAttr = '';
if ($src) {
    $srcAttr = 'data-plugs-async-src="' . htmlspecialchars($src, ENT_QUOTES) . '"';
}

$class = $attributes->get('class', 'plugs-async-wrapper');
$style = $attributes->get('style', '');
?>
<div class="plugs-async-component <?= htmlspecialchars($class, ENT_QUOTES) ?>" <?= $payloadAttr ?> <?= $srcAttr ?> style="<?= htmlspecialchars($style, ENT_QUOTES) ?>">
    <?= $slot ?? '' ?>
</div>
