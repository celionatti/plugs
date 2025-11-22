<?php
/**
 * PAGINATION USAGE EXAMPLES
 * Complete guide to using the enhanced Paginator with PlugModel
 */

// ============================================
// 1. BASIC USAGE WITH PLUGMODEL
// ============================================

// Example 1: Standard pagination
$result = User::where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->paginate(15, $_GET['page'] ?? 1);

$paginator = \Plugs\Paginator\Pagination::fromArray($result);
?>

<!-- In your view -->
<div class="users-list">
    <?php foreach ($result['data'] as $user): ?>
        <div class="user-item">
            <h3><?= $user->name ?></h3>
            <p><?= $user->email ?></p>
        </div>
    <?php endforeach; ?>
</div>

<!-- Default pagination (all features) -->
<?= $paginator->render() ?>

<?php
// ============================================
// 2. DIFFERENT RENDER STYLES
// ============================================

// Style 1: Standard with all features (First, Prev, Numbers, Next, Last)
?>
<h3>Standard Pagination</h3>
<?= $paginator->render() ?>

<!-- Style 2: With Ellipsis (1 ... 5 6 7 ... 20) -->
<h3>With Ellipsis</h3>
<?= $paginator->renderWithEllipsis() ?>

<!-- Style 3: Simple (Prev/Next only) -->
<h3>Simple Pagination</h3>
<?= $paginator->renderSimple() ?>

<!-- Style 4: Load More Button -->
<h3>Load More Style</h3>
<div id="results-container">
    <?php foreach ($result['data'] as $user): ?>
        <div class="user-item"><?= $user->name ?></div>
    <?php endforeach; ?>
</div>
<?= $paginator->renderLoadMore() ?>

<!-- Style 5: Compact (symbols only) -->
<h3>Compact Pagination</h3>
<?= $paginator->renderCompact() ?>

<?php
// ============================================
// 3. CUSTOMIZATION OPTIONS
// ============================================

// Example: Custom options
$paginator->setOptions([
    // Display
    'max_links' => 5,
    'show_first_last' => false,
    
    // CSS Classes
    'container_class' => 'my-pagination-wrapper',
    'pagination_class' => 'pagination pagination-lg',
    'link_class' => 'page-link',
    'active_class' => 'active',
    
    // Text
    'prev_text' => '<i class="bi bi-chevron-left"></i>',
    'next_text' => '<i class="bi bi-chevron-right"></i>',
    'info_format' => 'Displaying {from}-{to} of {total} items',
    
    // AJAX Load More
    'ajax_enabled' => true,
    'ajax_container' => '#results-container',
]);

echo $paginator->render();

// ============================================
// 4. DIFFERENT MODEL EXAMPLES
// ============================================

// Example: Posts with relations
$posts = Post::with(['author', 'comments'])
    ->where('published', true)
    ->latest()
    ->paginate(10);

$paginator = \Plugs\Paginator\Pagination::fromArray($posts);
?>

<div class="posts">
    <?php foreach ($posts['data'] as $post): ?>
        <article>
            <h2><?= $post->title ?></h2>
            <p>By <?= $post->author->name ?></p>
            <p><?= count($post->comments) ?> comments</p>
        </article>
    <?php endforeach; ?>
</div>

<?= $paginator->renderWithEllipsis() ?>

<?php
// Example: Search results
$query = $_GET['q'] ?? '';
$results = Product::where('name', 'LIKE', "%{$query}%")
    ->orWhere('description', 'LIKE', "%{$query}%")
    ->paginate(20);

$paginator = \Plugs\Paginator\Pagination::fromArray($results)
    ->appends(['q' => $query]); // Keep search query in URLs
?>

<h2>Search results for: <?= htmlspecialchars($query) ?></h2>
<?= $paginator->renderWithEllipsis() ?>

<?php
// ============================================
// 5. AJAX LOAD MORE EXAMPLE
// ============================================

// In your controller/page
$page = $_GET['page'] ?? 1;
$users = User::paginate(12, $page);

