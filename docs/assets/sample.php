<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Asset Manager Usage Examples
|--------------------------------------------------------------------------
|
| Examples of how to use the AssetManager in your Plugs framework.
*/

// ====================
// 1. IN VIEWS (*.plug.php files)
// ====================

?>
<!DOCTYPE html>
<html>
<head>
    <title>My App</title>
    
    <!-- Method 1: Simple asset URL with versioning -->
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    
    <!-- Method 2: Generate multiple CSS tags -->
    {{{ css(['assets/css/normalize.css', 'assets/css/app.css']) }}}
    
    <!-- Method 3: Use registered assets by name -->
    {{{ css(['normalize', 'app']) }}}
    
    <!-- Method 4: Compile and combine CSS files -->
    {{{ css([
        compile_css(['assets/css/normalize.css', 'assets/css/app.css'], 'frontend')
    ]) }}}
</head>
<body>
    <h1>Welcome to My App</h1>
    
    <!-- JavaScript assets at bottom -->
    {{{ js(['jquery', 'app']) }}}
    
    <!-- Or compile JS -->
    <script src="{{ asset(compile_js(['assets/js/app.js', 'assets/js/components.js'], 'bundle')) }}"></script>
</body>
</html>

<?php

// ====================
// 2. IN CONTROLLERS
// ====================

namespace App\Controllers;

use Plugs\Base\Controller\Controller;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class HomeController extends Controller
{
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        // Compile assets before rendering view
        $cssBundle = asset_manager()->compileCSS([
            'assets/css/normalize.css',
            'assets/css/utilities.css',
            'assets/css/app.css',
        ], 'frontend');
        
        $jsBundle = asset_manager()->compileJS([
            'assets/js/jquery.min.js',
            'assets/js/app.js',
        ], 'frontend');
        
        return $this->view('home', [
            'cssBundle' => $cssBundle,
            'jsBundle' => $jsBundle,
        ]);
    }
    
    public function clearCache(ServerRequestInterface $request): ResponseInterface
    {
        try {
            clear_asset_cache();
            return $this->json(['message' => 'Asset cache cleared successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}

// ====================
// 3. PROGRAMMATIC USAGE
// ====================

// Get asset manager instance
$manager = asset_manager();

// Register assets dynamically
$manager->css('custom', 'assets/css/custom.css', ['normalize']);
$manager->js('custom', 'assets/js/custom.js', ['jquery']);

// Compile with registered assets (resolves dependencies automatically)
$cssUrl = $manager->compileCSS(['custom'], 'custom-bundle');
// This will include: normalize.css, custom.css (in that order)

$jsUrl = $manager->compileJS(['custom'], 'custom-bundle');
// This will include: jquery.min.js, custom.js (in that order)

// Get asset URL with versioning
$logoUrl = $manager->url('assets/images/logo.png');

// Generate HTML tags
echo $manager->tags(['frontend'], 'css');
echo $manager->tags(['frontend'], 'js');

// Check if asset is cached
if (!$manager->isCached('frontend', 'css')) {
    $manager->compileCSS(['app'], 'frontend');
}

// ====================
// 4. IN BOOTSTRAP FILE (bootstrap/app.php)
// ====================

// Add this after loading other services:

// Precompile assets on application boot (optional - good for production)
if (env('APP_ENV', 'production') === 'production') {
    $manager = asset_manager();
    
    // Compile frontend assets
    $manager->compileCSS(['normalize', 'utilities', 'app'], 'frontend');
    $manager->compileJS(['jquery', 'app'], 'frontend');
    
    // Compile admin assets
    $manager->compileCSS(['normalize', 'utilities', 'app', 'admin'], 'admin');
    $manager->compileJS(['jquery', 'bootstrap', 'app', 'admin'], 'admin');
}

// ====================
// 5. COMMAND LINE SCRIPT (compile-assets.php)
// ====================

#!/usr/bin/env php
<?php

require __DIR__ . '/bootstrap/app.php';

echo "Compiling assets...\n";

$manager = asset_manager();

// Clear old cache
$manager->clearCache();
echo "✓ Cache cleared\n";

// Compile frontend assets
$frontendCss = $manager->compileCSS([
    'assets/css/normalize.css',
    'assets/css/utilities.css',
    'assets/css/app.css',
], 'frontend');
echo "✓ Frontend CSS compiled: {$frontendCss}\n";

$frontendJs = $manager->compileJS([
    'assets/js/jquery.min.js',
    'assets/js/app.js',
], 'frontend');
echo "✓ Frontend JS compiled: {$frontendJs}\n";

// Compile admin assets
$adminCss = $manager->compileCSS([
    'assets/css/normalize.css',
    'assets/css/utilities.css',
    'assets/css/app.css',
    'assets/css/admin.css',
], 'admin');
echo "✓ Admin CSS compiled: {$adminCss}\n";

$adminJs = $manager->compileJS([
    'assets/js/jquery.min.js',
    'assets/js/bootstrap.bundle.min.js',
    'assets/js/app.js',
    'assets/js/admin.js',
], 'admin');
echo "✓ Admin JS compiled: {$adminJs}\n";

echo "\nAll assets compiled successfully!\n";

// ====================
// 6. LAYOUT TEMPLATE EXAMPLE (views/layouts/app.plug.php)
// ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'My App')</title>
    
    @php
        // Compile assets once per request
        $cssBundle = compile_css(['normalize', 'utilities', 'app'], 'frontend');
    @endphp
    
    <link rel="stylesheet" href="{{ asset($cssBundle) }}">
    
    @stack('styles')
</head>
<body>
    @yield('content')
    
    @php
        $jsBundle = compile_js(['jquery', 'app'], 'frontend');
    @endphp
    
    <script src="{{ asset($jsBundle) }}"></script>
    
    @stack('scripts')
</body>
</html>

<?php

// ====================
// 7. ENVIRONMENT-SPECIFIC CONFIGURATION
// ====================

// In .env file:
// Development
// ASSET_MINIFY=false
// ASSET_COMBINE=false
// ASSET_VERSIONING=false

// Production
// ASSET_MINIFY=true
// ASSET_COMBINE=true
// ASSET_VERSIONING=true

// ====================
// 8. ADVANCED: CUSTOM MIDDLEWARE FOR ASSET COMPILATION
// ====================

namespace App\Middleware;

use Plugs\Http\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AssetCompilationMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Compile assets before handling request
        if (env('APP_ENV') === 'development') {
            $manager = asset_manager();
            
            // Auto-compile on every request in development
            $manager->compileCSS(['app'], 'frontend');
            $manager->compileJS(['app'], 'frontend');
        }
        
        return $handler->handle($request);
    }
}