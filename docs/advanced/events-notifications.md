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

### Dispatching Notifications
```php
use Plugs\Facades\Notification;

Notification::send($user, new InvoicePaid($invoice));

// Or via the Notifiable trait on the User model
$user->notify(new InvoicePaid($invoice));
```

### Available Channels
- **`mail`**: HTML/Plain-text emails.
- **`database`**: Persistent alerts in the UI.
- **`sms`**: Integration with Twilio/Nexmo.
- **`slack`**: Webhook-based Slack messages.

---

## Next Steps
Integrate with financial services using [Payments](./payments.md).
