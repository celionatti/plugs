# Queues

The Plugs Queue system allows you to offload time-consuming tasks to be processed in the background.

## Defining Jobs

A job class should implement the `Plugs\Queue\Job` interface:

```php
use Plugs\Queue\Job;

class SendEmailJob implements Job
{
    public function handle($data)
    {
        // Process the task...
    }
}
```

## Dispatching Jobs

```php
// Using the helper
dispatch(new SendEmailJob(), ['email' => 'user@example.com']);

// Using the Facade
use Plugs\Facades\Queue;
Queue::push(SendEmailJob::class, $data);

// Delayed dispatch
Queue::later(60, new ProcessJob(), $data);
```

## Running the Worker

To start processing jobs, run the `queue:work` CLI command:

```bash
php theplugs queue:work
```

### Options
- `--queue=name`: Specify which queue to process.
- `--sleep=3`: Number of seconds to sleep when no jobs are available.
- `--once`: Process only the next available job and then exit.

## Drivers

- **Sync**: Executes jobs immediately (default for local development).
- **Database**: Stores jobs in a database table for reliable background processing.
