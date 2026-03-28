# Production SSE Server Configuration

Standard web servers like NGINX and Apache are designed for short-lived HTTP requests. When introducing Server-Sent Events (SSE) via the `plugs sse:start` ReactPHP daemon (port `8080`), proxies often prematurely terminate the connection or batch output into buffers, preventing real-time delivery.

This guide covers the necessary "unbuffering" configurations for all major production environments.

---

## 🚀 1. Process Management

In production, the SSE daemon must stay alive after you close your terminal. Use a process manager like **Supervisor** or **Systemd**.

### Supervisor (Linux / Ubuntu)
Create `/etc/supervisor/conf.d/plugs-sse.conf`:

```ini
[program:plugs-sse]
command=php /var/www/html/theplugs sse:start --port=8080
directory=/var/www/html
user=www-data
autostart=true
autorestart=true
stderr_logfile=/var/log/plugs-sse.err.log
stdout_logfile=/var/log/plugs-sse.out.log
```

### Systemd (Modern Linux)
Create `/etc/systemd/system/plugs-sse.service`:

```ini
[Unit]
Description=Plugs SSE ReactPHP Daemon
After=network.target redis.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html
ExecStart=/usr/bin/php /var/www/html/theplugs sse:start --port=8080
Restart=always

[Install]
WantedBy=multi-user.target
```

---

## 🛠️ 2. NGINX Configuration

Nginx is the gold standard for proxying SSE. Ensure you disable `proxy_buffering` and extend timeouts.

```nginx
server {
    listen 80;
    server_name your-casino.com;

    # ... other standard Plugs/PHP-FPM rules ...

    # Proxy the SSE namespace exclusively to the ReactPHP daemon
    location /api/stream {
        proxy_pass http://127.0.0.1:8080;
        
        # 🟢 CRITICAL: Disable all buffering for real-time delivery
        proxy_buffering off;
        proxy_cache off;
        chunked_transfer_encoding off;
        
        # Disable Nginx's own response buffering (X-Accel-Buffering)
        # The daemon sends this header, but we force it here too
        proxy_set_header X-Accel-Buffering no;

        # 🟢 CRITICAL: Extend timeouts to prevent connection drops
        # Set to 24 hours (86400s) to keep connections alive
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;

        # Standard Proxy Headers
        proxy_http_version 1.1;
        proxy_set_header Connection '';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 🏛️ 3. Apache Configuration

Apache requires `mod_proxy` and `mod_proxy_http` to be enabled.

```apache
<VirtualHost *:80>
    ServerName your-casino.com

    # Proxy /api/stream to React daemon
    <Location /api/stream>
        ProxyPass http://127.0.0.1:8080/api/stream
        ProxyPassReverse http://127.0.0.1:8080/api/stream
        
        # 🟢 CRITICAL: Disable timeout and buffering
        ProxyTimeout 86400
        SetEnv proxy-nokeepalive 0
        SetEnv proxy-sendchunked 1
        
        # Disable output compression (Gzip) for this route
        SetEnv no-gzip 1
        SetEnv dont-vary 1
    </Location>
</VirtualHost>
```

---

## ⚡ 4. LiteSpeed / OpenLiteSpeed

LiteSpeed uses "Contexts" for reverse proxying.

### Via WebAdmin Console
1.  **External App**: Create a "Web Server" External Application:
    *   **Name**: `plugs_sse`
    *   **Address**: `127.0.0.1:8080`
2.  **Context**: Add a "Proxy" Context:
    *   **URI**: `/api/stream`
    *   **External App**: `plugs_sse`
    *   **Connection Timeout**: `3600` (or higher)
    *   **Read Timeout**: `3600` (or higher)

### Via .htaccess (Shared Hosting)
If your host supports LiteSpeed Rewrite Rules:

```apache
RewriteEngine On
# Proxy rule (Ensure External App name matches if configured)
RewriteRule ^api/stream(.*)$ http://127.0.0.1:8080/$1 [P,L]
```

---

## ☁️ 5. Cloudflare & CDNs

Cloudflare acts as another proxy layer. It has a default **100s timeout**.

> [!IMPORTANT]
> **Cloudflare Recommendations:**
> 1. **Page Rules**: Create a Page Rule for `*yourdomain.com/api/stream*`.
>    - **Cache Level**: Bypass
>    - **Rocket Loader**: Off
> 2. **Heartbeats**: The Plugs SSE daemon sends a heartbeat every **15 seconds** by default. This is well within Cloudflare's 100s limit, so connections will stay active as long as the heartbeat is regular.
> 3. **SSL**: Ensure your "SSL/TLS" setting is "Full" or "Full (Strict)" to match the proxy encryption.

---

## 🔍 6. Troubleshooting

- **503 Service Unavailable**: The daemon is either stopped or listening on the wrong port/IP. Use `netstat -tulnp | grep 8080` to verify.
- **Batched Updates**: If messages arrive in "chunks" instead of one-by-one, a proxy (Nginx/Cloudflare) is still buffering. Double-check `proxy_buffering off;`.
- **Connection Closed**: Check the `proxy_read_timeout`. Standard defaults are usually 60s, which is too short for SSE.

> [!TIP]
> Use `curl -N http://yourdomain.com/api/stream?topics=chat` to test real-time delivery in your terminal. The `-N` flag disables curl's internal buffering.
