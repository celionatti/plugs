# Task Scheduling

In the past, you may have written a cron configuration entry for every task you needed to schedule on your server. However, this can quickly become a pain because your task schedule is no longer in source control and you must SSH into your server to view your existing cron entries or add additional entries.

Plugs' command scheduler allows you to fluently and expressively define your command schedule within Plugs itself. When using the scheduler, only a single cron entry is needed on your server.

## Defining Schedules

You may define all of your scheduled tasks in the `schedule` method of your application's `App\Console\Kernel` class.

### Scheduling Artisan Commands

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('emails:send --force')->daily();
}
```

### Scheduling Closures

```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        DB::table('recent_users')->delete();
    })->daily();
}
```

## Schedule Frequency Options

There are a variety of frequencies you may assign to a task:

| Method | Description |
| --- | --- |
| `->everyMinute();` | Run the task every minute |
| `->everyFiveMinutes();` | Run the task every five minutes |
| `->everyTenMinutes();` | Run the task every ten minutes |
| `->everyFifteenMinutes();` | Run the task every fifteen minutes |
| `->everyThirtyMinutes();` | Run the task every thirty minutes |
| `->hourly();` | Run the task every hour |
| `->hourlyAt(17);` | Run the task every hour at 17 minutes past the hour |
| `->daily();` | Run the task every day at midnight |
| `->dailyAt('13:00');` | Run the task every day at 13:00 |
| `->twiceDaily(1, 13);` | Run the task daily at 1:00 & 13:00 |
| `->weekly();` | Run the task every Sunday at 00:00 |
| `->weeklyOn(1, '8:00');` | Run the task every Monday at 8:00 |
| `->monthly();` | Run the task every month on the 1st at 00:00 |
| `->monthlyOn(4, '15:00');` | Run the task every month on the 4th at 15:00 |
| `->lastDayOfMonth('15:00');` | Run the task on the last day of the month at 15:00 |
| `->yearly();` | Run the task every year on Jan 1st at 00:00 |
| `->cron('* * * * *');` | Run the task on a custom cron schedule |

## Running The Scheduler

When using the scheduler, you only need to add the following cron item to your server:

```bash
* * * * * cd /path-to-your-project && php theplugs schedule:run >> /dev/null 2>&1
```

This cron will call the Plugs command scheduler every minute. When the `schedule:run` command is executed, Plugs will evaluate your scheduled tasks and run the tasks that are due.
