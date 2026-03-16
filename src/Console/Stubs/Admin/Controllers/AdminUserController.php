<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

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
        $data = $request->getParsedBody();

        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        unset($data['password_confirmation'], $data['_token'], $data['_method']);

        User::create($data);

        return ResponseFactory::redirect('/admin/users')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display user details.
     */
    public function show(ServerRequestInterface $request, $id): ResponseInterface
    {
        $user = User::find($id);

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
        $user = User::find($id);

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

        $input = $request->getParsedBody();
        $data = array_intersect_key($input, array_flip(['name', 'email', 'password']));

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }

        $user->fill($data);
        $user->save();

        return ResponseFactory::redirect('/admin/users')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Delete user.
     */
    public function destroy(ServerRequestInterface $request, $id): ResponseInterface
    {
        $user = User::find($id);

        if ($user) {
            $user->delete();
        }

        return ResponseFactory::redirect('/admin/users')
            ->with('success', 'User deleted successfully.');
    }
}
