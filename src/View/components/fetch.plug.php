<?php
/**
 * Built-in Fetch Component Wrapper
 *
 * Fetches JSON from a URL and renders it through a client-side template.
 * Simple alternative to Alpine or Vue for basic dynamic data.
 *
 * Usage:
 * <x-fetch url="/api/users">
 *
 *     <!-- The initial loading state -->
 *     <slot:initial>Loading users...</slot:initial>
 *
 *     <!-- The template to render when data arrives.
 *          Use {{ var }} for output and @for item in list / @if for logic -->
 *     <slot:template>
 *         <ul>
 *             @for user in data
 *                 <li>{{ user.name }} ({{ user.email }})</li>
 *             @endfor
 *         </ul>
 *     </slot:template>
 *
 * </x-fetch>
 */

$url = $url ?? null;
$class = $attributes->get('class', '');
$style = $attributes->get('style', '');
?>
<div class="plugs-fetch-component <?= htmlspecialchars($class, ENT_QUOTES) ?>" data-plugs-fetch-url="<?= htmlspecialchars($url ?? '', ENT_QUOTES) ?>" style="<?= htmlspecialchars($style, ENT_QUOTES) ?>">
    <div class="plugs-fetch-initial">
        <?= $initial ?? ($slot ?? 'Loading...') ?>
    </div>
    <template class="plugs-fetch-success-template">
        <?= $template ?? '' ?>
    </template>
</div>
