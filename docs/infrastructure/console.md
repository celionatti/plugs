# CLI Tool (theplugs)

`theplugs` is the powerful command-line interface included with the Plugs framework. It streamlines your development workflow with scaffolding, database management, and optimization tools.

---

## 1. Getting Started

List all available commands:
```bash
php theplugs list
```

### Framework Insights
Get architectural explanations directly in your terminal:
```bash
php theplugs framework:explain controller
```

---

## 2. Generators (The `make` Commands)

Quickly scaffold various components of your application.

- **`make:controller`**: Create a new controller.
- **`make:model`**: Create a model (use `-m` for migration).
- **`make:crud`**: Scaffold a complete CRUD (Model, Migration, Controller, and 4 Views).
- **`make:feature-module`**: Scaffold a complete feature module.
- **`make:component`**: Create a view component (use `--bolt` for reactive).

---

## 3. Database & Migrations

- **`migrate`**: Run pending migrations.
- **`migrate:fresh`**: Wipe the DB and re-run all migrations.
- **`db:seed`**: Populate the database.
- **`db:backup`**: Create a database snapshot.

---

## 4. Productivity & Utilities

### **`tinker`**
An interactive PHP shell (REPL) powered by the Plugs container. It allows you to test code, interact with your models, and debug logic without creating temporary routes.

```bash
php theplugs tinker
```
- **Usage**: Type any PHP code (e.g., `App\Models\User::first()`) and see the result instantly.
- **Context**: The full framework is bootstrapped, so all helpers (`app()`, `db()`, `config()`) are available.

### **`env:sync`**
Ensures your `.env` file matches the structure of `.env.example`. It detects missing keys in your local environment and prompts you for their values.

```bash
php theplugs env:sync
```
- **When to use**: After pulling latest code from Git or adding new configuration requirements to the framework.

### **`share`**
Exposes your local development server to a public URL using **localtunnel**. This is perfect for testing webhooks (like Stripe or GitHub) or showing your progress to a client.

```bash
php theplugs share --subdomain=my-awesome-app
```
- **Requirements**: Requires Node.js and `npx`.
- **Options**: Use `--port` to specify a non-default (8000) port.

### **`serve`**
A high-performance development server with smart port detection. If the default port (8000) is busy, it automatically increments until an available one is found.

---

## 5. AI-Powered Scaffolding

### **`ai:scaffold`**
The ultimate shortcut for building new features. Provide a natural language description, and the AI will plan and execute a series of `make` commands to bootstrap your architecture.

```bash
php theplugs ai:scaffold "A task manager with tags, due dates, and priority levels"
```

**How it works:**
1.  **Analysis**: The AI analyzes your prompt to identify necessary Models, Controllers, and Migrations.
2.  **Plan**: It presents a proposed execution plan (e.g., calling `make:crud`).
3.  **Confirmation**: You review the plan and confirm execution.
4.  **Execution**: The framework runs the commands sequentially to build your feature.

---

## 6. Optimization (Production)

Caching your framework state is critical for production performance.

- **`optimize`**: Cache routes, configuration, and container data.
- **`route:cache`**: Cache route matching for maximum speed.
- **`config:cache`**: Cache environment and config files.

---

## 7. Custom Commands

Extend the CLI by creating your own commands in `app/Console/Commands/`.

```php
namespace App\Console\Commands;

use Plugs\Console\Command;

class SendReports extends Command
{
    protected $signature = 'report:send {type}';
    protected $description = 'Send daily reports';

    public function handle()
    {
        $type = $this->argument('type');
        $this->info("Sending {$type} report...");
    }
}
```

---

## Next Steps
Manage your application's data with [Filesystem Storage](./filesystem.md).
