# Queues & Scheduling

The Plugs Queue system provides a unified API across a variety of different queue backends, such as Redis, a relational database, or even a synchronous driver for local development.

Queues allow you to defer the processing of a time-consuming task, such as sending an email, until a later time. Deferring these tasks drastically speeds up web requests to your application.

---

## 1. Queues (Background Jobs)

### Configuration
The queue configuration file is stored at `config/queue.php`. In this file, you will find connection configurations for each of the queue drivers supported by the framework.

#### Supported Drivers
-   **`sync`**: Executes jobs immediately (useful for local development).
-   **`database`**: Stores jobs in a database table.
-   **`redis`**: Uses Redis for high-performance job processing.

#### Database Setup
If you use the `database` queue driver, you will need a database table to hold the jobs. You can use the following commands to create the migration for this table:

```bash
php theplugs queue:table
php theplugs queue:failed-table
php theplugs migrate
```

### Defining a Job
By default, all of the queueable jobs for your application are stored in the `app/Jobs` directory. You may generate a new job using the CLI:

```bash
php theplugs make:job SendEmailJob
```

A job class contains a `handle` method which is called when the job is processed by the queue.

```php
namespace App\Jobs;

use Plugs\Queue\InteractsWithQueue;
use Plugs\Queue\Queueable;

class SendEmailJob
{
    use InteractsWithQueue, Queueable;

    public function __construct(protected $user) {}

    public function handle()
    {
        // Process the job...
        // e.g., Mail::to($this->user)->send(new WelcomeEmail());
    }
}
```

### Dispatching Jobs
Once you have written your job class, you may dispatch it using the `Queue` facade:

```php
use Plugs\Facades\Queue;
use App\Jobs\SendEmailJob;

// Dispatch to the default queue
Queue::push(new SendEmailJob($user));

// Dispatch with a delay (seconds)
Queue::later(60, new SendEmailJob($user));

// Dispatch to a specific queue
Queue::push(new SendEmailJob($user), 'high-priority');
```

### Running the Queue Worker
Plugs includes a queue worker that will process new jobs as they are pushed into the queue. You may run the worker using the `queue:work` command:

```bash
php theplugs queue:work
```

#### Options
-   **`--queue=name`**: The name of the queue to work.
-   **`--tries=3`**: Number of times to attempt a job before failing.
-   **`--delay=3`**: Delay (in seconds) between retries.
-   **`--sleep=3`**: Seconds to sleep when no jobs are available.

> [!IMPORTANT]
> **Production Note**: In production, you should use a process monitor such as **Supervisor** to ensure that the queue worker does not stop running.

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

Sometimes your queued jobs will fail. If a job exceeds the maximum number of attempts defined by the `--tries` option, it will be inserted into the `failed_jobs` table.

### Managing Failed Jobs
-   **`queue:failed`**: List all failed jobs.
-   **`queue:retry {id}`**: Retry a specific job.
-   **`queue:retry all`**: Retry all failed jobs.
-   **`queue:forget {id}`**: Delete a failed job.
-   **`queue:flush`**: Clear all failed jobs.

---

## 4. Purpose and Benefits

-   **Improved UX**: Users don't have to wait for long-running tasks to finish.
-   **Reliability**: Jobs can be retried automatically if they fail.
-   **Scalability**: Offload heavy workloads to separate servers.

---

## Next Steps
Enhance your application with [AI & Agents](../advanced/ai.md).
