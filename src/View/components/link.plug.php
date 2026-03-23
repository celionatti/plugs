<?php
/**
 * Built-in Link Component (SPA Navigation)
 *
 * A drop-in replacement for <a> tags that enables SPA-style navigation
 * via plugs-spa.js. Automatically adds data-spa="true" and supports
 * all SPA attributes like prefetching, partial targets, and confirmations.
 *
 * Usage: <x-link href="/about">About</x-link>
 *        <x-link href="/dashboard" target="#main-content">Dashboard</x-link>
 *        <x-link href="/delete" method="POST" confirm="Are you sure?">Delete</x-link>
 *
 * Props: href, target (SPA content target selector), method, confirm, prefetch, active-class
 */

$href = $href ?? '#';
$target = $target ?? '';
$method = $method ?? '';
$confirm = $confirm ?? '';
$prefetch = filter_var($prefetch ?? true, FILTER_VALIDATE_BOOLEAN);
$activeClass = $activeClass ?? '';
$activeStyle = $activeStyle ?? '';

// Determine if this link is "active" (current page matches href)
$currentPath = $_SERVER['REQUEST_URI'] ?? '';
$currentPath = strtok($currentPath, '?'); // strip query string
$hrefPath = strtok($href, '?');
$isActive = ($currentPath === $hrefPath) || ($hrefPath !== '/' && str_starts_with($currentPath, $hrefPath));

$extraAttrs = '';
if ($target) {
    $extraAttrs .= ' data-spa-target="' . htmlspecialchars($target, ENT_QUOTES) . '"';
}
if ($method) {
    $extraAttrs .= ' p-method="' . htmlspecialchars(strtoupper($method), ENT_QUOTES) . '"';
}
if ($confirm) {
    $extraAttrs .= ' p-confirm="' . htmlspecialchars($confirm, ENT_QUOTES) . '"';
}
if (!$prefetch) {
    $extraAttrs .= ' data-no-prefetch="true"';
}
if ($isActive && $activeClass) {
    $extraAttrs .= ' class="' . htmlspecialchars($activeClass, ENT_QUOTES) . '"';
}
if ($isActive && $activeStyle) {
    $extraAttrs .= ' style="' . htmlspecialchars($activeStyle, ENT_QUOTES) . '"';
}
?>
<a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" data-spa="true"<?= $extraAttrs ?> <?= $attributes ?? '' ?>><?= $slot ?? '' ?></a>
