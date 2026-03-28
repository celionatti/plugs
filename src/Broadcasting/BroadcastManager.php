<?php

declare(strict_types=1);

namespace Plugs\Broadcasting;

use Plugs\SSE\Publisher;

/**
 * BroadcastManager
 *
 * The central orchestrator for the broadcasting system. Manages:
 * 1. Channel authorization callbacks for private/presence channels
 * 2. Event broadcasting via the SSE Publisher
 * 3. Presence channel member info resolution
 * 4. Route registration for the /broadcasting/auth endpoint
 */
class BroadcastManager
{
    /**
     * Registered channel authorization callbacks.
     * Key: channel pattern (e.g. 'private-user.{id}')
     * Value: Closure that receives ($user, ...$params) and returns bool|array
     *
     * @var array<string, \Closure>
     */
    protected array $channels = [];

    /**
     * Singleton instance.
     */
    protected static ?self $instance = null;

    /**
     * Get the singleton instance.
     */
    public static function getInstance(): self
    {
        if (static::$instance === null) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * Register a channel authorization callback.
     *
     * For Private channels, the callback should return true/false.
     * For Presence channels, the callback should return an array of
     * user info (displayed to other members) or false to deny.
     *
     * The channel pattern supports {parameter} placeholders:
     *   'private-user.{id}' → matches 'private-user.42'
     *   'presence-game.{gameId}' → matches 'presence-game.7'
     *
     * @param string $pattern Channel name pattern
     * @param \Closure $callback fn($user, ...$params): bool|array
     * @return void
     */
    public function channel(string $pattern, \Closure $callback): void
    {
        $this->channels[$pattern] = $callback;
    }

    /**
     * Authorize a user for a specific channel.
     *
     * @param object $user The authenticated user
     * @param string $channel The requested channel name
     * @return bool|array False if denied, true for private, array for presence
     */
    public function authorize(object $user, string $channel): bool|array
    {
        foreach ($this->channels as $pattern => $callback) {
            $params = $this->matchPattern($pattern, $channel);

            if ($params !== null) {
                $result = $callback($user, ...$params);

                // Presence channels should return user info array
                if (is_array($result)) {
                    return $result;
                }

                return (bool) $result;
            }
        }

        // No matching channel pattern found — deny by default
        return false;
    }

    /**
     * Broadcast a ShouldBroadcast event to its channels.
     *
     * This is called automatically by the Event Dispatcher when an
     * event implementing ShouldBroadcast is dispatched.
     *
     * @param ShouldBroadcast $event The event to broadcast
     * @return void
     */
    public function broadcast(ShouldBroadcast $event): void
    {
        $channels = $this->resolveChannels($event->broadcastOn());
        $topic    = $event->broadcastAs();
        $payload  = $event->broadcastWith();

        foreach ($channels as $channelName) {
            try {
                Publisher::emit($channelName, [
                    'event' => $topic,
                    'data'  => $payload,
                ]);
            } catch (\Throwable $e) {
                error_log("[Broadcasting] Failed to broadcast '{$topic}' on '{$channelName}': " . $e->getMessage());
            }
        }
    }

    /**
     * Generate an auth token for a user on a given channel.
     *
     * @param object $user The authenticated user
     * @param string $channel The channel name
     * @return string|null The signed token, or null if authorization fails
     */
    public function authorizeAndSign(object $user, string $channel): ?array
    {
        $result = $this->authorize($user, $channel);

        if ($result === false) {
            return null;
        }

        $userId = method_exists($user, 'getAuthIdentifier')
            ? $user->getAuthIdentifier()
            : ($user->id ?? 0);

        $token = BroadcastToken::sign($channel, $userId);

        $response = ['auth' => $token];

        // For presence channels, include user info
        if (str_starts_with($channel, 'presence-')) {
            $response['channel_data'] = [
                'user_id'   => $userId,
                'user_info' => is_array($result) ? $result : $this->defaultPresenceInfo($user),
            ];
        }

        return $response;
    }

    /**
     * Get default presence info from a user object.
     *
     * @param object $user
     * @return array
     */
    protected function defaultPresenceInfo(object $user): array
    {
        $info = [];

        $info['id'] = method_exists($user, 'getAuthIdentifier')
            ? $user->getAuthIdentifier()
            : ($user->id ?? null);

        // Try common username fields
        foreach (['username', 'name', 'display_name', 'email'] as $field) {
            if (isset($user->$field)) {
                $info['name'] = $user->$field;
                break;
            }
        }

        return $info;
    }

    /**
     * Resolve channel definitions into an array of channel name strings.
     *
     * @param string|array|Channel $channels
     * @return array<string>
     */
    protected function resolveChannels(string|array|Channel $channels): array
    {
        if ($channels instanceof Channel) {
            return [$channels->name];
        }

        if (is_string($channels)) {
            return [$channels];
        }

        return array_map(function ($channel) {
            return $channel instanceof Channel ? $channel->name : (string) $channel;
        }, $channels);
    }

    /**
     * Match a channel name against a pattern with {parameter} placeholders.
     *
     * @param string $pattern e.g. 'private-user.{id}'
     * @param string $channel e.g. 'private-user.42'
     * @return array|null Extracted parameter values, or null if no match
     */
    protected function matchPattern(string $pattern, string $channel): ?array
    {
        // Escape regex special characters in the pattern, then replace {param} with capture groups
        $regex = preg_replace('/\\\{[^}]+\\\}/', '([^.]+)', preg_quote($pattern, '/'));
        $regex = '/^' . $regex . '$/';

        if (preg_match($regex, $channel, $matches)) {
            array_shift($matches); // Remove the full match
            return $matches;
        }

        return null;
    }

    /**
     * Get all registered channel patterns.
     *
     * @return array<string, \Closure>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Reset the manager (primarily for testing).
     */
    public static function reset(): void
    {
        if (static::$instance) {
            static::$instance->channels = [];
        }
        static::$instance = null;
    }
}
