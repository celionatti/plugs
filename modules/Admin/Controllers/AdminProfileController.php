<?php

declare(strict_types=1);

namespace Modules\Admin\Controllers;

use Plugs\Http\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use App\Models\User;

class AdminProfileController
{
    /**
     * Display personal profile.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $user = auth()->user();

        return response(view('admin::profile', [
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
        $data = $request->getParsedBody();

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }

        $user->fill($data);
        $user->save();

        return ResponseFactory::redirect('/admin/profile')
            ->with('success', 'Profile updated successfully.');
    }
}
