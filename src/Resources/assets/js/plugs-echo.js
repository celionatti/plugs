/**
 * Plugs Echo — High-Level Broadcasting Client
 *
 * A channel-oriented JavaScript client that wraps the native PlugsSSE
 * transport with authentication, private channels, and presence tracking.
 *
 * This is the Plugs equivalent of Laravel Echo, built on SSE instead of WebSockets.
 *
 * Usage:
 *   const echo = new PlugsEcho();
 *   echo.channel('chat').listen('NewMessage', (data) => { ... });
 *   echo.private('user.42').listen('BalanceUpdated', (data) => { ... });
 *   echo.join('game-lobby.1')
 *       .here((members) => { ... })
 *       .joining((member) => { ... })
 *       .leaving((member) => { ... })
 *       .listen('RoundStarted', (data) => { ... });
 */
class PlugsEcho {
    /**
     * @param {Object} options
     * @param {string}  options.host        - SSE daemon host (default: current origin)
     * @param {string}  options.authEndpoint - Auth endpoint (default: /broadcasting/auth)
     * @param {Object}  options.authHeaders  - Extra headers for auth requests
     * @param {string}  options.csrfToken    - CSRF token (auto-detected from meta tag)
     */
    constructor(options = {}) {
        this.host = options.host || window.location.origin;
        this.authEndpoint = options.authEndpoint || '/_plugs/broadcasting/auth';
        this.authHeaders = options.authHeaders || {};
        this.csrfToken = options.csrfToken || this._detectCsrfToken();

        this._channels = {};
        this._authTokenCache = {};
    }

    /**
     * Subscribe to a public channel.
     * @param {string} name - Channel name (e.g. 'chat', 'notifications')
     * @returns {PlugsPublicChannel}
     */
    channel(name) {
        if (!this._channels[name]) {
            this._channels[name] = new PlugsPublicChannel(this, name);
        }
        return this._channels[name];
    }

    /**
     * Subscribe to a private (authenticated) channel.
     * @param {string} name - Channel name WITHOUT the 'private-' prefix
     * @returns {PlugsPrivateChannel}
     */
    private(name) {
        const fullName = `private-${name}`;
        if (!this._channels[fullName]) {
            this._channels[fullName] = new PlugsPrivateChannel(this, fullName);
        }
        return this._channels[fullName];
    }

    /**
     * Join a presence channel (authenticated + member tracking).
     * @param {string} name - Channel name WITHOUT the 'presence-' prefix
     * @returns {PlugsPresenceChannel}
     */
    join(name) {
        const fullName = `presence-${name}`;
        if (!this._channels[fullName]) {
            this._channels[fullName] = new PlugsPresenceChannel(this, fullName);
        }
        return this._channels[fullName];
    }

    /**
     * Leave and disconnect from a channel.
     * @param {string} name - Full channel name or short name
     */
    leave(name) {
        // Try full names first, then prefixed versions
        const candidates = [
            name,
            `private-${name}`,
            `presence-${name}`,
        ];

        for (const channelName of candidates) {
            if (this._channels[channelName]) {
                this._channels[channelName].disconnect();
                delete this._channels[channelName];
                delete this._authTokenCache[channelName];
            }
        }
    }

    /**
     * Disconnect all channels.
     */
    disconnect() {
        Object.values(this._channels).forEach(ch => ch.disconnect());
        this._channels = {};
        this._authTokenCache = {};
    }

    /**
     * Request an auth token from the server for a private/presence channel.
     * @param {string} channelName - Full channel name (e.g. 'private-user.42')
     * @returns {Promise<Object>} - { auth: 'token', channel_data?: { ... } }
     */
    async authenticate(channelName) {
        // Check cache first
        if (this._authTokenCache[channelName]) {
            return this._authTokenCache[channelName];
        }

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...this.authHeaders,
        };

        if (this.csrfToken) {
            headers['X-CSRF-TOKEN'] = this.csrfToken;
        }

        const response = await fetch(this.authEndpoint, {
            method: 'POST',
            headers,
            credentials: 'same-origin',
            body: JSON.stringify({ channel_name: channelName }),
        });

        if (!response.ok) {
            const error = await response.text();
            throw new Error(`Broadcasting auth failed for ${channelName}: ${response.status} ${error}`);
        }

        const data = await response.json();
        this._authTokenCache[channelName] = data;

