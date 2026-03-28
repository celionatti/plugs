# Reverse Proxy Configuration for ReactPHP SSE Daemon

Standard web servers like NGINX and Apache are designed for short-lived HTTP requests. When introducing Server-Sent Events (SSE) via a custom daemon on port `8080`, proxies often prematurely terminate the connection via `timeout` or batch output into buffers preventing real-time delivery.

## NGINX Configuration

To proxy the `/api/stream` properly to your `plugs sse:start` ReactPHP daemon, add this location block inside your `server { ... }` block (typically around where Plugs routes to `index.php`):

```nginx
server {
    listen 80;
    server_name your-casino.com;

    # ... other standard Plugs/PHP-FPM rules ...

    # Proxy the SSE namespace exclusively to the ReactPHP daemon
    location /api/stream {
        proxy_pass http://127.0.0.1:8080;
        
        # Disable Buffering for Real-time pushing
        proxy_buffering off;
        proxy_cache off;
        chunked_transfer_encoding off;

        # Keep Connections Alive
        proxy_set_header Connection '';
        proxy_http_version 1.1;
        
        # Extend read timeouts for long-lived streams (24 hours)
        proxy_read_timeout 86400s;
        proxy_send_timeout 86400s;
        
        # Forward Client IP
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## Apache Configuration

If you're using Apache (e.g., standard XAMPP) with `mod_proxy` and `mod_proxy_http` enabled:

```apache
<VirtualHost *:80>
    ServerName your-casino.com

    # ... standard DocumentRoot plugs/public ...

    # Proxy /api/stream to React daemon
    <Location /api/stream>
        ProxyPass http://127.0.0.1:8080/api/stream
        ProxyPassReverse http://127.0.0.1:8080/api/stream
        
        # Disable timeout and buffering for immediate delivery
        ProxyTimeout 86400
        SetEnv proxy-nokeepalive 0
        SetEnv proxy-sendchunked 1
    </Location>
</VirtualHost>
```

> [!CAUTION]
> If you test locally and connect straight to `http://localhost:8080/api/stream?topics=chat`, you bypass Nginx/Apache entirely, which is good for debugging daemon issues!
