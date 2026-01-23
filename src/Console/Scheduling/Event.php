<?php

declare(strict_types=1);

namespace Plugs\Console\Scheduling;

/**
 * Represents a single scheduled event (command execution).
 */
class Event
{
    protected string $command;
    protected array $parameters;
    protected ?string $cronExpression = null;
    protected ?string $description = null;
    protected bool $withoutOverlapping = false;
    protected bool $runInBackground = false;
    protected ?\Closure $filter = null;
    protected ?\Closure $reject = null;

    public function __construct(string $command, array $parameters = [])
    {
        $this->command = $command;
        $this->parameters = $parameters;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set a raw cron expression.
     */
    public function cron(string $expression): self
    {
        $this->cronExpression = $expression;
        return $this;
    }

    /**
     * Run the event every minute.
     */
    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    /**
     * Run the event every five minutes.
     */
    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    /**
     * Run the event every ten minutes.
     */
    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    /**
     * Run the event every fifteen minutes.
     */
    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    /**
     * Run the event every thirty minutes.
     */
    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    /**
     * Run the event hourly.
     */
    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Run the event hourly at a specific minute.
     */
    public function hourlyAt(int $minute): self
    {
        return $this->cron("{$minute} * * * *");
    }

    /**
     * Run the event daily.
     */
    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    /**
     * Run the event daily at a specific time (HH:MM).
     */
    public function dailyAt(string $time): self
    {
        [$hour, $minute] = explode(':', $time);
        return $this->cron("{$minute} {$hour} * * *");
    }

    /**
     * Run the event twice daily.
     */
    public function twiceDaily(int $first = 1, int $second = 13): self
    {
        return $this->cron("0 {$first},{$second} * * *");
    }

    /**
     * Run the event weekly.
     */
    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    /**
     * Run the event on a specific day and time.
     * @param int $dayOfWeek 0 (Sunday) - 6 (Saturday)
     */
    public function weeklyOn(int $dayOfWeek, string $time = '0:0'): self
    {
        [$hour, $minute] = explode(':', $time);
        return $this->cron("{$minute} {$hour} * * {$dayOfWeek}");
    }

    /**
     * Run the event monthly.
     */
    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    /**
     * Run the event monthly on a specific day and time.
     */
    public function monthlyOn(int $day = 1, string $time = '0:0'): self
    {
        [$hour, $minute] = explode(':', $time);
        return $this->cron("{$minute} {$hour} {$day} * *");
    }

    /**
     * Run the event quarterly.
     */
    public function quarterly(): self
    {
        return $this->cron('0 0 1 1,4,7,10 *');
    }

    /**
     * Run the event yearly.
     */
    public function yearly(): self
    {
        return $this->cron('0 0 1 1 *');
    }

    /**
     * Run the event on weekdays only.
     */
    public function weekdays(): self
    {
        return $this->cron('* * * * 1-5');
    }

    /**
     * Run the event on weekends only.
     */
    public function weekends(): self
    {
        return $this->cron('* * * * 0,6');
    }

    /**
     * Run the event on Sundays.
     */
    public function sundays(): self
    {
        return $this->weeklyOn(0);
    }

    /**
     * Run the event on Mondays.
     */
    public function mondays(): self
    {
        return $this->weeklyOn(1);
    }

    /**
     * Run the event on Tuesdays.
     */
    public function tuesdays(): self
    {
        return $this->weeklyOn(2);
    }

    /**
     * Run the event on Wednesdays.
     */
    public function wednesdays(): self
    {
        return $this->weeklyOn(3);
    }

    /**
     * Run the event on Thursdays.
     */
    public function thursdays(): self
    {
        return $this->weeklyOn(4);
    }

    /**
     * Run the event on Fridays.
     */
    public function fridays(): self
    {
        return $this->weeklyOn(5);
    }

    /**
     * Run the event on Saturdays.
     */
    public function saturdays(): self
    {
        return $this->weeklyOn(6);
    }

    /**
     * Set the description for the event.
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Alias for description.
     */
    public function name(string $description): self
    {
        return $this->description($description);
    }

    /**
     * Prevent overlapping of the same event.
     */
    public function withoutOverlapping(): self
    {
        $this->withoutOverlapping = true;
        return $this;
    }

    /**
     * Run the task in the background.
     */
    public function runInBackground(): self
    {
        $this->runInBackground = true;
        return $this;
    }

    /**
     * Register a callback to filter the event.
     */
    public function when(\Closure $callback): self
    {
        $this->filter = $callback;
        return $this;
    }

    /**
     * Register a callback to reject the event.
     */
    public function skip(\Closure $callback): self
    {
        $this->reject = $callback;
        return $this;
    }

    /**
     * Check if the event passes its filters.
     */
    public function filtersPass(): bool
    {
        if ($this->filter && !call_user_func($this->filter)) {
            return false;
        }

        if ($this->reject && call_user_func($this->reject)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the event is due to run.
     */
    public function isDue(): bool
    {
        if (!$this->cronExpression) {
            return false;
        }

        return $this->matchesCronExpression(new \DateTimeImmutable());
    }

    /**
     * Simple cron expression matcher.
     */
    protected function matchesCronExpression(\DateTimeImmutable $date): bool
    {
        $parts = explode(' ', $this->cronExpression);

        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        return $this->matchesCronPart($minute, (int) $date->format('i'))
            && $this->matchesCronPart($hour, (int) $date->format('G'))
            && $this->matchesCronPart($dayOfMonth, (int) $date->format('j'))
            && $this->matchesCronPart($month, (int) $date->format('n'))
            && $this->matchesCronPart($dayOfWeek, (int) $date->format('w'));
    }

    /**
     * Match a single cron field.
     */
    protected function matchesCronPart(string $expression, int $value): bool
    {
        // Wildcard
        if ($expression === '*') {
            return true;
        }

        // List (e.g., 1,15)
        if (str_contains($expression, ',')) {
            $values = array_map('intval', explode(',', $expression));
            return in_array($value, $values, true);
        }

        // Range (e.g., 1-5)
        if (str_contains($expression, '-')) {
            [$start, $end] = array_map('intval', explode('-', $expression));
            return $value >= $start && $value <= $end;
        }

        // Step (e.g., */5)
        if (str_starts_with($expression, '*/')) {
            $step = (int) substr($expression, 2);
            return $step > 0 && $value % $step === 0;
        }

        // Exact value
        return (int) $expression === $value;
    }
}
