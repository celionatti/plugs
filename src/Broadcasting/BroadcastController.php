<?php

declare(strict_types=1);

namespace Plugs\Broadcasting;

use Plugs\Container\Container;

/**
 * BroadcastController
 *
 * Handles the POST /broadcasting/auth endpoint.
 * Authenticates the current user and verifies their authorization
 * for the requested private or presence channel.
 *
 * This controller is designed to be registered in your routes file:
 *
 *   Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate']);
 */
class BroadcastController
{
    /**
     * Authenticate a user for a private or presence channel.
     *
     * Expects POST body: { "channel_name": "private-user.42" }
     * Returns JSON:
     *   Success: { "auth": "signed_token", "channel_data": { ... } }
     *   Failure: 403 Forbidden
     */
    public function authenticate(): void
    {
        // 1. Get the authenticated user
        $user = $this->resolveUser();

        if (!$user) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthenticated']);
            return;
        }

        // 2. Get the requested channel from POST body
        $input = $this->getInput();
        $channel = $input['channel_name'] ?? '';

        if (empty($channel)) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'channel_name is required']);
            return;
        }

        // 3. Only private and presence channels need auth
        if (!str_starts_with($channel, 'private-') && !str_starts_with($channel, 'presence-')) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Public channels do not require authentication']);
            return;
        }

        // 4. Authorize via BroadcastManager
        $manager = BroadcastManager::getInstance();
        $result  = $manager->authorizeAndSign($user, $channel);

        if ($result === null) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden']);
            return;
        }

        // 5. Return the auth token (and presence data if applicable)
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Resolve the currently authenticated user.
     *
     * Tries the Auth facade/manager, falls back to session-based resolution.
     *
     * @return object|null
     */
    protected function resolveUser(): ?object
    {
        // Try via the Auth facade
        if (class_exists(\Plugs\Facades\Auth::class)) {
            try {
                $user = \Plugs\Facades\Auth::user();
                if ($user) {
                    return $user;
                }
            } catch (\Throwable $e) {
                // Fall through
            }
        }

        // Try via container
        try {
            $container = Container::getInstance();
            if ($container->has('auth')) {
                $auth = $container->make('auth');
                return $auth->user();
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return null;
    }

    /**
     * Get the POST input data.
     *
     * @return array
     */
    protected function getInput(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            return json_decode($raw, true) ?: [];
        }

        return $_POST;
    }
}
