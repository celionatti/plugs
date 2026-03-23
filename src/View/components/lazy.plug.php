<?php
/**
 * Built-in Lazy Component Wrapper
 *
 * Infers rendering until the element scrolls into the viewport using IntersectionObserver.
 * Perfect for heavy widgets below the fold.
 *
 * Usage:
 * <x-lazy component="HeavyGraph" :props="['data' => $data]">
 *     <div style="height: 300px" class="animate-pulse bg-gray-200 rounded"></div>
 * </x-lazy>
 */

$component = $component ?? null;
$props = $props ?? [];

$payloadAttr = '';
if ($component) {
    $lazyPayload = [
        'component' => $component,
        'attributes' => $props
    ];
    $encryptedPayload = \Plugs\Facades\Crypt::encrypt($lazyPayload);
    $payloadAttr = 'data-plugs-lazy-payload="' . htmlspecialchars($encryptedPayload, ENT_QUOTES) . '"';
}

$class = $attributes->get('class', '');
$style = $attributes->get('style', '');
?>
<div class="plugs-lazy-component <?= htmlspecialchars($class, ENT_QUOTES) ?>" <?= $payloadAttr ?> style="<?= htmlspecialchars($style, ENT_QUOTES) ?>">
    <?= $slot ?? '' ?>
</div>
