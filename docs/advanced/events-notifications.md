# Events & Notifications

Plugs provides a robust observer implementation and notification system, allowing you to decouple your application logic and send alerts across various channels.

---

## 1. Events & Listeners

Events provide a way to hook into your application's lifecycle without modifying core logic.

### Creating Events
```bash
php theplugs make:event UserRegistered
php theplugs make:listener SendWelcomeEmail --event=UserRegistered
```

### Dispatching Events
```php
use Plugs\Facades\Event;

Event::dispatch(new UserRegistered($user));
```

### Listening for Events
Listeners are registered in your `EventServiceProvider`. They can also be queued to run in the background.

```php
public function handle(UserRegistered $event)
{
    // Access $event->user ...
}
```

---

## 2. Notifications

Notifications are short, informational messages delivered through multiple channels (Mail, Database, SMS, Slack).

### Creating Notifications
```bash
php theplugs make:notification InvoicePaid
```

This generates an `app/Notifications/InvoicePaidNotification.php` file with a basic `via()` method and a `toMail()` stub.

### Dispatching Notifications
```php
use Plugs\Facades\Notification;

Notification::send($user, new InvoicePaid($invoice));

// Or via the Notifiable trait on the User model
$user->notify(new InvoicePaid($invoice));
```

### Available Channels

| Channel      | Key          | Description                              |
|-------------|-------------|------------------------------------------|
| Mail        | `mail`      | HTML/Plain-text emails                   |
| Database    | `database`  | Persistent alerts stored in your DB      |
| SMS         | `sms`       | Twilio-compatible SMS messages           |
| Slack       | `slack`     | Webhook-based Slack messages             |

You can send via multiple channels simultaneously by returning them from `via()`:

```php
public function via($notifiable): array
{
    return ['mail', 'sms', 'slack', 'database'];
}
```

---

## 3. Notifiable Trait

Add the `NotifiableTrait` to any model that should receive notifications:

```php
use Plugs\Notification\NotifiableTrait;

class User extends PlugModel
{
    use NotifiableTrait;
}
```

The trait provides default routing methods for each channel. Override them to customize how each entity is reached:

```php
// Default: returns $this->email
public function routeNotificationForMail(): ?string
{
    return $this->work_email;
}

// Default: returns $this->phone or $this->phone_number
public function routeNotificationForSms(): ?string
{
    return $this->mobile;
}

// Default: returns null (must be overridden)
public function routeNotificationForSlack(): ?string
{
    return 'https://hooks.slack.com/services/T.../B.../xxx';
}
```

---

## 4. Mail Channel

The `mail` channel calls `toMail()` on your notification and sends the result via the framework's `MailService`.

```php
use Plugs\Notification\Notification;

class InvoicePaidNotification extends Notification
{
    public function __construct(
        protected Invoice $invoice
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): array
    {
        return [
            'subject' => 'Invoice Paid',
            'body'    => "Invoice #{$this->invoice->id} for \${$this->invoice->amount} has been paid.",
        ];
    }
}
```

---

## 5. Database Channel

The `database` channel persists notifications to a database table for in-app alerts. Your notification must implement `toDatabase()` or `toArray()`:

```php
class NewCommentNotification extends Notification
{
    public function __construct(
        protected Comment $comment
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'comment_id' => $this->comment->id,
            'message'    => "{$this->comment->author} commented on your post.",
        ];
    }
}
```

The notifiable must implement `routeNotificationForDatabase()` to return the model/table where notifications are stored.

---

## 6. SMS Channel

The `sms` channel sends text messages via a **Twilio-compatible REST API** using cURL.

### Configuration

Add the following to your application config:

```php
'notifications' => [
    'sms' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM', '+15551234567'),
        // Optional: override the API endpoint for other providers
        // 'url' => 'https://api.vonage.com/...',
    ],
],
```

### Building SMS Notifications

Your notification's `toSms()` method can return a simple string or a fluent `SmsMessage`:

#### Simple String
```php
public function toSms($notifiable): string
{
    return "Your verification code is: {$this->code}";
}
```

#### Fluent Builder
```php
use Plugs\Notification\Messages\SmsMessage;

public function toSms($notifiable): SmsMessage
{
    return SmsMessage::create("Your order #{$this->orderId} has shipped!")
        ->from('+15559876543');
}
```

### SmsMessage API

| Method               | Description                       |
|---------------------|-----------------------------------|
| `create($content)`  | Static factory                    |
| `to($phone)`        | Override recipient phone number   |
| `from($phone)`      | Override sender phone number      |
| `content($text)`    | Set the message body              |
| `toArray()`         | Get the message as an array       |

### Full Example

