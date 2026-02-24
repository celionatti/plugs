# AI in Controllers

Plugs provides an `InteractWithAI` trait that empowers controllers with high-level AI capabilities.

## Setup

The base `Plugs\Base\Controller\Controller` already includes this trait. If you are creating a standalone controller, include it manually:

```php
use Plugs\AI\Traits\InteractWithAI;

class MyController
{
    use InteractWithAI;
}
```

## Available Methods

### `aiPrompt(string $prompt, array $options = [])`

Sends a quick prompt to the default AI driver. Supports performance options like `cache` and `swr`.

```php
// Standard prompt
$reply = $this->aiPrompt("Refine this user bio: " . $bio);

// Prompt with 10-minute cache
$reply = $this->aiPrompt($prompt, ['cache' => 600]);

// Zero-latency prompt using SWR
$reply = $this->aiPrompt($prompt, ['swr' => true]);
```

### `defer()`

Returns a deferred version of the AI manager. Methods called on this will return a `LazyString` that resolves asynchronously.

```php
public function index()
{
    // Initiates AI request without blocking
    $headlines = ai()->defer()->prompt("Get trending headlines");

    // Resolves only when rendered in the view
    return view('dashboard', compact('headlines'));
}
```

### `aiAgent(?string $instructions = null)`

Returns an instance of `Plugs\AI\Agent`. Agents maintain conversation state and can "think" through complex tasks.

```php
$agent = $this->aiAgent("You are a helpful customer support assistant.");
$response = $agent->think("How do I reset my password?");
```

### `aiClassify(string $text, array $categories = [])`

Categorizes text into one of the provided options.

```php
$category = $this->aiClassify($userMessage, ['billing', 'technical', 'sales']);
```

### `aiGenerate(string $prompt, array $options = [])`

Generates content while automatically providing the current controller's class name as context.

```php
$content = $this->aiGenerate("Write a welcome message for a new user.");
```
