# AI in Models

The `HasAI` trait empowers your models with automated content generation and data prediction.

## Setup

All `Plugs\Base\Model\PlugModel` instances include the `HasAI` trait by default.

```php
namespace App\Models;

use Plugs\Base\Model\PlugModel;

class Product extends PlugModel
{
    // Ready to use AI!
}
```

## Available Methods

### `generate(string $attribute, string $prompt, array $options = [])`

Generates content for a specific model field based on instructions. It automatically provides the model's current attributes as context to the AI.

```php
$product = new Product(['name' => 'UltraWidget Pro']);
$product->generate('description', 'Write a catchy 2-sentence product description.');
$product->save();
```

### `summarize(int $length = 50)`

Returns an AI-generated summary of the model instance data.

```php
$summary = $userProfile->summarize(100);
```

### `predict(array $fields)`

Suggests values for the given fields based on the model's current state.

```php
// Based on product name and category, predict tags and SEO title
$suggestions = $product->predict(['tags', 'seo_title']);
// Returns ['tags' => 'widget, tech, pro', 'seo_title' => 'UltraWidget Pro - Best in Class']
```
