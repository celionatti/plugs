# Maintenance Mode

When managing an application, you may need to disable it for users while you perform updates or maintenance tasks. PLUGS makes this easy with the `down` and `up` commands.

## Enabling Maintenance Mode

To put your application into maintenance mode, execute the `down` command:

```bash
php theplugs down
```

This will return a 503 "Service Unavailable" response to all users.

### Custom Message

You can provide a custom message to be displayed:

```bash
php theplugs down --message="We are upgrading our database. Back soon!"
```

### Retry Header

You can specify the `Retry-After` HTTP header value (in seconds):

```bash
php theplugs down --retry=60
```

## Bypassing Maintenance Mode

You may need to access the application yourself while it is down. To do this, provide a `secret` token when running the command:

```bash
php theplugs down --secret="1630542a-246b-4b66-afa1-dd72a4c43515"
```

After running this, you can visit your application URL appended with the secret:

`https://your-domain.com/1630542a-246b-4b66-afa1-dd72a4c43515`

This will set a secure cookie in your browser, allowing you to browse the site normally while other users still see the maintenance page.

## Disabling Maintenance Mode

To bring your application back online, use the `up` command:

```bash
php theplugs up
```

## Customizing the View

By default, a simple HTML page is shown. You can customize this by creating a file at `resources/views/maintenance.html` (or wherever your `resource_path` is configured).

If this file exists, PLUGS will render it instead of the default template.

```html
<!-- resources/views/maintenance.html -->
<!DOCTYPE html>
<html>
<head>
    <title>We'll be back.</title>
    <style>
        body { text-align: center; padding: 150px; font-family: sans-serif; }
        h1 { font-size: 50px; }
        body { font: 20px Helvetica, sans-serif; color: #333; }
    </style>
</head>
<body>
    <article>
        <h1>We&rsquo;ll be back soon!</h1>
        <p>Sorry for the inconvenience but we&rsquo;re performing some maintenance at the moment.</p>
    </article>
</body>
</html>
```
