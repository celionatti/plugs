<?php

/**
 * ============================================
 * PAGINATOR CLASS - USAGE EXAMPLES
 * ============================================
 */

use Plugs\Support\Paginator;
use App\Models\Category;
use App\Models\Post;

// ============================================
// EXAMPLE 1: Basic Array Pagination
// ============================================
$data = range(1, 100); // Your data array
$currentPage = $_GET['page'] ?? 1;

$paginator = new Paginator($data, perPage: 10, currentPage: (int)$currentPage);

echo $paginator->render(); // Outputs full pagination HTML
print_r($paginator->items()); // Gets items for current page


// ============================================
// EXAMPLE 2: With Query Builder (Model)
// ============================================
$query = Category::where('is_active', 1)->orderBy('name', 'ASC');
$currentPage = (int)($_GET['page'] ?? 1);

$paginator = Paginator::fromQuery($query, perPage: 15, currentPage: $currentPage);

$categories = $paginator->items(); // Array of categories for current page


// ============================================
// EXAMPLE 3: Custom Options
// ============================================
$paginator = new Paginator($data, 20, $currentPage);
$paginator->setOptions([
    'max_links' => 5, // Show max 5 page numbers
    'prev_text' => '← Prev',
    'next_text' => 'Next →',
    'show_first_last' => false, // Hide first/last buttons
    'container_class' => 'my-pagination-wrapper',
    'pagination_class' => 'my-pagination',
]);


// ============================================
// EXAMPLE 4: Appending Query Parameters
// ============================================
$paginator = Paginator::fromQuery($query, 15, $currentPage);
$paginator->appends([
    'sort_by' => $_GET['sort_by'] ?? 'name',
    'status' => $_GET['status'] ?? 'all',
    'search' => $_GET['search'] ?? '',
]);

echo $paginator->render();
// URLs will be: ?page=2&sort_by=name&status=all&search=keyword


// ============================================
// EXAMPLE 5: Simple Pagination (No Total Count)
// ============================================
$paginator = Paginator::simple($data, 15, $currentPage);
echo $paginator->renderSimple(); // Only shows Prev/Next


// ============================================
// EXAMPLE 6: In Controller (Full Example)
// ============================================
class AdminCategoryController extends Controller
{
    public function manage(Request $request): Response
    {
        $queryParams = $request->getQueryParams();
        $sortBy = $queryParams['sort_by'] ?? 'name';
        $statusFilter = $queryParams['status'] ?? 'all';
        $currentPage = (int)($queryParams['page'] ?? 1);
        $perPage = 20;

        // Build query
        $query = Category::query();

        if ($statusFilter === '1') {
            $query = $query->where('is_active', 1);
        } elseif ($statusFilter === '0') {
            $query = $query->where('is_active', 0);
        }

        switch ($sortBy) {
            case 'date':
                $query = $query->orderBy('created_at', 'DESC');
                break;
            case 'updated':
                $query = $query->orderBy('updated_at', 'DESC');
                break;
            default:
                $query = $query->orderBy('name', 'ASC');
                break;
        }

        // Create paginator
        $paginator = Paginator::fromQuery($query, $perPage, $currentPage);
        
        // Append current filters to pagination links
        $paginator->appends([
            'sort_by' => $sortBy,
            'status' => $statusFilter,
        ]);

        $categories = $paginator->items();

        return $this->view('admin.categories.manage', [
            'categories' => $categories,
            'paginator' => $paginator,
            'sort_by' => $sortBy,
            'status_filter' => $statusFilter,
        ]);
    }
}


// ============================================
// EXAMPLE 7: In Blade View
// ============================================
/*
@extends('layouts.admin')

@section('content')
<div class="categories-grid">
    @foreach($categories as $category)
        <div class="category-card">
            <h3>{{ $category->name }}</h3>
        </div>
    @endforeach
</div>

<!-- Render pagination -->
{!! $paginator->render() !!}

<!-- OR simple pagination -->
{!! $paginator->renderSimple() !!}

<!-- OR access properties -->
<div class="info">
    Page {{ $paginator->currentPage }} of {{ $paginator->lastPage }}
    Showing {{ $paginator->from }} to {{ $paginator->to }} of {{ $paginator->total }} items
</div>
@endsection
*/


