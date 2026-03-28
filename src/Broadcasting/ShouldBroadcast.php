<?php

declare(strict_types=1);

namespace Plugs\Broadcasting;

/**
 * ShouldBroadcast Interface
 *
 * Any Event class implementing this interface will be automatically
 * published to the SSE stream when dispatched via the Event Dispatcher.
 *
 * This is the Plugs equivalent of Laravel's ShouldBroadcast — but it
 * pushes through the native SSE daemon instead of WebSocket servers.
 */
interface ShouldBroadcast
{
    /**
     * Get the channel(s) the event should broadcast on.
     *
     * Return a channel name string, an array of channel name strings,
     * or Channel value objects (Channel, PrivateChannel, PresenceChannel).
     *
     * Channel naming conventions:
     *   - 'chat'                → Public channel (no auth)
     *   - 'private-user.42'     → Private channel (requires auth)
     *   - 'presence-lobby.1'    → Presence channel (auth + member tracking)
     *
     * @return string|array<string>|Channel|array<Channel>
     */
    public function broadcastOn(): string|array|Channel;

    /**
     * Get the SSE event/topic name.
     *
     * Defaults to the class basename (e.g. 'NewMessageEvent' → 'NewMessageEvent').
     * Override to use a custom topic name.
     *
     * @return string
     */
    public function broadcastAs(): string;

    /**
     * Get the data payload to broadcast.
     *
     * Defaults to all public properties of the event.
     * Override to customize the payload sent to clients.
     *
     * @return array
     */
    public function broadcastWith(): array;
}
