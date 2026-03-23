<?php
/**
 * Built-in Pagination Component
 *
 * Usage: <x-pagination :currentPage="$page" :totalPages="$total" baseUrl="/posts" />
 *
 * Props: currentPage, totalPages, baseUrl, maxVisible (number of page links to show)
 */

$currentPage = (int)($currentPage ?? 1);
$totalPages = (int)($totalPages ?? 1);
$baseUrl = rtrim($baseUrl ?? '', '/');
$maxVisible = (int)($maxVisible ?? 7);

if ($totalPages <= 1) return;

// Calculate page range
$halfVisible = (int) floor($maxVisible / 2);
$startPage = max(1, $currentPage - $halfVisible);
$endPage = min($totalPages, $startPage + $maxVisible - 1);

if ($endPage - $startPage < $maxVisible - 1) {
    $startPage = max(1, $endPage - $maxVisible + 1);
}

$pageUrl = function(int $page) use ($baseUrl): string {
    $separator = str_contains($baseUrl, '?') ? '&' : '?';
    return htmlspecialchars($baseUrl . $separator . 'page=' . $page);
};
?>
<nav aria-label="Pagination" style="display:flex;align-items:center;justify-content:center;gap:4px;margin:20px 0" <?= $attributes ?? '' ?>>
    <?php if ($currentPage > 1): ?>
    <a href="<?= $pageUrl($currentPage - 1) ?>" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-size:14px;color:#64748b;text-decoration:none;transition:all .15s;border:1px solid #e2e8f0" aria-label="Previous">&lsaquo;</a>
    <?php else: ?>
    <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-size:14px;color:#cbd5e1;border:1px solid #f1f5f9;cursor:not-allowed">&lsaquo;</span>
    <?php endif; ?>

    <?php if ($startPage > 1): ?>
    <a href="<?= $pageUrl(1) ?>" style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;border-radius:8px;font-size:14px;color:#64748b;text-decoration:none;transition:all .15s;padding:0 4px">1</a>
    <?php if ($startPage > 2): ?>
    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;font-size:14px;color:#94a3b8">&hellip;</span>
    <?php endif; ?>
    <?php endif; ?>

    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
    <?php if ($i === $currentPage): ?>
    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;border-radius:8px;font-size:14px;font-weight:700;color:#fff;background:linear-gradient(135deg,#6366f1,#4f46e5);box-shadow:0 2px 6px rgba(99,102,241,.3);padding:0 4px" aria-current="page"><?= $i ?></span>
    <?php else: ?>
    <a href="<?= $pageUrl($i) ?>" style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;border-radius:8px;font-size:14px;color:#64748b;text-decoration:none;transition:all .15s;padding:0 4px"><?= $i ?></a>
    <?php endif; ?>
    <?php endfor; ?>

    <?php if ($endPage < $totalPages): ?>
    <?php if ($endPage < $totalPages - 1): ?>
    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;font-size:14px;color:#94a3b8">&hellip;</span>
    <?php endif; ?>
    <a href="<?= $pageUrl($totalPages) ?>" style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;border-radius:8px;font-size:14px;color:#64748b;text-decoration:none;transition:all .15s;padding:0 4px"><?= $totalPages ?></a>
    <?php endif; ?>

    <?php if ($currentPage < $totalPages): ?>
    <a href="<?= $pageUrl($currentPage + 1) ?>" style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-size:14px;color:#64748b;text-decoration:none;transition:all .15s;border:1px solid #e2e8f0" aria-label="Next">&rsaquo;</a>
    <?php else: ?>
    <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;font-size:14px;color:#cbd5e1;border:1px solid #f1f5f9;cursor:not-allowed">&rsaquo;</span>
    <?php endif; ?>
</nav>
