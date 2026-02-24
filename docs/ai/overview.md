# AI Integration in Plugs Framework

The Plugs framework features deep AI integration across its core components, allowing you to build intelligent applications with minimal effort. AI is blended directly into Controllers, Models, Requests, and Validation.

## Core Concepts

AI in Plugs is powered by the `AIManager`, which supports multiple drivers:

- **OpenAI** (GPT-4o, GPT-3.5-Turbo, etc.)
- **Anthropic** (Claude 3 Opus/Sonnet/Haiku)
- **Google Gemini**
- **Groq**
- **OpenRouter**

## Global Helper

You can access the AI manager anywhere using the global `ai()` helper:

```php
$response = ai()->prompt("Summarize this: " . $content);
```

## AI Everywhere

- **Controllers**: Use the `InteractWithAI` trait to empower your logic with intelligent processing.
- **Models**: Use the `HasAI` trait for automated content generation, summarization, and data prediction.
- **Form Requests**: Analyze incoming data before it even reaches your controller.
- **Validation**: Pass data through an AI check using the new `ai` validation rule.
- **Performance**: High-speed AI execution with Caching, SWR, and Async support.

## Performance Highlights

Plugs is built for performance. AI operations can be optimized using:

- **AI Caching**: Automatically cache prompt results to eliminate API round-trips.
- **SWR (Stale-While-Revalidate)**: Serve cached AI content instantly while refreshing it in the background.
- **Async Execution**: Run AI calls in parallel with other request logic to reduce total blocking time.

[Learn more about AI Performance & Optimization](file:///docs/ai/performance.md)

## Getting Started

Ensure you have configured your AI providers in `.env`:

```env
AI_DEFAULT=openai
OPENAI_API_KEY=your-api-key-here
```

Explore the detailed documentation for each component to see what's possible!
