<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Modules\Admin\Services\AdminUserService;
use App\Models\User;

class AdminProfileController
{
    protected AdminUserService $userService;

    public function __construct(AdminUserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display personal profile.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $user = auth()->user();

        return response(view('admin::profile.index', [
            'title' => 'My Profile',
            'user' => $user
        ]));
    }

    /**
     * Update profile.
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $user = auth()->user();
        $this->userService->updateUser($user, $request->getParsedBody());

        return ResponseFactory::redirect('/admin/profile')
            ->with('success', 'Profile updated successfully.');
    }
}
