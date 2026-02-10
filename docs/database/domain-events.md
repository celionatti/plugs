# Domain Events

Plugs models can record and emit explicit domain events, allowing for decoupled, event-driven architectures focused on business logic transitions.

## Recording Events

Use the `#[RecordsEvents]` attribute to enable event recording on a model.

```php
use Plugs\Database\Attributes\RecordsEvents;

#[RecordsEvents]
class User extends PlugModel {
    public function register() {
        // ... logic ...
        $this->recordEvent(new UserRegistered($this));
    }
}
```

### Automatic Dispatch
Events recorded via `recordEvent()` are held in an internal buffer. They are **automatically dispatched** via the framework's event bus only after the model has been successfully saved to the database. This ensures events are only published for permanent state changes.

## Manual Release
If you need to handle events before or without saving, you can release them manually:

```php
$events = $user->releaseEvents();
foreach ($events as $event) {
    // Handle manually
}
```

## Event Replayer

The `EventReplayer` utility allows you to reconstruct a model's state or audit its history by replaying a stream of events.

```php
use Plugs\Database\Observability\EventReplayer;

$replayer = new EventReplayer();
$replayedUser = $replayer->replay($user, $events);
```

This is particularly useful for:
- **Audit Trails**: Seeing how a model reached its current state.
- **State Restoration**: Re-materializing models from event stores.
- **Testing**: Verifying that business rules result in the correct state transitions.
