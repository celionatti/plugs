<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Models\User;
use App\Models\Article;

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

        $stats = [
            'users_count' => User::count(),
            'articles_count' => class_exists(Article::class) ? Article::count() : 0,
            'recent_users' => $recentUsers,
        ];

        return response(view('admin::dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats
        ]));
    }
}
