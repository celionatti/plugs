# Real-Time Events with SSE & Redis

Plugs provides an enterprise-grade, non-blocking Server-Sent Events (SSE) architecture designed for high-frequency applications like cryptocurrency casinos, live analytics, and real-time chat.

## Architecture Overview

The system consists of three main components:
1. **The Publisher**: A PHP-FPM service that pushes events to a Redis Pub/Sub channel.
2. **The SSE Daemon**: A long-running ReactPHP process that maintains open HTTP connections and broadcasts Redis messages to the correct clients.
3. **The Multiplexer**: A frontend JavaScript class that handles a single SSE connection for multiple topics.

---

## 1. Requirements

- **Redis**: You must have a Redis server running.
- **PHP Dependencies**: Ensure you have installed the required async libraries:
  ```bash
  composer require react/event-loop react/http react/socket clue/redis-react predis/predis
  ```

---

## 2. Configuration

### Environment Variables
Add these to your `.env` file:
```env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
# If using a password
# REDIS_PASSWORD=your_password
```

### Web Server Proxy
Since the SSE Daemon runs on a separate port (default: `8080`), you must configure your web server to proxy `/api/stream` requests. 

See [SSE Server Config](sse_server_config.md) for detailed NGINX and Apache snippets.

---

## 3. Backend: Publishing Events

In your Controllers or Services, use the `Plugs\SSE\Publisher` class to emit events. This is non-blocking to the client but instantly triggers the SSE stream.

```php
use Plugs\SSE\Publisher;

public function resolveBet($betId) {
    // ... logic ...
    
    // Broadcast to the 'admin_telemetry' topic
    Publisher::emit('admin_telemetry', [
        'bet_id' => $betId,
        'result' => 'win',
        'amount' => 500
    ]);
}
```

---

## 4. Running the Daemon

### Development
In development, you can run the daemon manually:
```bash
php theplugs sse:start
```

### Production
> [!IMPORTANT]
> The SSE server MUST run as a persistent background process in production. Do NOT run it using `&` or as a simple command. 
> 
> Use a process manager like **Supervisor** or **Systemd**. See [SSE Server Config](sse_server_config.md) for ready-to-use configuration files.

---

## 5. Frontend: Listening to Events

Plugs provides an internal asset `plugs-sse.js` that multiplexes multiple topics over a single connection.

### Including the Asset
In your view:
```html
<script src="/plugs/plugs-sse.js"></script>
```

### Usage
The script automatically initializes a global `plugsSSE` instance pointing to your current domain.

```javascript
// Listen for crash game updates
plugsSSE.listen('crash_game', (data) => {
    document.getElementById('multiplier').innerText = data.multiplier + 'x';
});

// Listen for global chat
plugsSSE.listen('chat', (message) => {
    appendMessage(message.user, message.text);
});
```

---

## Performance & Scaling

- **Concurrency**: Unlike standard PHP-FPM, this daemon can hold 10,000+ idle connections with minimal memory usage.
- **Heartbeat**: The daemon automatically sends a heartbeat every 15 seconds to prevent proxy timeouts.
- **Multiplexing**: The frontend `PlugsSSE` class ensures only ONE TCP connection is opened even if you listen to 5 different topics.