// Check if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$paginator = \Plugs\Paginator\Pagination::fromArray($users)
    ->setOptions([
        'ajax_enabled' => true,
        'ajax_container' => '#user-grid',
        'load_more_text' => 'Load More Users',
    ]);

if ($isAjax) {
    // Return only the items HTML for AJAX
    foreach ($users['data'] as $user) {
        echo "<div class='user-card'>{$user->name}</div>";
    }
    exit;
}
?>

<!-- Full page -->
<div id="user-grid" class="user-grid">
    <?php foreach ($users['data'] as $user): ?>
        <div class="user-card">
            <h4><?= $user->name ?></h4>
            <p><?= $user->email ?></p>
        </div>
    <?php endforeach; ?>
</div>

<?= $paginator->renderLoadMore() ?>
<?= $paginator->getAjaxScript() ?>

<?php
// ============================================
// 6. ADVANCED FILTERING WITH PAGINATION
// ============================================

// Get filter parameters
$filters = [
    'status' => $_GET['status'] ?? 'all',
    'role' => $_GET['role'] ?? 'all',
    'sort' => $_GET['sort'] ?? 'created_at',
];

$query = User::query();

if ($filters['status'] !== 'all') {
    $query->where('status', $filters['status']);
}

if ($filters['role'] !== 'all') {
    $query->where('role', $filters['role']);
}

$query->orderBy($filters['sort'], 'DESC');
$users = $query->paginate(25);

$paginator = \Plugs\Paginator\Pagination::fromArray($users)
    ->appends($filters); // Keep all filters in pagination URLs
?>

<form method="GET">
    <select name="status">
        <option value="all">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>
    
    <select name="role">
        <option value="all">All Roles</option>
        <option value="admin">Admin</option>
        <option value="user">User</option>
    </select>
    
    <button type="submit">Filter</button>
</form>

<?= $paginator->render() ?>

<?php
// ============================================
// 7. BOOTSTRAP 5 STYLED PAGINATION
// ============================================

$paginator->setOptions([
    'container_class' => 'd-flex justify-content-between align-items-center',
    'pagination_class' => 'pagination mb-0',
    'link_class' => 'page-link',
    'active_class' => 'active',
    'disabled_class' => 'disabled',
    'info_class' => 'text-muted',
]);

echo $paginator->render();

// ============================================
// 8. TAILWIND CSS STYLED PAGINATION
// ============================================

$paginator->setOptions([
    'container_class' => 'flex justify-between items-center',
    'pagination_class' => 'inline-flex space-x-2',
    'link_class' => 'px-3 py-2 bg-white border rounded hover:bg-gray-100',
    'active_class' => 'bg-blue-500 text-white',
    'disabled_class' => 'opacity-50 cursor-not-allowed',
    'info_class' => 'text-gray-600',
]);

echo $paginator->renderWithEllipsis();

// ============================================
// 9. API RESPONSE (JSON)
// ============================================

// For API endpoints
header('Content-Type: application/json');
echo $paginator->toJson();

// Output example:
/*
{
    "data": [...],
    "current_page": 2,
    "per_page": 15,
    "total": 150,
    "last_page": 10,
    "from": 16,
    "to": 30,
    "first_page_url": "/users?page=1",
    "last_page_url": "/users?page=10",
    "next_page_url": "/users?page=3",
    "prev_page_url": "/users?page=1",
    "path": "/users"
}
*/

// ============================================
// 10. CUSTOM PATH AND QUERY PARAMS
// ============================================

$paginator->setPath('/custom/path')
          ->appends(['category' => 'tech', 'tag' => 'php'])
          ->setOptions(['max_links' => 10]);

echo $paginator->render();

// ============================================
// CSS STYLES - Add to your stylesheet
// ============================================
?>

<style>
/* ================================================
   DEFAULT PAGINATION STYLES
   ================================================ */

.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 0;
    flex-wrap: wrap;
    gap: 15px;
}

.pagination-info {
    color: #6c757d;
    font-size: 14px;
}

