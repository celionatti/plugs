# AI SDK

The Plugs AI SDK provides a unified interface for interacting with various Large Language Models (LLMs) like OpenAI, Anthropic, and Google Gemini.

## Configuration

Plugs AI SDK comes with built-in configuration. You only need to add your API keys to your `.env` file. If you wish to customize more settings, you can create a `config/ai.php` file in your project.

```env
AI_DRIVER=openai

OPENAI_API_KEY=your-api-key
OPENAI_MODEL=gpt-4o

ANTHROPIC_API_KEY=your-api-key
ANTHROPIC_MODEL=claude-3-5-sonnet-latest

GEMINI_API_KEY=your-api-key
GEMINI_MODEL=gemini-1.5-flash
```

## Basic Usage

You can use the `AI` facade to quickly interact with the default AI provider.

### Simple Prompt

```php
use Plugs\Facades\AI;

$response = AI::prompt('Explain quantum computing in simple terms.');

echo $response;
```

### Chat Completion

```php
use Plugs\Facades\AI;

$response = AI::chat([
    ['role' => 'user', 'content' => 'Hello, who are you?'],
    ['role' => 'assistant', 'content' => 'I am an AI assistant.'],
    ['role' => 'user', 'content' => 'Tell me a joke.'],
]);

echo $response;
```

## Switching Drivers

You can easily switch between different AI providers on the fly:

```php
use Plugs\Facades\AI;

// Use Anthropic
$response = AI::driver('anthropic')->prompt('Hello Claude!');

// Use Gemini
$response = AI::driver('gemini')->prompt('Hello Gemini!');
```

## Changing Models

You can specify a different model for a specific request:

```php
use Plugs\Facades\AI;

$response = AI::withModel('gpt-3.5-turbo')->prompt('Quick summary of AI history.');
```

## Advanced Options

You can pass additional options directly to the underlying API:

```php
use Plugs\Facades\AI;

$response = AI::prompt('Write a story.', [
    'temperature' => 0.7,
    'max_tokens' => 500,
]);
```
