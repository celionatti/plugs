<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Plugs\Http\ResponseFactory;

class HomeController
{
    /**
     * Return string (automatically wrapped in HTML response)
     */
    public function home(ServerRequestInterface $request)
    {
        return '<h1>Welcome Home!</h1>';
    }

    /**
     * Return array (automatically converted to JSON)
     */
    public function contact(ServerRequestInterface $request)
    {
        return [
            'email' => 'contact@example.com',
            'phone' => '+1234567890'
        ];
    }

    /**
     * Return PSR-7 response directly
     */
    public function about(ServerRequestInterface $request): ResponseInterface
    {
        return ResponseFactory::html('<h1>About Us</h1><p>We are awesome!</p>');
    }

    /**
     * Route with parameter
     */
    public function show(ServerRequestInterface $request)
    {
        $id = $request->getAttribute('id');
        
        return [
            'id' => $id,
            'title' => 'Post Title',
            'content' => 'Post content here...'
        ];
    }

    /**
     * POST request handler
     */
    public function post(ServerRequestInterface $request)
    {
        $id = $request->getAttribute('id');
        $body = $request->getParsedBody();
        
        return ResponseFactory::json([
            'message' => 'Post updated',
            'id' => $id,
            'data' => $body
        ], 200);
    }

    /**
     * DELETE request handler
     */
    public function delete(ServerRequestInterface $request)
    {
        $id = $request->getAttribute('id');
        
        return ResponseFactory::json([
            'message' => 'Post deleted',
            'id' => $id
        ], 200);
    }

    /**
     * Route with optional parameter
     */
    public function posts(ServerRequestInterface $request)
    {
        $id = $request->getAttribute('id');
        
        if ($id) {
            return "Showing post: {$id}";
        }
        
        return "Showing all posts";
    }

    /**
     * User profile with constraints
     */
    public function user(ServerRequestInterface $request)
    {
        $id = $request->getAttribute('id');
        
        return ResponseFactory::json([
            'id' => (int)$id,
            'username' => 'user' . $id,
            'email' => "user{$id}@example.com"
        ]);
    }

    /**
     * Profile with multiple parameters
     */
    public function profile(ServerRequestInterface $request)
    {
        $username = $request->getAttribute('username');
        $tab = $request->getAttribute('tab') ?? 'posts';
        
        return [
            'username' => $username,
            'active_tab' => $tab,
            'data' => "Content for {$tab} tab"
        ];
    }

    /**
     * API endpoints
     */
    public function apiUsers(ServerRequestInterface $request)
    {
        return [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie']
        ];
    }

    public function apiCreateUser(ServerRequestInterface $request)
    {
        $data = $request->getParsedBody();
        
        return ResponseFactory::json([
            'message' => 'User created successfully',
            'user' => [
                'id' => rand(100, 999),
                'name' => $data['name'] ?? 'Unknown',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ], 201);
    }

    public function adminDashboard(ServerRequestInterface $request)
    {
        return ResponseFactory::html('<h1>Admin Dashboard</h1>');
    }

    /**
     * Multiple HTTP methods
     */
    public function form(ServerRequestInterface $request)
    {
        if ($request->getMethod() === 'GET') {
            return '<form method="POST"><input name="name"><button>Submit</button></form>';
        }
        
        $data = $request->getParsedBody();
        return ResponseFactory::json(['submitted' => $data]);
    }

    /**
     * Webhook handler (all methods)
     */
    public function webhook(ServerRequestInterface $request)
    {
        return ResponseFactory::json([
            'method' => $request->getMethod(),
            'received' => true,
            'timestamp' => time()
        ]);
    }
}