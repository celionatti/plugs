# Building an AI-Powered Blog System

This guide demonstrates how to use Plugs AI features to build a blog where AI assists with every step: topic suggestion, content drafting, SEO, and quality auditing.

## 1. The Model (`Article`)

By using the `HasAI` trait, your article model becomes a "smart" component.

```php
use Plugs\AI\Traits\HasAI;
use Plugs\Database\Model;

class Article extends Model {
    use HasAI;
}
```

## 2. Workflow: From Idea to Publication

### Step 1: Suggesting Topics

Instead of staring at a blank page, ask the AI for trending topics in your niche.

```php
// In your controller
$topics = Article::suggestTopics('Modern PHP Development');
```

### Step 2: Drafting Content

Once a topic is chosen, let the AI write the first draft.

```php
$article = new Article(['title' => $topic]);

// Generate the full post body
$article->generate('content', "Write a technical blog post about '{$topic}'");

// Automatically summarize for the meta description
$article->summary = $article->summarize(50);
```

### Step 3: Predictive SEO

The AI can suggest keywords based on the content it just wrote.

```php
$keywords = $article->predict(['seo_keywords']);
$article->seo_keywords = $keywords['seo_keywords'];
```

### Step 4: Human-in-the-Loop Audit

Before you click "Publish", run an AI audit to check for accuracy, tone, and readability.

```php
$auditReport = AI::prompt("Audit this post for readability: " . $article->content);
```

## Reference Implementation

We have provided a full reference implementation in:

- **Model**: `src/AI/Examples/Article.php`
- **Controller**: `src/AI/Examples/ArticleAdminController.php`

> [!TIP]
> This workflow ensures that while AI does the "heavy lifting" of drafting, the human admin always has the final word on the content and quality.
