<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AdminUserController
{
    /**
     * Display a listing of the users.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return response(view('admin::index', [
            'title' => 'Admin - Users Management',
            'users' => [] // Logic to fetch users would go here
        ]));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        return response(view('admin::create', [
            'title' => 'Admin - Create User'
        ]));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        // Logic to store user
        return ResponseFactory::redirect(route('admin.users.index'));
    }

    /**
     * Display the specified user.
     */
    public function show(ServerRequestInterface $request, $id): ResponseInterface
    {
        return response(view('admin::show', [
            'id' => $id
        ]));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(ServerRequestInterface $request, $id): ResponseInterface
    {
        return response(view('admin::edit', [
            'id' => $id
        ]));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(ServerRequestInterface $request, $id): ResponseInterface
    {
        // Logic to update user
        return ResponseFactory::redirect(route('admin.users.index'));
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(ServerRequestInterface $request, $id): ResponseInterface
    {
        // Logic to delete user
        return ResponseFactory::redirect(route('admin.users.index'));
    }
}
