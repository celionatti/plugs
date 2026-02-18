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

Sends a quick prompt to the default AI driver.

```php
$reply = $this->aiPrompt("Refine this user bio: " . $bio);
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
