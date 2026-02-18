# AI Validation & Request Analysis

Plugs allows you to leverage AI for complex data validation and request enrichment.

## AI Validation Rule

The `ai` rule allows you to validate fields using natural language instructions.

### Usage in Controllers

```php
$this->validate($request, [
    'comment' => 'required|string|ai:check if this is a helpful product review',
    'username' => 'required|ai:ensure this name is not offensive'
]);
```

### Implementation Detail

The `ai` rule sends the input to the AI driver along with your instruction. The AI must respond with "yes" for validation to pass. Otherwise, the error message will contain the AI's explanation of why it failed.

## AI in Form Requests

Form Requests are empowered with AI context awareness.

### `aiPrompt(string $prompt, array $options = [])`

Sends a prompt to the AI with the entire request data attached as context.

```php
$analysis = $request->aiPrompt("Provide a sentiment score from 1-10 for this request.");
```

### `aiAnalyze(string $instruction = "...")`

A high-level method to get structured JSON feedback about the request data.

```php
$insights = $request->aiAnalyze("Identify potential upsell opportunities based on this user search.");
// Returns an array decoded from AI's JSON response
```