// ============================================
// EXAMPLE 8: JSON API Response
// ============================================
$paginator = Paginator::fromQuery($query, 15, $currentPage);

return $this->json([
    'success' => true,
    'pagination' => $paginator->toArray(),
    // OR
    // 'pagination' => [
    //     'data' => $paginator->items(),
    //     'current_page' => $paginator->currentPage,
    //     'last_page' => $paginator->lastPage,
    //     'total' => $paginator->total,
    //     'per_page' => $paginator->perPage,
    //     'from' => $paginator->from,
    //     'to' => $paginator->to,
    // ]
]);


// ============================================
// EXAMPLE 9: Custom Path
// ============================================
$paginator = new Paginator($data, 15, $currentPage);
$paginator->setPath('/admin/categories');
// URLs will be: /admin/categories?page=2


// ============================================
// EXAMPLE 10: Bootstrap/Tailwind Styles
// ============================================

// Bootstrap 5 Style
$paginator->setOptions([
    'container_class' => 'd-flex justify-content-between align-items-center',
    'pagination_class' => 'pagination mb-0',
    'link_class' => 'page-link',
    'active_class' => 'active',
    'info_class' => 'text-muted',
]);

// Tailwind CSS Style
$paginator->setOptions([
    'container_class' => 'flex justify-between items-center',
    'pagination_class' => 'flex space-x-2',
    'link_class' => 'px-3 py-2 rounded-md text-sm',
    'active_class' => 'bg-blue-500 text-white',
    'info_class' => 'text-gray-600',
]);


// ============================================
// EXAMPLE 11: Checking Pagination State
// ============================================
if ($paginator->hasPages()) {
    echo "Multiple pages available";
}

if ($paginator->onFirstPage()) {
    echo "You're on the first page";
}

if ($paginator->hasNextPage()) {
    echo '<a href="' . $paginator->url($paginator->nextPage()) . '">Next</a>';
}


// ============================================
// EXAMPLE 12: Manual URL Building
// ============================================
echo '<a href="' . $paginator->url(1) . '">First Page</a>';
echo '<a href="' . $paginator->url(5) . '">Page 5</a>';
echo '<a href="' . $paginator->url($paginator->lastPage) . '">Last Page</a>';


// ============================================
// EXAMPLE 13: With Search/Filters
// ============================================
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

$query = Post::query();

if ($search) {
    $query = $query->where('title', 'LIKE', "%{$search}%");
}

if ($category) {
    $query = $query->where('category_id', $category);
}

$paginator = Paginator::fromQuery($query, 15, $currentPage);
$paginator->appends([
    'search' => $search,
    'category' => $category,
]);

// Pagination will preserve search and category in all links


// ============================================
// EXAMPLE 14: Different Items Per Page Options
// ============================================
$perPageOptions = [10, 25, 50, 100];
$perPage = in_array($_GET['per_page'] ?? 25, $perPageOptions) 
    ? (int)$_GET['per_page'] 
    : 25;

$paginator = Paginator::fromQuery($query, $perPage, $currentPage);
$paginator->appends(['per_page' => $perPage]);

// In view:
/*
<select name="per_page" onchange="window.location.href='?page=1&per_page='+this.value">
    <option value="10" {{ $perPage === 10 ? 'selected' : '' }}>10</option>
    <option value="25" {{ $perPage === 25 ? 'selected' : '' }}>25</option>
    <option value="50" {{ $perPage === 50 ? 'selected' : '' }}>50</option>
    <option value="100" {{ $perPage === 100 ? 'selected' : '' }}>100</option>
</select>
*/