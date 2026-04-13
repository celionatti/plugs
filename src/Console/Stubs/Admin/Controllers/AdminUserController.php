<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Modules\Admin\Services\AdminUserService;
use Modules\Auth\Models\User;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AdminUserController
{
    protected AdminUserService $userService;

    public function __construct(AdminUserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the users.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $users = $this->userService->getAllUsers();
        } catch (\Throwable $e) {
            $users = [];
        }

        return response(view('admin::users.index', [
            'title' => 'Users Management',
            'users' => $users
        ]));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return response(view('admin::users.create', [
            'title' => 'Create User'
        ]));
    }

    /**
     * Store a newly created user.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $this->userService->createUser($request->getParsedBody());

        return ResponseFactory::redirect('/admin/users')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display user details.
     */
    public function show(ServerRequestInterface $request, $id): ResponseInterface
    {
        $user = $this->userService->findUser((int) $id);

        if (!$user) {
            return ResponseFactory::redirect('/admin/users')
                ->with('error', 'User not found.');
        }

        return response(view('admin::users.show', [
            'title' => 'User Details',
            'user' => $user
        ]));
    }

    /**
     * Show edit form.
     */
    public function edit(ServerRequestInterface $request, $id): ResponseInterface
    {
        $user = $this->userService->findUser((int) $id);

        if (!$user) {
            return ResponseFactory::redirect('/admin/users')
                ->with('error', 'User not found.');
        }

        return response(view('admin::users.edit', [
            'title' => 'Edit User',
            'user' => $user
        ]));
    }

    /**
     * Update user.
     */
    public function update(ServerRequestInterface $request, $id): ResponseInterface
    {
        $user = User::find($id);

        if (!$user) {
            return ResponseFactory::redirect('/admin/users')
                ->with('error', 'User not found.');
        }

        $this->userService->updateUser($user, $request->getParsedBody());

        return ResponseFactory::redirect('/admin/users')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Delete user.
     */
    public function destroy(ServerRequestInterface $request, $id): ResponseInterface
    {
        $user = $this->userService->findUser((int) $id);

        if ($user) {
            $this->userService->deleteUser($user);
        }

        return ResponseFactory::redirect('/admin/users')
            ->with('success', 'User deleted successfully.');
    }
}