```php
use Plugs\Notification\Notification;
use Plugs\Notification\Messages\SmsMessage;

class OrderShippedNotification extends Notification
{
    public function __construct(
        protected Order $order
    ) {}

    public function via($notifiable): array
    {
        return ['sms', 'database'];
    }

    public function toSms($notifiable): SmsMessage
    {
        return SmsMessage::create()
            ->content("Hi {$notifiable->name}, your order #{$this->order->id} is on its way!")
            ->from('+15551234567');
    }

    public function toDatabase($notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'message'  => 'Your order has been shipped.',
        ];
    }
}
```

### Routing

By default, `NotifiableTrait` returns `$this->phone` or `$this->phone_number`. Override for custom logic:

```php
public function routeNotificationForSms(): ?string
{
    return $this->mobile_number;
}
```

---

## 7. Slack Channel

The `slack` channel posts messages to **Slack Incoming Webhooks** via cURL.

### Configuration

**Option A — Per-notifiable webhook** (recommended for multi-tenant apps):

Override `routeNotificationForSlack()` on your notifiable:

```php
public function routeNotificationForSlack(): ?string
{
    return $this->slack_webhook_url;
}
```

**Option B — Global webhook** (single-team apps):

Set a fallback in your config:

```php
'notifications' => [
    'slack' => [
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],
],
```

### Building Slack Notifications

Your notification's `toSlack()` method can return a simple string or a fluent `SlackMessage`:

#### Simple String
```php
public function toSlack($notifiable): string
{
    return "New order #{$this->order->id} received!";
}
```

#### Fluent Builder
```php
use Plugs\Notification\Messages\SlackMessage;

public function toSlack($notifiable): SlackMessage
{
    return SlackMessage::create('A new order has been placed!')
        ->from('OrderBot')
        ->emoji(':shopping_cart:')
        ->to('#orders')
        ->success("Order #{$this->order->id}", "Total: \${$this->order->total}");
}
```

### SlackMessage API

| Method                        | Description                                      |
|------------------------------|--------------------------------------------------|
| `create($content)`           | Static factory                                   |
| `to($channel)`               | Set the Slack channel (e.g. `#general`)           |
| `content($text)`             | Set the main message text                        |
| `from($username)`            | Set the bot username                             |
| `emoji($emoji)`              | Set the bot emoji icon (e.g. `:robot_face:`)     |
| `attachment($array)`         | Add a raw attachment                             |
| `success($title, $text)`     | Add a green-colored attachment                   |
| `warning($title, $text)`     | Add a yellow-colored attachment                  |
| `error($title, $text)`       | Add a red-colored attachment                     |
| `toArray()`                  | Get the full Slack payload as an array           |

### Full Example

```php
use Plugs\Notification\Notification;
use Plugs\Notification\Messages\SlackMessage;

class DeployCompletedNotification extends Notification
{
    public function __construct(
        protected string $version,
        protected string $environment
    ) {}

    public function via($notifiable): array
    {
        return ['slack'];
    }

    public function toSlack($notifiable): SlackMessage
    {
        return SlackMessage::create()
            ->from('DeployBot')
            ->emoji(':rocket:')
            ->to('#deployments')
            ->content("Deployment to *{$this->environment}* completed.")
            ->success("Version {$this->version}", 'All health checks passed.');
    }
}

// Dispatch
$admin->notify(new DeployCompletedNotification('v2.5.0', 'production'));
```

### Attachments

You can add multiple attachments with custom colors and fields:

```php
public function toSlack($notifiable): SlackMessage
{
    return SlackMessage::create('Weekly Sales Report')
        ->from('ReportBot')
        ->emoji(':bar_chart:')
        ->attachment([
            'color'  => '#3b82f6',
            'title'  => 'Revenue',
            'text'   => '$12,450.00',
            'fields' => [
                ['title' => 'Orders', 'value' => '142', 'short' => true],
                ['title' => 'Avg Value', 'value' => '$87.68', 'short' => true],
            ],
        ])
        ->success('Growth', '+12% from last week');
}
```

---

## 8. Custom Channels

You can create custom notification channels by implementing a class with a `send()` method:

```php
namespace App\Notifications\Channels;

class TelegramChannel
{
    public function send($notifiable, $notification): void
    {
        $chatId  = $notifiable->routeNotificationForTelegram();
        $message = $notification->toTelegram($notifiable);

        // Send via Telegram Bot API ...
    }
}
```

Then reference the fully-qualified class name in `via()`:

```php
public function via($notifiable): array
{
    return [\App\Notifications\Channels\TelegramChannel::class];
}
```

---

## Next Steps
-   Learn how to process background tasks with **[Queues](../infrastructure/queues-scheduling.md)**.
-   Integrate with financial services using **[Payments](./payments.md)**.
