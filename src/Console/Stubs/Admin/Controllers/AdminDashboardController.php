<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Modules\Auth\Models\User;
use Modules\Article\Models\Article;
use Plugs\FeatureModule\FeatureModuleManager;

class AdminDashboardController
{
    /**
     * Display the admin dashboard.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $recentUsers = User::latest()->limit(5)->get();
        } catch (\Exception $e) {
            $recentUsers = [];
        }

        $totalPlugs = count(FeatureModuleManager::getInstance()->getModules());

        $securityConfig = config('security', []);
        $securityTagsCount = 0;
        
        if ($securityConfig['csrf']['enabled'] ?? false) $securityTagsCount++;
        if ($securityConfig['security_shield']['enabled'] ?? false) $securityTagsCount++;
        if ($securityConfig['csp']['enabled'] ?? false) $securityTagsCount++;
        if ($securityConfig['rate_limit']['enabled'] ?? false) $securityTagsCount++;
        if ($securityConfig['cors']['enabled'] ?? false) $securityTagsCount++;
        if ($securityConfig['profiler']['enabled'] ?? false) $securityTagsCount++;

        $stats = [
            'users_count' => User::count(),
            'articles_count' => class_exists(Article::class) ? Article::count() : 0,
            'recent_users' => $recentUsers,
            'plugs_count' => $totalPlugs,
            'security_tags_count' => $securityTagsCount,
        ];

        return response(view('admin::dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats
        ]));
    }
}
