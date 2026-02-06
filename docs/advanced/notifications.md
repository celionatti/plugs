# Notifications

Plugs provides support for sending notifications across various delivery channels, including email and database.

## Generating Notifications

To create a new notification, use the `make:notification` command:

```bash
php theplugs make:notification InvoicePaid
```

## Sending Notifications

### Using the Notifiable Trait

Models can use the `Plugs\Notification\NotifiableTrait` to send notifications:

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;
use Plugs\Notification\NotifiableTrait;

class User extends PlugModel
{
    use NotifiableTrait;
}
```

Then you can send a notification using the `notify` method:

```php
$user->notify(new InvoicePaid($invoice));
```

### Using the Notification Manager

You may also send notifications via the `notifications` instance:

```php
use Plugs\Container\Container;

$manager = Container::getInstance()->make('notifications');
$manager->send($users, new InvoicePaid($invoice));
```

## Specifying Delivery Channels

Every notification class has a `via` method that determines which channels the notification will be delivered on:

```php
public function via($notifiable): array
{
    return ['mail', 'database'];
}
```

## Mail Notifications

To support the `mail` channel, your notification should define a `toMail` method. This method should return either a string (the body) or an array containing the subject and body:

```php
public function toMail($notifiable)
{
    return [
        'subject' => 'Invoice Paid',
        'body' => 'Your invoice has been paid successfully.',
    ];
}
```

## Database Notifications

The `database` channel stores the notification information in a database table. This table will contain information such as the notification type and a JSON data blob that describes the notification.

Your notification should define a `toDatabase` or `toArray` method:

```php
public function toDatabase($notifiable): array
{
    return [
        'invoice_id' => $this->invoice->id,
        'amount' => $this->invoice->total,
    ];
}
```

### Accessing Notifications

If a model uses the `NotifiableTrait`, it should define the `routeNotificationForDatabase` method or have a `notifications()` relationship defined to access stored notifications. (Note: Ensure the `notifications` table is migrated).
