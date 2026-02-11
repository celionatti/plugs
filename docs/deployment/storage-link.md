# Storage Link Deployment Guide

When deploying your Plugs application to production, you must create a symbolic link from `public/storage` to `storage/app/public`. This ensures that files uploaded to the public disk are accessible from the web.

## VPS / Dedicated Servers

If you have SSH access to your server, simply run the following command from your application's root directory:

```bash
php theplugs storage:link
```

## Shared Hosting (cPanel / DirectAdmin)

Most shared hosting providers do not provide SSH access. Here are two ways to create the link in those environments:

### Method A: Temporary Route (Recommended)

You can create a temporary route in your `routes/web.php` file and visit it in your browser.

1. **Add the route**:

   ```php
   use Plugs\Facades\Route;

   Route::get('/setup-storage', function() {
       $target = base_path('storage/app/public');
       $link = public_path('storage');

       if (file_exists($link)) {
           return "The storage link already exists.";
       }

       if (symlink($target, $link)) {
           return "Storage link created successfully!";
       }

       return "Failed to create storage link. Check folder permissions or contact support.";
   });
   ```

2. **Visit the URL**: Go to `yourdomain.com/setup-storage`.
3. **Remove the route**: Delete the code from your `web.php` file once the link is created.

### Method B: Cron Job

If you can't use a route, you can set up a one-time Cron Job in your hosting panel.

1. **Command**:
   ```bash
   php /home/your-username/public_html/theplugs storage:link
   ```
2. **Execution**: Set it to run every minute, wait for it to execute once, then **delete the Cron Job**.

---

> [!IMPORTANT]
> Some shared hosts disable the `symlink` PHP function for security. If both methods fail, contact your hosting provider's support and ask them to enable symlinks for your account.