        return data;
    }

    /**
     * Build an SSE URL with topics and optional auth token.
     * @param {string} channelName
     * @param {string|null} token
     * @param {string|null} userInfoB64
     * @returns {string}
     */
    buildStreamUrl(channelName, token = null, userInfoB64 = null) {
        let url = `${this.host}/api/stream?topics=${encodeURIComponent(channelName)}`;

        if (token) {
            url += `&token=${encodeURIComponent(token)}`;
        }

        if (userInfoB64) {
            url += `&user_info=${encodeURIComponent(userInfoB64)}`;
        }

        return url;
    }

    /**
     * Auto-detect the CSRF token from meta tags.
     * @returns {string|null}
     */
    _detectCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Public Channel
// ─────────────────────────────────────────────────────────────────────────────

class PlugsPublicChannel {
    constructor(echo, name) {
        this.echo = echo;
        this.name = name;
        this.source = null;
        this._listeners = {};
        this._reconnectTimer = null;
        this._reconnectDelay = 1000;
        this._maxReconnectDelay = 30000;

        this._connect();
    }

    _connect() {
        if (this.source) {
            this.source.close();
        }

        const url = this.echo.buildStreamUrl(this.name);
        this.source = new EventSource(url);

        this.source.onopen = () => {
            this._reconnectDelay = 1000; // Reset backoff on success
        };

        this.source.onerror = () => {
            this.source.close();
            this._scheduleReconnect();
        };

        // Re-bind all existing listeners
        Object.entries(this._listeners).forEach(([event, callbacks]) => {
            callbacks.forEach(cb => this._bindEvent(event, cb));
        });
    }

    _bindEvent(eventName, callback) {
        this.source.addEventListener(this.name, (e) => {
            if (e.data === ':') return;
            try {
                const parsed = JSON.parse(e.data);
                // Match by event name within the channel data
                if (parsed.event === eventName || eventName === '*') {
                    callback(parsed.data || parsed, e.lastEventId);
                }
            } catch (err) {
                // Fallback: try parsing as raw data
                try {
                    const raw = JSON.parse(e.data);
                    callback(raw, e.lastEventId);
                } catch (e2) { /* ignore */ }
            }
        });
    }

    /**
     * Listen for a specific event on this channel.
     * @param {string} eventName - Event name (e.g. 'NewMessage', 'GameStarted')
     * @param {Function} callback - Handler function
     * @returns {this}
     */
    listen(eventName, callback) {
        if (!this._listeners[eventName]) {
            this._listeners[eventName] = [];
        }
        this._listeners[eventName].push(callback);

        if (this.source && this.source.readyState !== EventSource.CLOSED) {
            this._bindEvent(eventName, callback);
        }

        return this;
    }

    /**
     * Stop listening for a specific event.
     * @param {string} eventName
     * @returns {this}
     */
    stopListening(eventName) {
        delete this._listeners[eventName];
        // EventSource doesn't support removeEventListener by ref easily,
        // so we reconnect to clean up
        this._connect();
        return this;
    }

    /**
     * Disconnect from this channel.
     */
    disconnect() {
        if (this._reconnectTimer) {
            clearTimeout(this._reconnectTimer);
            this._reconnectTimer = null;
        }

        if (this.source) {
            this.source.close();
            this.source = null;
        }
    }