.pagination {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 5px;
}

.page-item {
    display: inline-block;
}

.page-link {
    display: block;
    padding: 8px 12px;
    text-decoration: none;
    color: #007bff;
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    transition: all 0.2s;
}

.page-link:hover {
    background-color: #e9ecef;
    color: #0056b3;
}

.page-item.active .page-link {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
    cursor: default;
}

.page-item.disabled .page-link {
    color: #6c757d;
    background-color: #fff;
    border-color: #dee2e6;
    cursor: not-allowed;
    opacity: 0.5;
}

.page-ellipsis .page-link {
    border: none;
    background: transparent;
    cursor: default;
}

/* ================================================
   LOAD MORE STYLE
   ================================================ */

.pagination-load-more {
    text-align: center;
    padding: 30px 0;
}

.btn-load-more {
    display: inline-block;
    padding: 12px 30px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    transition: background-color 0.3s;
}

.btn-load-more:hover {
    background-color: #0056b3;
}

.loading-indicator {
    color: #6c757d;
    margin-top: 10px;
}

.no-more-results {
    color: #6c757d;
    font-style: italic;
}

/* ================================================
   COMPACT STYLE
   ================================================ */

.pagination-compact .pagination {
    gap: 2px;
}

.pagination-compact .page-link {
    padding: 6px 10px;
    font-size: 14px;
    min-width: 35px;
    text-align: center;
}

/* ================================================
   SIMPLE STYLE
   ================================================ */

.pagination-simple .pagination {
    justify-content: center;
    gap: 15px;
}

/* ================================================
   MOBILE RESPONSIVE
   ================================================ */

@media (max-width: 768px) {
    .pagination-container {
        flex-direction: column;
    }
    
    .pagination {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .page-link {
        padding: 6px 10px;
        font-size: 14px;
    }
    
    /* Hide first/last on mobile */
    .pagination-container .page-item:first-child,
    .pagination-container .page-item:last-child {
        display: none;
    }
}

/* ================================================
   DARK MODE
   ================================================ */

@media (prefers-color-scheme: dark) {
    .page-link {
        background-color: #2d3748;
        color: #a0aec0;
        border-color: #4a5568;
    }
    
    .page-link:hover {
        background-color: #4a5568;
        color: #e2e8f0;
    }
    
    .page-item.active .page-link {
        background-color: #3182ce;
        border-color: #3182ce;
    }
    
    .pagination-info,
    .loading-indicator,
    .no-more-results {
        color: #a0aec0;
    }
}

/* ================================================
   BOOTSTRAP 5 COMPATIBILITY
   ================================================ */

.pagination.pagination-lg .page-link {
    padding: 12px 20px;
    font-size: 18px;
}

.pagination.pagination-sm .page-link {
    padding: 5px 10px;
    font-size: 12px;
}

/* ================================================
   TAILWIND-LIKE UTILITIES
   ================================================ */

.pagination-rounded .page-link {
    border-radius: 50%;
    width: 40px;
    height: 40px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pagination-pills .page-link {
    border-radius: 20px;
}

/* ================================================
   MODERN GRADIENT STYLE
   ================================================ */

.pagination-gradient .page-item.active .page-link {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

.pagination-gradient .page-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>

<?php
// ============================================
// 11. HELPER FUNCTIONS
// ============================================

/**
 * Quick pagination helper
 */
function paginate($data, $perPage = 15, $page = null) {
    $page = $page ?? $_GET['page'] ?? 1;
    
    if (is_array($data)) {
        return \Plugs\Paginator\Pagination::fromArray([
            'data' => array_slice($data, ($page - 1) * $perPage, $perPage),
            'per_page' => $perPage,
            'current_page' => $page,
            'total' => count($data)
        ]);
    }
    
    // Assume it's PlugModel pagination array
    return \Plugs\Paginator\Pagination::fromArray($data);
}

// Usage:
$users = User::paginate(15);
$paginator = paginate($users);
echo $paginator->render();
?>