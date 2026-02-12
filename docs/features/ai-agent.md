# Autonomous AI Agent

Plugs introduces an agentic approach to AI integration, moving beyond simple chat to an autonomous development partner.

## 1. AI Agent (`ai:agent`)

The `ai:agent` command starts an interactive session with an "Agent" that can reason about your project goals.

### Usage

```bash
php theplugs ai:agent "Help me build a blog system"
```

### Features

- **Task Decomposition**: The agent automatically breaks down your high-level goal into actionable steps (migrations, models, controllers).
- **Interactive Cooperation**: You can chat with the agent to refine parts of the plan or ask it for specific code snippets for each step.
- **Context Persistence**: The agent maintains state throughout the session, remembering previous steps and decisions.

---

## 2. AI Reasoning Engine (`ai:think`)

When you encounter a complex architectural challenge or a subtle bug, use `ai:think` to get a deep analysis.

### Usage

```bash
php theplugs ai:think "How should I implement a multi-tenant payment gateway that supports recurring billing?"
```

### Process

The engine uses **Chain of Thought** reasoning to:

1. Analyze the current framework state.
2. Identify core technical challenges.
3. Propose a multi-step logical solution.
4. Provide a final architectural recommendation.

---

## 3. Core Agent API

You can also use the `Agent` class directly in your own code to build autonomous features.

```php
use Plugs\AI\Agent;
use Plugs\Facades\AI;

$agent = new Agent(AI::driver());

// Break down a task
$steps = $agent->decompose("Set up an OAuth2 server");

// Progressively think through a conversation
$response = $agent->think("What's the best way to handle token refresh?");
```

> [!IMPORTANT]
> The Agent is framework-aware. It knows about your project paths, PHP version, and the Plugs ecosystem.
