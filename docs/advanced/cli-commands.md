# CLI Tool (theplugs)

The `theplugs` CLI tool is the command-line interface included with the Plugs framework. It provides a number of helpful commands that can assist you while you build your application.

## Usage

To see a list of all available commands, you may use the `list` command:

```bash
php theplugs list
```

## Built-in Commands

### Framework Insights (New)

The `framework:explain` command is an educational tool that explains framework concepts, lifecycles, and architecture directly in your terminal:

```bash
php theplugs framework:explain controller
php theplugs framework:explain model
php theplugs framework:explain middleware
```

Outputs include:

- **Concept Definition**: Clear explanation of the role.
- **Visual Lifecycle**: ASCII diagrams of request/data flow.
- **Common Mistakes**: Proactive tips to avoid typical developer pitfalls.

### Key Generation

The `key:generate` command sets your application key in your `.env` file:

```bash
php theplugs key:generate
```

### Server

The `serve` command starts the PHP built-in server:

```bash
php theplugs serve --port=8080
```

### Database Migrations

Run your database migrations:

```bash
php theplugs migrate
```

Check the status of your migrations:

```bash
php theplugs migrate:status
```

Rollback the last migration:

```bash
php theplugs migrate:rollback
```

Reset all migrations (rollback everything):

```bash
php theplugs migrate:reset
```

Fresh migration (drop all tables and re-run all):

```bash
php theplugs migrate:fresh
```

Validate migration integrity:

```bash
php theplugs migrate:validate
```

### Database Backup & Recovery (New)

Back up your database:

```bash
php theplugs db:backup
```

Restore from a backup:

```bash
php theplugs db:restore [path]
```

**Alias:** `dbb` (backup)

### Generators

Plugs includes several "make" commands to quickly scaffold components:

```bash
php theplugs make:controller UserController
php theplugs make:model Post -m  # With migration
php theplugs make:middleware AuthMiddleware
php theplugs make:migration create_posts_table
php theplugs make:event UserRegistered
php theplugs make:listener SendWelcomeEmail
php theplugs make:notification InvoicePaid
php theplugs make:pdf-template invoice
php theplugs make:pagination-template tailwind
php theplugs make:spa-asset --min  # Generate SPA bridge with minification
php theplugs make:component MyButton  # Create a simple view component
php theplugs make:component SearchBar --bolt  # Create a reactive Bolt component
```

### Events & Listeners (New)

Generate event and listener classes:

```bash
php theplugs make:event [name]
php theplugs make:listener [name]
```

**Aliases:** `g:evt`, `g:lis`

### Notifications (New)

Generate notification classes:

```bash
php theplugs make:notification [name]
```

**Alias:** `g:not`

### View Components (New)

Automate the creation of view components:

```bash
php theplugs make:component [name]
```

**Options:**

- `--bolt, -b`: Create a reactive Bolt component (with PHP class).
- `--force, -f`: Overwrite existing files.

**Alias:** `g:comp`

### SPA Bridge (New)

Generate the Plugs SPA Bridge asset for your application:

```bash
php theplugs make:spa-asset
```

**Options:**

- `--min`: Create a minified version (`plugs-spa.min.js`).
- `--force`: Overwrite existing files.

**Alias:** `g:spa`

### Plugs Core Assets (New)

Publish and minify core framework assets (Editor, Lazy Loading) to the `public/plugs` directory:

```bash
php theplugs make:plugs-assets
```

**Options:**

- `--min`: Create minified versions (`.min.js`, `.min.css`).
- `--force`: Overwrite existing files.

**Alias:** `g:assets`

````

### PDF Templates

Generate professional, pre-designed PDF templates:

```bash
php theplugs make:pdf-template [type]
````

**Types:** `invoice`, `receipt`, `ticket`, `booking`, `certificate`, `cv`.

### Pagination Templates

Generate modern pagination templates:

```bash
php theplugs make:pagination-template [type]
```

**Types:** `tailwind`, `bootstrap`, `simple`.

```bash
php theplugs queue:work
```

### Scheduling

List the scheduled tasks:

```bash
php theplugs schedule:list
```

Run the scheduled tasks that are due:

```bash
php theplugs schedule:run
```

## Creating Custom Commands

You can create your own custom commands by extending the `Plugs\Console\Command` class and registering it in your `app/Console/Kernel.php`.

```php
namespace App\Console\Commands;

use Plugs\Console\Command;

class SendEmails extends Command
{
    protected $signature = 'mail:send {user}';
    protected $description = 'Send an email to a user';

    public function handle()
    {
        $userId = $this->argument('user');
        // Logic to send email...
        $this->info("Email sent to user {$userId}!");
    }
}
```
