# AI & Agents

Plugs is an **AI-Native** framework, featuring deep integration of Large Language Models (LLMs) across its core subsystems. This allows you to build intelligent, autonomous applications with minimal effort.

---

## 1. Global `ai()` Helper

The `ai()` helper provides direct access to the `AIManager`, supporting OpenAI, Anthropic, Gemini, and more.

```php
$summary = ai()->prompt("Summarize this article: " . $content);
```

You can specify a driver on the fly:
```php
$answer = ai('gemini')->prompt("Analyze this data...");
```

---

## 2. AI-Enhanced Query Assistant

Query your database using natural language. The engine converts prompts into safe, parametric SQL.

```php
// Find top 10 customers based on order value
$customers = User::ai("Find top 10 customers with orders > $1000")->get();
```

### Security & Safety
- **Parametric Binding**: Prevents SQL injection.
- **Read-Only**: Strictly enforces `SELECT` statements only.
- **Schema-Aware**: Automatically analyzes your model's schema for precise queries.

---

## 3. Autonomous AI Agent

The `ai:agent` CLI tool acts as your development partner, capable of reasoning about your project and decomposing complex tasks.

```bash
php theplugs ai:agent "Help me build a multi-tenant subscription system"
```

### Features
- **Task Decomposition**: Breaks high-level goals into migrations, models, and controllers.
- **Context Persistence**: Maintains state throughout your conversation.
- **Architectural Reasoning**: Use `ai:think` for deep analysis of specific bugs or designs.

---

## 4. AI in Core Components

- **Controllers**: Use the `InteractWithAI` trait for intelligent request processing.
- **Models**: Use the `HasAI` trait for automated content generation or summarization.
- **Validation**: Pass data through an AI check using the `ai` validation rule.

---

## 5. Performance & Optimization

AI calls can be latent. Plugs optimizes this using:

- **AI Caching**: Automatically cache prompt results globally.
- **SWR (Stale-While-Revalidate)**: Serve cached AI content instantly while refreshing it in the background.
- **Async Support**: Run AI calls in parallel with other logic using PHP Fibers.

---

## Next Steps
Optimize your application's [Performance & Caching](../advanced/performance.md).
