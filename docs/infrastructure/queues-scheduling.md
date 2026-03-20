# Queues & Scheduling

Plugs provides integrated support for background task processing and automated task scheduling, allowing you to offload heavy workloads and automate routine maintenance.

---

## 1. Queues (Background Jobs)

Queues allow you to defer time-consuming tasks (like sending emails or processing images) to the background.

### Defining a Job
Create a class that implements the `Plugs\Queue\Job` interface:

```php
class SendWelcomeEmail implements Job
{
    public function handle($data)
    {
        // Logic to send email to $data['email']
    }
}
```

### Dispatching Jobs
```php
// Dispatch immediately
dispatch(new SendWelcomeEmail(), ['email' => 'user@example.com']);

// Dispatch with delay
Queue::later(60, new SendWelcomeEmail(), $data);
```

### Running the Worker
To process jobs, start the worker via the CLI:
```bash
php theplugs queue:work --tries=3
```

---

## 2. Task Scheduling

The Plugs scheduler allows you to define your cron jobs expressively within your application code.

### Defining Schedules
Define your tasks in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Run an artisan command daily
    $schedule->command('cleanup:logs')->daily();

    // Run a closure every hour
    $schedule->call(function () {
        // Custom logic
    })->hourly();
}
```

### Running the Scheduler
Add a single cron entry to your server to trigger the Plugs scheduler every minute:
```bash
* * * * * cd /path-to-your-project && php theplugs schedule:run >> /dev/null 2>&1
```

---

## 3. Failed Job Management

If a job fails, it is stored in the `failed_jobs` table. Use the CLI to manage them:

- **`queue:failed`**: List all failed jobs.
- **`queue:retry {id}`**: Retry a specific job.
- **`queue:flush`**: Clear all failed jobs.

---

## Next Steps
Enhance your application with [AI & Agents](../advanced/ai.md).
