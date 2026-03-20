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
- **`make:feature-module`**: Scaffold a complete feature module.
- **`make:component`**: Create a view component (use `--bolt` for reactive).

---

## 3. Database & Migrations

- **`migrate`**: Run pending migrations.
- **`migrate:fresh`**: Wipe the DB and re-run all migrations.
- **`db:seed`**: Populate the database.
- **`db:backup`**: Create a database snapshot.

---

## 4. Optimization (Production)

Caching your framework state is critical for production performance.

- **`optimize`**: Cache routes, configuration, and container data.
- **`route:cache`**: Cache route matching for maximum speed.
- **`config:cache`**: Cache environment and config files.

---

## 5. Custom Commands

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
