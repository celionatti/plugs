<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use App\Http\Requests\UserRequest;
use App\Services\UserService;
use App\Models\User;
use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AdminUserController
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Display a listing of the users.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $users = $this->userService->getAllUsers();

        return response(view('admin::index', [
            'title' => 'Users Management',
            'users' => $users
        ]));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return response(view('admin::create', [
            'title' => 'Create User'
        ]));
    }

    /**
     * Store a newly created user.
     */
    public function store(UserRequest $request): ResponseInterface
    {
        $this->userService->createUser($request->validated());

        return ResponseFactory::redirect(route('admin.users.index'))
            ->with('success', 'User created successfully.');
    }

    /**
     * Display user details.
     */
    public function show(ServerRequestInterface $request, $id): ResponseInterface
    {
        $user = User::find($id);

        return response(view('admin::show', [
            'user' => $user
        ]));
    }

    /**
     * Show edit form.
     */
    public function edit(ServerRequestInterface $request, $id): ResponseInterface
    {
        $user = User::find($id);

        return response(view('admin::edit', [
            'user' => $user
        ]));
    }

    /**
     * Update user.
     */
    public function update(UserRequest $request, $id): ResponseInterface
    {
        $user = User::find($id);
        $this->userService->updateUser($user, $request->validated());

        return ResponseFactory::redirect(route('admin.users.index'))
            ->with('success', 'User updated successfully.');
    }

    /**
     * Delete user.
     */
    public function destroy(ServerRequestInterface $request, $id): ResponseInterface
    {
        $user = User::find($id);
        $this->userService->deleteUser($user);

        return ResponseFactory::redirect(route('admin.users.index'))
            ->with('success', 'User deleted successfully.');
    }
}