    _scheduleReconnect() {
        if (this._reconnectTimer) return;

        this._reconnectTimer = setTimeout(() => {
            this._reconnectTimer = null;
            this._reconnectDelay = Math.min(this._reconnectDelay * 2, this._maxReconnectDelay);
            this._connect();
        }, this._reconnectDelay);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Private Channel (Authenticated)
// ─────────────────────────────────────────────────────────────────────────────

class PlugsPrivateChannel extends PlugsPublicChannel {
    constructor(echo, name) {
        // Don't call super's constructor yet — we need to auth first
        // We skip the parent constructor and initialize manually
        Object.assign(this, {
            echo,
            name,
            source: null,
            _listeners: {},
            _reconnectTimer: null,
            _reconnectDelay: 1000,
            _maxReconnectDelay: 30000,
        });

        this._authAndConnect();
    }

    async _authAndConnect() {
        try {
            const authData = await this.echo.authenticate(this.name);
            this._authToken = authData.auth;
            this._connect();
        } catch (err) {
            console.error(`[PlugsEcho] Auth failed for ${this.name}:`, err);
            // Retry auth after a delay
            setTimeout(() => this._authAndConnect(), 5000);
        }
    }

    _connect() {
        if (!this._authToken) return;

        if (this.source) {
            this.source.close();
        }

        const url = this.echo.buildStreamUrl(this.name, this._authToken);
        this.source = new EventSource(url);

        this.source.onopen = () => {
            this._reconnectDelay = 1000;
        };

        this.source.onerror = () => {
            this.source.close();
            // On error, re-authenticate in case the token expired
            delete this.echo._authTokenCache[this.name];
            this._scheduleReconnect();
        };

        // Re-bind all existing listeners
        Object.entries(this._listeners).forEach(([event, callbacks]) => {
            callbacks.forEach(cb => this._bindEvent(event, cb));
        });
    }

    _scheduleReconnect() {
        if (this._reconnectTimer) return;

        this._reconnectTimer = setTimeout(() => {
            this._reconnectTimer = null;
            this._reconnectDelay = Math.min(this._reconnectDelay * 2, this._maxReconnectDelay);
            this._authAndConnect(); // Re-authenticate on reconnect
        }, this._reconnectDelay);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Presence Channel (Authenticated + Member Tracking)
// ─────────────────────────────────────────────────────────────────────────────

class PlugsPresenceChannel extends PlugsPrivateChannel {
    constructor(echo, name) {
        super(echo, name);
        this._hereCallbacks = [];
        this._joiningCallbacks = [];
        this._leavingCallbacks = [];
        this._members = [];
    }

    async _authAndConnect() {
        try {
            const authData = await this.echo.authenticate(this.name);
            this._authToken = authData.auth;
            this._channelData = authData.channel_data || {};
            this._connect();
        } catch (err) {
            console.error(`[PlugsEcho] Auth failed for presence ${this.name}:`, err);
            setTimeout(() => this._authAndConnect(), 5000);
        }
    }

    _connect() {
        if (!this._authToken) return;

        if (this.source) {
            this.source.close();
        }

        // Encode user info for the daemon
        const userInfoB64 = this._channelData.user_info
            ? btoa(JSON.stringify(this._channelData.user_info))
            : null;

        const url = this.echo.buildStreamUrl(this.name, this._authToken, userInfoB64);
        this.source = new EventSource(url);

        this.source.onopen = () => {
            this._reconnectDelay = 1000;
        };

        this.source.onerror = () => {
            this.source.close();
            delete this.echo._authTokenCache[this.name];
            this._scheduleReconnect();
        };

        // Listen for presence-specific events (here, joining, leaving)
        this.source.addEventListener(this.name, (e) => {
            if (e.data === ':') return;
            try {
                const parsed = JSON.parse(e.data);

                switch (parsed.event) {
                    case 'here':
                        this._members = parsed.data || [];
                        this._hereCallbacks.forEach(cb => cb(this._members));
                        break;

                    case 'joining':
                        const joiner = parsed.data;
                        this._members.push(joiner);
                        this._joiningCallbacks.forEach(cb => cb(joiner));
                        break;

                    case 'leaving':
                        const leaver = parsed.data;
                        this._members = this._members.filter(m =>
                            (m.id || m.user_id) !== (leaver.id || leaver.user_id)
                        );
                        this._leavingCallbacks.forEach(cb => cb(leaver));
                        break;

                    default:
                        // Regular event — handled by listen() callbacks
                        break;
                }
            } catch (err) { /* ignore parse errors */ }
        });

        // Re-bind all regular event listeners
        Object.entries(this._listeners).forEach(([event, callbacks]) => {
            callbacks.forEach(cb => this._bindEvent(event, cb));
        });
    }

    /**
     * Register a callback for when the initial member list is received.
     * @param {Function} callback - fn(members: Array)
     * @returns {this}
     */
    here(callback) {
        this._hereCallbacks.push(callback);
        // If we already have members, fire immediately
        if (this._members.length > 0) {
            callback(this._members);
        }
        return this;
    }

    /**
     * Register a callback for when a member joins the channel.
     * @param {Function} callback - fn(member: Object)
     * @returns {this}
     */
    joining(callback) {
        this._joiningCallbacks.push(callback);
        return this;
    }

    /**
     * Register a callback for when a member leaves the channel.
     * @param {Function} callback - fn(member: Object)
     * @returns {this}
     */
    leaving(callback) {
        this._leavingCallbacks.push(callback);
        return this;
    }

    /**
     * Get the current member list.
     * @returns {Array}
     */
    get members() {
        return this._members;
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Global Registration
// ─────────────────────────────────────────────────────────────────────────────

// Export classes to window
if (typeof window !== 'undefined') {
    window.PlugsEcho = PlugsEcho;
    window.PlugsPublicChannel = PlugsPublicChannel;
    window.PlugsPrivateChannel = PlugsPrivateChannel;
    window.PlugsPresenceChannel = PlugsPresenceChannel;
}
